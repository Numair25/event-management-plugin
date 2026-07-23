<?php
/**
 * The core plugin class.
 */
class EMP_Core {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'EMP_VERSION' ) ) {
			$this->version = EMP_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'event-management-plugin';
		
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		// Loader to keep track of hooks
		require_once EMP_PLUGIN_DIR . 'includes/class-emp-loader.php';
		$this->loader = new EMP_Loader();
		
		// Core functionalities
		require_once EMP_PLUGIN_DIR . 'includes/core/class-emp-cpt.php';
		require_once EMP_PLUGIN_DIR . 'services/class-emp-audit-logger.php';
	}

	private function define_admin_hooks() {
		$plugin_cpt = new EMP_CPT();
		$this->loader->add_action( 'init', $plugin_cpt, 'register' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-event-meta.php';
		$plugin_event_meta = new EMP_Event_Meta();
		$this->loader->add_action( 'add_meta_boxes', $plugin_event_meta, 'register_meta_boxes' );
		$this->loader->add_action( 'save_post_emp_event', $plugin_event_meta, 'save_meta_box' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-ticket-types-admin.php';
		$plugin_ticket_types_admin = new EMP_Ticket_Types_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_ticket_types_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-attendees-admin.php';
		$plugin_attendees_admin = new EMP_Attendees_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_attendees_admin, 'register_menu' );
		$this->loader->add_action( 'admin_post_emp_export_attendees', $plugin_attendees_admin, 'export_csv' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-get-started-admin.php';
		$plugin_get_started = new EMP_Get_Started_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_get_started, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-badges-admin.php';
		$plugin_badges_admin = new EMP_Badges_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_badges_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-badge-print-handler.php';
		$plugin_print_handler = new EMP_Badge_Print_Handler();
		$this->loader->add_action( 'admin_init', $plugin_print_handler, 'init' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-kiosk-admin.php';
		$plugin_kiosk_admin = new EMP_Kiosk_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_kiosk_admin, 'register_menu' );
		$this->loader->add_action( 'wp_ajax_emp_get_gf_fields', $plugin_kiosk_admin, 'ajax_get_gf_fields' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-import-admin.php';
		$plugin_import_admin = new EMP_Import_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_import_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-scan-points-admin.php';
		$plugin_scan_points = new EMP_Scan_Points_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_scan_points, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-scanner-app.php';
		$plugin_scanner = new EMP_Scanner_App();
		$this->loader->add_action( 'admin_menu', $plugin_scanner, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-communications-admin.php';
		$plugin_comms_admin = new EMP_Communications_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_comms_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-dashboard-admin.php';
		$plugin_dashboard_admin = new EMP_Dashboard_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_dashboard_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-scan-stats-admin.php';
		$plugin_scan_stats_admin = new EMP_Scan_Stats_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_scan_stats_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-audit-log-admin.php';
		$plugin_audit_log_admin = new EMP_Audit_Log_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_audit_log_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-settings-admin.php';
		$plugin_settings_admin = new EMP_Settings_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_settings_admin, 'register_menu' );
		$this->loader->add_action( 'wp_ajax_emp_global_search', $plugin_settings_admin, 'ajax_global_search' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-qr-settings-admin.php';
		$plugin_qr_settings_admin = new EMP_QR_Settings_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_qr_settings_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'admin/class-emp-qr-approvals-admin.php';
		$plugin_qr_approvals_admin = new EMP_QR_Approvals_Admin();
		$this->loader->add_action( 'admin_menu', $plugin_qr_approvals_admin, 'register_menu' );

		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-integration.php';
		$plugin_gf_integration = new EMP_GF_Integration();
		$this->loader->add_action( 'gform_loaded', $plugin_gf_integration, 'init' );

		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-cleanup.php';
		$plugin_gf_cleanup = new EMP_GF_Cleanup();
		$plugin_gf_cleanup->init();
	}

	private function define_public_hooks() {
		require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
		$plugin_comms = new EMP_Communications();
		$this->loader->add_action( 'init', $plugin_comms, 'handle_public_badge_download' );

		require_once EMP_PLUGIN_DIR . 'public/class-emp-feedback-portal.php';
		$plugin_feedback = new EMP_Feedback_Portal();
		$this->loader->add_action( 'init', $plugin_feedback, 'register_shortcodes' );

		require_once EMP_PLUGIN_DIR . 'public/class-emp-frontend-scanner.php';
		$plugin_frontend_scanner = new EMP_Frontend_Scanner();
		$this->loader->add_action( 'init', $plugin_frontend_scanner, 'register_shortcodes' );

		require_once EMP_PLUGIN_DIR . 'api/class-emp-rest-scanner.php';
		$plugin_rest_scanner = new EMP_REST_Scanner();
		$this->loader->add_action( 'rest_api_init', $plugin_rest_scanner, 'register_endpoints' );

		// QR Frontend Script Loader
		require_once EMP_PLUGIN_DIR . 'public/class-emp-qr-frontend.php';
		$plugin_qr_frontend = new EMP_QR_Frontend();
		$this->loader->add_action( 'gform_enqueue_scripts', $plugin_qr_frontend, 'enqueue_assets', 10, 2 );

		// QR Screenshot AJAX Upload Handler
		require_once EMP_PLUGIN_DIR . 'services/class-emp-qr-upload-handler.php';
		$plugin_qr_upload_handler = new EMP_QR_Upload_Handler();
		$this->loader->add_action( 'wp_ajax_nopriv_emp_upload_qr_screenshot', $plugin_qr_upload_handler, 'handle_screenshot_upload' );
		$this->loader->add_action( 'wp_ajax_emp_upload_qr_screenshot', $plugin_qr_upload_handler, 'handle_screenshot_upload' );
		
		$this->loader->add_action( 'admin_init', $this, 'ensure_pages_exist' );
		$this->loader->add_action( 'admin_post_emp_sync_phones', $this, 'retroactive_phone_sync' );
	}
	
	public function retroactive_phone_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( "Unauthorized." );
		}
		
		check_admin_referer( 'emp_sync_phones_action', 'emp_sync_phones_nonce' );
			if ( ! class_exists( 'GFAPI' ) ) {
				wp_die( "Gravity Forms not active." );
			}

			global $wpdb;
			$table_attendees = $wpdb->prefix . 'emp_attendees';
			$attendees = $wpdb->get_results( "SELECT * FROM $table_attendees WHERE phone IS NULL OR phone = ''" );
			
			echo "Found " . count($attendees) . " attendees missing phone numbers.<br>";
			$updated_count = 0;

			foreach ( $attendees as $att ) {
				$event_id = $att->event_id;
				$email = $att->email;
				
				$form_id = get_post_meta( $event_id, '_emp_gf_form_id', true );
				if ( ! $form_id && class_exists( 'EMP_GF_Addon' ) ) {
					// Fallback to searching feeds
					$addon = EMP_GF_Addon::get_instance();
					$feeds = $addon->get_active_feeds();
					foreach ( $feeds as $feed ) {
						if ( rgar( $feed['meta'], 'event_id' ) == $event_id ) {
							$form_id = $feed['form_id'];
							break;
						}
					}
				}
				
				if ( ! $form_id ) continue;
				
				$entries = GFAPI::get_entries( $form_id, array( 'status' => 'active' ), null, array( 'offset' => 0, 'page_size' => 500 ) );
				$phone = '';
				
				if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) {
					$form = GFAPI::get_form( $form_id );
					$phone_field_id = null;
					$email_field_id = null;
					
					foreach ( $form['fields'] as $field ) {
						if ( $field->type === 'phone' || stripos( $field->label, 'phone' ) !== false || stripos( $field->label, 'whatsapp' ) !== false || stripos( $field->label, 'mobile' ) !== false ) {
							$phone_field_id = strval( $field->id );
						}
						if ( $field->type === 'email' || stripos( $field->label, 'email' ) !== false ) {
							$email_field_id = strval( $field->id );
						}
					}
					
					if ( $phone_field_id && $email_field_id ) {
						foreach ( $entries as $entry ) {
							if ( strtolower( trim( rgar( $entry, $email_field_id ) ) ) === strtolower( trim( $email ) ) ) {
								$phone = rgar( $entry, $phone_field_id );
								break;
							}
						}
					}
				}
				
				if ( ! empty( $phone ) ) {
					$wpdb->update( $table_attendees, array( 'phone' => $phone ), array( 'id' => $att->id ) );
					$updated_count++;
				}
			}

			$redirect_url = admin_url( 'edit.php?post_type=emp_event&page=emp-settings&tab=tools&sync_status=success&updated_count=' . $updated_count . '&total_missing=' . count($attendees) );
			wp_safe_redirect( $redirect_url );
			exit;
	}
	
	public function ensure_pages_exist() {
		$page_title = 'Scanner Access';
		$page_check = get_page_by_title( $page_title );
		if ( ! isset( $page_check->ID ) ) {
			$page = array(
				'post_title'   => $page_title,
				'post_content' => '[emp_frontend_scanner]',
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_type'    => 'page',
			);
			wp_insert_post( $page );
		}
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
}
