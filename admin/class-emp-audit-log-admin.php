<?php
/**
 * Admin Audit Log UI.
 */
class EMP_Audit_Log_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Audit Log', 'event-management-plugin' ),
			__( 'Audit Log', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-audit-log',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		$event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : ( ! empty( $events ) ? $events[0]->ID : 0 );

		echo '<div class="wrap" style="max-width: 1200px;">';
		echo '<h1>' . __( 'Live Scan Audit Log', 'event-management-plugin' ) . '</h1>';
		
		if ( empty( $events ) ) {
			echo '<p>' . __( 'No events found.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		global $wpdb;
		$table_points = $wpdb->prefix . 'emp_scan_points';
		$points = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM $table_points WHERE event_id = %d ORDER BY name ASC", $event_id ) );

		$filter_point_id = isset( $_GET['point_id'] ) ? intval( $_GET['point_id'] ) : 0;
		$filter_result = isset( $_GET['result'] ) ? sanitize_text_field( $_GET['result'] ) : '';
		$filter_search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

		// Filter Form
		echo '<div class="tablenav top">';
		echo '<form method="get" action="">';
		echo '<input type="hidden" name="post_type" value="emp_event" />';
		echo '<input type="hidden" name="page" value="emp-audit-log" />';
		
		echo '<select name="event_id" onchange="this.form.submit()" style="margin-right: 10px;">';
		foreach ( $events as $event ) {
			$selected = ( $event->ID == $event_id ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $event->ID ) . '" ' . $selected . '>' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select>';

		echo '<select name="point_id" style="margin-right: 10px;">';
		echo '<option value="">' . __( 'All Stations', 'event-management-plugin' ) . '</option>';
		if ( $points ) {
			foreach ( $points as $point ) {
				$selected = ( $point->id == $filter_point_id ) ? 'selected' : '';
				echo '<option value="' . esc_attr( $point->id ) . '" ' . $selected . '>' . esc_html( $point->name ) . '</option>';
			}
		}
		echo '</select>';

		echo '<select name="result" style="margin-right: 10px;">';
		echo '<option value="">' . __( 'All Results', 'event-management-plugin' ) . '</option>';
		echo '<option value="pass" ' . ( $filter_result === 'pass' ? 'selected' : '' ) . '>' . __( 'Pass', 'event-management-plugin' ) . '</option>';
		echo '<option value="fail" ' . ( $filter_result === 'fail' ? 'selected' : '' ) . '>' . __( 'Fail', 'event-management-plugin' ) . '</option>';
		echo '</select>';

		echo '<input type="text" name="search" value="' . esc_attr( $filter_search ) . '" placeholder="' . esc_attr__( 'Search Attendee...', 'event-management-plugin' ) . '" style="margin-right: 10px;" />';
		
		echo '<input type="submit" class="button button-primary" value="' . __( 'Filter', 'event-management-plugin' ) . '" />';
		echo '</form>';
		echo '</div>';

		// Query Logs
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		$query = "
			SELECT l.*, p.name as point_name, a.name as attendee_name 
			FROM $table_logs l
			LEFT JOIN $table_points p ON l.scan_point_id = p.id
			LEFT JOIN $table_attendees a ON l.attendee_id = a.id
			WHERE a.event_id = %d
		";
		
		$args = array( $event_id );

		if ( $filter_point_id > 0 ) {
			$query .= " AND l.scan_point_id = %d";
			$args[] = $filter_point_id;
		}

		if ( $filter_result !== '' ) {
			$query .= " AND l.result = %s";
			$args[] = $filter_result;
		}

		if ( $filter_search !== '' ) {
			$query .= " AND a.name LIKE %s";
			$args[] = '%' . $wpdb->esc_like( $filter_search ) . '%';
		}

		$query .= " ORDER BY l.scanned_at DESC LIMIT 500"; // Increased limit for audit log page

		$logs = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . __( 'Time', 'event-management-plugin' ) . '</th><th>' . __( 'Attendee', 'event-management-plugin' ) . '</th><th>' . __( 'Station', 'event-management-plugin' ) . '</th><th>' . __( 'Result', 'event-management-plugin' ) . '</th><th>' . __( 'Reason', 'event-management-plugin' ) . '</th><th>' . __( 'Staff ID', 'event-management-plugin' ) . '</th></tr></thead>';
		echo '<tbody>';

		if ( $logs ) {
			foreach ( $logs as $log ) {
				$color = ( $log->result === 'pass' ) ? 'green' : 'red';
				echo '<tr>';
				echo '<td>' . esc_html( $log->scanned_at ) . '</td>';
				echo '<td>' . esc_html( $log->attendee_name ?: 'Unknown' ) . '</td>';
				echo '<td>' . esc_html( $log->point_name ) . '</td>';
				echo '<td style="color:' . $color . '; font-weight:bold;">' . esc_html( strtoupper( $log->result ) ) . '</td>';
				echo '<td>' . esc_html( $log->reason ) . '</td>';
				echo '<td>' . esc_html( $log->staff_user_id ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="6">' . __( 'No scans found matching criteria.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table>';
		echo '</div>';
	}
}
