<?php
/**
 * Handles Communications (Email, WhatsApp links).
 */
class EMP_Communications {

	public function send_email( $attendee_id, $type, $subject = '', $message = '' ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE id = %d", $attendee_id ) );
		if ( ! $attendee || empty( $attendee->email ) ) {
			return false;
		}

		$event_title = get_the_title( $attendee->event_id );
		
		// If subject/message not provided, load defaults based on type
		if ( empty( $subject ) || empty( $message ) ) {
			$defaults = $this->get_default_template( $type, $event_title, $attendee );
			$subject = empty( $subject ) ? $defaults['subject'] : $subject;
			$message = empty( $message ) ? $defaults['message'] : $message;
		}

		// Replace tokens
		$message = str_replace( '{name}', $attendee->name, $message );
		$message = str_replace( '{event}', $event_title, $message );
		$message = str_replace( '{badge_link}', $this->get_badge_link( $attendee->id ), $message );
		$message = str_replace( '{whatsapp_link}', $this->get_whatsapp_link( $attendee->id ), $message );

		$headers = array('Content-Type: text/html; charset=UTF-8');
		
		$sent = wp_mail( $attendee->email, $subject, wpautop( $message ), $headers );

		if ( $sent ) {
			$this->log_communication( $attendee->id, $type, 'email', 'sent' );
		} else {
			$this->log_communication( $attendee->id, $type, 'email', 'failed' );
		}

		return $sent;
	}

	public function get_whatsapp_link( $attendee_id ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		// Fetch attendee details including phone and event ID
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT name, phone, event_id, qr_token FROM {$table_attendees} WHERE id = %d", $attendee_id ) );
		
		if ( ! $attendee ) {
			return '';
		}

		$event_title = get_the_title( $attendee->event_id );
		$event_date = get_post_meta( $attendee->event_id, '_emp_start_date', true );
		$event_location = get_post_meta( $attendee->event_id, '_emp_location', true );
		$badge_link = site_url( '?emp_download_badge=' . $attendee->qr_token );
		
		// Build detailed message
		$text = sprintf( "Hi %s,\n\nHere is your badge for %s.", $attendee->name, $event_title );
		
		if ( $event_date ) {
			$text .= "\nDate: " . date( 'F j, Y, g:i a', strtotime( $event_date ) );
		}
		if ( $event_location ) {
			$text .= "\nLocation: " . $event_location;
		}
		
		$text .= "\n\nDownload Badge: " . $badge_link;

		// Clean phone number (strip everything except digits and plus sign)
		$phone = '';
		if ( ! empty( $attendee->phone ) ) {
			$phone = preg_replace( '/[^0-9\+]/', '', $attendee->phone );
		}

		if ( ! empty( $phone ) ) {
			return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $text );
		} else {
			return 'https://wa.me/?text=' . rawurlencode( $text );
		}
	}

	public function get_badge_link( $attendee_id ) {
		// Public route to download badge securely using token
		global $wpdb;
		$token = $wpdb->get_var( $wpdb->prepare( "SELECT qr_token FROM {$wpdb->prefix}emp_attendees WHERE id = %d", $attendee_id ) );
		return site_url( '?emp_download_badge=' . $token );
	}

	private function get_default_template( $type, $event_title, $attendee ) {
		switch ( $type ) {
			case 'confirmation':
				return array(
					'subject' => "Registration Confirmed: $event_title",
					'message' => "Hi {name},\n\nYour registration for {event} is confirmed.\n\nYou can download your badge here: {badge_link}\nOr send it via WhatsApp: {whatsapp_link}\n\nSee you there!"
				);
			case 'reminder':
				return array(
					'subject' => "Reminder: $event_title is coming up!",
					'message' => "Hi {name},\n\nWe can't wait to see you at {event}.\n\nDon't forget to have your badge ready: {badge_link}"
				);
			case 'thank_you':
				return array(
					'subject' => "Thank you for attending $event_title",
					'message' => "Hi {name},\n\nThank you for joining us at {event}. We hope you had a great time!"
				);
			default:
				return array(
					'subject' => "Update regarding $event_title",
					'message' => "Hi {name},\n\nPlease see the latest updates regarding {event}."
				);
		}
	}

	private function log_communication( $attendee_id, $type, $channel, $status ) {
		global $wpdb;
		$table_comms = $wpdb->prefix . 'emp_communications';
		$wpdb->insert( $table_comms, array(
			'attendee_id' => $attendee_id,
			'type'        => $type,
			'channel'     => $channel,
			'status'      => $status,
		) );
	}

	// Hook to handle public badge download
	public function handle_public_badge_download() {
		if ( isset( $_GET['emp_download_badge'] ) ) {
			$token = sanitize_text_field( $_GET['emp_download_badge'] );
			if ( strpos( $token, 'invalidated_' ) === 0 ) {
				wp_die( __( 'This badge has been invalidated.', 'event-management-plugin' ) );
			}

			global $wpdb;
			$table_attendees = $wpdb->prefix . 'emp_attendees';
			$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT id, event_id, printed_status FROM {$table_attendees} WHERE qr_token = %s", $token ) );
			
			if ( $attendee ) {
				$limit = get_post_meta( $attendee->event_id, '_emp_badge_download_limit', true );
				if ( empty( $limit ) ) $limit = 'multiple';

				// Single download restriction
				if ( $limit === 'single' && $attendee->printed_status == 1 ) {
					wp_die( __( 'This badge has already been downloaded. You can only download your badge once.', 'event-management-plugin' ) );
				}
				// Mark as downloaded
				$wpdb->update( 
					$table_attendees, 
					array( 'printed_status' => 1 ), 
					array( 'id' => $attendee->id ) 
				);

				require_once EMP_PLUGIN_DIR . 'services/class-emp-badge-generator.php';
				$generator = new EMP_Badge_Generator();
				$generator->generate_individual( $attendee->id, 'D' ); // 'D' triggers forced download
				exit;
			} else {
				wp_die( __( 'Invalid badge link.', 'event-management-plugin' ) );
			}
		}
	}
}
