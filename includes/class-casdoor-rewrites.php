<?php

defined('ABSPATH') || exit;

/**
 * Routes `?auth=casdoor` (and `/auth/casdoor`) to the login, see casdoor_handle_auth_request().
 */
class Casdoor_Rewrites
{
    public static function init()
    {
        // The rules are flushed when the plugin is activated or deactivated, not on every request.
        add_filter('rewrite_rules_array', [__CLASS__, 'create_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_redirect_intercept']);
        add_action('wp_body_open', [__CLASS__, 'show_message']);
    }

    public static function create_rewrite_rules($rules): array
    {
        global $wp_rewrite;
        $newRule = ['auth/(.+)' => 'index.php?auth=' . $wp_rewrite->preg_index(1)];

        return $newRule + (array) $rules;
    }

    public static function add_query_vars($qvars): array
    {
        $qvars[] = 'auth';
        return $qvars;
    }

    /**
     * The message of a failed login, see casdoor_login_failed().
     *
     * @return string
     */
    public static function message(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a message to show.
        return isset($_GET['casdoor_message']) && is_string($_GET['casdoor_message']) ? sanitize_key(wp_unslash($_GET['casdoor_message'])) : '';
    }

    public static function show_message()
    {
        if (self::message() !== '') {
            require CASDOOR_PLUGIN_DIR . 'templates/error-msg.php';
        }
    }

    public static function template_redirect_intercept()
    {
        if (!absint(casdoor_get_option('active'))) {
            return;
        }
        $auth = (string) get_query_var('auth');

        // Some pages of casdoor add "?code=" to the redirect uri although it has a "?" already,
        // then the value of auth is like "casdoor?code=c9550137370a99bc2137".
        if (preg_match('/^casdoor\?code=([a-zA-Z0-9]+)$/', $auth, $matches)) {
            $args = ['auth' => 'casdoor', 'code' => $matches[1]];
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the state is checked on the callback.
            if (isset($_GET['state']) && is_string($_GET['state'])) {
                $args['state'] = sanitize_text_field(wp_unslash($_GET['state']));
            }
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            wp_safe_redirect(home_url('?' . http_build_query($args)));
            exit;
        }

        // No auto login on the page that shows why the login failed, it would fail again.
        $auto_sso = absint(casdoor_get_option('auto_sso')) && !is_user_logged_in() && self::message() === '';

        if ($auth === 'casdoor' || $auto_sso) {
            casdoor_handle_auth_request();
            exit;
        }
    }
}
