<?php

/**
 * This file is called when the auth param is found in the URL.
 */
defined('ABSPATH') or die('No script kiddies please!');

// Redirect the user back to the home page if logged in.
if (is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

// Grab a copy of the options and set the redirect location.
$user_redirect = casdoor_get_user_redirect_url();

// Check for custom redirect
if (!empty($_GET['redirect_uri'])) {
    $user_redirect = esc_url($_GET['redirect_uri']);
}

// Authenticate Check and Redirect
if (!isset($_GET['code'])) {
    $params = [
        'oauth'         => 'authorize',
        'response_type' => 'code',
        'client_id'     => casdoor_get_option('client_id'),
        'client_secret' => casdoor_get_option('client_secret'),
        'redirect_uri'  => site_url('?auth=casdoor'),
        'state'         => urlencode($user_redirect)
    ];
    $params = http_build_query($params);
    wp_redirect(casdoor_get_option('backend') . '/login/oauth/authorize?' . $params);
    exit;
}

// Handle the callback from the backend is there is one.
if (!empty($_GET['code'])) {
    // If the state is present, let's redirect to that link.
    if (!empty($_GET['state'])) {
        $user_redirect = sanitize_text_field($_GET['state']);
    }

    $code       = sanitize_text_field($_GET['code']);
    $backend    = casdoor_get_option('backend') . '/api/login/oauth/access_token';
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
        'sslverify'   => false
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        exit("Something went wrong: {$error_message}");
    }

    $tokens = json_decode(wp_remote_retrieve_body($response));

    if (isset($tokens->error)) {
        wp_die($tokens->error_description);
    }

    $access_token = $tokens->access_token;
    $info = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $access_token)[1]))));
    
    $user_id = username_exists($info->name);

    if (!$user_id && (empty($info->email) || email_exists($info->email) == false)) {
        if (casdoor_get_option('login_only') == 1) {
            wp_safe_redirect(home_url() . '?message=casdoor_login_only');
            exit;
        }

        // Does not have an account... Register and then log the user in
        $random_password = wp_generate_password($length = 12, $include_standard_special_chars = false);
        $user_data = [
            'user_email'   => $info->email,
            'user_login'   => $info->name,
            'user_pass'    => $random_password,
            'display_name' => $info->displayName,
        ];
        if ($info->isGlobalAdmin) {
            $user_data['role'] = 'administrator';
        }

        $user_id = wp_insert_user($user_data);

        // Trigger new user created action so that there can be modifications to what happens after the user is created.
        // This can be used to collect other information about the user.
        do_action('casdoor_user_created', $info, 1);

        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        if (is_user_logged_in()) {
            wp_safe_redirect($user_redirect);
            exit;
        }
    } else {
        // Already Registered... Log the User In using ID or Email
        $random_password = __('User already exists.  Password inherited.');
        // Get the user by name
        $user            = get_user_by('login', $info->name);

        /*
         * Added just in case the user is not used but the email may be. If the user returns false from the user ID,
         * we should check the user by email. This may be the case when the users are preregistered outside of OAuth
         */
        if (!$user) {
            $user = get_user_by('email', $info->email);
        }

        // Trigger action when a user is logged in.
        // This will help allow extensions to be used without modifying the core plugin.
        do_action('casdoor_user_login', $info, 1);

        // User ID 1 is not allowed
        // if ('1' == $user->ID) {
        //     wp_safe_redirect(home_url() . '?message=casdoor_id_not_allowed');
        //     exit;
        // }

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        if (is_user_logged_in()) {
            wp_safe_redirect($user_redirect);
            exit;
        }
    }

    wp_safe_redirect(home_url() . '?message=casdoor_sso_failed');
    exit;
}
