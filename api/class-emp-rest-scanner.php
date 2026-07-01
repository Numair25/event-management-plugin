<?php
/**
 * REST API for the Scanner App.
 */
class EMP_REST_Scanner {

	public function register_endpoints() {
		register_rest_route( 'emp/v1', '/scan-points', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_scan_points' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );

		register_rest_route( 'emp/v1', '/scan', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'process_scan' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );

		register_rest_route( 'emp/v1', '/manual-lookup', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'manual_lookup' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );
	}

	public function check_permission() {
		return current_user_can( 'scan_attendees' );
	}

	public function get_scan_points( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_scan_points';
		$points = $wpdb->get_results( "SELECT * FROM $table_name" );
		
		$response = array();
		if ( $points ) {
			foreach ( $points as $p ) {
				$event_title = get_the_title( $p->event_id );
				$response[] = array(
					'id' => $p->id,
					'event_id' => $p->event_id,
					'event_title' => $event_title,
					'name' => $p->name,
					'mode' => $p->mode
				);
			}
		}
		
		return rest_ensure_response( $response );
	}

	public function process_scan( $request ) {
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$point_id = intval( $request->get_param( 'point_id' ) );
		
		if ( empty( $token ) || empty( $point_id ) ) {
			return new WP_Error( 'invalid_data', 'Token and Scan Point ID are required.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$table_points = $wpdb->prefix . 'emp_scan_points';
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		
		$point = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_points WHERE id = %d", $point_id ) );
		if ( ! $point ) {
			return new WP_Error( 'invalid_point', 'Scan Point not found.', array( 'status' => 404 ) );
		}

		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE qr_token = %s", $token ) );
		if ( ! $attendee ) {
			$this->log_scan( 0, $point_id, 'fail', 'Invalid Token' );
			return rest_ensure_response( array( 'success' => false, 'message' => 'Invalid or Unrecognized Badge.' ) );
		}

		// Validation Rules
		if ( $attendee->event_id != $point->event_id ) {
			$this->log_scan( $attendee->id, $point_id, 'fail', 'Wrong Event' );
			return rest_ensure_response( array( 'success' => false, 'message' => 'Badge is for a different event.', 'attendee' => $this->format_attendee( $attendee ) ) );
		}

		if ( $attendee->status === 'cancelled' || $attendee->status === 'waitlisted' || strpos( $attendee->qr_token, 'invalidated_' ) === 0 ) {
			$this->log_scan( $attendee->id, $point_id, 'fail', 'Cancelled/Refunded' );
			return rest_ensure_response( array( 'success' => false, 'message' => 'Badge has been cancelled or refunded.', 'attendee' => $this->format_attendee( $attendee ) ) );
		}

		if ( $attendee->payment_status !== 'paid' && $attendee->payment_status !== 'comp' ) {
			$this->log_scan( $attendee->id, $point_id, 'fail', 'Unpaid' );
			return rest_ensure_response( array( 'success' => false, 'message' => 'Attendee has an unpaid balance.', 'attendee' => $this->format_attendee( $attendee ) ) );
		}

		// Check Repeat Rule
		if ( $point->rule === 'single' ) {
			$previous_scan = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_logs WHERE attendee_id = %d AND scan_point_id = %d AND result = 'pass'", $attendee->id, $point_id ) );
			if ( $previous_scan > 0 ) {
				$this->log_scan( $attendee->id, $point_id, 'fail', 'Already Scanned' );
				return rest_ensure_response( array( 'success' => false, 'message' => 'Badge already scanned at this point.', 'attendee' => $this->format_attendee( $attendee ) ) );
			}
		}

		// Check-out Logic
		if ( $point->mode === 'check-out' ) {
			$wpdb->update( $table_attendees, array( 'status' => 'registered' ), array( 'id' => $attendee->id ) );
			$this->log_scan( $attendee->id, $point_id, 'pass', 'Checked Out' );
			return rest_ensure_response( array( 'success' => true, 'message' => 'Successfully checked out.', 'attendee' => $this->format_attendee( $attendee ) ) );
		}

		// Success / Check-in
		if ( $point->mode === 'entry' ) {
			$wpdb->update( $table_attendees, array( 'status' => 'checked-in' ), array( 'id' => $attendee->id ) );
		}
		
		$this->log_scan( $attendee->id, $point_id, 'pass', '' );
		return rest_ensure_response( array( 'success' => true, 'message' => 'Access Granted.', 'attendee' => $this->format_attendee( $attendee ) ) );
	}

	public function manual_lookup( $request ) {
		$query = sanitize_text_field( $request->get_param( 'query' ) );
		
		if ( empty( $query ) || strlen( $query ) < 3 ) {
			return new WP_Error( 'invalid_data', 'Query must be at least 3 characters.', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$search = '%' . $wpdb->esc_like( $query ) . '%';
		$attendees = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE name LIKE %s OR email LIKE %s LIMIT 10", $search, $search ) );
		
		$response = array();
		if ( $attendees ) {
			foreach ( $attendees as $a ) {
				$response[] = array(
					'id' => $a->id,
					'name' => $a->name,
					'email' => $a->email,
					'token' => $a->qr_token,
					'status' => $a->status,
					'payment' => $a->payment_status
				);
			}
		}
		
		return rest_ensure_response( $response );
	}

	private function format_attendee( $attendee ) {
		global $wpdb;
		$table_tickets = $wpdb->prefix . 'emp_ticket_types';
		$ticket_name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table_tickets WHERE id = %d", $attendee->ticket_type_id ) );

		$upload_dir = wp_upload_dir();
		$photo_url = '';
		if ( ! empty( $attendee->photo_path ) ) {
			$photo_url = $upload_dir['baseurl'] . '/' . str_replace( 'emp_photos/', 'emp_photos/', $attendee->photo_path );
		}

		return array(
			'id' => $attendee->id,
			'name' => $attendee->name,
			'organization' => $attendee->organization,
			'ticket_type' => $ticket_name,
			'photo_url' => $photo_url,
			'status' => $attendee->status
		);
	}

	private function log_scan( $attendee_id, $point_id, $result, $reason ) {
		global $wpdb;
		$table_logs = $wpdb->prefix . 'emp_scan_logs';
		$wpdb->insert( $table_logs, array(
			'attendee_id'   => $attendee_id,
			'scan_point_id' => $point_id,
			'staff_user_id' => get_current_user_id(),
			'result'        => $result,
			'reason'        => $reason
		) );
	}
}
