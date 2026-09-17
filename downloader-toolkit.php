<?php
/**
 * Plugin Name:       Downloader Toolkit
 * Plugin URI:        https://wordpress.org/plugins/downloader-toolkit
 * Description:       A comprehensive WordPress asset and file manager toolkit. Download installed themes, plugins, media library files, and manage site files directly from WP Dashboard.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Md. Bayzid Mostafiz
 * Author URI:        https://www.linkedin.com/in/md-bayzid-mostafiz-152b80139/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       downloader-toolkit
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
	define( 'WPDT_VERSION', '1.0.0' );
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

// Require Core Plugin Classes safely.
require_once WPDT_PATH . 'includes/class-wpdt-main.php';
require_once WPDT_PATH . 'includes/class-wpdt-theme-downloader.php';
require_once WPDT_PATH . 'includes/class-wpdt-plugin-downloader.php';
require_once WPDT_PATH . 'includes/class-wpdt-media-downloader.php';
require_once WPDT_PATH . 'includes/class-wpdt-file-manager.php';

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
