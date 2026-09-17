<?php
/**
 * Theme Downloader Class for Wp Downloader Toolkit.
 *
 * Handles ZIP archiving and streaming for installed WordPress themes.
 *
 * @package WP_Downloader_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDT_Theme_Downloader' ) ) {

	class WPDT_Theme_Downloader {

		/**
		 * Download an installed theme by stylesheet/slug as a ZIP archive.
		 *
		 * @param string $stylesheet Theme stylesheet folder name.
		 * @return void
		 */
		public static function wpdt_download_theme( $stylesheet ) {
			if ( empty( $stylesheet ) ) {
				wp_die( esc_html__( 'Invalid theme specified.', 'downloader-toolkit' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'PHP ZipArchive extension is required on your server to create ZIP archives.', 'downloader-toolkit' ) );
			}

			$theme = wp_get_theme( $stylesheet );
			if ( ! $theme->exists() ) {
				wp_die( esc_html__( 'Theme does not exist.', 'downloader-toolkit' ) );
			}

			$theme_dir = $theme->get_stylesheet_directory();
			if ( ! is_dir( $theme_dir ) ) {
				wp_die( esc_html__( 'Theme directory not found on server.', 'downloader-toolkit' ) );
			}

			$zip_name = sanitize_file_name( $stylesheet ) . '.zip';
			$temp_dir = get_temp_dir();
			$zip_file_path = trailingslashit( $temp_dir ) . 'wpdt-theme-' . time() . '-' . $zip_name;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
				wp_die( esc_html__( 'Could not create ZIP archive for theme.', 'downloader-toolkit' ) );
			}

			self::wpdt_add_folder_to_zip( $theme_dir, $zip, strlen( dirname( $theme_dir ) ) + 1 );
			$zip->close();

			if ( file_exists( $zip_file_path ) ) {
				self::wpdt_stream_file( $zip_file_path, $zip_name );
				wp_delete_file( $zip_file_path );
				exit;
			} else {
				wp_die( esc_html__( 'Failed to generate theme ZIP package.', 'downloader-toolkit' ) );
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

		/**
		 * Output file transfer headers and stream file content to user browser.
		 *
		 * @param string $file_path Absolute path to target file.
		 * @param string $download_filename Name of the file presented for download.
		 * @return void
		 */
		public static function wpdt_stream_file( $file_path, $download_filename ) {
			if ( ! file_exists( $file_path ) ) {
				wp_die( esc_html__( 'File not found for download.', 'downloader-toolkit' ) );
			}

			if ( ob_get_level() ) {
				ob_end_clean();
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $download_filename ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file_path ) );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile( $file_path );
			exit;
		}
	}
}
