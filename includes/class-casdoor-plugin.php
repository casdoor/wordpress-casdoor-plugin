<?php

defined('ABSPATH') || exit;

/**
 * The main class of plugin
 */
class Casdoor_Plugin
{
    private static $instance = null;

    private function __construct()
    {
        add_action('init', [__CLASS__, 'custom_login']);
        add_action('init', 'casdoor_register_shortcodes');
        // The user id is needed to log the user out of casdoor, so ask for the hook argument.
        add_action('wp_logout', [__CLASS__, 'logout'], 10, 1);
    }

    /**
     * populate the instance if the plugin for extendability
     *
     * @return Casdoor_Plugin
     */
    public static function instance(): Casdoor_Plugin
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Keep the settings of an earlier installation and add the `/auth/casdoor` rewrite rule.
     */
    public static function activate()
    {
        add_option(CASDOOR_OPTIONS, casdoor_default_options());
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * When wp-login.php was visited, redirect to the login page of casdoor
     *
     * Users that only exist in wordpress can not log in on casdoor, so the wordpress login form
     * is still served on `wp-login.php?use_native_login=1`, see casdoor_should_redirect_login().
     *
     * @return void
     */
    public static function custom_login()
    {
        global $pagenow;
        $activated = absint(casdoor_get_option('active'));
        if ('wp-login.php' !== $pagenow || !$activated) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides which login page to show.
        if (casdoor_should_redirect_login($method, wp_unslash($_GET))) {
            // The login starts on the callback, it creates the state and keeps `redirect_to`.
            $url = casdoor_redirect_uri();
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- validated on the callback.
            if (!empty($_GET['redirect_to']) && is_string($_GET['redirect_to'])) {
                $url = add_query_arg('redirect_uri', rawurlencode(esc_url_raw(wp_unslash($_GET['redirect_to']))), $url);
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            wp_safe_redirect($url);
            exit;
        }

        // The wordpress login form is shown, keep the links on it (e.g. "Back to login" on the
        // lost password page) on the wordpress form and offer casdoor as well.
        add_filter('login_url', 'casdoor_native_login_url');
        add_action('login_form', 'casdoor_login_form_button');
    }

    /**
     * When the user logs out of wordpress, log the user out of casdoor as well.
     * Without this the casdoor session stays alive, so the user is still logged in on the
     * casdoor side and the next login will not ask for the credentials again.
     *
     * @param int $user_id the user that logs out, passed by the `wp_logout` hook
     *
     * @return void
     */
    public static function logout($user_id = 0)
    {
        // The current user is already reset when `wp_logout` fires, so the id comes from the hook.
        $user_id    = absint($user_id);
        $logout_url = absint(casdoor_get_option('logout_from_casdoor')) ? casdoor_get_logout_url($user_id) : '';

        // The token belongs to the session that just ended, casdoor expires it during the logout.
        if ($user_id) {
            delete_user_meta($user_id, CASDOOR_TOKEN_META_KEY);
        }

        if ($logout_url !== '') {
            // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- casdoor is another host.
            wp_redirect($logout_url);
            exit;
        }

        if (!absint(casdoor_get_option('auto_sso'))) {
            wp_safe_redirect(home_url());
            exit;
        }
    }
}
