<?php
/**
 * Plugin Downloader Class for Wp Downloader Toolkit.
 *
 * Handles ZIP archiving and streaming for installed WordPress plugins.
 *
 * @package WP_Downloader_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDT_Plugin_Downloader' ) ) {

	class WPDT_Plugin_Downloader {

		/**
		 * Download an installed plugin by plugin path (e.g., 'woocommerce/woocommerce.php').
		 *
		 * @param string $plugin_file Plugin file path relative to WP_PLUGIN_DIR.
		 * @return void
		 */
		public static function wpdt_download_plugin( $plugin_file ) {
			if ( empty( $plugin_file ) ) {
				wp_die( esc_html__( 'Invalid plugin specified.', 'nizbay-asset-downloader' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'PHP ZipArchive extension is required on your server to create ZIP archives.', 'nizbay-asset-downloader' ) );
			}

			$full_plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( ! file_exists( $full_plugin_path ) ) {
				wp_die( esc_html__( 'Plugin file or directory does not exist.', 'nizbay-asset-downloader' ) );
			}

			$is_dir = false;
			$target_dir = dirname( $full_plugin_path );
			$plugin_slug = sanitize_file_name( dirname( $plugin_file ) );

			if ( strpos( $plugin_file, '/' ) !== false && is_dir( $target_dir ) ) {
				$is_dir = true;
			} else {
				$plugin_slug = sanitize_file_name( pathinfo( $plugin_file, PATHINFO_FILENAME ) );
			}

			$zip_name = $plugin_slug . '.zip';
			$temp_dir = get_temp_dir();
			$zip_file_path = trailingslashit( $temp_dir ) . 'wpdt-plugin-' . time() . '-' . $zip_name;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
				wp_die( esc_html__( 'Could not create ZIP archive for plugin.', 'nizbay-asset-downloader' ) );
			}

			if ( $is_dir ) {
				self::wpdt_add_folder_to_zip( $target_dir, $zip, strlen( WP_PLUGIN_DIR ) + 1 );
			} else {
				$zip->addFile( $full_plugin_path, basename( $full_plugin_path ) );
			}

			$zip->close();

			if ( file_exists( $zip_file_path ) ) {
				WPDT_Theme_Downloader::wpdt_stream_file( $zip_file_path, $zip_name );
				wp_delete_file( $zip_file_path );
				exit;
			} else {
				wp_die( esc_html__( 'Failed to generate plugin ZIP package.', 'nizbay-asset-downloader' ) );
			}
		}

		/**
		 * Recursively add folder files to ZipArchive.
		 *
		 * @param string     $folder_path Absolute path to target folder.
		 * @param ZipArchive $zip Zip Archive instance.
		 * @param int        $strip_prefix Prefix length to strip for internal ZIP relative path.
		 * @return void
		 */
		private static function wpdt_add_folder_to_zip( $folder_path, &$zip, $strip_prefix ) {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $folder_path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $files as $file ) {
				if ( ! $file->isDir() ) {
					$file_path = $file->getRealPath();
					$relative_path = substr( $file_path, $strip_prefix );
					$relative_path = str_replace( '\\', '/', $relative_path );
					$zip->addFile( $file_path, $relative_path );
				}
			}
		}
	}
}
