<?php
/**
 * Plugin Name:       Casdoor – SSO, OAuth 2.0 & OIDC Login
 * Plugin URI:        https://github.com/casdoor/wordpress-casdoor-plugin
 * Description:       Log in to WordPress with Casdoor, the open-source identity and access management (IAM) and single sign-on (SSO) platform, over OAuth 2.0 and OpenID Connect.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Casdoor
 * Author URI:        https://casdoor.ai
 * License:           Apache-2.0
 * License URI:       https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain:       casdoor
 */

defined('ABSPATH') || exit;

if (!defined('CASDOOR_PLUGIN_DIR')) {
    define('CASDOOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once CASDOOR_PLUGIN_DIR . 'includes/functions.php';
require_once CASDOOR_PLUGIN_DIR . 'includes/callback.php';
require_once CASDOOR_PLUGIN_DIR . 'includes/class-casdoor-plugin.php';
require_once CASDOOR_PLUGIN_DIR . 'includes/class-casdoor-admin.php';
require_once CASDOOR_PLUGIN_DIR . 'includes/class-casdoor-rewrites.php';

Casdoor_Plugin::instance();
Casdoor_Admin::init();
Casdoor_Rewrites::init();

register_activation_hook(__FILE__, ['Casdoor_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Casdoor_Plugin', 'deactivate']);
