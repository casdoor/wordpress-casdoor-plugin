<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

// The user meta that keeps the casdoor access token of a user, it is needed to log the
// user out of casdoor when the user logs out of wordpress.
if (!defined('CASDOOR_TOKEN_META_KEY')) {
    define('CASDOOR_TOKEN_META_KEY', 'casdoor_access_token');
}

function defaults()
{
    return [
        'client_id'             => '',
        'client_secret'         => '',
        'backend'               => '',
        'redirect_to_dashboard' => 0,
        'login_only'            => 0,
        'logout_from_casdoor'   => 0,
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
 * @return void|string
 */
function casdoor_get_option(string $option_name)
{
    $options = casdoor_get_options_internal();
    if (!empty($v = $options[$option_name])) {
        return $v;
    }
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
 * @param string $redirect
 *
 * @return string
 */
function get_casdoor_login_url(string $redirect = ''): string
{
    $params = [
        'oauth'         => 'authorize',
        'response_type' => 'code',
        'client_id'     => casdoor_get_option('client_id'),
        'client_secret' => casdoor_get_option('client_secret'),
        'redirect_uri'  => site_url('?auth=casdoor'),
        'state'         => urlencode($redirect)
    ];
    $params = http_build_query($params);
    return casdoor_get_option('backend') . '/login/oauth/authorize?' . $params;
}

/**
 * Get the logout url of casdoor.
 *
 * Casdoor implements the OIDC RP-Initiated Logout on `/api/logout`, it ends the casdoor
 * session and sends the user back to `post_logout_redirect_uri` afterwards.
 * An empty string is returned when the logout can not be done, e.g. when the user logged
 * in with the wordpress login form and there is no casdoor session at all.
 *
 * @param int    $user_id  the wordpress user that is logging out
 * @param string $redirect where casdoor should send the user back to
 *
 * @return string
 */
function get_casdoor_logout_url(int $user_id, string $redirect = ''): string
{
    $backend = casdoor_get_option('backend');
    if (empty($backend) || empty($user_id)) {
        return '';
    }

    // Casdoor needs the access token to know which session has to be ended, it was saved
    // when the user logged in.
    $access_token = get_user_meta($user_id, CASDOOR_TOKEN_META_KEY, true);
    if (empty($access_token)) {
        return '';
    }

    if (empty($redirect)) {
        $redirect = home_url('/');
    }
    // The redirect must be in the `Redirect URLs` list of the casdoor application,
    // otherwise casdoor refuses to redirect back.
    $redirect = apply_filters('casdoor_post_logout_redirect_url', $redirect);

    $params = http_build_query([
        'id_token_hint'            => $access_token,
        'post_logout_redirect_uri' => $redirect,
        'client_id'                => casdoor_get_option('client_id')
    ]);

    return rtrim($backend, '/') . '/api/logout?' . $params;
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
    $user_redirect_set = $options['redirect_to_dashboard'] == '1' ? get_dashboard_url() : site_url();
    $user_redirect     = apply_filters('casdoor_user_redirect_url', $user_redirect_set);

    return $user_redirect;
}
