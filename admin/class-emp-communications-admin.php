<?php
/**
 * Admin UI for Communications (Bulk Announcements, Resends).
 */
class EMP_Communications_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Communications', 'event-management-plugin' ),
			__( 'Communications', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-communications',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
		$comms_service = new EMP_Communications();

		// Handle Resend
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'resend_confirmation' && isset( $_GET['attendee_id'] ) ) {
			check_admin_referer( 'resend_conf_' . $_GET['attendee_id'] );
			$sent = $comms_service->send_email( intval( $_GET['attendee_id'] ), 'confirmation' );
			if ( $sent ) {
				echo '<div class="updated"><p>' . __( 'Confirmation email resent.', 'event-management-plugin' ) . '</p></div>';
			} else {
				echo '<div class="error"><p>' . __( 'Failed to send email.', 'event-management-plugin' ) . '</p></div>';
			}
		}

		// Handle Bulk Announcement
		if ( isset( $_POST['emp_send_bulk'] ) ) {
			check_admin_referer( 'send_bulk_announcement' );
			
			$event_id = intval( $_POST['event_id'] );
			$subject = sanitize_text_field( $_POST['subject'] );
			$message = wp_kses_post( wp_unslash( $_POST['message'] ) ); // Allow HTML
			$segment = sanitize_text_field( $_POST['segment'] );

			global $wpdb;
			$table_attendees = $wpdb->prefix . 'emp_attendees';
			
			$where = "event_id = $event_id";
			if ( $segment === 'paid' ) $where .= " AND payment_status = 'paid'";
			if ( $segment === 'checked-in' ) $where .= " AND status = 'checked-in'";
			
			$attendees = $wpdb->get_col( "SELECT id FROM $table_attendees WHERE $where" );
			
			$count = 0;
			foreach ( $attendees as $att_id ) {
				if ( $comms_service->send_email( $att_id, 'announcement', $subject, $message ) ) {
					$count++;
				}
			}
			
			echo '<div class="updated"><p>' . sprintf( __( 'Bulk announcement sent to %d attendees.', 'event-management-plugin' ), $count ) . '</p></div>';
		}

		$this->render_form();
	}

	private function render_form() {
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		
		echo '<div class="wrap" style="max-width: 800px;">';
		echo '<h1>' . __( 'Event Communications', 'event-management-plugin' ) . '</h1>';
		
		echo '<h2>' . __( 'Send Bulk Announcement', 'event-management-plugin' ) . '</h2>';
		
		echo '<form method="post" action="?post_type=emp_event&page=emp-communications">';
		wp_nonce_field( 'send_bulk_announcement' );
		echo '<input type="hidden" name="emp_send_bulk" value="1" />';
		
		echo '<table class="form-table">';
		
		echo '<tr><th><label>' . __( 'Event', 'event-management-plugin' ) . '</label></th><td><select name="event_id" required>';
		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( $event->ID ) . '">' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Target Segment', 'event-management-plugin' ) . '</label></th><td><select name="segment">';
		echo '<option value="all">' . __( 'All Registered Attendees (Excl. Cancelled)', 'event-management-plugin' ) . '</option>';
		echo '<option value="paid">' . __( 'Paid Attendees Only', 'event-management-plugin' ) . '</option>';
		echo '<option value="checked-in">' . __( 'Checked-in Attendees Only', 'event-management-plugin' ) . '</option>';
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Subject', 'event-management-plugin' ) . '</label></th><td><input type="text" name="subject" class="large-text" required /></td></tr>';
		
		echo '<tr><th><label>' . __( 'Message', 'event-management-plugin' ) . '</label></th><td>';
		wp_editor( '', 'message', array( 'media_buttons' => false, 'textarea_rows' => 10 ) );
		echo '<p class="description">Tokens: {name}, {event}, {badge_link}, {whatsapp_link}</p>';
		echo '</td></tr>';
		
		echo '</table>';
		
		submit_button( __( 'Send Announcement', 'event-management-plugin' ) );
		echo '</form>';
		echo '</div>';
	}
}
