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

		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-integration.php';
		$plugin_gf_integration = new EMP_GF_Integration();
		$this->loader->add_action( 'gform_loaded', $plugin_gf_integration, 'init' );
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
		
		$this->loader->add_action( 'admin_init', $this, 'ensure_pages_exist' );
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
