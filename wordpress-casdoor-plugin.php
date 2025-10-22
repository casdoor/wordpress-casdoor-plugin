<?php
/**
 * Plugin Name: Wordpress Casdoor Plugin
 * Plugin URI: https://github.com/casdoor/wordpress-casdoor-plugin
 * Version: 1.3.0
 * Description: Creates the ability to login using Single Sign On from casdoor.
 * Author: casdoor
 * Author URI: https://github.com/casdoor/
 * License: Apache
 */

 // ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

if (!defined('CASDOOR_PLUGIN_DIR')) {
    define('CASDOOR_PLUGIN_DIR', trailingslashit(plugin_dir_path(__FILE__)));
}

// Make plugin version available as a constant (single source of truth)
if (!defined('CASDOOR_PLUGIN_VERSION')) {
    define('CASDOOR_PLUGIN_VERSION', '1.3.0');
}

// Require the main plugin class
require_once(CASDOOR_PLUGIN_DIR . 'Casdoor.php');

add_action('wp_loaded', 'casdoor_register_files');

function casdoor_register_files()
{
    // Register a CSS stylesheet.
    wp_register_style('casdoor_admin', plugins_url('/assets/css/admin.css', __FILE__));
    // Register a new script.
    wp_register_script('casdoor_admin', plugins_url('/assets/js/admin.js', __FILE__));
}

$casdoor = new Casdoor();
add_action('admin_menu', [$casdoor, 'plugin_init']);
add_action('wp_enqueue_scripts', [$casdoor, 'wp_enqueue']);
add_action('wp_logout', [$casdoor, 'logout']);
register_activation_hook(__FILE__, [$casdoor, 'setup']);
register_activation_hook(__FILE__, [$casdoor, 'upgrade']);
