<?php
/**
 * Intercepts HTTP requests to download badges.
 */
class EMP_Badge_Print_Handler {

	public function init() {
		if ( isset( $_GET['emp_action'] ) && $_GET['emp_action'] == 'print_badge' ) {
			if ( ! current_user_can( 'manage_attendees' ) && ! current_user_can( 'manage_event_settings' ) ) {
				wp_die( __( 'You do not have permission to print badges.', 'event-management-plugin' ) );
			}
			
			require_once EMP_PLUGIN_DIR . 'services/class-emp-badge-generator.php';
			$generator = new EMP_Badge_Generator();

			if ( isset( $_GET['attendee_id'] ) ) {
				$attendee_id = intval( $_GET['attendee_id'] );
				$generator->generate_individual( $attendee_id, 'D' ); // D = Download
				exit;
			}
			
			if ( isset( $_GET['ticket_type_id'] ) ) {
				$ticket_type_id = intval( $_GET['ticket_type_id'] );
				$success = $generator->generate_bulk( $ticket_type_id, 'D' );
				if ( ! $success ) {
					wp_die( __( 'No unprinted badges found for this ticket type.', 'event-management-plugin' ) );
				}
				exit;
			}
		}
	}
}
