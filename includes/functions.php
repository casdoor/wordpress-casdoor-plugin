<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * @todo
 * Auto redirect for users that are not logged in.
 */
add_filter('template_redirect', 'casdoor_init', 11);

function casdoor_init($template)
{
    // if (!is_user_logged_in()) {
    //     $options = get_option('casdoor_options');
    // }
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
    $options = get_option('casdoor_options');
    if (!empty($v = $options[$option_name])) {
        return $v;
    }
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
add_action('login_form', 'casdoor_login_form_button');

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

/**
 * Add message to the login/out page with login only
 */
function casdoor_login_only_message(string $message): string
{
    if (empty($message) && isset($_GET['casdoor_login_only'])) {
        return '<p><strong>' . apply_filters('casdoor_login_only_msg', 'You need to have an account to use Casdoor Single Sign On') . '</strong></p>';
    } else {
        return $message;
    }
}

add_filter('login_message', 'casdoor_login_only_message');
