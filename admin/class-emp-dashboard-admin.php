<?php
/**
 * Admin Dashboard & Reporting UI.
 */
class EMP_Dashboard_Admin {

	public function register_menu() {
		// Make Dashboard the first sub-menu item under Events
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Dashboard & Reports', 'event-management-plugin' ),
			__( 'Dashboard & Reports', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-dashboard',
			array( $this, 'render_page' ),
			1
		);
	}

	public function render_page() {
		// Handle CSV Export
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'export_csv' && isset( $_GET['event_id'] ) ) {
			if ( current_user_can( 'manage_finances' ) || current_user_can( 'manage_event_settings' ) ) {
				$this->export_csv( intval( $_GET['event_id'] ) );
			} else {
				wp_die( __( 'You do not have permission to export data.', 'event-management-plugin' ) );
			}
		}

		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		$event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : ( ! empty( $events ) ? $events[0]->ID : 0 );

		echo '<div class="wrap" style="max-width: 1200px;">';
		echo '<h1>' . __( 'Event Dashboard & Reports', 'event-management-plugin' ) . '</h1>';
		
		if ( empty( $events ) ) {
			echo '<p>' . __( 'No events found.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		// Event Selector
		echo '<form method="get" action="">';
		echo '<input type="hidden" name="post_type" value="emp_event" />';
		echo '<input type="hidden" name="page" value="emp-dashboard" />';
		echo '<select name="event_id" onchange="this.form.submit()" style="font-size:16px; padding:5px; margin-bottom:20px;">';
		foreach ( $events as $event ) {
			$selected = ( $event->ID == $event_id ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $event->ID ) . '" ' . $selected . '>' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select>';
		echo '</form>';

		$stats = $this->get_event_stats( $event_id );

		// Stats Widgets
		echo '<div style="display: flex; gap: 20px; margin-bottom: 30px;">';
		
		$this->render_widget( 'Total Registrations', $stats['total_registered'], '#0073aa' );
		$this->render_widget( 'Total Revenue', '$' . number_format( $stats['total_revenue'], 2 ), '#28a745' );
		$this->render_widget( 'Checked-in Today', $stats['checked_in'], '#17a2b8' );
		$this->render_widget( 'Pending Payments', $stats['pending_payments'], '#dc3545' );
		
		echo '</div>';

		// Peak Entry Times Chart
		echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">';
		echo '<h2>' . __( 'Peak Entry Times', 'event-management-plugin' ) . '</h2>';
		echo '<div style="position: relative; height: 300px; width: 100%;">';
		echo '<canvas id="peakEntryChart"></canvas>';
		echo '</div>';
		echo '</div>';

		// Load Chart.js
		echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
		echo '<script>';
		echo 'document.addEventListener("DOMContentLoaded", function() {';
		echo '	var ctx = document.getElementById("peakEntryChart").getContext("2d");';
		
		$entry_data = $this->get_peak_entry_data( $event_id );
		
		echo '	var chart = new Chart(ctx, {';
		echo '		type: "line",';
		echo '		data: {';
		echo '			labels: ' . json_encode( array_keys( $entry_data ) ) . ',';
		echo '			datasets: [{';
		echo '				label: "Check-ins",';
		echo '				data: ' . json_encode( array_values( $entry_data ) ) . ',';
		echo '				borderColor: "#0073aa",';
		echo '				backgroundColor: "rgba(0, 115, 170, 0.1)",';
		echo '				fill: true,';
		echo '				tension: 0.4';
		echo '			}]';
		echo '		},';
		echo '		options: {';
		echo '			responsive: true,';
		echo '			maintainAspectRatio: false,';
		echo '			scales: {';
		echo '				y: {';
		echo '					beginAtZero: true,';
		echo '					suggestedMax: 5,';
		echo '					ticks: { stepSize: 1 }';
		echo '				}';
		echo '			}';
		echo '		}';
		echo '	});';
		echo '});';
		echo '</script>';

		// Export Button
		$export_url = admin_url( 'edit.php?post_type=emp_event&page=emp-dashboard&action=export_csv&event_id=' . $event_id );
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button button-primary">' . __( 'Export Attendee Data (CSV)', 'event-management-plugin' ) . '</a></p>';

		// Recent Scans Table (Audit)
		$this->render_recent_scans( $event_id );

		echo '</div>';
	}

	private function render_widget( $title, $value, $color ) {
		echo '<div style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; border-left: 5px solid ' . esc_attr( $color ) . '; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h3 style="margin: 0 0 10px 0; color: #555; font-size: 14px; text-transform: uppercase;">' . esc_html( $title ) . '</h3>';
		echo '<div style="font-size: 32px; font-weight: bold; color: ' . esc_attr( $color ) . ';">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	private function get_event_stats( $event_id ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$table_payments = $wpdb->prefix . 'emp_payments';

		$total_registered = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_attendees WHERE event_id = %d AND status != 'cancelled'", $event_id ) );
		$checked_in = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_attendees WHERE event_id = %d AND status = 'checked-in'", $event_id ) );
		$pending_payments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_attendees WHERE event_id = %d AND payment_status = 'pending'", $event_id ) );
		
		// Join to get revenue for this event's attendees
		$total_revenue = $wpdb->get_var( $wpdb->prepare( "
			SELECT SUM(p.amount) 
			FROM $table_payments p
			INNER JOIN $table_attendees a ON p.attendee_id = a.id
			WHERE a.event_id = %d
		", $event_id ) );

		return array(
			'total_registered' => intval( $total_registered ),
			'checked_in'       => intval( $checked_in ),
			'pending_payments' => intval( $pending_payments ),
			'total_revenue'    => floatval( $total_revenue ),
		);
	}

	private function get_peak_entry_data( $event_id ) {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		// Group by hour
		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT DATE_FORMAT(l.scanned_at, '%%H:00') as hour, COUNT(*) as count 
			FROM $table_logs l
			INNER JOIN $table_attendees a ON l.attendee_id = a.id
			WHERE a.event_id = %d AND l.result = 'success'
			GROUP BY hour
			ORDER BY hour ASC
		", $event_id ) );

		$data = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$data[$row->hour] = intval( $row->count );
			}
		}
		
		return empty($data) ? array('08:00' => 0) : $data;
	}

	private function render_recent_scans( $event_id ) {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		$table_points = $wpdb->prefix . 'emp_scan_points';
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		$logs = $wpdb->get_results( $wpdb->prepare( "
			SELECT l.*, p.name as point_name, a.name as attendee_name 
			FROM $table_logs l
			LEFT JOIN $table_points p ON l.scan_point_id = p.id
			LEFT JOIN $table_attendees a ON l.attendee_id = a.id
			WHERE a.event_id = %d
			ORDER BY l.scanned_at DESC
			LIMIT 20
		", $event_id ) );

		echo '<h2>' . __( 'Live Scan Audit Log', 'event-management-plugin' ) . '</h2>';
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
			echo '<tr><td colspan="6">' . __( 'No scans recorded yet.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table>';
	}

	private function export_csv( $event_id ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$attendees = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE event_id = %d ORDER BY id ASC", $event_id ), ARRAY_A );
		
		if ( ! $attendees ) {
			wp_die( __( 'No data to export.', 'event-management-plugin' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=event_' . $event_id . '_attendees.csv' );
		
		$output = fopen( 'php://output', 'w' );
		
		// Header row
		fputcsv( $output, array( 'ID', 'Ticket Type ID', 'Name', 'Email', 'Organization', 'Status', 'Payment Status', 'Source', 'Registered At' ) );
		
		foreach ( $attendees as $row ) {
			fputcsv( $output, array(
				$row['id'],
				$row['ticket_type_id'],
				$row['name'],
				$row['email'],
				$row['organization'],
				$row['status'],
				$row['payment_status'],
				$row['source'],
				$row['created_at'],
			) );
		}
		
		fclose( $output );
		exit;
	}
}
