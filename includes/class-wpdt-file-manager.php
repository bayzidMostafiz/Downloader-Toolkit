<?php
/**
 * File Manager Class for Downloader Toolkit.
 *
 * Handles root directory exploration, breadcrumb navigation, code editing, file saving, file uploading, deletion, streaming, and bulk ZIP exports.
 *
 * @package WP_Downloader_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDT_File_Manager' ) ) {

	class WPDT_File_Manager {

		/**
		 * Helper to initialize WP_Filesystem API.
		 *
		 * @return WP_Filesystem_Base|false
		 */
		public static function wpdt_init_filesystem() {
			global $wp_filesystem;

			if ( ! empty( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
				return $wp_filesystem;
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';

			if ( WP_Filesystem() ) {
				return $wp_filesystem;
			}

			return false;
		}

		/**
		 * Verify if target path is safely within WordPress root directory (ABSPATH).
		 *
		 * @param string $path Target filesystem path (relative or absolute).
		 * @return string|false Canonical absolute path or false if unsafe/restricted.
		 */
		public static function wpdt_validate_path( $path = '' ) {
			$real_abspath = realpath( ABSPATH );
			if ( ! $real_abspath ) {
				return false;
			}

			$norm_abspath = str_replace( '\\', '/', $real_abspath );

			if ( empty( $path ) || '.' === $path || '/' === $path || '\\' === $path ) {
				return $real_abspath;
			}

			$norm_path = str_replace( '\\', '/', $path );

			if ( 0 === stripos( $norm_path, $norm_abspath ) ) {
				$target_abs = $path;
			} else {
				$target_abs = trailingslashit( $real_abspath ) . ltrim( $path, '/\\' );
			}

			$real_path = realpath( $target_abs );
			if ( ! $real_path ) {
				return false;
			}

			$norm_real_path = str_replace( '\\', '/', $real_path );

			// Ensure path stays strictly within WordPress root ABSPATH.
			if ( 0 === stripos( $norm_real_path, $norm_abspath ) ) {
				return $real_path;
			}

			return false;
		}

		/**
		 * Get relative path from ABSPATH.
		 *
		 * @param string $abs_path Absolute path.
		 * @return string Relative path.
		 */
		public static function wpdt_get_relative_path( $abs_path ) {
			$real_abspath = realpath( ABSPATH );
			$real_path = realpath( $abs_path );

			if ( ! $real_abspath || ! $real_path ) {
				return '';
			}

			$norm_abspath = str_replace( '\\', '/', $real_abspath );
			$norm_path = str_replace( '\\', '/', $real_path );

			if ( 0 !== stripos( $norm_path, $norm_abspath ) ) {
				return '';
			}

			$rel = substr( $norm_path, strlen( $norm_abspath ) );
			$rel = ltrim( $rel, '/' );
			return $rel;
		}

		/**
		 * List directory contents for File Manager explorer.
		 *
		 * @param string $relative_path Subdirectory path relative to ABSPATH.
		 * @return array
		 */
		public static function wpdt_get_directory_contents( $relative_path = '' ) {
			$dir_abs = self::wpdt_validate_path( $relative_path );

			if ( ! $dir_abs || ! is_dir( $dir_abs ) ) {
				$dir_abs = realpath( ABSPATH );
				$relative_path = '';
			}

			$items = array();
			$scanned = @scandir( $dir_abs );

			if ( false === $scanned ) {
				return $items;
			}

			foreach ( $scanned as $node ) {
				if ( '.' === $node || '..' === $node ) {
					continue;
				}

				$full_item_path = $dir_abs . DIRECTORY_SEPARATOR . $node;
				$is_directory = is_dir( $full_item_path );
				$rel_item_path = self::wpdt_get_relative_path( $full_item_path );
				$file_size = $is_directory ? 0 : @filesize( $full_item_path );
				$mtime = @filemtime( $full_item_path );
				$is_editable = $is_directory ? false : self::wpdt_is_editable_file( $node );

				$icon = 'dashicons-media-default';
				if ( $is_directory ) {
					$icon = 'dashicons-category';
				} else {
					$ext = strtolower( pathinfo( $node, PATHINFO_EXTENSION ) );
					if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ), true ) ) {
						$icon = 'dashicons-format-image';
					} elseif ( in_array( $ext, array( 'mp4', 'mov', 'avi', 'webm' ), true ) ) {
						$icon = 'dashicons-format-video';
					} elseif ( in_array( $ext, array( 'mp3', 'wav', 'ogg' ), true ) ) {
						$icon = 'dashicons-format-audio';
					} elseif ( in_array( $ext, array( 'zip', 'tar', 'gz', 'rar', '7z' ), true ) ) {
						$icon = 'dashicons-archive';
					} elseif ( in_array( $ext, array( 'php', 'js', 'css', 'json', 'html' ), true ) ) {
						$icon = 'dashicons-editor-code';
					} elseif ( in_array( $ext, array( 'pdf', 'doc', 'docx', 'txt' ), true ) ) {
						$icon = 'dashicons-media-document';
					}
				}

				$items[] = array(
					'name'           => $node,
					'relative_path'  => $rel_item_path,
					'is_dir'         => $is_directory,
					'is_editable'    => $is_editable,
					'size'           => $file_size,
					'size_formatted' => $is_directory ? '-' : self::wpdt_format_size( $file_size ),
					'mtime'          => $mtime,
					'mtime_date'     => $mtime ? gmdate( 'Y-m-d H:i', $mtime ) : '-',
					'icon'           => $icon,
				);
			}

			usort(
				$items,
				function ( $a, $b ) {
					if ( $a['is_dir'] === $b['is_dir'] ) {
						return strnatcasecmp( $a['name'], $b['name'] );
					}
					return $a['is_dir'] ? -1 : 1;
				}
			);

			return $items;
		}

		/**
		 * Upload file into target directory safely with WP filetype verification.
		 *
		 * @param string $relative_target_dir Target directory relative path.
		 * @param array  $file_array $_FILES item array.
		 * @return bool
		 */
		public static function wpdt_upload_file( $relative_target_dir, array $file_array ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			if ( empty( $file_array['name'] ) || UPLOAD_ERR_OK !== $file_array['error'] ) {
				return false;
			}

			$valid_dir = self::wpdt_validate_path( $relative_target_dir );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			if ( ! $valid_dir || ! is_dir( $valid_dir ) || ! is_writable( $valid_dir ) ) {
				return false;
			}

			$filename = sanitize_file_name( basename( $file_array['name'] ) );
			if ( empty( $filename ) ) {
				return false;
			}

			// Verify File Extension & Type.
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$wp_filetype = wp_check_filetype( $filename );
			$allowed_extra_exts = array( 'php', 'htaccess', 'ini', 'env', 'sql', 'log', 'yaml', 'yml', 'json', 'svg' );
			if ( empty( $wp_filetype['ext'] ) && ! in_array( $ext, $allowed_extra_exts, true ) ) {
				return false;
			}

			$destination = trailingslashit( $valid_dir ) . $filename;

			// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return move_uploaded_file( $file_array['tmp_name'], $destination );
		}

		/**
		 * Check if a file extension is editable in text editor.
		 *
		 * @param string $filename File name.
		 * @return bool
		 */
		public static function wpdt_is_editable_file( $filename ) {
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$editable_exts = array(
				'php', 'js', 'css', 'html', 'json', 'txt', 'xml', 'svg',
				'md', 'htaccess', 'ini', 'sql', 'log', 'yaml', 'yml', 'env',
			);
			return in_array( $ext, $editable_exts, true );
		}

		/**
		 * Read file contents for code editing using WP_Filesystem.
		 *
		 * @param string $relative_path Relative path to target file.
		 * @return string|false
		 */
		public static function wpdt_get_file_content( $relative_path ) {
			$valid_path = self::wpdt_validate_path( $relative_path );
			if ( ! $valid_path || ! is_file( $valid_path ) ) {
				return false;
			}

			$fs = self::wpdt_init_filesystem();
			if ( $fs ) {
				return $fs->get_contents( $valid_path );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return file_get_contents( $valid_path );
		}

		/**
		 * Save/Update edited file content using WP_Filesystem.
		 *
		 * @param string $relative_path Relative path to target file.
		 * @param string $content New content to save.
		 * @return bool
		 */
		public static function wpdt_save_file_content( $relative_path, $content ) {
			$valid_path = self::wpdt_validate_path( $relative_path );
			if ( ! $valid_path || ! is_file( $valid_path ) ) {
				return false;
			}

			$fs = self::wpdt_init_filesystem();
			if ( $fs ) {
				return $fs->put_contents( $valid_path, $content, FS_CHMOD_FILE );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return ( false !== file_put_contents( $valid_path, $content ) );
		}

		/**
		 * Delete a file or directory safely using WP_Filesystem.
		 *
		 * @param string $relative_path Relative path to target item.
		 * @return bool
		 */
		public static function wpdt_delete_item( $relative_path ) {
			$valid_path = self::wpdt_validate_path( $relative_path );

			// Prevent deleting root ABSPATH directory itself!
			$real_abspath = realpath( ABSPATH );
			if ( ! $valid_path || $valid_path === $real_abspath ) {
				return false;
			}

			$fs = self::wpdt_init_filesystem();
			if ( $fs ) {
				return $fs->delete( $valid_path, true );
			}

			if ( is_file( $valid_path ) ) {
				return wp_delete_file( $valid_path );
			} elseif ( is_dir( $valid_path ) ) {
				return self::wpdt_delete_directory( $valid_path );
			}

			return false;
		}

		/**
		 * Recursively delete directory and its contents fallback.
		 *
		 * @param string $dir_path Absolute directory path.
		 * @return bool
		 */
		private static function wpdt_delete_directory( $dir_path ) {
			if ( ! is_dir( $dir_path ) ) {
				return false;
			}

			$fs = self::wpdt_init_filesystem();
			if ( $fs ) {
				return $fs->delete( $dir_path, true );
			}

			$files = array_diff( scandir( $dir_path ), array( '.', '..' ) );
			foreach ( $files as $file ) {
				$item_path = $dir_path . DIRECTORY_SEPARATOR . $file;
				if ( is_dir( $item_path ) ) {
					self::wpdt_delete_directory( $item_path );
				} else {
					wp_delete_file( $item_path );
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			return @rmdir( $dir_path );
		}

		/**
		 * Format file size in bytes.
		 *
		 * @param int $bytes File size in bytes.
		 * @return string
		 */
		public static function wpdt_format_size( $bytes ) {
			if ( $bytes >= 1073741824 ) {
				return number_format( $bytes / 1073741824, 2 ) . ' GB';
			} elseif ( $bytes >= 1048576 ) {
				return number_format( $bytes / 1048576, 2 ) . ' MB';
			} elseif ( $bytes >= 1024 ) {
				return number_format( $bytes / 1024, 2 ) . ' KB';
			} else {
				return $bytes . ' bytes';
			}
		}

		/**
		 * Build breadcrumb trail from root (ABSPATH).
		 *
		 * @param string $relative_path Current relative path.
		 * @return array
		 */
		public static function wpdt_get_breadcrumbs( $relative_path = '' ) {
			$crumbs = array(
				array(
					'name' => 'Root (ABSPATH)',
					'path' => '',
				),
			);

			$clean_rel = trim( str_replace( '\\', '/', $relative_path ), '/' );
			if ( empty( $clean_rel ) ) {
				return $crumbs;
			}

			$parts = explode( '/', $clean_rel );
			$accumulated = '';

			foreach ( $parts as $part ) {
				if ( empty( $part ) ) {
					continue;
				}
				$accumulated = empty( $accumulated ) ? $part : $accumulated . '/' . $part;
				$crumbs[] = array(
					'name' => $part,
					'path' => $accumulated,
				);
			}

			return $crumbs;
		}

		/**
		 * Download custom file or directory as ZIP or direct stream.
		 *
		 * @param string $path Relative or absolute path inside ABSPATH.
		 * @return void
		 */
		public static function wpdt_download_path( $path ) {
			$valid_path = self::wpdt_validate_path( $path );

			if ( ! $valid_path ) {
				wp_die( esc_html__( 'Invalid or restricted path specified.', 'downloader-toolkit' ) );
			}

			if ( is_file( $valid_path ) ) {
				WPDT_Theme_Downloader::wpdt_stream_file( $valid_path, basename( $valid_path ) );
				exit;
			} elseif ( is_dir( $valid_path ) ) {
				if ( ! class_exists( 'ZipArchive' ) ) {
					wp_die( esc_html__( 'PHP ZipArchive extension is required on your server to create ZIP archives.', 'downloader-toolkit' ) );
				}

				$folder_name = sanitize_file_name( basename( $valid_path ) );
				if ( empty( $folder_name ) ) {
					$folder_name = 'site-root';
				}

				$zip_name = 'wpdt-folder-' . $folder_name . '-' . time() . '.zip';
				$temp_dir = get_temp_dir();
				$zip_file_path = trailingslashit( $temp_dir ) . $zip_name;

				$zip = new ZipArchive();
				if ( $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
					wp_die( esc_html__( 'Could not create ZIP archive for requested directory.', 'downloader-toolkit' ) );
				}

				self::wpdt_add_folder_to_zip( $valid_path, $zip, strlen( dirname( $valid_path ) ) + 1 );
				$zip->close();

				if ( file_exists( $zip_file_path ) ) {
					WPDT_Theme_Downloader::wpdt_stream_file( $zip_file_path, $zip_name );
					wp_delete_file( $zip_file_path );
					exit;
				} else {
					wp_die( esc_html__( 'Failed to generate ZIP archive.', 'downloader-toolkit' ) );
				}
			} else {
				wp_die( esc_html__( 'Path does not exist on server.', 'downloader-toolkit' ) );
			}
		}

		/**
		 * Download multiple files and directories as a single ZIP archive.
		 *
		 * @param array $relative_paths List of relative paths from ABSPATH.
		 * @return void
		 */
		public static function wpdt_download_bulk_paths( array $relative_paths ) {
			$paths = array_filter( array_map( 'sanitize_text_field', $relative_paths ) );
			if ( empty( $paths ) ) {
				wp_die( esc_html__( 'No items selected for bulk download.', 'downloader-toolkit' ) );
			}

			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'PHP ZipArchive extension is required on your server to create ZIP archives.', 'downloader-toolkit' ) );
			}

			$temp_dir = get_temp_dir();
			$zip_name = 'wpdt-root-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
			$zip_file_path = trailingslashit( $temp_dir ) . $zip_name;

			$zip = new ZipArchive();
			if ( $zip->open( $zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
				wp_die( esc_html__( 'Could not create bulk ZIP archive.', 'downloader-toolkit' ) );
			}

			$added_count = 0;
			$real_abspath = realpath( ABSPATH );

			foreach ( $paths as $rel_path ) {
				$valid_path = self::wpdt_validate_path( $rel_path );
				if ( $valid_path && file_exists( $valid_path ) ) {
					if ( is_file( $valid_path ) ) {
						$rel_zip = self::wpdt_get_relative_path( $valid_path );
						$zip->addFile( $valid_path, empty( $rel_zip ) ? basename( $valid_path ) : $rel_zip );
						$added_count++;
					} elseif ( is_dir( $valid_path ) ) {
						self::wpdt_add_folder_to_zip( $valid_path, $zip, strlen( $real_abspath ) + 1 );
						$added_count++;
					}
				}
			}

			$zip->close();

			if ( $added_count > 0 && file_exists( $zip_file_path ) ) {
				WPDT_Theme_Downloader::wpdt_stream_file( $zip_file_path, $zip_name );
				wp_delete_file( $zip_file_path );
				exit;
			} else {
				wp_die( esc_html__( 'Failed to generate bulk ZIP archive.', 'downloader-toolkit' ) );
			}
		}

		/**
		 * Helper to recursively add directory contents to ZipArchive.
		 *
		 * @param string     $folder_path Absolute folder path.
		 * @param ZipArchive $zip Zip archive object.
		 * @param int        $strip_prefix Strip prefix length.
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
