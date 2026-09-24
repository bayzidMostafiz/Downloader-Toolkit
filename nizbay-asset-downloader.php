<?php
/**
 * Plugin Name:       Nizbay Asset Downloader
 * Plugin URI:        https://github.com/bayzidMostafiz/Downloader-Toolkit.git
 * Description:       A comprehensive WordPress asset downloader toolkit. Export installed themes, plugins, and media library files directly from your WordPress Dashboard as ZIP archives.
 * Version:           1.0.3
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Md. Bayzid Mostafiz
 * Author URI:        https://bayzidmostafiz.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nizbay-asset-downloader
 * Domain Path:       /languages
 *
 * @package WP_Downloader_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants with WPDT_ prefix safely.
if ( ! defined( 'WPDT_VERSION' ) ) {
	define( 'WPDT_VERSION', '1.0.3' );
}
if ( ! defined( 'WPDT_PATH' ) ) {
	define( 'WPDT_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPDT_URL' ) ) {
	define( 'WPDT_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPDT_FILE' ) ) {
	define( 'WPDT_FILE', __FILE__ );
}

// Remote API Endpoint URL matching WPDT Data Collector plugin (/wp-json/wpdt-collector/v1/subscribe).
if ( ! defined( 'WPDT_REMOTE_API_URL' ) ) {
	define( 'WPDT_REMOTE_API_URL', 'https://bayzidmostafiz.com/wp-json/wpdt-collector/v1/subscribe' );
}

// Require Core Plugin Classes safely.
require_once WPDT_PATH . 'includes/class-wpdt-main.php';
require_once WPDT_PATH . 'includes/class-wpdt-theme-downloader.php';
require_once WPDT_PATH . 'includes/class-wpdt-plugin-downloader.php';
require_once WPDT_PATH . 'includes/class-wpdt-media-downloader.php';

if ( ! function_exists( 'wpdt_activate_plugin' ) ) {
	/**
	 * Set transient upon plugin activation for dashboard redirect and reset optin test state.
	 *
	 * @return void
	 */
	function wpdt_activate_plugin() {
		set_transient( 'wpdt_activation_redirect', true, 60 );
		delete_option( 'wpdt_optin_completed' );
		delete_option( 'wpdt_optin_dismissed' );
	}
}
register_activation_hook( __FILE__, 'wpdt_activate_plugin' );

if ( ! function_exists( 'wpdt_init' ) ) {
	/**
	 * Initialize the plugin instance.
	 *
	 * @return WPDT_Main
	 */
	function wpdt_init() {
		return WPDT_Main::wpdt_get_instance();
	}
}

// Start the plugin.
wpdt_init();
