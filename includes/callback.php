<?php
defined('ABSPATH') or die('No script kiddies please!');

// Determine desired post-login redirect target
$user_redirect = home_url('/');
if (!empty($_GET['redirect_to'])) {
    $resolved = esc_url_raw((string) $_GET['redirect_to']);
    $validated = wp_validate_redirect($resolved, $user_redirect);
    if (!empty($validated)) {
        $user_redirect = $validated;
    }
} elseif (!empty($_GET['redirect_uri'])) {
    // Back-compat: not recommended name, but preserve behavior
    $resolved = esc_url_raw((string) $_GET['redirect_uri']);
    if ($resolved !== '' && casdoor_same_origin($resolved)) {
        $user_redirect = $resolved;
    }
}

// First leg: send to authorize if no code yet
if (!isset($_GET['code'])) {
    $params = [
        'oauth'         => 'authorize',
        'response_type' => 'code',
        'client_id'     => casdoor_get_option('client_id'),
        'redirect_uri'  => site_url('?auth=casdoor'),
        // do NOT urlencode here; http_build_query handles encoding
        'state'         => $user_redirect,
    ];
    $params = http_build_query($params);
    wp_redirect(casdoor_get_option('backend') . '/login/oauth/authorize?' . $params);
    exit;
}

// Handle callback with code
if (!empty($_GET['code'])) {
    if (!empty($_GET['state'])) {
        // Validate returned state to prevent open redirect
        $state = (string) $_GET['state'];
        $validated = wp_validate_redirect(esc_url_raw($state), home_url('/'));
        if (!empty($validated)) {
            $user_redirect = $validated;
        }
    }

    $code       = sanitize_text_field($_GET['code']);
    $backend    = rtrim(casdoor_get_option('backend'), '/') . '/api/login/oauth/access_token';

    // Keep default TLS verification disabled, with option to force verify
    $opts_all  = get_option('casdoor_options', []);
    $sslverify = !empty($opts_all['force_ssl_verify']) ? true : false;

    $response   = wp_remote_post($backend, [
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => [],
        'body'        => [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => casdoor_get_option('client_id'),
            'client_secret' => casdoor_get_option('client_secret'),
            'redirect_uri'  => site_url('?auth=casdoor')
        ],
        'cookies'     => [],
        // Default remains false; admin can force verification via setting.
        'sslverify'   => $sslverify
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        exit("Something went wrong: {$error_message}");
    }

    $tokens = json_decode(wp_remote_retrieve_body($response));
    if (isset($tokens->error)) {
        wp_die($tokens->error_description);
    }

    // Access token is a JWT; Casdoor’s logout expects it as id_token_hint
    $access_token = isset($tokens->access_token) ? (string) $tokens->access_token : '';

    // Store access token in a secure, HttpOnly cookie for RP-initiated logout later
    if ($access_token !== '') {
        $cookie_name  = 'casdoor_access_token';
        $cookie_domain = parse_url(home_url(), PHP_URL_HOST);
        $cookie_opts = [
            'expires'  => time() + DAY_IN_SECONDS,
            'path'     => '/',
            'domain'   => $cookie_domain ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookie_name, $access_token, $cookie_opts);
        } else {
            // best-effort fallback without SameSite
            setcookie($cookie_name, $access_token, $cookie_opts['expires'], $cookie_opts['path'], $cookie_opts['domain'], $cookie_opts['secure'], $cookie_opts['httponly']);
        }
    }

    // Decode user info from JWT payload (unchanged behavior)
    if ($access_token === '') {
        wp_die('Missing access token in Casdoor response.');
    }
    $info = json_decode(base64_decode(strtr(explode('.', $access_token)[1], '-_', '+/')));

    $user_id = username_exists($info->name);

    if (!$user_id && (empty($info->email) || email_exists($info->email) == false)) {
        if (casdoor_get_option('login_only') == 1) {
            wp_safe_redirect(home_url() . '?message=casdoor_login_only');
            exit;
        }

        // Register and then log the user in
        $random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);
        $user_data = [
            'user_email'   => $info->email,
            'user_login'   => $info->name,
            'user_pass'    => $random_password,
            'display_name' => $info->displayName,
        ];
        if (!empty($info->isGlobalAdmin) && $info->isGlobalAdmin) {
            $user_data['role'] = 'administrator';
        }

        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        do_action('wp_login', $info->name, get_user_by('id', $user_id));
    } else {
        // Log existing user in
        if (!$user_id) {
            $user = get_user_by('email', $info->email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        if ($user_id) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            do_action('wp_login', $info->name, get_user_by('id', $user_id));
        } else {
            wp_die('Unable to find or create a local user for Casdoor account.');
        }
    }

    // Redirect after successful login
    if (absint(casdoor_get_option('redirect_to_dashboard')) === 1) {
        wp_safe_redirect(admin_url());
    } else {
        wp_safe_redirect($user_redirect);
    }
    exit;
}
