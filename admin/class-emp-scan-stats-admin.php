<?php
/**
 * Admin Scan Statistics UI.
 */
class EMP_Scan_Stats_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Scan Statistics', 'event-management-plugin' ),
			__( 'Scan Statistics', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-scan-stats',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		$event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : ( ! empty( $events ) ? $events[0]->ID : 0 );
		$selected_date = isset( $_GET['filter_date'] ) ? sanitize_text_field( $_GET['filter_date'] ) : wp_date( 'Y-m-d' );
		$selected_point_id = isset( $_GET['scan_point_id'] ) ? sanitize_text_field( $_GET['scan_point_id'] ) : 'all';

		echo '<div class="wrap" style="max-width: 1200px;">';
		echo '<h1>' . __( 'Scan Statistics', 'event-management-plugin' ) . '</h1>';
		
		if ( empty( $events ) ) {
			echo '<p>' . __( 'No events found.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		global $wpdb;
		$table_points = $wpdb->prefix . 'emp_scan_points';
		$scan_points = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_points WHERE event_id = %d ORDER BY name ASC", $event_id ) );

		// Filters
		echo '<form method="get" action="">';
		echo '<input type="hidden" name="post_type" value="emp_event" />';
		echo '<input type="hidden" name="page" value="emp-scan-stats" />';
		echo '<div style="display:flex; gap:15px; margin-bottom:20px; align-items:center;">';
		
		echo '<select name="event_id" onchange="this.form.submit()" style="font-size:16px; padding:5px;">';
		foreach ( $events as $event ) {
			$selected = ( $event->ID == $event_id ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $event->ID ) . '" ' . $selected . '>' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select>';
		
		echo '<input type="date" name="filter_date" value="' . esc_attr( $selected_date ) . '" onchange="this.form.submit()" style="font-size:16px; padding:5px;" />';
		
		echo '<select name="scan_point_id" onchange="this.form.submit()" style="font-size:16px; padding:5px;">';
		echo '<option value="all" ' . selected( $selected_point_id, 'all', false ) . '>' . __( 'All Scan Points', 'event-management-plugin' ) . '</option>';
		if ( $scan_points ) {
			foreach ( $scan_points as $sp ) {
				echo '<option value="' . esc_attr( $sp->id ) . '" ' . selected( $selected_point_id, $sp->id, false ) . '>' . esc_html( $sp->name ) . '</option>';
			}
		}
		echo '</select>';
		
		echo '</div>';
		echo '</form>';

		// Scan Statistics for Date
		echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px;">';
		echo '<h2>' . sprintf( __( 'Statistics for %s', 'event-management-plugin' ), esc_html( wp_date( get_option('date_format'), strtotime( $selected_date ) ) ) ) . '</h2>';
		
		$scan_stats = $this->get_scan_point_stats( $event_id, $selected_date, $selected_point_id );
		
		if ( ! empty( $scan_stats ) ) {
			echo '<div style="display: flex; gap: 20px; flex-wrap: wrap;">';
			foreach ( $scan_stats as $stat ) {
				$this->render_widget( $stat->point_name, $stat->scan_count, '#6f42c1' );
			}
			echo '</div>';
		} else {
			echo '<p>' . __( 'No successful scans recorded on this date with the selected filters.', 'event-management-plugin' ) . '</p>';
		}
		echo '</div>';

		echo '</div>';
	}

	private function render_widget( $title, $value, $color ) {
		echo '<div style="flex: 1; min-width: 200px; background: #fff; padding: 20px; border-radius: 8px; border-left: 5px solid ' . esc_attr( $color ) . '; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h3 style="margin: 0 0 10px 0; color: #555; font-size: 14px; text-transform: uppercase;">' . esc_html( $title ) . '</h3>';
		echo '<div style="font-size: 32px; font-weight: bold; color: ' . esc_attr( $color ) . ';">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	private function get_scan_point_stats( $event_id, $date, $scan_point_id ) {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		$table_points = $wpdb->prefix . 'emp_scan_points';
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		$query = "
			SELECT p.name as point_name, COUNT(l.id) as scan_count
			FROM $table_logs l
			INNER JOIN $table_points p ON l.scan_point_id = p.id
			INNER JOIN $table_attendees a ON l.attendee_id = a.id
			WHERE a.event_id = %d AND l.result = 'pass' AND DATE(l.scanned_at) = %s
		";
		$args = array( $event_id, $date );

		if ( $scan_point_id !== 'all' ) {
			$query .= " AND p.id = %d";
			$args[] = intval( $scan_point_id );
		}

		$query .= " GROUP BY p.id ORDER BY p.name ASC";

		$results = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

		return $results;
	}
}
