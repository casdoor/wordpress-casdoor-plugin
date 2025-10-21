<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * The main class of plugin
 */
class Casdoor
{
    public $version = '1.3.0';

    public static $_instance = null;

    protected $default_settings = [
        'active'               => 0,
        'client_id'            => '',
        'client_secret'        => '',
        'backend'              => '',
        'organization'         => 'built-in',
        'server_oauth_trigger' => 'oauth',
        'server_auth_endpoint' => 'authorize',
        'server_token_endpont' => 'token',
        'server_user_endpoint' => 'me'
    ];

    public function __construct()
    {
        add_action('init', [__CLASS__, 'includes']);
        add_action('init', [__CLASS__, 'custom_login']);
    }

    /**
     * populate the instance if the plugin for extendability
     *
     * @return Casdoor
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * plugin includes called during load of plugin
     *
     * @return void
     */
    public static function includes()
    {
        require_once(CASDOOR_PLUGIN_DIR . '/includes/functions.php');
        require_once(CASDOOR_PLUGIN_DIR . '/includes/admin-options.php');
        require_once(CASDOOR_PLUGIN_DIR . '/includes/Rewrites.php');
    }

    /**
     * Plugin Setup
     */
    public function setup()
    {
        $options = get_option('casdoor_options');
        if (!isset($options['backend'])) {
            update_option('casdoor_options', $this->default_settings);
        }
        $this->install();
    }

    /**
     * When wp-login.php was visited, redirect to the login page of casdoor
     *
     * @return void
     */
    public static function custom_login() {
        global $pagenow;
        $activated = absint( casdoor_get_option( 'active' ) );
        $action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_STRING ) ?? '';
        if (
            'wp-login.php' === $pagenow
            && 'logout'     !== $action
            && $activated
        ) {
            // Preserve the intended destination (redirect_to chain or safe referer)
            $redirect = casdoor_get_login_target_from_request();
            $url = get_casdoor_login_url($redirect);
            wp_redirect( $url );
            exit();
        }
    }

    /**
     * WordPress logout hook handler.
     * Performs RP-initiated logout at Casdoor and then returns the user here.
     *
     * @return void
     */
    public function logout()
    {
        // Where to return users after logout
        $post_logout_redirect = home_url('/');

        // If WP provided a redirect_to, validate and prefer it
        if (!empty($_REQUEST['redirect_to'])) {
            $maybe_redirect = esc_url_raw((string) $_REQUEST['redirect_to']);
            $validated = wp_validate_redirect($maybe_redirect, $post_logout_redirect);
            if (!empty($validated)) {
                $post_logout_redirect = $validated;
            }
        }

        // Resolve Casdoor backend
        $backend = trim((string) casdoor_get_option('backend'));
        if ($backend === '') {
            wp_redirect($post_logout_redirect);
            exit;
        }
        $backend = rtrim($backend, '/');

        // Retrieve access token stored at login (used as id_token_hint for Casdoor)
        $cookie_name = 'casdoor_access_token';
        $access_token = isset($_COOKIE[$cookie_name]) ? sanitize_text_field((string) $_COOKIE[$cookie_name]) : '';

        // Clear the cookie locally regardless
        $cookie_domain = parse_url(home_url(), PHP_URL_HOST);
        $cookie_opts = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $cookie_domain ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        // setcookie options array is available on PHP >= 7.3
        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookie_name, '', $cookie_opts);
        } else {
            // best-effort fallback without SameSite
            setcookie($cookie_name, '', $cookie_opts['expires'], $cookie_opts['path'], $cookie_opts['domain'], $cookie_opts['secure'], $cookie_opts['httponly']);
        }

        // If we have a token, use RP-initiated logout with redirect
        if (!empty($access_token)) {
            $logout_endpoint = $backend . '/api/logout';
            $logout_url = add_query_arg(
                [
                    // Casdoor expects an access token here
                    'id_token_hint'            => $access_token,
                    'post_logout_redirect_uri' => $post_logout_redirect,
                ],
                $logout_endpoint
            );
            wp_redirect($logout_url);
            exit;
        }

        // Fallback: finish locally if no token is present
        wp_redirect($post_logout_redirect);
        exit;
    }

    /**
     * Loads the plugin styles and scripts into scope
     *
     * @return void
     */
    public function wp_enqueue()
    {
        wp_enqueue_script('jquery-ui-accordion');
        wp_enqueue_style('casdoor_admin');
        wp_enqueue_script('casdoor_admin');
    }

    /**
     * Plugin Initializer
     */
    public function plugin_init()
    {
    }

    /**
     * Plugin Install
     */
    public function install()
    {
    }

    /**
     * Plugin Upgrade
     */
    public function upgrade()
    {
    }
}
