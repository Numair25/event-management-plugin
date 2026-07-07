<?php
/**
 * Admin UI for Walk-in Kiosk Registration.
 */
class EMP_Kiosk_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Walk-in Kiosk', 'event-management-plugin' ),
			__( 'Walk-in Kiosk', 'event-management-plugin' ),
			'manage_attendees', // Registration staff can access
			'emp-kiosk',
			array( $this, 'render_page' )
		);
	}
	
	public function ajax_get_gf_fields() {
		if ( ! current_user_can( 'manage_attendees' ) ) wp_send_json_error();
		$event_id = intval( $_POST['event_id'] );
		$gf_form_id = get_post_meta( $event_id, '_emp_gf_form_id', true );
		
		if ( empty( $gf_form_id ) || ! class_exists( 'GFAPI' ) ) {
			wp_send_json_success( array( 'html' => '', 'gf_form_id' => 0 ) );
		}
		
		$form = GFAPI::get_form( $gf_form_id );
		$html = '';
		if ( $form && isset( $form['fields'] ) ) {
			$html .= '<tr><th colspan="2"><h3 style="margin-top:0;">' . __( 'Registration Form', 'event-management-plugin' ) . '</h3><p class="description">Fields from Gravity Form: ' . esc_html( $form['title'] ) . '</p></th></tr>';
			foreach ( $form['fields'] as $field ) {
				// Skip fields that we manually handle in Kiosk
				$label = esc_html( $field->label );
				$input_name = 'gf_field_' . $field->id;
				
				if ( is_array( $field->inputs ) && ! empty( $field->inputs ) ) {
					$html .= '<tr><th><label>' . $label . '</label></th><td>';
					foreach ( $field->inputs as $input ) {
						if ( isset($input['isHidden']) && $input['isHidden'] ) continue;
						$input_id_safe = str_replace( '.', '_', $input['id'] );
						$html .= '<div style="margin-bottom: 5px;">';
						$html .= '<label style="display:inline-block; width: 120px;">' . esc_html( $input['label'] ) . '</label>';
						$html .= '<input type="text" name="gf_field_' . $input_id_safe . '" class="regular-text" />';
						$html .= '</div>';
					}
					$html .= '</td></tr>';
				} elseif ( $field->type == 'textarea' ) {
					$html .= '<tr><th><label>' . $label . '</label></th><td><textarea name="' . $input_name . '" class="regular-text"></textarea></td></tr>';
				} elseif ( $field->type == 'select' || $field->type == 'radio' ) {
					$html .= '<tr><th><label>' . $label . '</label></th><td><select name="' . $input_name . '"><option value=""></option>';
					if ( is_array( $field->choices ) ) {
						foreach ( $field->choices as $choice ) {
							$html .= '<option value="' . esc_attr( $choice['value'] ) . '">' . esc_html( $choice['text'] ) . '</option>';
						}
					}
					$html .= '</select></td></tr>';
				} elseif ( $field->type != 'fileupload' && $field->type != 'captcha' && $field->type != 'page' ) {
					// Fallback for text, email, phone, website, number, etc.
					$html .= '<tr><th><label>' . $label . '</label></th><td><input type="text" name="' . $input_name . '" class="regular-text" /></td></tr>';
				}
			}
		}
		
		wp_send_json_success( array( 'html' => $html, 'gf_form_id' => $gf_form_id ) );
	}

	public function render_page() {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		if ( isset( $_POST['emp_kiosk_submit'] ) ) {
			check_admin_referer( 'submit_kiosk' );

			$event_id = intval( $_POST['event_id'] );
			$ticket_type_id = intval( $_POST['ticket_type_id'] );
			$name = '';
			$email = '';
			$organization = '';
			$payment_status = sanitize_text_field( $_POST['payment_status'] );
			
			// Build GF Entry first to extract Name/Email/Org
			$gf_form_id = isset( $_POST['emp_kiosk_gf_form_id'] ) ? intval( $_POST['emp_kiosk_gf_form_id'] ) : 0;
			$entry = array();
			if ( $gf_form_id && class_exists( 'GFAPI' ) ) {
				$entry['form_id'] = $gf_form_id;
				$entry['status'] = 'active';
				$entry['source_url'] = admin_url('admin.php?page=emp-kiosk');
				
				foreach ( $_POST as $key => $val ) {
					if ( strpos( $key, 'gf_field_' ) === 0 ) {
						$field_id = str_replace( 'gf_field_', '', $key );
						$field_id = str_replace( '_', '.', $field_id );
						$entry[ $field_id ] = sanitize_text_field( $val );
					}
				}
				
				$form = GFAPI::get_form( $gf_form_id );
				if ( $form && isset( $form['fields'] ) ) {
					foreach ( $form['fields'] as $field ) {
						if ( $field->type === 'name' ) {
							$first = isset($entry[ $field->id . '.3' ]) ? $entry[ $field->id . '.3' ] : '';
							$last = isset($entry[ $field->id . '.6' ]) ? $entry[ $field->id . '.6' ] : '';
							$name = trim( $first . ' ' . $last );
							if ( empty($name) && isset($entry[ $field->id ]) ) {
								$name = $entry[ $field->id ];
							}
						} elseif ( $field->type === 'email' ) {
							$email = isset($entry[ $field->id ]) ? $entry[ $field->id ] : '';
						} elseif ( strpos( strtolower( $field->label ), 'organization' ) !== false || strpos( strtolower( $field->label ), 'company' ) !== false ) {
							$organization = isset($entry[ $field->id ]) ? $entry[ $field->id ] : '';
						}
					}
				}
			}
			
			// Fallbacks if not found
			if ( empty( $name ) && isset($_POST['name']) ) $name = sanitize_text_field($_POST['name']);
			if ( empty( $email ) && isset($_POST['email']) ) $email = sanitize_email($_POST['email']);
			if ( empty( $organization ) ) $organization = isset($_POST['organization']) ? sanitize_text_field($_POST['organization']) : '';
			
			// Validate that we have at least SOME identifying info
			if ( empty( trim( $name ) ) && empty( trim( $email ) ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Error: You must provide at least a Name or an Email address to register an attendee.', 'event-management-plugin' ) . '</p></div>';
				$this->render_form();
				return;
			}
			
			// Set defaults if one is missing but the other is present
			if ( empty( $name ) ) $name = 'Walk-in Attendee';
			if ( empty( $email ) ) $email = 'walkin_' . time() . '@example.com';
			
			// Handle photo upload
			$photo_path = '';
			if ( ! empty( $_FILES['photo']['tmp_name'] ) ) {
				$upload_dir = wp_upload_dir();
				$emp_uploads = trailingslashit( $upload_dir['basedir'] ) . 'emp_photos';
				if ( ! file_exists( $emp_uploads ) ) {
					wp_mkdir_p( $emp_uploads );
				}
				
				$filename = 'walkin_' . time() . '_' . uniqid() . '.jpg';
				$file_path = $emp_uploads . '/' . $filename;
				
				if ( move_uploaded_file( $_FILES['photo']['tmp_name'], $file_path ) ) {
					// Resize & Crop (Target: 300x300 for badge)
					$editor = wp_get_image_editor( $file_path );
					if ( ! is_wp_error( $editor ) ) {
						$editor->resize( 300, 300, true );
						$editor->save( $file_path );
					}
					$photo_path = 'emp_photos/' . $filename;
				}
			}

			$qr_token = wp_generate_password( 32, false, false );

			// Insert Attendee
			$data = array(
				'event_id'       => $event_id,
				'ticket_type_id' => $ticket_type_id,
				'name'           => $name,
				'email'          => $email,
				'organization'   => $organization,
				'photo_path'     => $photo_path,
				'qr_token'       => $qr_token,
				'status'         => 'registered', // Walk-ins bypass waitlist usually, or we can check capacity
				'payment_status' => $payment_status,
				'source'         => 'walk-in',
				'printed_status' => 1 // Will be printed instantly
			);

			$wpdb->insert( $table_attendees, $data );
			$attendee_id = $wpdb->insert_id;
			
			// Save GF Entry
			if ( ! empty( $entry['form_id'] ) ) {
				$entry_id = GFAPI::add_entry( $entry );
				if ( ! is_wp_error( $entry_id ) ) {
					GFAPI::add_note( $entry_id, 1, 'Event Management Plugin', "Created Attendee ID: {$attendee_id}" );
				}
			}

			// Log payment if paid/comp
			if ( $payment_status === 'paid' || $payment_status === 'comp' ) {
				$table_payments = $wpdb->prefix . 'emp_payments';
				$wpdb->insert( $table_payments, array(
					'attendee_id' => $attendee_id,
					'amount'      => 0,
					'method'      => 'walk-in-' . $payment_status,
					'reference'   => 'Desk Registration',
				) );
			}

			// Instant Print Redirect
			$print_url = admin_url( 'admin.php?emp_action=print_badge&attendee_id=' . $attendee_id );
			
			echo '<div class="updated"><p>' . __( 'Walk-in registered successfully! Badge is generating...', 'event-management-plugin' ) . '</p></div>';
			echo '<script>window.open("' . esc_url_raw( $print_url ) . '", "_blank");</script>';
		}

		$this->render_form();
	}

	private function render_form() {
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		
		global $wpdb;
		$table_tickets = $wpdb->prefix . 'emp_ticket_types';
		$tickets = $wpdb->get_results( "SELECT * FROM $table_tickets" );
		
		echo '<div class="wrap" style="max-width: 600px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
		echo '<h1>' . __( 'On-Site Walk-In Registration', 'event-management-plugin' ) . '</h1>';
		
		echo '<form method="post" enctype="multipart/form-data" action="">';
		wp_nonce_field( 'submit_kiosk' );
		echo '<input type="hidden" name="emp_kiosk_submit" value="1" />';
		
		echo '<table class="form-table">';
		
		echo '<tr><th><label>' . __( 'Event', 'event-management-plugin' ) . '</label></th><td><select name="event_id" style="width:100%;" required>';
		foreach ( $events as $event ) {
			echo '<option value="' . esc_attr( $event->ID ) . '">' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Ticket Type', 'event-management-plugin' ) . '</label></th><td><select name="ticket_type_id" style="width:100%;" required>';
		foreach ( $tickets as $ticket ) {
			$event_title = get_the_title( $ticket->event_id );
			echo '<option value="' . esc_attr( $ticket->id ) . '">' . esc_html( $event_title . ' - ' . $ticket->name ) . ' (' . $ticket->price . ')</option>';
		}
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Payment Received', 'event-management-plugin' ) . '</label></th><td>';
		echo '<select name="payment_status" style="width:100%;">';
		echo '<option value="paid">' . __( 'Paid at Desk (Cash/Card Terminal)', 'event-management-plugin' ) . '</option>';
		echo '<option value="pending">' . __( 'Pending (Pay Later)', 'event-management-plugin' ) . '</option>';
		echo '<option value="comp">' . __( 'Complimentary / VIP', 'event-management-plugin' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th><label>' . __( 'Photo (Webcam/Upload)', 'event-management-plugin' ) . '</label></th><td>';
		echo '<input type="file" name="photo" accept="image/*" capture="environment" />';
		echo '<p class="description">' . __( 'On tablets, this will open the camera app.', 'event-management-plugin' ) . '</p>';
		echo '</td></tr>';
		
		echo '<tbody id="dynamic-gf-fields"></tbody>';
		
		echo '</table>';
		
		echo '<input type="hidden" name="emp_kiosk_gf_form_id" id="emp_kiosk_gf_form_id" value="0" />';
		
		echo '<p class="submit"><input type="submit" class="button button-primary button-hero" value="' . __( 'Register & Print Badge', 'event-management-plugin' ) . '" style="width:100%;" /></p>';
		
		echo '</form></div>';
		
		// Add Javascript to handle AJAX fetching of GF fields
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			var eventSelect = document.querySelector('select[name="event_id"]');
			var dynamicFields = document.getElementById('dynamic-gf-fields');
			var gfFormIdInput = document.getElementById('emp_kiosk_gf_form_id');
			
			function fetchGfFields() {
				var eventId = eventSelect.value;
				if (!eventId) return;
				
				var data = new URLSearchParams();
				data.append('action', 'emp_get_gf_fields');
				data.append('event_id', eventId);
				
				fetch(ajaxurl, {
					method: 'POST',
					body: data
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						dynamicFields.innerHTML = data.data.html;
						gfFormIdInput.value = data.data.gf_form_id;
					}
				});
			}
			
			eventSelect.addEventListener('change', fetchGfFields);
			fetchGfFields(); // Fetch on load for the initially selected event
		});
		</script>
		<?php
	}
}
