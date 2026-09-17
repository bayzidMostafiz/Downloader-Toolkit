<?php
/**
 * Main Plugin Class for Downloader Toolkit.
 *
 * Handles admin menu, asset enqueuing, action routing, and dashboard tab rendering.
 *
 * @package WP_Downloader_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPDT_Main' ) ) {

	class WPDT_Main {

		/**
		 * Instance of this class.
		 *
		 * @var WPDT_Main|null
		 */
		private static $wpdt_instance = null;

		/**
		 * Get instance singleton.
		 *
		 * @return WPDT_Main
		 */
		public static function wpdt_get_instance() {
			if ( null === self::$wpdt_instance ) {
				self::$wpdt_instance = new self();
			}
			return self::$wpdt_instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'wpdt_register_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'wpdt_enqueue_admin_assets' ) );
			add_action( 'admin_init', array( $this, 'wpdt_handle_action_request' ) );
		}

		/**
		 * Register WP Dashboard menu page.
		 *
		 * @return void
		 */
		public function wpdt_register_admin_menu() {
			add_menu_page(
				__( 'Downloader Toolkit', 'downloader-toolkit' ),
				__( 'Downloader Toolkit', 'downloader-toolkit' ),
				'manage_options',
				'downloader-toolkit',
				array( $this, 'wpdt_render_admin_page' ),
				'dashicons-download',
				75
			);
		}

		/**
		 * Enqueue stylesheet and script files in WP Admin.
		 *
		 * @param string $hook_suffix Page hook name.
		 * @return void
		 */
		public function wpdt_enqueue_admin_assets( $hook_suffix ) {
			if ( 'toplevel_page_downloader-toolkit' !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style(
				'wpdt-admin-css',
				WPDT_URL . 'admin/css/wpdt-admin.css',
				array(),
				WPDT_VERSION
			);

			wp_enqueue_script(
				'wpdt-admin-js',
				WPDT_URL . 'admin/js/wpdt-admin.js',
				array( 'jquery' ),
				WPDT_VERSION,
				true
			);
		}

		/**
		 * Intercept and process action requests securely.
		 *
		 * @return void
		 */
		public function wpdt_handle_action_request() {
			if ( ! isset( $_REQUEST['wpdt_action'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized access. You do not have permission to perform this action.', 'downloader-toolkit' ) );
			}

			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wpdt_nonce' ) ) {
				wp_die( esc_html__( 'Security verification failed. Invalid nonce.', 'downloader-toolkit' ) );
			}

			$action = sanitize_text_field( wp_unslash( $_REQUEST['wpdt_action'] ) );

			switch ( $action ) {
				case 'wpdt_download_theme':
					$theme_slug = isset( $_REQUEST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['theme_slug'] ) ) : '';
					WPDT_Theme_Downloader::wpdt_download_theme( $theme_slug );
					break;

				case 'wpdt_download_plugin':
					$plugin_file = isset( $_REQUEST['plugin_file'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin_file'] ) ) : '';
					WPDT_Plugin_Downloader::wpdt_download_plugin( $plugin_file );
					break;

				case 'wpdt_download_single_media':
					$attachment_id = isset( $_REQUEST['attachment_id'] ) ? absint( wp_unslash( $_REQUEST['attachment_id'] ) ) : 0;
					WPDT_Media_Downloader::wpdt_download_single_media( $attachment_id );
					break;

				case 'wpdt_download_bulk_media':
					$media_ids = isset( $_POST['media_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['media_ids'] ) ) : array();
					WPDT_Media_Downloader::wpdt_download_bulk_media( $media_ids );
					break;

				case 'wpdt_download_custom_file':
					$target_path = isset( $_REQUEST['target_path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['target_path'] ) ) : '';
					WPDT_File_Manager::wpdt_download_path( $target_path );
					break;

				case 'wpdt_download_bulk_file_manager':
					$paths = isset( $_POST['fm_paths'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fm_paths'] ) ) : array();
					WPDT_File_Manager::wpdt_download_bulk_paths( $paths );
					break;

				case 'wpdt_save_file_content':
					$target_file = isset( $_POST['target_file'] ) ? sanitize_text_field( wp_unslash( $_POST['target_file'] ) ) : '';
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$new_content = isset( $_POST['file_content'] ) ? wp_unslash( $_POST['file_content'] ) : '';
					$saved = WPDT_File_Manager::wpdt_save_file_content( $target_file, $new_content );

					$redirect_url = add_query_arg(
						array(
							'page'        => 'downloader-toolkit',
							'tab'         => 'filemanager',
							'action_edit' => '1',
							'path'        => $target_file,
							'wpdt_notice' => $saved ? 'saved' : 'save_error',
						),
						admin_url( 'admin.php' )
					);
					wp_safe_redirect( $redirect_url );
					exit;

				case 'wpdt_upload_file':
					$target_dir = isset( $_POST['target_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['target_dir'] ) ) : '';
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$uploaded_file = isset( $_FILES['wpdt_upload_file_input'] ) ? $_FILES['wpdt_upload_file_input'] : array();
					$success = WPDT_File_Manager::wpdt_upload_file( $target_dir, $uploaded_file );

					$redirect_url = add_query_arg(
						array(
							'page'        => 'downloader-toolkit',
							'tab'         => 'filemanager',
							'path'        => $target_dir,
							'wpdt_notice' => $success ? 'uploaded' : 'upload_error',
						),
						admin_url( 'admin.php' )
					);
					wp_safe_redirect( $redirect_url );
					exit;

				case 'wpdt_delete_item':
					$target_item = isset( $_REQUEST['target_item'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['target_item'] ) ) : '';
					$parent_dir = dirname( trim( str_replace( '\\', '/', $target_item ), '/' ) );
					if ( '.' === $parent_dir || '\\' === $parent_dir || '/' === $parent_dir ) {
						$parent_dir = '';
					}

					$deleted = WPDT_File_Manager::wpdt_delete_item( $target_item );
					$redirect_url = add_query_arg(
						array(
							'page'        => 'downloader-toolkit',
							'tab'         => 'filemanager',
							'path'        => $parent_dir,
							'wpdt_notice' => $deleted ? 'deleted' : 'delete_error',
						),
						admin_url( 'admin.php' )
					);
					wp_safe_redirect( $redirect_url );
					exit;
			}
		}

		/**
		 * Render the main WP Dashboard admin page.
		 *
		 * @return void
		 */
		public function wpdt_render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'themes';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$is_editing = isset( $_GET['action_edit'] ) && '1' === (string) wp_unslash( $_GET['action_edit'] );
			$nonce = wp_create_nonce( 'wpdt_nonce' );

			// Render Admin Notices
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['wpdt_notice'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$notice = sanitize_key( wp_unslash( $_GET['wpdt_notice'] ) );
				if ( 'saved' === $notice ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'File updated successfully!', 'downloader-toolkit' ) . '</p></div>';
				} elseif ( 'save_error' === $notice ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to save file changes.', 'downloader-toolkit' ) . '</p></div>';
				} elseif ( 'uploaded' === $notice ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'File uploaded successfully!', 'downloader-toolkit' ) . '</p></div>';
				} elseif ( 'upload_error' === $notice ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to upload file to target directory.', 'downloader-toolkit' ) . '</p></div>';
				} elseif ( 'deleted' === $notice ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Item deleted successfully!', 'downloader-toolkit' ) . '</p></div>';
				} elseif ( 'delete_error' === $notice ) {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete requested item.', 'downloader-toolkit' ) . '</p></div>';
				}
			}
			?>
			<div class="wrap wpdt-wrapper">
				<h1 class="wpdt-title">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Downloader Toolkit', 'downloader-toolkit' ); ?>
				</h1>
				<p class="wpdt-subtitle">
					<?php esc_html_e( 'Download themes, plugins, media files, and manage site files (WP File Manager style: Edit, Save, Upload & Delete) directly from your WP Dashboard.', 'downloader-toolkit' ); ?>
				</p>

				<h2 class="nav-tab-wrapper wpdt-tabs">
					<a href="?page=downloader-toolkit&tab=themes" class="nav-tab <?php echo 'themes' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Themes', 'downloader-toolkit' ); ?>
					</a>
					<a href="?page=downloader-toolkit&tab=plugins" class="nav-tab <?php echo 'plugins' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Plugins', 'downloader-toolkit' ); ?>
					</a>
					<a href="?page=downloader-toolkit&tab=media" class="nav-tab <?php echo 'media' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Media Library', 'downloader-toolkit' ); ?>
					</a>
					<a href="?page=downloader-toolkit&tab=filemanager" class="nav-tab <?php echo 'filemanager' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-category"></span> <?php esc_html_e( 'WP File Manager (Root Explorer & Editor)', 'downloader-toolkit' ); ?>
					</a>
				</h2>

				<div class="wpdt-tab-content">
					<?php
					switch ( $current_tab ) {
						case 'plugins':
							$this->wpdt_render_plugins_tab( $nonce );
							break;
						case 'media':
							$this->wpdt_render_media_tab( $nonce );
							break;
						case 'filemanager':
							if ( $is_editing ) {
								$this->wpdt_render_file_editor_page( $nonce );
							} else {
								$this->wpdt_render_file_manager_tab( $nonce );
							}
							break;
						case 'themes':
						default:
							$this->wpdt_render_themes_tab( $nonce );
							break;
					}
					?>
				</div>
			</div>
			<?php
		}

		/**
		 * Render Themes Tab.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_themes_tab( $nonce ) {
			$themes = wp_get_themes();
			$active_theme = wp_get_theme();
			?>
			<div class="wpdt-grid">
				<?php foreach ( $themes as $slug => $theme ) : ?>
					<?php
					$is_active = ( $active_theme->get_stylesheet() === $slug );
					$download_url = add_query_arg(
						array(
							'page'        => 'downloader-toolkit',
							'wpdt_action' => 'wpdt_download_theme',
							'theme_slug'  => $slug,
							'_wpnonce'    => $nonce,
						),
						admin_url( 'admin.php' )
					);
					?>
					<div class="wpdt-card <?php echo $is_active ? 'wpdt-active-item' : ''; ?>">
						<div class="wpdt-card-header">
							<h3><?php echo esc_html( $theme->get( 'Name' ) ); ?></h3>
							<?php if ( $is_active ) : ?>
								<span class="wpdt-badge wpdt-badge-active"><?php esc_html_e( 'Active', 'downloader-toolkit' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wpdt-card-body">
							<p><strong><?php esc_html_e( 'Version:', 'downloader-toolkit' ); ?></strong> <?php echo esc_html( $theme->get( 'Version' ) ); ?></p>
							<p><strong><?php esc_html_e( 'Author:', 'downloader-toolkit' ); ?></strong> <?php echo wp_kses_post( $theme->get( 'Author' ) ); ?></p>
						</div>
						<div class="wpdt-card-footer">
							<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary wpdt-btn-download">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download ZIP', 'downloader-toolkit' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Render Plugins Tab.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_plugins_tab( $nonce ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );
			?>
			<div class="wpdt-grid">
				<?php foreach ( $plugins as $file => $plugin ) : ?>
					<?php
					$is_active = in_array( $file, $active_plugins, true );
					$download_url = add_query_arg(
						array(
							'page'        => 'downloader-toolkit',
							'wpdt_action' => 'wpdt_download_plugin',
							'plugin_file' => $file,
							'_wpnonce'    => $nonce,
						),
						admin_url( 'admin.php' )
					);
					?>
					<div class="wpdt-card <?php echo $is_active ? 'wpdt-active-item' : ''; ?>">
						<div class="wpdt-card-header">
							<h3><?php echo esc_html( $plugin['Name'] ); ?></h3>
							<?php if ( $is_active ) : ?>
								<span class="wpdt-badge wpdt-badge-active"><?php esc_html_e( 'Active', 'downloader-toolkit' ); ?></span>
							<?php else : ?>
								<span class="wpdt-badge wpdt-badge-inactive"><?php esc_html_e( 'Inactive', 'downloader-toolkit' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wpdt-card-body">
							<p><strong><?php esc_html_e( 'Version:', 'downloader-toolkit' ); ?></strong> <?php echo esc_html( $plugin['Version'] ); ?></p>
							<p><strong><?php esc_html_e( 'Author:', 'downloader-toolkit' ); ?></strong> <?php echo wp_kses_post( $plugin['Author'] ); ?></p>
						</div>
						<div class="wpdt-card-footer">
							<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary wpdt-btn-download">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download ZIP', 'downloader-toolkit' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
		}

		/**
		 * Render Media Library Tab.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_media_tab( $nonce ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$paged = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mime_type = isset( $_GET['mime_type'] ) ? sanitize_text_field( wp_unslash( $_GET['mime_type'] ) ) : '';

			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 24,
				'paged'          => $paged,
			);

			if ( ! empty( $mime_type ) ) {
				$args['post_mime_type'] = $mime_type;
			}

			$query = new WP_Query( $args );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media' ) ); ?>" id="wpdt-media-form">
				<input type="hidden" name="wpdt_action" value="wpdt_download_bulk_media" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

				<div class="wpdt-actions-bar">
					<div class="wpdt-filter-group">
						<label for="wpdt_mime_filter"><strong><?php esc_html_e( 'Filter Type:', 'downloader-toolkit' ); ?></strong></label>
						<select id="wpdt_mime_filter" onchange="location = this.value;">
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media' ) ); ?>" <?php selected( $mime_type, '' ); ?>><?php esc_html_e( 'All Media Types', 'downloader-toolkit' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media&mime_type=image' ) ); ?>" <?php selected( $mime_type, 'image' ); ?>><?php esc_html_e( 'Images', 'downloader-toolkit' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media&mime_type=video' ) ); ?>" <?php selected( $mime_type, 'video' ); ?>><?php esc_html_e( 'Videos', 'downloader-toolkit' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media&mime_type=audio' ) ); ?>" <?php selected( $mime_type, 'audio' ); ?>><?php esc_html_e( 'Audio', 'downloader-toolkit' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=media&mime_type=application' ) ); ?>" <?php selected( $mime_type, 'application' ); ?>><?php esc_html_e( 'Documents', 'downloader-toolkit' ); ?></option>
						</select>
					</div>

					<div class="wpdt-bulk-actions">
						<label><input type="checkbox" id="wpdt-select-all-media" /> <?php esc_html_e( 'Select All', 'downloader-toolkit' ); ?></label>
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-archive"></span> <?php esc_html_e( 'Download Selected as ZIP', 'downloader-toolkit' ); ?>
						</button>
					</div>
				</div>

				<div class="wpdt-media-grid">
					<?php if ( $query->have_posts() ) : ?>
						<?php while ( $query->have_posts() ) : $query->the_post(); ?>
							<?php
							$id = get_the_ID();
							$file_url = wp_get_attachment_url( $id );
							$mime = get_post_mime_type( $id );
							$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
							$single_download_url = add_query_arg(
								array(
									'page'          => 'downloader-toolkit',
									'wpdt_action'   => 'wpdt_download_single_media',
									'attachment_id' => $id,
									'_wpnonce'      => $nonce,
								),
								admin_url( 'admin.php' )
							);
							?>
							<div class="wpdt-media-item">
								<div class="wpdt-media-checkbox">
									<input type="checkbox" name="media_ids[]" value="<?php echo esc_attr( $id ); ?>" class="wpdt-media-cb" />
								</div>
								<div class="wpdt-media-preview">
									<?php if ( strpos( $mime, 'image' ) !== false && $thumb ) : ?>
										<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php the_title_attribute(); ?>" />
									<?php elseif ( strpos( $mime, 'video' ) !== false ) : ?>
										<span class="dashicons dashicons-format-video wpdt-icon-large"></span>
									<?php elseif ( strpos( $mime, 'audio' ) !== false ) : ?>
										<span class="dashicons dashicons-format-audio wpdt-icon-large"></span>
									<?php else : ?>
										<span class="dashicons dashicons-media-default wpdt-icon-large"></span>
									<?php endif; ?>
								</div>
								<div class="wpdt-media-info">
									<span class="wpdt-media-title" title="<?php echo esc_attr( get_the_title() ); ?>"><?php echo esc_html( get_the_title() ); ?></span>
								</div>
								<div class="wpdt-media-actions">
									<a href="<?php echo esc_url( $single_download_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Download Direct File', 'downloader-toolkit' ); ?>">
										<span class="dashicons dashicons-download"></span>
									</a>
								</div>
							</div>
						<?php endwhile; wp_reset_postdata(); ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No media items found.', 'downloader-toolkit' ); ?></p>
					<?php endif; ?>
				</div>

				<?php
				$total_pages = $query->max_num_pages;
				if ( $total_pages > 1 ) :
					?>
					<div class="tablenav wpdt-pagination">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $total_pages,
									'current'   => $paged,
								)
							)
						);
						?>
					</div>
				<?php endif; ?>
			</form>
			<?php
		}

		/**
		 * Render File Editor Page for editing code/text files.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_file_editor_page( $nonce ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target_file = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
			$content = WPDT_File_Manager::wpdt_get_file_content( $target_file );

			if ( false === $content ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to open or edit requested file.', 'downloader-toolkit' ) . '</p></div>';
				return;
			}

			$parent_dir = dirname( trim( str_replace( '\\', '/', $target_file ), '/' ) );
			if ( '.' === $parent_dir || '\\' === $parent_dir || '/' === $parent_dir ) {
				$parent_dir = '';
			}

			$back_url = add_query_arg(
				array(
					'page' => 'downloader-toolkit',
					'tab'  => 'filemanager',
					'path' => $parent_dir,
				),
				admin_url( 'admin.php' )
			);
			?>
			<div class="wpdt-editor-box">
				<div class="wpdt-editor-header">
					<h3>
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Editing File:', 'downloader-toolkit' ); ?>
						<code><?php echo esc_html( $target_file ); ?></code>
					</h3>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to File Manager', 'downloader-toolkit' ); ?>
					</a>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="downloader-toolkit" />
					<input type="hidden" name="wpdt_action" value="wpdt_save_file_content" />
					<input type="hidden" name="target_file" value="<?php echo esc_attr( $target_file ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

					<div class="wpdt-editor-wrapper">
						<textarea name="file_content" id="wpdt_file_content" rows="24" class="large-text code wpdt-code-area" spellcheck="false"><?php echo esc_textarea( $content ); ?></textarea>
					</div>

					<div class="wpdt-editor-actions">
						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Changes', 'downloader-toolkit' ); ?>
						</button>
						<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary button-hero">
							<?php esc_html_e( 'Cancel', 'downloader-toolkit' ); ?>
						</a>
					</div>
				</form>
			</div>
			<?php
		}

		/**
		 * Render WP File Manager Style Root Explorer Tab.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_file_manager_tab( $nonce ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_rel_path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
			$items = WPDT_File_Manager::wpdt_get_directory_contents( $current_rel_path );
			$breadcrumbs = WPDT_File_Manager::wpdt_get_breadcrumbs( $current_rel_path );
			?>
			<div class="wpdt-fm-container">
				<!-- Breadcrumbs Bar -->
				<div class="wpdt-fm-breadcrumbs">
					<span class="dashicons dashicons-admin-home"></span>
					<?php
					$crumb_count = count( $breadcrumbs );
					foreach ( $breadcrumbs as $index => $crumb ) :
						$crumb_url = add_query_arg(
							array(
								'page' => 'downloader-toolkit',
								'tab'  => 'filemanager',
								'path' => $crumb['path'],
							),
							admin_url( 'admin.php' )
						);
						?>
						<?php if ( $index > 0 ) : ?>
							<span class="wpdt-crumb-sep">/</span>
						<?php endif; ?>
						<?php if ( $index === $crumb_count - 1 ) : ?>
							<span class="wpdt-crumb-current"><?php echo esc_html( $crumb['name'] ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $crumb_url ); ?>" class="wpdt-crumb-link"><?php echo esc_html( $crumb['name'] ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<!-- Actions & File Upload Bar -->
				<div class="wpdt-toolbar-row">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=downloader-toolkit&tab=filemanager&path=' . urlencode( $current_rel_path ) ) ); ?>" id="wpdt-fm-form">
						<input type="hidden" name="wpdt_action" value="wpdt_download_bulk_file_manager" />
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

						<div class="wpdt-bulk-actions">
							<label><input type="checkbox" id="wpdt-select-all-fm" /> <?php esc_html_e( 'Select All', 'downloader-toolkit' ); ?></label>
							<button type="submit" class="button button-primary">
								<span class="dashicons dashicons-archive"></span> <?php esc_html_e( 'Download Selected as ZIP', 'downloader-toolkit' ); ?>
							</button>
						</div>
					</form>

					<!-- File Upload Box -->
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" enctype="multipart/form-data" class="wpdt-upload-form">
						<input type="hidden" name="page" value="downloader-toolkit" />
						<input type="hidden" name="wpdt_action" value="wpdt_upload_file" />
						<input type="hidden" name="target_dir" value="<?php echo esc_attr( $current_rel_path ); ?>" />
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

						<div class="wpdt-upload-controls">
							<input type="file" name="wpdt_upload_file_input" required class="wpdt-file-input" />
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload to Folder', 'downloader-toolkit' ); ?>
							</button>
						</div>
					</form>
				</div>

				<div class="wpdt-fm-path-info">
					<code><?php echo esc_html( ABSPATH . ltrim( str_replace( '\\', '/', $current_rel_path ), '/' ) ); ?></code>
				</div>

				<!-- File Manager Explorer Table -->
				<table class="wp-list-table widefat fixed striped wpdt-fm-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="wpdt-cb-select-all" /></td>
							<th class="column-primary"><?php esc_html_e( 'Name', 'downloader-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Size', 'downloader-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Last Modified', 'downloader-toolkit' ); ?></th>
							<th class="wpdt-text-right"><?php esc_html_e( 'Actions', 'downloader-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $current_rel_path ) ) : ?>
							<?php
							$parent_path = dirname( trim( str_replace( '\\', '/', $current_rel_path ), '/' ) );
							if ( '.' === $parent_path || '\\' === $parent_path || '/' === $parent_path ) {
								$parent_path = '';
							}
							$parent_url = add_query_arg(
								array(
									'page' => 'downloader-toolkit',
									'tab'  => 'filemanager',
									'path' => $parent_path,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td></td>
								<td class="column-primary" colspan="4">
									<a href="<?php echo esc_url( $parent_url ); ?>" class="wpdt-parent-dir-link">
										<span class="dashicons dashicons-arrow-up-alt2"></span> <strong>.. (Up to Parent Directory)</strong>
									</a>
								</td>
							</tr>
						<?php endif; ?>

						<?php if ( ! empty( $items ) ) : ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$item_rel = $item['relative_path'];
								$item_url = add_query_arg(
									array(
										'page' => 'downloader-toolkit',
										'tab'  => 'filemanager',
										'path' => $item_rel,
									),
									admin_url( 'admin.php' )
								);

								$download_url = add_query_arg(
									array(
										'page'        => 'downloader-toolkit',
										'wpdt_action' => 'wpdt_download_custom_file',
										'target_path' => $item_rel,
										'_wpnonce'    => $nonce,
									),
									admin_url( 'admin.php' )
								);

								$edit_url = add_query_arg(
									array(
										'page'        => 'downloader-toolkit',
										'tab'         => 'filemanager',
										'action_edit' => '1',
										'path'        => $item_rel,
									),
									admin_url( 'admin.php' )
								);

								$delete_url = add_query_arg(
									array(
										'page'        => 'downloader-toolkit',
										'wpdt_action' => 'wpdt_delete_item',
										'target_item' => $item_rel,
										'_wpnonce'    => $nonce,
									),
									admin_url( 'admin.php' )
								);
								?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="fm_paths[]" value="<?php echo esc_attr( $item_rel ); ?>" class="wpdt-fm-cb" form="wpdt-fm-form" />
									</th>
									<td class="column-primary" data-colname="Name">
										<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?> wpdt-file-icon"></span>
										<?php if ( $item['is_dir'] ) : ?>
											<a href="<?php echo esc_url( $item_url ); ?>" class="wpdt-folder-link">
												<strong><?php echo esc_html( $item['name'] ); ?>/</strong>
											</a>
										<?php else : ?>
											<span class="wpdt-file-name"><?php echo esc_html( $item['name'] ); ?></span>
										<?php endif; ?>
									</td>
									<td data-colname="Size"><?php echo esc_html( $item['size_formatted'] ); ?></td>
									<td data-colname="Last Modified"><?php echo esc_html( $item['mtime_date'] ); ?></td>
									<td class="wpdt-text-right wpdt-row-actions" data-colname="Actions">
										<?php if ( $item['is_editable'] ) : ?>
											<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small button-secondary" title="<?php esc_attr_e( 'Edit Code / File', 'downloader-toolkit' ); ?>">
												<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'downloader-toolkit' ); ?>
											</a>
										<?php endif; ?>

										<a href="<?php echo esc_url( $download_url ); ?>" class="button button-small button-primary" title="<?php esc_attr_e( 'Download', 'downloader-toolkit' ); ?>">
											<span class="dashicons dashicons-download"></span>
											<?php echo $item['is_dir'] ? esc_html__( 'ZIP', 'downloader-toolkit' ) : esc_html__( 'Download', 'downloader-toolkit' ); ?>
										</a>

										<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small wpdt-btn-delete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this item? This action cannot be undone.', 'downloader-toolkit' ) ); ?>');" title="<?php esc_attr_e( 'Delete Item', 'downloader-toolkit' ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'Directory is empty.', 'downloader-toolkit' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
}
