<?php
/**
 * Fired during plugin activation
 */
class EMP_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::add_roles();
		self::create_scanner_page();
		flush_rewrite_rules();
	}

	private static function create_scanner_page() {
		$page_title = 'Scanner Access';
		$page_content = '[emp_frontend_scanner]';
		
		$page_check = get_page_by_title( $page_title );
		if ( ! isset( $page_check->ID ) ) {
			$page = array(
				'post_title'   => $page_title,
				'post_content' => $page_content,
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_type'    => 'page',
			);
			wp_insert_post( $page );
		}
	}

	/**
	 * Create necessary database tables using dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Ticket Types Table
		$table_ticket_types = $wpdb->prefix . 'emp_ticket_types';
		$sql_ticket_types = "CREATE TABLE $table_ticket_types (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			name varchar(255) NOT NULL,
			price decimal(10,2) NOT NULL DEFAULT '0.00',
			capacity int(11) DEFAULT NULL,
			color_code varchar(20) DEFAULT NULL,
			is_comp tinyint(1) NOT NULL DEFAULT '0',
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) $charset_collate;";
		dbDelta( $sql_ticket_types );

		// Attendees Table
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$sql_attendees = "CREATE TABLE $table_attendees (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			ticket_type_id bigint(20) NOT NULL,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			organization varchar(255) DEFAULT NULL,
			photo_path varchar(255) DEFAULT NULL,
			qr_token varchar(64) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'registered',
			payment_status varchar(50) NOT NULL DEFAULT 'pending',
			source varchar(50) NOT NULL DEFAULT 'online',
			printed_status tinyint(1) NOT NULL DEFAULT '0',
			group_id bigint(20) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY qr_token (qr_token),
			KEY event_id (event_id),
			KEY email (email)
		) $charset_collate;";
		dbDelta( $sql_attendees );

		// Scan Points Table
		$table_scan_points = $wpdb->prefix . 'emp_scan_points';
		$sql_scan_points = "CREATE TABLE $table_scan_points (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			name varchar(255) NOT NULL,
			mode varchar(50) NOT NULL DEFAULT 'entry',
			rule varchar(50) NOT NULL DEFAULT 'single',
			soft_prerequisite bigint(20) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY event_id (event_id)
		) $charset_collate;";
		dbDelta( $sql_scan_points );

		// Scan Logs Table
		$table_scan_logs = $wpdb->prefix . 'emp_scan_logs';
		$sql_scan_logs = "CREATE TABLE $table_scan_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attendee_id bigint(20) NOT NULL,
			scan_point_id bigint(20) NOT NULL,
			staff_user_id bigint(20) NOT NULL,
			result varchar(50) NOT NULL,
			reason text DEFAULT NULL,
			scanned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY attendee_id (attendee_id),
			KEY scan_point_id (scan_point_id)
		) $charset_collate;";
		dbDelta( $sql_scan_logs );

		// Payments Table
		$table_payments = $wpdb->prefix . 'emp_payments';
		$sql_payments = "CREATE TABLE $table_payments (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attendee_id bigint(20) NOT NULL,
			amount decimal(10,2) NOT NULL,
			method varchar(50) NOT NULL,
			reference varchar(255) DEFAULT NULL,
			refund_info text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY attendee_id (attendee_id)
		) $charset_collate;";
		dbDelta( $sql_payments );

		// Communications Table
		$table_comms = $wpdb->prefix . 'emp_communications';
		$sql_comms = "CREATE TABLE $table_comms (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attendee_id bigint(20) NOT NULL,
			type varchar(50) NOT NULL,
			channel varchar(50) NOT NULL,
			status varchar(50) NOT NULL,
			sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY attendee_id (attendee_id)
		) $charset_collate;";
		dbDelta( $sql_comms );

		// Audit Logs Table
		$table_audit_logs = $wpdb->prefix . 'emp_audit_logs';
		$sql_audit_logs = "CREATE TABLE $table_audit_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			action varchar(100) NOT NULL,
			target varchar(100) NOT NULL,
			summary text NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $sql_audit_logs );
	}

	/**
	 * Register custom roles for the plugin.
	 */
	private static function add_roles() {
		// Organizer
		add_role( 'emp_organizer', 'Event Organizer', array(
			'read' => true,
			'edit_posts' => true,
			'upload_files' => true,
			'manage_event_settings' => true,
			'view_event_reports' => true,
			'manage_attendees' => true,
			'manage_finances' => true,
		) );

		// Registration Staff
		add_role( 'emp_reg_staff', 'Registration Staff', array(
			'read' => true,
			'manage_attendees' => true, // walk-in register, print, manual check-in
			'view_event_reports' => false,
			'manage_finances' => false, // no finances
		) );

		// Scanning Staff
		add_role( 'emp_scan_staff', 'Scanning Staff', array(
			'read' => true,
			'scan_attendees' => true, // scan only
			'manage_attendees' => false,
			'view_event_reports' => false,
			'manage_finances' => false,
		) );

		// Ensure Admin has all caps
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_event_settings' );
			$admin_role->add_cap( 'view_event_reports' );
			$admin_role->add_cap( 'manage_attendees' );
			$admin_role->add_cap( 'manage_finances' );
			$admin_role->add_cap( 'scan_attendees' );
		}
	}
}
