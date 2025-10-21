<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

function defaults()
{
    return [
        'client_id'             => '',
        'client_secret'         => '',
        'backend'               => '',
        'redirect_to_dashboard' => 0,
        'login_only'            => 0,
        'auto_sso'              => 0, // If enabled (1), automatically attempt Single Sign-On (SSO) for users on login page load.
        'active'                => 0, // If enabled (1), activates the Casdoor authentication plugin; if disabled (0), plugin is inactive.
    ];
}

function casdoor_get_options_internal()
{
    $options = get_option(casdoor_admin::OPTIONS_NAME, []);
    if (!is_array($options)) {
        $options = defaults();
    }
    $options = array_merge(defaults(), $options);
    return $options;
}

/**
 * get option value
 *
 * @param string $option_name
 *
 * @return mixed|null
 */
function casdoor_get_option(string $option_name)
{
    $options = casdoor_get_options_internal();
    // Safe access; avoid undefined index warnings and preserve 0 values
    return $options[$option_name] ?? null;
}

function casdoor_set_options(string $key, $value)
{
    $options = casdoor_get_options_internal();
    $options[$key] = $value;
    update_option(casdoor_admin::OPTIONS_NAME, $options);
}

/**
 * Get the login url of casdoor
 *
 * @param string $redirect Full or relative URL to return to (goes into OAuth state)
 *
 * @return string
 */
function get_casdoor_login_url(string $redirect = ''): string
{
    // IMPORTANT: Do NOT include client_secret in the browser-facing authorize URL.
    $params = [
        'oauth'         => 'authorize',
        'response_type' => 'code',
        'client_id'     => casdoor_get_option('client_id'),
        'redirect_uri'  => site_url('?auth=casdoor'),
        'state'         => urlencode($redirect),
    ];
    $params = http_build_query($params);
    return casdoor_get_option('backend') . '/login/oauth/authorize?' . $params;
}

/**
 * Add login button for casdoor on the login form.
 *
 * @link https://codex.wordpress.org/Plugin_API/Action_Reference/login_form
 */
function casdoor_login_form_button()
{
    ?>
    <a style="color:#FFF; width:100%; text-align:center; margin-bottom:1em;" class="button button-primary button-large"
       href="<?php echo site_url('?auth=casdoor'); ?>">Casdoor Single Sign On</a>
    <div style="clear:both;"></div>
    <?php
}
// Fires following the ‘Password’ field in the login form.
// It can be used to customize the built-in WordPress login form. Use in conjunction with ‘login_head‘ (for validation).
// add_action('login_form', 'casdoor_login_form_button');

/**
 * Login Button Shortcode
 *
 * @param  [type] $atts [description]
 *
 * @return [type]       [description]
 */
function casdoor_login_button_shortcode($atts)
{
    $a = shortcode_atts([
        'type'   => 'primary',
        'title'  => 'Login using Casdoor',
        'class'  => 'sso-button',
        'target' => '_blank',
        'text'   => 'Casdoor Single Sign On'
    ], $atts);

    return '<a class="' . $a['class'] . '" href="' . site_url('?auth=casdoor') . '" title="' . $a['title'] . '" target="' . $a['target'] . '">' . $a['text'] . '</a>';
}
add_shortcode('sso_button', 'casdoor_login_button_shortcode');

/**
 * Get user login redirect.
 * Just in case the user wants to redirect the user to a new url.
 *
 * @return string
 */
function casdoor_get_user_redirect_url(): string
{
    $options           = get_option('casdoor_options');
    // Retrieves the URL to the user’s dashboard.
    $user_redirect_set = !empty($options['redirect_to_dashboard']) && $options['redirect_to_dashboard'] == '1'
        ? get_dashboard_url()
        : site_url();
    $user_redirect     = apply_filters('casdoor_user_redirect_url', $user_redirect_set);

    return $user_redirect;
}

/**
 * Treat relative URLs as same-origin. For absolute URLs, enforce same host.
 */
function casdoor_same_origin(string $url): bool
{
    if ($url === '') return false;
    $t = wp_parse_url($url);
    if (empty($t['host'])) {
        // Relative => same origin
        return true;
    }
    $s = wp_parse_url(home_url());
    if (empty($s['host'])) return false;
    return strtolower($t['host']) === strtolower($s['host']);
}

/**
 * Resolve nested redirect chains commonly used by WordPress/WooCommerce.
 * Unwraps up to 3 levels: redirect_to, redirect, wc-redirect, return_to.
 */
function casdoor_resolve_redirect_chain(string $url): string
{
    $current = $url;
    $keys = ['redirect_to', 'redirect', 'wc-redirect', 'return_to'];

    for ($i = 0; $i < 3; $i++) {
        if ($current === '') break;
        $parsed = wp_parse_url($current);
        if (empty($parsed['query'])) break;

        parse_str($parsed['query'], $q);
        $candidate = '';
        foreach ($keys as $k) {
            if (!empty($q[$k])) {
                $candidate = (string)$q[$k];
                break;
            }
        }
        if ($candidate === '' || !casdoor_same_origin($candidate)) break;
        $current = $candidate;
    }
    return $current;
}

/**
 * Compute the intended post-login target from the current request.
 * - Prefer redirect_to (unwraps nested), otherwise safe referer, otherwise admin/home.
 * - Returns a sanitized URL (may be relative).
 */
function casdoor_get_login_target_from_request(): string
{
    $target = '';

    if (!empty($_REQUEST['redirect_to'])) {
        $resolved = casdoor_resolve_redirect_chain((string) $_REQUEST['redirect_to']);
        if ($resolved !== '' && casdoor_same_origin($resolved)) {
            $target = wp_sanitize_redirect($resolved);
        }
    }

    if ($target === '') {
        $ref = wp_get_referer();
        if (!empty($ref)
            && casdoor_same_origin($ref)
            && strpos($ref, 'wp-login.php') === false
            && strpos($ref, '?auth=casdoor') === false) {
            $target = wp_sanitize_redirect($ref);
        }
    }

    if ($target === '') {
        $fallback = admin_url();
        // In normal configurations, admin_url() should always be same-origin.
        // If this is not the case, it may indicate a misconfiguration (e.g., site_url and home_url domains differ).
        if (!casdoor_same_origin($fallback)) {
            error_log('[casdoor] Warning: admin_url() is not same-origin. Falling back to home_url("/"). This may indicate a configuration issue.');
            $fallback = home_url('/');
        }
        $target = wp_sanitize_redirect($fallback);
    }

    return $target;
}
