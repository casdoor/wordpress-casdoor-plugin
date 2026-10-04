<?php

/**
 * This file is called when the auth param is found in the URL.
 */
defined('ABSPATH') or die('No script kiddies please!');

// Redirect the user back to the home page if logged in.
if (is_user_logged_in()) {
    wp_safe_redirect(home_url());
    exit;
}

// Start the login: remember where to go afterwards and send the user to casdoor.
if (empty($_GET['code'])) {
    if (!empty($_GET['error'])) {
        casdoor_login_failed('casdoor_sso_failed');
    }

    $user_redirect = casdoor_get_user_redirect_url();
    if (!empty($_GET['redirect_uri']) && is_string($_GET['redirect_uri'])) {
        $user_redirect = wp_validate_redirect(esc_url_raw(wp_unslash($_GET['redirect_uri'])), $user_redirect);
    }

    wp_redirect(get_casdoor_login_url(casdoor_create_state($user_redirect)));
    exit;
}

// Handle the callback from casdoor.
$state         = isset($_GET['state']) && is_string($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
$user_redirect = casdoor_consume_state($state);
if ($user_redirect === null) {
    casdoor_login_failed('casdoor_invalid_state');
}

$access_token = casdoor_exchange_code(sanitize_text_field(wp_unslash((string) $_GET['code'])));
if (is_wp_error($access_token)) {
    wp_die(esc_html('Casdoor Single Sign On failed: ' . $access_token->get_error_message()));
}

$info = casdoor_get_account($access_token);
if (is_wp_error($info)) {
    wp_die(esc_html('Casdoor Single Sign On failed: ' . $info->get_error_message()));
}

$organization = (string) casdoor_get_option('organization');
if ($organization !== '' && $info->owner !== $organization) {
    casdoor_login_failed('casdoor_wrong_organization');
}

$user = casdoor_find_user($info);
if ($user) {
    // Trigger action when a user is logged in.
    // This will help allow extensions to be used without modifying the core plugin.
    do_action('casdoor_user_login', $info, 1);
} else {
    if (casdoor_get_option('login_only') == 1) {
        casdoor_login_failed('casdoor_login_only');
    }

    $user = casdoor_create_user($info);
    if (is_wp_error($user)) {
        casdoor_login_failed($user->get_error_code() === 'casdoor_email_conflict' ? 'casdoor_email_conflict' : 'casdoor_sso_failed');
    }

    // Trigger new user created action so that there can be modifications to what happens after the user is created.
    // This can be used to collect other information about the user.
    do_action('casdoor_user_created', $info, 1);
}

update_user_meta($user->ID, CASDOOR_USER_META_KEY, casdoor_account_id($info));
// Keep the token, it is needed to log the user out of casdoor on wordpress logout.
update_user_meta($user->ID, CASDOOR_TOKEN_META_KEY, $access_token);

wp_clear_auth_cookie();
wp_set_current_user($user->ID);
wp_set_auth_cookie($user->ID);

wp_safe_redirect($user_redirect);
exit;
