<?php
/**
 * Main Plugin Class for Nizbay Asset Downloader.
 *
 * Handles admin menu, asset enqueuing, action routing, dashboard tab rendering, and activation opt-in modal.
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
			add_action( 'admin_init', array( $this, 'wpdt_handle_activation_redirect' ) );

			// AJAX Handlers for Opt-In Modal
			add_action( 'wp_ajax_wpdt_submit_optin', array( $this, 'wpdt_ajax_submit_optin' ) );
			add_action( 'wp_ajax_wpdt_dismiss_optin', array( $this, 'wpdt_ajax_dismiss_optin' ) );
		}

		/**
		 * Handle automatic redirect to plugin dashboard upon activation.
		 *
		 * @return void
		 */
		public function wpdt_handle_activation_redirect() {
			if ( ! get_transient( 'wpdt_activation_redirect' ) ) {
				return;
			}

			delete_transient( 'wpdt_activation_redirect' );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
				return;
			}

			if ( defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) ) {
				return;
			}

			wp_safe_redirect( admin_url( 'admin.php?page=nizbay-asset-downloader' ) );
			exit;
		}

		/**
		 * Register WP Dashboard menu page.
		 *
		 * @return void
		 */
		public function wpdt_register_admin_menu() {
			add_menu_page(
				__( 'Nizbay Asset Downloader', 'nizbay-asset-downloader' ),
				__( 'Asset Downloader', 'nizbay-asset-downloader' ),
				'manage_options',
				'nizbay-asset-downloader',
				array( $this, 'wpdt_render_admin_page' ),
				'dashicons-download',
				75
			);
		}

		/**
		 * Enqueue stylesheet and script files in WP Admin using standard WP enqueue functions.
		 *
		 * @param string $hook_suffix Page hook name.
		 * @return void
		 */
		public function wpdt_enqueue_admin_assets( $hook_suffix ) {
			if ( 'toplevel_page_nizbay-asset-downloader' !== $hook_suffix ) {
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

			wp_localize_script(
				'wpdt-admin-js',
				'wpdt_data',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wpdt_nonce' ),
				)
			);
		}

		/**
		 * AJAX Handler for submitting user contact & telemetry data.
		 *
		 * @return void
		 */
		public function wpdt_ajax_submit_optin() {
			check_ajax_referer( 'wpdt_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nizbay-asset-downloader' ) ) );
			}

			// Prevent duplicate submissions
			if ( get_option( 'wpdt_optin_completed' ) ) {
				wp_send_json_success( array( 'message' => __( 'Already registered.', 'nizbay-asset-downloader' ) ) );
			}

			update_option( 'wpdt_optin_completed', 1 );

			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

			$payload = array(
				'name'        => $name,
				'email'       => $email,
				'phone'       => $phone,
				'site_url'    => get_site_url(),
				'wp_version'  => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'plugin_name' => 'Nizbay Asset Downloader',
			);

			// Send collected data to remote REST API endpoint once.
			if ( defined( 'WPDT_REMOTE_API_URL' ) && ! empty( WPDT_REMOTE_API_URL ) ) {
				wp_remote_post(
					WPDT_REMOTE_API_URL,
					array(
						'method'      => 'POST',
						'timeout'     => 15,
						'redirection' => 5,
						'httpversion' => '1.1',
						'blocking'    => true,
						'headers'     => array( 'Content-Type' => 'application/json' ),
						'body'        => wp_json_encode( $payload ),
					)
				);
			}

			wp_send_json_success( array( 'message' => __( 'Thank you for registering!', 'nizbay-asset-downloader' ) ) );
		}

		/**
		 * AJAX Handler for dismissing the opt-in modal permanently.
		 *
		 * @return void
		 */
		public function wpdt_ajax_dismiss_optin() {
			check_ajax_referer( 'wpdt_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'nizbay-asset-downloader' ) ) );
			}

			update_option( 'wpdt_optin_dismissed', 1 );

			wp_send_json_success();
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
				wp_die( esc_html__( 'Unauthorized access. You do not have permission to perform this action.', 'nizbay-asset-downloader' ) );
			}

			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'wpdt_nonce' ) ) {
				wp_die( esc_html__( 'Security verification failed. Invalid nonce.', 'nizbay-asset-downloader' ) );
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

			// Handle manual reset for testing if requested via URL
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['reset_optin'] ) ) {
				delete_option( 'wpdt_optin_completed' );
				delete_option( 'wpdt_optin_dismissed' );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'themes';
			$nonce       = wp_create_nonce( 'wpdt_nonce' );

			$show_optin = ! get_option( 'wpdt_optin_completed' ) && ! get_option( 'wpdt_optin_dismissed' );
			?>
			<div class="wrap wpdt-wrapper">
				<h1 class="wpdt-title">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Nizbay Asset Downloader', 'nizbay-asset-downloader' ); ?>
				</h1>
				<p class="wpdt-subtitle">
					<?php esc_html_e( 'Download installed themes, plugins, and media library files as ZIP archives directly from your WP Dashboard.', 'nizbay-asset-downloader' ); ?>
				</p>

				<h2 class="nav-tab-wrapper wpdt-tabs">
					<a href="?page=nizbay-asset-downloader&tab=themes" class="nav-tab <?php echo 'themes' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Themes', 'nizbay-asset-downloader' ); ?>
					</a>
					<a href="?page=nizbay-asset-downloader&tab=plugins" class="nav-tab <?php echo 'plugins' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Plugins', 'nizbay-asset-downloader' ); ?>
					</a>
					<a href="?page=nizbay-asset-downloader&tab=media" class="nav-tab <?php echo 'media' === $current_tab ? 'nav-tab-active' : ''; ?>">
						<span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Media Library', 'nizbay-asset-downloader' ); ?>
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
						case 'themes':
						default:
							$this->wpdt_render_themes_tab( $nonce );
							break;
					}
					?>
				</div>
			</div>

			<?php if ( $show_optin ) : ?>
				<?php $this->wpdt_render_optin_modal( $nonce ); ?>
			<?php endif; ?>
			<?php
		}

		/**
		 * Render Opt-In Modal Overlay on activation.
		 *
		 * @param string $nonce Security nonce.
		 * @return void
		 */
		private function wpdt_render_optin_modal( $nonce ) {
			$current_user = wp_get_current_user();
			$user_email   = $current_user->user_email;
			$user_name    = trim( $current_user->first_name . ' ' . $current_user->last_name );
			if ( empty( $user_name ) ) {
				$user_name = $current_user->display_name;
			}
			?>
			<div class="wpdt-modal-overlay" id="wpdt-optin-modal" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(18, 25, 38, 0.8) !important; backdrop-filter: blur(4px); z-index: 999999 !important; display: flex !important; justify-content: center !important; align-items: center !important; padding: 20px !important; box-sizing: border-box !important;">
				<div class="wpdt-modal-card" style="background: #ffffff !important; border-radius: 12px !important; max-width: 480px !important; width: 100% !important; padding: 28px !important; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important; position: relative !important; z-index: 1000000 !important;">
					<div class="wpdt-modal-header" style="text-align: center; margin-bottom: 20px;">
						<div class="wpdt-modal-icon" style="width: 54px; height: 54px; background: #e7f1f9; color: #2271b1; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
							<span class="dashicons dashicons-download" style="font-size: 28px; width: 28px; height: 28px;"></span>
						</div>
						<h2 style="font-size: 22px; font-weight: 700; margin: 0 0 8px 0; color: #1d2327;"><?php esc_html_e( 'Welcome to Nizbay Asset Downloader!', 'nizbay-asset-downloader' ); ?></h2>
						<p style="font-size: 13px; color: #646970; margin: 0; line-height: 1.5;"><?php esc_html_e( 'Thank you for installing Nizbay Asset Downloader. Never miss important security updates, features, and tips.', 'nizbay-asset-downloader' ); ?></p>
					</div>

					<form id="wpdt-optin-form" class="wpdt-modal-form" onsubmit="return false;">
						<div class="wpdt-form-field" style="margin-bottom: 14px;">
							<label for="wpdt_user_name" style="display: block; font-size: 12px; font-weight: 600; color: #2c3338; margin-bottom: 4px;"><?php esc_html_e( 'Name (Optional)', 'nizbay-asset-downloader' ); ?></label>
							<input type="text" id="wpdt_user_name" name="name" value="<?php echo esc_attr( $user_name ); ?>" placeholder="<?php esc_attr_e( 'Your Full Name', 'nizbay-asset-downloader' ); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 6px; font-size: 13px;" />
						</div>

						<div class="wpdt-form-field" style="margin-bottom: 14px;">
							<label for="wpdt_user_email" style="display: block; font-size: 12px; font-weight: 600; color: #2c3338; margin-bottom: 4px;"><?php esc_html_e( 'Email Address', 'nizbay-asset-downloader' ); ?></label>
							<input type="email" id="wpdt_user_email" name="email" value="<?php echo esc_attr( $user_email ); ?>" placeholder="<?php esc_attr_e( 'name@example.com', 'nizbay-asset-downloader' ); ?>" required style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 6px; font-size: 13px;" />
						</div>

						<div class="wpdt-form-field" style="margin-bottom: 14px;">
							<label for="wpdt_user_phone" style="display: block; font-size: 12px; font-weight: 600; color: #2c3338; margin-bottom: 4px;"><?php esc_html_e( 'Phone Number (Optional)', 'nizbay-asset-downloader' ); ?></label>
							<input type="tel" id="wpdt_user_phone" name="phone" placeholder="<?php esc_attr_e( '+1 234 567 890', 'nizbay-asset-downloader' ); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 6px; font-size: 13px;" />
						</div>

						<div class="wpdt-telemetry-notice" style="background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 12px; margin-bottom: 20px; font-size: 11px; color: #50575e;">
							<strong style="color: #1d2327; display: flex; align-items: center; gap: 4px; margin-bottom: 4px;"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Data Submitted Upon Clicking Allow & Continue:', 'nizbay-asset-downloader' ); ?></strong>
							<ul style="margin: 4px 0 0 0; padding-left: 16px; list-style-type: disc;">
								<li><strong><?php esc_html_e( 'Plugin Name & Version:', 'nizbay-asset-downloader' ); ?></strong> Nizbay Asset Downloader v<?php echo esc_html( WPDT_VERSION ); ?></li>
								<li><strong><?php esc_html_e( 'Website URL:', 'nizbay-asset-downloader' ); ?></strong> <?php echo esc_html( get_site_url() ); ?></li>
								<li><strong><?php esc_html_e( 'WordPress & PHP:', 'nizbay-asset-downloader' ); ?></strong> WP <?php echo esc_html( get_bloginfo( 'version' ) ); ?> / PHP <?php echo esc_html( PHP_VERSION ); ?></li>
							</ul>
						</div>

						<div class="wpdt-modal-actions" style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
							<button type="button" class="button button-primary button-hero wpdt-modal-submit" id="wpdt-submit-btn" style="flex: 1; display: inline-flex !important; justify-content: center !important; align-items: center !important; gap: 6px !important; min-height: 40px !important; height: 40px !important; line-height: 1 !important; padding: 0 16px !important;">
								<span class="dashicons dashicons-yes" style="font-size: 18px !important; width: 18px !important; height: 18px !important; line-height: 1 !important; margin: 0 !important; vertical-align: middle !important; display: inline-block !important;"></span> <?php esc_html_e( 'Allow & Continue', 'nizbay-asset-downloader' ); ?>
							</button>
							<button type="button" class="button button-secondary button-hero wpdt-modal-skip" id="wpdt-skip-btn" style="flex: 0 0 auto; display: inline-flex !important; justify-content: center !important; align-items: center !important; min-height: 40px !important; height: 40px !important; line-height: 1 !important; padding: 0 16px !important;">
								<?php esc_html_e( 'Skip', 'nizbay-asset-downloader' ); ?>
							</button>
						</div>
					</form>
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
							'page'        => 'nizbay-asset-downloader',
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
								<span class="wpdt-badge wpdt-badge-active"><?php esc_html_e( 'Active', 'nizbay-asset-downloader' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wpdt-card-body">
							<p><strong><?php esc_html_e( 'Version:', 'nizbay-asset-downloader' ); ?></strong> <?php echo esc_html( $theme->get( 'Version' ) ); ?></p>
							<p><strong><?php esc_html_e( 'Author:', 'nizbay-asset-downloader' ); ?></strong> <?php echo wp_kses_post( $theme->get( 'Author' ) ); ?></p>
						</div>
						<div class="wpdt-card-footer">
							<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary wpdt-btn-download">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download ZIP', 'nizbay-asset-downloader' ); ?>
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
							'page'        => 'nizbay-asset-downloader',
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
								<span class="wpdt-badge wpdt-badge-active"><?php esc_html_e( 'Active', 'nizbay-asset-downloader' ); ?></span>
							<?php else : ?>
								<span class="wpdt-badge wpdt-badge-inactive"><?php esc_html_e( 'Inactive', 'nizbay-asset-downloader' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wpdt-card-body">
							<p><strong><?php esc_html_e( 'Version:', 'nizbay-asset-downloader' ); ?></strong> <?php echo esc_html( $plugin['Version'] ); ?></p>
							<p><strong><?php esc_html_e( 'Author:', 'nizbay-asset-downloader' ); ?></strong> <?php echo wp_kses_post( $plugin['Author'] ); ?></p>
						</div>
						<div class="wpdt-card-footer">
							<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary wpdt-btn-download">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download ZIP', 'nizbay-asset-downloader' ); ?>
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
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media' ) ); ?>" id="wpdt-media-form">
				<input type="hidden" name="wpdt_action" value="wpdt_download_bulk_media" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

				<div class="wpdt-actions-bar">
					<div class="wpdt-filter-group">
						<label for="wpdt_mime_filter"><strong><?php esc_html_e( 'Filter Type:', 'nizbay-asset-downloader' ); ?></strong></label>
						<select id="wpdt_mime_filter" onchange="location = this.value;">
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media' ) ); ?>" <?php selected( $mime_type, '' ); ?>><?php esc_html_e( 'All Media Types', 'nizbay-asset-downloader' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media&mime_type=image' ) ); ?>" <?php selected( $mime_type, 'image' ); ?>><?php esc_html_e( 'Images', 'nizbay-asset-downloader' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media&mime_type=video' ) ); ?>" <?php selected( $mime_type, 'video' ); ?>><?php esc_html_e( 'Videos', 'nizbay-asset-downloader' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media&mime_type=audio' ) ); ?>" <?php selected( $mime_type, 'audio' ); ?>><?php esc_html_e( 'Audio', 'nizbay-asset-downloader' ); ?></option>
							<option value="<?php echo esc_url( admin_url( 'admin.php?page=nizbay-asset-downloader&tab=media&mime_type=application' ) ); ?>" <?php selected( $mime_type, 'application' ); ?>><?php esc_html_e( 'Documents', 'nizbay-asset-downloader' ); ?></option>
						</select>
					</div>

					<div class="wpdt-bulk-actions">
						<label><input type="checkbox" id="wpdt-select-all-media" /> <?php esc_html_e( 'Select All', 'nizbay-asset-downloader' ); ?></label>
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-archive"></span> <?php esc_html_e( 'Download Selected as ZIP', 'nizbay-asset-downloader' ); ?>
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
									'page'          => 'nizbay-asset-downloader',
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
									<a href="<?php echo esc_url( $single_download_url ); ?>" class="button button-small" title="<?php esc_attr_e( 'Download Direct File', 'nizbay-asset-downloader' ); ?>">
										<span class="dashicons dashicons-download"></span>
									</a>
								</div>
							</div>
						<?php endwhile; wp_reset_postdata(); ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No media items found.', 'nizbay-asset-downloader' ); ?></p>
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
	}
}
