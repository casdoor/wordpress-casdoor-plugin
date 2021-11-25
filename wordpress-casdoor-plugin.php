<?php
/**
 * Plugin Name: Wordpress Casdoor Plugin
 * Plugin URI: https://github.com/casdoor/wordpress-casdoor-plugin
 * Version: 1.0.0
 * Description: Creates the ability to login using Single Sign On from casdoor.
 * Author: casdoor
 * Author URI: https://github.com/casdoor/
 * License: GPL2
 *
 * This program is GLP but; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

 // ABSPATH prevent public user to directly access your .php files through URL.
defined('ABSPATH') or die('No script kiddies please!');

if (!defined('CASDOOR_PLUGIN_DIR')) {
    define('CASDOOR_PLUGIN_DIR', trailingslashit(plugin_dir_path(__FILE__)));
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

add_action('admin_menu', [new Casdoor(), 'plugin_init']);
register_activation_hook(__FILE__, [new Casdoor(), 'setup']);
register_activation_hook(__FILE__, [new Casdoor(), 'upgrade']);
