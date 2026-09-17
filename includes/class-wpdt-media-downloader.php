<?php
/**
 * Media Downloader Class for Wp Downloader Toolkit.
 *
 * Handles single and bulk media library file exports.
 *
 * @package WP_Downloader_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDT_Media_Downloader' ) ) {

	class WPDT_Media_Downloader {

		/**
		 * Download a single media attachment file.
		 *
		 * @param int $attachment_id Media attachment post ID.
		 * @return void
		 */
		public static function wpdt_download_single_media( $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id ) {
				wp_die( esc_html__( 'Invalid attachment ID.', 'downloader-toolkit' ) );
			}

			$file_path = get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				wp_die( esc_html__( 'Media file not found on disk.', 'downloader-toolkit' ) );
			}

			$filename = basename( $file_path );
			WPDT_Theme_Downloader::wpdt_stream_file( $file_path, $filename );
		}

		/**
		 * Download multiple media files as a ZIP archive.
		 *
		 * @param array $attachment_ids Array of media attachment post IDs.
		 * @return void
		 */
		public static function wpdt_download_bulk_media( array $attachment_ids ) {
			$ids = array_filter( array_map( 'absint', $attachment_ids ) );
			if ( empty( $ids ) ) {
				wp_die( esc_html__( 'No valid media files selected for download.', 'downloader-toolkit' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'PHP ZipArchive extension is required on your server to create ZIP archives.', 'downloader-toolkit' ) );
			}

			$temp_dir = get_temp_dir();
			$zip_name = 'wpdt-media-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
			$zip_file_path = trailingslashit( $temp_dir ) . $zip_name;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
				wp_die( esc_html__( 'Could not create ZIP archive for media files.', 'downloader-toolkit' ) );
			}

			$added_count = 0;
			foreach ( $ids as $id ) {
				$file_path = get_attached_file( $id );
				if ( $file_path && file_exists( $file_path ) ) {
					$filename = basename( $file_path );
					$zip_path = $filename;
					$counter = 1;
					while ( $zip->locateName( $zip_path ) !== false ) {
						$info = pathinfo( $filename );
						$ext = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
						$zip_path = $info['filename'] . '_' . $counter . $ext;
						$counter++;
					}
					$zip->addFile( $file_path, $zip_path );
					$added_count++;
				}
			}

			$zip->close();

			if ( $added_count > 0 && file_exists( $zip_file_path ) ) {
				WPDT_Theme_Downloader::wpdt_stream_file( $zip_file_path, $zip_name );
				wp_delete_file( $zip_file_path );
				exit;
			} else {
				wp_die( esc_html__( 'Failed to create ZIP package or no files were accessible.', 'downloader-toolkit' ) );
			}
		}
	}
}
