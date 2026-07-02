<?php
/**
 * Integrates Gravity Forms with the plugin.
 */
class EMP_GF_Integration {

	public static $last_attendee_ids = array();

	public function init() {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}
		
		require_once EMP_PLUGIN_DIR . 'services/class-emp-gf-addon.php';
		
		GFAddOn::register( 'EMP_GF_Addon' );

		// Hook into Gravity Forms payment actions
		add_action( 'gform_post_payment_action', array( $this, 'handle_payment_action' ), 10, 2 );

		// Hook into confirmation to add instant download link
		add_filter( 'gform_confirmation', array( $this, 'append_badge_download' ), 10, 4 );

		// Auto-create attendee if form is linked directly to an event (no feed required)
		add_action( 'gform_entry_post_save', array( $this, 'auto_create_attendee_for_linked_form' ), 5, 2 );

		// Currency settings
		add_filter( 'gform_currencies', array( $this, 'add_inr_currency' ) );
		if ( get_option( 'emp_gf_force_inr', 0 ) ) {
			add_filter( 'gform_currency', array( $this, 'set_inr_currency' ) );
		}

		// Duplicate validation
		add_filter( 'gform_validation', array( $this, 'validate_duplicate_attendee' ) );

		// Entry deletion sync
		add_action( 'gform_delete_entry', array( $this, 'sync_delete_entry' ) );
	}

	public function validate_duplicate_attendee( $validation_result ) {
		$form = $validation_result['form'];
		
		global $wpdb;
		$event_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_emp_gf_form_id' AND meta_value = %d LIMIT 1", $form['id'] ) );
		
		if ( ! $event_id && class_exists( 'EMP_GF_Addon' ) ) {
			$addon = EMP_GF_Addon::get_instance();
			$feeds = $addon->get_active_feeds( $form['id'] );
			if ( ! empty( $feeds ) ) {
				$event_id = rgar( $feeds[0]['meta'], 'event_id' );
			}
		}

		if ( ! $event_id ) {
			return $validation_result;
		}

		$email_field = null;
		$email_value = '';
		foreach ( $form['fields'] as &$field ) {
			if ( $field->type === 'email' ) {
				$email_field = $field;
				$email_value = rgpost( 'input_' . str_replace( '.', '_', $field->id ) );
				break;
			}
		}

		if ( $email_field && ! empty( $email_value ) ) {
			// Check GF for existing entry with this email in this form
			$search_criteria = array(
				'status' => 'active',
				'field_filters' => array(
					array(
						'key' => strval( $email_field->id ),
						'value' => $email_value,
						'operator' => 'is'
					)
				)
			);
			
			$entries = GFAPI::get_entries( $form['id'], $search_criteria, null, array( 'offset' => 0, 'page_size' => 1 ) );

			if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) {
				$validation_result['is_valid'] = false;
				$email_field->failed_validation = true;
				$email_field->validation_message = __( 'This email is already registered for this event.', 'event-management-plugin' );
			}
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	public function sync_delete_entry( $entry_id ) {
		if ( ! class_exists( 'GFAPI' ) ) return;
		$notes = GFAPI::get_notes( $entry_id );
		foreach ( $notes as $note ) {
			if ( preg_match( '/Created Attendee ID: (\d+)/', $note->value, $matches ) ) {
				$attendee_id = intval( $matches[1] );
				global $wpdb;
				// Delete scan logs
				$wpdb->delete( $wpdb->prefix . 'emp_scan_logs', array( 'attendee_id' => $attendee_id ) );
				// Delete payments
				$wpdb->delete( $wpdb->prefix . 'emp_payments', array( 'attendee_id' => $attendee_id ) );
				// Delete attendee
				$wpdb->delete( $wpdb->prefix . 'emp_attendees', array( 'id' => $attendee_id ) );
			}
		}
	}

	public function add_inr_currency( $currencies ) {
		$currencies['INR'] = array(
			'name'               => __( 'Indian Rupee', 'event-management-plugin' ),
			'symbol_left'        => '₹',
			'symbol_right'       => '',
			'symbol_padding'     => ' ',
			'thousand_separator' => ',',
			'decimal_separator'  => '.',
			'decimals'           => 2,
		);
		return $currencies;
	}

	public function set_inr_currency( $currency ) {
		return 'INR';
	}

	public function auto_create_attendee_for_linked_form( $entry, $form ) {
		// If the GF Addon has active feeds for this form, skip auto-creation.
		// The feeds handle attendee creation with proper ticket types from conditional logic.
		if ( class_exists( 'EMP_GF_Addon' ) ) {
			$addon = EMP_GF_Addon::get_instance();
			$feeds = $addon->get_active_feeds( $form['id'] );
			if ( ! empty( $feeds ) ) {
				return; // Let the feed's process_feed handle it
			}
		}

		// Check if this form is attached to any event via _emp_gf_form_id
		global $wpdb;
		$event_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_emp_gf_form_id' AND meta_value = %d LIMIT 1", $form['id'] ) );
		
		if ( ! $event_id ) {
			return; // Not linked to any event directly
		}

		// Also get the first available ticket type for this event as default
		$ticket_type_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}emp_ticket_types WHERE event_id = %d ORDER BY price ASC LIMIT 1", $event_id ) );
		if ( ! $ticket_type_id ) $ticket_type_id = 0;

		$table_attendees = $wpdb->prefix . 'emp_attendees';

		// Extract basic fields (Name, Email, Org) automatically by scanning form fields
		$name = '';
		$email = '';
		$org = '';

		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'name' ) {
				$first = isset( $entry[ $field->id . '.3' ] ) ? $entry[ $field->id . '.3' ] : '';
				$last = isset( $entry[ $field->id . '.6' ] ) ? $entry[ $field->id . '.6' ] : '';
				$name = trim( $first . ' ' . $last );
			} elseif ( $field->type === 'email' ) {
				$email = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
			} elseif ( strpos( strtolower( $field->label ), 'organization' ) !== false || strpos( strtolower( $field->label ), 'company' ) !== false ) {
				$org = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
			}
		}

		if ( empty( $name ) ) $name = 'Attendee ' . $entry['id'];
		if ( empty( $email ) ) $email = 'no-reply@example.com';

		$qr_token = wp_generate_password( 32, false, false );

		$data = array(
			'event_id'       => $event_id,
			'ticket_type_id' => $ticket_type_id,
			'name'           => $name,
			'email'          => $email,
			'organization'   => $org,
			'photo_path'     => '',
			'qr_token'       => $qr_token,
			'status'         => 'registered',
			'payment_status' => 'paid', // Default to paid if no payment gateway logic caught it
			'source'         => 'online',
		);

		$wpdb->insert( $table_attendees, $data );
		$attendee_id = $wpdb->insert_id;

		if ( $attendee_id ) {
			self::$last_attendee_ids[] = $attendee_id;
			GFAPI::add_note( $entry['id'], 0, 'Event Management', sprintf( 'Created Attendee ID: %d with status: %s', $attendee_id, 'registered' ) );
			
			require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
			$comms = new EMP_Communications();
			$comms->send_email( $attendee_id, 'confirmation' );
		}
	}

	public function append_badge_download( $confirmation, $form, $entry, $ajax ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		// Try static IDs first (set by auto_create or feed process_feed)
		$attendee_ids = self::$last_attendee_ids;

		// Fallback: check this entry's notes for "Created Attendee ID"
		// The feed's process_feed writes a note to the GF entry when it creates an attendee
		if ( empty( $attendee_ids ) && class_exists( 'GFAPI' ) ) {
			$notes = GFAPI::get_notes( $entry['id'] );
			if ( ! is_wp_error( $notes ) ) {
				foreach ( $notes as $note ) {
					if ( preg_match( '/Created Attendee ID: (\d+)/', $note->value, $matches ) ) {
						$attendee_ids[] = intval( $matches[1] );
					}
				}
			}
		}

		if ( empty( $attendee_ids ) ) {
			return $confirmation;
		}

		$download_html = '<div class="emp-instant-download" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa; border-radius: 4px;">';
		$download_html .= '<h4>' . __( 'Download Your Badge(s)', 'event-management-plugin' ) . '</h4>';
		
		foreach ( $attendee_ids as $attendee_id ) {
			$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT name, qr_token, payment_status FROM $table_attendees WHERE id = %d", $attendee_id ) );
			if ( $attendee ) {
				require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
				$comms = new EMP_Communications();
				$whatsapp_link = $comms->get_whatsapp_link( $attendee_id );
				
				if ( $attendee->payment_status === 'paid' || $attendee->payment_status === 'comp' ) {
					$download_url = home_url( '/?emp_download_badge=' . $attendee->qr_token );
					$download_html .= '<p style="margin-bottom: 10px;"><strong>' . esc_html( $attendee->name ) . '</strong></p>';
					$download_html .= '<p>';
					$download_html .= '<a href="' . esc_url( $download_url ) . '" class="button button-primary" style="background:#0073aa; color:#fff; padding:8px 15px; text-decoration:none; border-radius:4px; display:inline-block; margin-right:10px;" target="_blank">' . __( 'Download PDF', 'event-management-plugin' ) . '</a>';
					$download_html .= '<a href="' . esc_url( $whatsapp_link ) . '" class="button button-secondary" style="background:#25D366; color:#fff; padding:8px 15px; text-decoration:none; border-radius:4px; display:inline-block;" target="_blank">' . __( 'Send to WhatsApp', 'event-management-plugin' ) . '</a>';
					$download_html .= '</p>';
				} else {
					$download_html .= '<p><strong>' . esc_html( $attendee->name ) . ':</strong> ' . __( 'Your badge will be available to download once payment is completed.', 'event-management-plugin' ) . '</p>';
				}
			}
		}
		
		$download_html .= '</div>';

		if ( is_string( $confirmation ) ) {
			return $confirmation . $download_html;
		} elseif ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
			return $confirmation;
		}

		return $confirmation;
	}

	public function handle_payment_action( $entry, $action ) {
		// $action['type'] can be 'complete_payment', 'refund_payment', 'fail_payment', etc.
		// We need to find the attendee associated with this entry.
		
		$notes = GFAPI::get_notes( $entry['id'] );
		$attendee_id = 0;
		foreach ( $notes as $note ) {
			if ( preg_match( '/Created Attendee ID: (\d+)/', $note->value, $matches ) ) {
				$attendee_id = intval( $matches[1] );
				break;
			}
		}

		if ( ! $attendee_id ) {
			return;
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$table_payments = $wpdb->prefix . 'emp_payments';

		switch ( $action['type'] ) {
			case 'complete_payment':
				$wpdb->update(
					$table_attendees,
					array( 'payment_status' => 'paid' ),
					array( 'id' => $attendee_id )
				);

				// Trigger confirmation email if payment was pending and now paid
				if ( $attendee_id ) {
					require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
					$comms = new EMP_Communications();
					$comms->send_email( $attendee_id, 'confirmation' );
				}

				// Log Payment
				$wpdb->insert( $table_payments, array(
					'attendee_id' => $attendee_id,
					'amount'      => floatval( $action['amount'] ),
					'method'      => 'gateway',
					'reference'   => $action['transaction_id'],
				) );
				break;

			case 'refund_payment':
				// Invalidate token on refund
				$wpdb->update( $table_attendees, array( 
					'payment_status' => 'refunded',
					'qr_token'       => 'invalidated_' . time() . '_' . $attendee_id
				), array( 'id' => $attendee_id ) );
				
				$wpdb->insert( $table_payments, array(
					'attendee_id' => $attendee_id,
					'amount'      => -floatval( $action['amount'] ),
					'method'      => 'gateway_refund',
					'reference'   => $action['transaction_id'],
				) );
				break;
		}
	}
}
