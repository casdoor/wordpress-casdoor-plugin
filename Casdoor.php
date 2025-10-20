<?php

// ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

/**
 * The main class of plugin
 */
class Casdoor
{
    public $version = '1.0.0';

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

    public function logout()
    {
        $auto_sso = absint(casdoor_get_option('auto_sso'));
        if (!$auto_sso) {
            wp_redirect(home_url());
            die();
        }
    }

    /**
     * Loads the plugin styles and scripts into scope
     *
     * @return void
     */
    public function wp_enqueue()
    {
        // Registers the script if $src provided (does NOT overwrite), and enqueues it.
        wp_enqueue_script('jquery-ui-accordion');
        // Registers the style if source provided (does NOT overwrite) and enqueues.
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
