<?php
/**
 * PHPUnit bootstrap file for WordPress Casdoor Plugin tests.
 */

// Require composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load Brain Monkey for mocking WordPress functions
require_once dirname(__DIR__) . '/vendor/brain/monkey/inc/patchwork-loader.php';

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('CASDOOR_PLUGIN_DIR')) {
    define('CASDOOR_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Ensure the canonical plugin version constant is available for tests.
// Keep in sync with the plugin header Version set in wordpress-casdoor-plugin.php.
if (!defined('CASDOOR_PLUGIN_VERSION')) {
    define('CASDOOR_PLUGIN_VERSION', '1.3.0');
}

// Start Brain Monkey
\Brain\Monkey\setUp();

// Clean up after tests
register_shutdown_function(function () {
    \Brain\Monkey\tearDown();
});
