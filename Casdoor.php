<?php

defined('ABSPATH') or die('No script kiddies please!');

/**
 * The main class of plugin
 */
class Casdoor
{
    /**
     * Instance-level representation of the plugin version.
     * Populated from CASDOOR_PLUGIN_VERSION (plugin header) when available.
     *
     * @var string
     */
    public $version;

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
        // Populate instance-level version from canonical plugin constant, with safe fallback.
        $this->version = defined('CASDOOR_PLUGIN_VERSION') ? CASDOOR_PLUGIN_VERSION : '1.0.0';

        add_action('init', [__CLASS__, 'includes']);
        add_action('init', [__CLASS__, 'custom_login']);
    }

    /**
     * Return the plugin version from the canonical source (plugin header constant).
     *
     * @return string
     */
    public static function getVersionStatic(): string
    {
        if (defined('CASDOOR_PLUGIN_VERSION')) {
            return CASDOOR_PLUGIN_VERSION;
        }

        // Fallback for test environments where the plugin header constant may not be defined.
        return '1.0.0';
    }

    /**
     * Backwards-compatible instance method to get the plugin version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return self::getVersionStatic();
    }

    /**
     * populate the instance for extendability
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

    // ... other methods (includes, custom_login, etc.) remain as in the PR ...

    public static function custom_login()
    {
        // implementation from PR (left as-is)
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
