<?php
/**
 * Admin UI for CSV Import.
 */
class EMP_Import_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Import Attendees', 'event-management-plugin' ),
			__( 'Import Attendees', 'event-management-plugin' ),
			'manage_attendees',
			'emp-import',
			array( $this, 'render_page' )
		);
		
		add_action( 'wp_ajax_emp_get_ticket_types', array( $this, 'ajax_get_ticket_types' ) );
	}
	
	public function ajax_get_ticket_types() {
		if ( ! current_user_can( 'manage_attendees' ) ) wp_send_json_error();
		
		$event_id = intval( $_POST['event_id'] );
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM $table_name WHERE event_id = %d", $event_id ), ARRAY_A );
		
		wp_send_json_success( $results );
	}

	public function render_page() {
		if ( isset( $_POST['emp_import_csv'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'emp_import_action' ) ) {
			$this->handle_upload();
		}

		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		?>
		<div class="wrap">
			<h1><?php _e( 'Import Attendees via CSV', 'event-management-plugin' ); ?></h1>
			<p><?php _e( 'Upload a CSV file to bulk register attendees. The CSV must have headers exactly matching: <strong>Name, Email, Phone, Organization</strong>.', 'event-management-plugin' ); ?></p>
			
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'emp_import_action' ); ?>
				<input type="hidden" name="emp_import_csv" value="1" />
				
				<table class="form-table">
					<tr>
						<th><label><?php _e( 'Select Event', 'event-management-plugin' ); ?></label></th>
						<td>
							<select name="event_id" id="event_id" required>
								<option value="">-- <?php _e( 'Select an Event', 'event-management-plugin' ); ?> --</option>
								<?php foreach ( $events as $event ) : ?>
									<option value="<?php echo esc_attr( $event->ID ); ?>"><?php echo esc_html( $event->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php _e( 'Select Ticket Type', 'event-management-plugin' ); ?></label></th>
						<td>
							<select name="ticket_type_id" id="ticket_type_id" required>
								<option value="">-- <?php _e( 'Select an Event First', 'event-management-plugin' ); ?> --</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php _e( 'Payment Status', 'event-management-plugin' ); ?></label></th>
						<td>
							<select name="payment_status">
								<option value="paid"><?php _e( 'Paid', 'event-management-plugin' ); ?></option>
								<option value="comp"><?php _e( 'Complimentary (Comp)', 'event-management-plugin' ); ?></option>
								<option value="pending"><?php _e( 'Pending', 'event-management-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php _e( 'CSV File', 'event-management-plugin' ); ?></label></th>
						<td>
							<input type="file" name="csv_file" accept=".csv" required />
						</td>
					</tr>
				</table>
				
				<?php submit_button( __( 'Import CSV', 'event-management-plugin' ) ); ?>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($){
			$('#event_id').change(function(){
				var event_id = $(this).val();
				if(!event_id) {
					$('#ticket_type_id').html('<option value="">-- Select an Event First --</option>');
					return;
				}
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: { action: 'emp_get_ticket_types', event_id: event_id },
					success: function(response){
						if(response.success){
							var html = '';
							$.each(response.data, function(i, item){
								html += '<option value="'+item.id+'">'+item.name+'</option>';
							});
							$('#ticket_type_id').html(html);
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	private function handle_upload() {
		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			echo '<div class="error"><p>' . __( 'No file uploaded.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		$event_id = intval( $_POST['event_id'] );
		$ticket_type_id = intval( $_POST['ticket_type_id'] );
		$payment_status = sanitize_text_field( $_POST['payment_status'] );

		if ( ! $event_id || ! $ticket_type_id ) {
			echo '<div class="error"><p>' . __( 'Event and Ticket Type are required.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		$handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
		if ( ! $handle ) {
			echo '<div class="error"><p>' . __( 'Could not read file.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		$headers = fgetcsv( $handle );
		$header_map = array();
		foreach ( $headers as $index => $col ) {
			$col = strtolower( trim( $col ) );
			if ( strpos( $col, 'name' ) !== false ) $header_map['name'] = $index;
			if ( strpos( $col, 'email' ) !== false ) $header_map['email'] = $index;
			if ( strpos( $col, 'phone' ) !== false ) $header_map['phone'] = $index;
			if ( strpos( $col, 'org' ) !== false ) $header_map['organization'] = $index;
		}

		if ( ! isset( $header_map['name'] ) || ! isset( $header_map['email'] ) ) {
			echo '<div class="error"><p>' . __( 'CSV must contain at least a Name and Email column.', 'event-management-plugin' ) . '</p></div>';
			return;
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$count = 0;

		require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
		$comms = new EMP_Communications();

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$name = isset( $header_map['name'] ) && isset( $data[$header_map['name']] ) ? trim( $data[$header_map['name']] ) : '';
			$email = isset( $header_map['email'] ) && isset( $data[$header_map['email']] ) ? trim( $data[$header_map['email']] ) : '';
			$phone = isset( $header_map['phone'] ) && isset( $data[$header_map['phone']] ) ? trim( $data[$header_map['phone']] ) : '';
			$organization = isset( $header_map['organization'] ) && isset( $data[$header_map['organization']] ) ? trim( $data[$header_map['organization']] ) : '';

			if ( empty( $name ) || empty( $email ) ) continue;

			// Skip if email exists for this event
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_attendees WHERE event_id = %d AND email = %s", $event_id, $email ) );
			if ( $exists ) continue;

			$qr_token = wp_generate_password( 32, false, false );

			$attendee_data = array(
				'event_id'       => $event_id,
				'ticket_type_id' => $ticket_type_id,
				'name'           => $name,
				'email'          => $email,
				'phone'          => $phone,
				'organization'   => $organization,
				'qr_token'       => $qr_token,
				'status'         => 'registered',
				'payment_status' => $payment_status,
				'source'         => 'import',
			);

			$wpdb->insert( $table_attendees, $attendee_data );
			$attendee_id = $wpdb->insert_id;

			if ( $attendee_id ) {
				$count++;
				// Send confirmation immediately since it's an import
				$comms->send_email( $attendee_id, 'confirmation' );
			}
		}

		fclose( $handle );

		echo '<div class="updated"><p>' . sprintf( __( 'Successfully imported %d attendees.', 'event-management-plugin' ), $count ) . '</p></div>';
	}
}
