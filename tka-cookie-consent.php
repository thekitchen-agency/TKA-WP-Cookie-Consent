<?php
/**
 * Plugin Name:       TKA Cookie Consent
 * Description:       A lightweight, modern cookie consent plugin compatible with the WP Consent API standard.
 * Version:           1.1.0
 * Author:            TKA
 * Author URI:        https://wp-play.ddev.site
 * License:           MIT
 * Text Domain:       tka-cookie-consent
 * Domain Path:       /languages
 * Requires PHP:      8.3
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

// Define plugin constants
define('TKA_COOKIE_CONSENT_VERSION', '1.1.0');
define('TKA_COOKIE_CONSENT_PATH', plugin_dir_path(__FILE__));
define('TKA_COOKIE_CONSENT_URL', plugin_dir_url(__FILE__));

/**
 * Register PSR-4 Autoloader for the plugin.
 */
spl_autoload_register(function ($class) {
	$prefix = 'Tka\\CookieConsent\\';
	$base_dir = TKA_COOKIE_CONSENT_PATH . 'includes/';

	$len = strlen($prefix);
	if (strncmp($prefix, $class, $len) !== 0) {
		return;
	}

	$relative_class = substr($class, $len);
	$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

	if (file_exists($file)) {
		require_once $file;
	}
});

/**
 * Begin plugin execution.
 */
add_action('plugins_loaded', function () {
	// Initialize Consent API compatibility / fallbacks
	Tka\CookieConsent\ConsentAPI::init();

	// Initialize the main plugin manager
	Tka\CookieConsent\Plugin::getInstance()->run();
});

add_action('init', function () {
	load_plugin_textdomain('tka-cookie-consent', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
