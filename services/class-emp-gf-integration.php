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
		add_action( 'gform_pre_submission', array( $this, 'clear_attendee_cache' ) );
		
		// Intercept QR custom payment fields
		add_filter( 'gform_entry_created', array( $this, 'intercept_qr_submission' ), 10, 2 );

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

	public function clear_attendee_cache() {
		self::$last_attendee_ids = array();
	}
	
	public function intercept_qr_submission( $entry, $form ) {
		if ( isset( $_POST['emp_qr_transaction_id'] ) && ! empty( $_POST['emp_qr_transaction_id'] ) ) {
			$entry['payment_status'] = 'Pending';
			
			// Get amount from settings
			$settings = get_option( 'emp_qr_payment_settings', array() );
			$form_id = intval( $form['id'] );
			if ( isset( $settings[ $form_id ] ) && ! empty( $settings[ $form_id ]['amount'] ) ) {
				$entry['payment_amount'] = floatval( $settings[ $form_id ]['amount'] );
			}
			
			GFAPI::update_entry( $entry );
			
			gform_update_meta( $entry['id'], 'emp_qr_transaction_id', sanitize_text_field( $_POST['emp_qr_transaction_id'] ) );
			if ( isset( $_POST['emp_qr_screenshot_url'] ) ) {
				gform_update_meta( $entry['id'], 'emp_qr_screenshot_url', esc_url_raw( $_POST['emp_qr_screenshot_url'] ) );
			}
			
			GFAPI::add_note( $entry['id'], 0, 'Event Management', 'QR Payment intercepted. Waiting for manual approval.' );
		}
		return $entry;
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
			// Strict Email Validation
			if ( $field->type === 'email' ) {
				$email_field = $field;
				$email_value = rgpost( 'input_' . str_replace( '.', '_', $field->id ) );
				
				if ( ! empty( $email_value ) ) {
					if ( ! is_email( $email_value ) || ! preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email_value ) ) {
						$validation_result['is_valid'] = false;
						$field->failed_validation = true;
						$field->validation_message = __( 'Please enter a valid, real email address.', 'event-management-plugin' );
					}
				}
			}

			// Strict Phone Validation (At least 10 digits)
			if ( $field->type === 'phone' || strpos( strtolower( $field->label ), 'phone' ) !== false || strpos( strtolower( $field->label ), 'mobile' ) !== false || strpos( strtolower( $field->label ), 'whatsapp' ) !== false ) {
				$phone_val = rgpost( 'input_' . str_replace( '.', '_', $field->id ) );
				if ( ! empty( $phone_val ) ) {
					$digits_only = preg_replace( '/\D/', '', $phone_val );
					if ( strlen( $digits_only ) < 10 ) {
						$validation_result['is_valid'] = false;
						$field->failed_validation = true;
						$field->validation_message = __( 'Please enter a valid phone number with at least 10 digits.', 'event-management-plugin' );
					}
				}
			}
		}

		// Only check duplicate if email is structurally valid
		if ( $email_field && ! empty( $email_value ) && ! $email_field->failed_validation ) {
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
		$notes = GFAPI::get_notes( array( 'entry_id' => $entry_id ) );
		if ( is_array( $notes ) && ! is_wp_error( $notes ) ) {
			foreach ( $notes as $note ) {
				if ( preg_match( '/Created Attendee ID: (\d+)/', $note->value, $matches ) ) {
					$attendee_id = intval( $matches[1] );
					if ( $attendee_id ) {
						global $wpdb;
						
						// Delete related records
						$wpdb->delete( $wpdb->prefix . 'emp_scan_logs', array( 'attendee_id' => $attendee_id ) );
						$wpdb->delete( $wpdb->prefix . 'emp_payments', array( 'attendee_id' => $attendee_id ) );
						$wpdb->delete( $wpdb->prefix . 'emp_communications', array( 'attendee_id' => $attendee_id ) );
						
						// Optional: delete audit logs mentioning this attendee ID to fully clean up
						// Since audit logs are string summaries, we use a LIKE query
						$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emp_audit_logs WHERE target = %s OR summary LIKE %s", 'Attendee', '%Attendee ID ' . $attendee_id . '%' ) );

						// Delete the attendee record itself
						$wpdb->delete( $wpdb->prefix . 'emp_attendees', array( 'id' => $attendee_id ) );
					}
				}
			}
		}
	}

	public function get_calling_code_from_ip( $ip ) {
		// Standard WordPress HTTP request to ip-api
		$response = wp_remote_get( 'http://ip-api.com/json/' . $ip . '?fields=countryCode' );
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['countryCode'] ) ) {
			return '';
		}

		$iso = strtoupper( $data['countryCode'] );

		// Comprehensive list of Country Calling Codes
		$codes = array(
			'AF'=>'+93','AL'=>'+355','DZ'=>'+213','AD'=>'+376','AO'=>'+244','AR'=>'+54','AM'=>'+374','AU'=>'+61',
			'AT'=>'+43','AZ'=>'+994','BH'=>'+973','BD'=>'+880','BY'=>'+375','BE'=>'+32','BZ'=>'+501','BJ'=>'+229',
			'BT'=>'+975','BO'=>'+591','BA'=>'+387','BW'=>'+267','BR'=>'+55','BN'=>'+673','BG'=>'+359','BF'=>'+226',
			'BI'=>'+257','KH'=>'+855','CM'=>'+237','CA'=>'+1','CV'=>'+238','CF'=>'+236','TD'=>'+235','CL'=>'+56',
			'CN'=>'+86','CO'=>'+57','KM'=>'+269','CG'=>'+242','CD'=>'+243','CR'=>'+506','HR'=>'+385','CU'=>'+53',
			'CY'=>'+357','CZ'=>'+420','DK'=>'+45','DJ'=>'+253','DO'=>'+1809','EC'=>'+593','EG'=>'+20','SV'=>'+503',
			'GQ'=>'+240','ER'=>'+291','EE'=>'+372','ET'=>'+251','FJ'=>'+679','FI'=>'+358','FR'=>'+33','GA'=>'+241',
			'GM'=>'+220','GE'=>'+995','DE'=>'+49','GH'=>'+233','GR'=>'+30','GD'=>'+1473','GT'=>'+502','GN'=>'+224',
			'GW'=>'+245','GY'=>'+592','HT'=>'+509','HN'=>'+504','HU'=>'+36','IS'=>'+354','IN'=>'+91','ID'=>'+62',
			'IR'=>'+98','IQ'=>'+964','IE'=>'+353','IL'=>'+972','IT'=>'+39','JM'=>'+1876','JP'=>'+81','JO'=>'+962',
			'KZ'=>'+7','KE'=>'+254','KI'=>'+686','KP'=>'+850','KR'=>'+82','KW'=>'+965','KG'=>'+996','LA'=>'+856',
			'LV'=>'+371','LB'=>'+961','LS'=>'+266','LR'=>'+231','LY'=>'+218','LI'=>'+423','LT'=>'+370','LU'=>'+352',
			'MK'=>'+389','MG'=>'+261','MW'=>'+265','MY'=>'+60','MV'=>'+960','ML'=>'+223','MT'=>'+356','MH'=>'+692',
			'MR'=>'+222','MU'=>'+230','MX'=>'+52','FM'=>'+691','MD'=>'+373','MC'=>'+377','MN'=>'+976','ME'=>'+382',
			'MA'=>'+212','MZ'=>'+258','MM'=>'+95','NA'=>'+264','NR'=>'+674','NP'=>'+977','NL'=>'+31','NZ'=>'+64',
			'NI'=>'+505','NE'=>'+227','NG'=>'+234','NO'=>'+47','OM'=>'+968','PK'=>'+92','PW'=>'+680','PA'=>'+507',
			'PG'=>'+675','PY'=>'+595','PE'=>'+51','PH'=>'+63','PL'=>'+48','PT'=>'+351','QA'=>'+974','RO'=>'+40',
			'RU'=>'+7','RW'=>'+250','WS'=>'+685','SM'=>'+378','ST'=>'+239','SA'=>'+966','SN'=>'+221','RS'=>'+381',
			'SC'=>'+248','SL'=>'+232','SG'=>'+65','SK'=>'+421','SI'=>'+386','SB'=>'+677','SO'=>'+252','ZA'=>'+27',
			'SS'=>'+211','ES'=>'+34','LK'=>'+94','SD'=>'+249','SR'=>'+597','SZ'=>'+268','SE'=>'+46','CH'=>'+41',
			'SY'=>'+963','TJ'=>'+992','TZ'=>'+255','TH'=>'+66','TG'=>'+228','TO'=>'+676','TT'=>'+1868','TN'=>'+216',
			'TR'=>'+90','TM'=>'+993','TV'=>'+688','UG'=>'+256','UA'=>'+380','AE'=>'+971','GB'=>'+44','US'=>'+1',
			'UY'=>'+598','UZ'=>'+998','VU'=>'+678','VE'=>'+58','VN'=>'+84','YE'=>'+967','ZM'=>'+260','ZW'=>'+263'
		);

		return isset( $codes[ $iso ] ) ? $codes[ $iso ] : '';
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
			'code'               => 'INR',
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
				return $entry; // Let the feed's process_feed handle it
			}
		}

		// Check if this form is attached to any event via _emp_gf_form_id
		global $wpdb;
		$event_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_emp_gf_form_id' AND meta_value = %d LIMIT 1", $form['id'] ) );
		
		if ( ! $event_id ) {
			return $entry; // Not linked to any event directly
		}
		
		$payment_status = isset( $entry['payment_status'] ) ? strtolower( $entry['payment_status'] ) : '';
		
		// If it's a QR payment that is pending, always delay attendee creation
		if ( $payment_status === 'pending' ) {
			$is_qr = gform_get_meta( $entry['id'], 'emp_qr_transaction_id' );
			if ( $is_qr ) {
				return $entry;
			}
		}

		$require_payment = get_post_meta( $event_id, '_emp_require_payment', true );
		if ( $require_payment ) {
			if ( ! in_array( $payment_status, array( 'paid', 'approved', 'active' ) ) ) {
				// Skip creation now. It will be created by handle_payment_action when payment completes.
				return $entry;
			}
		}

		// Also get the first available ticket type for this event as default
		$ticket_type_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}emp_ticket_types WHERE event_id = %d ORDER BY price ASC LIMIT 1", $event_id ) );
		if ( ! $ticket_type_id ) $ticket_type_id = 0;

		$table_attendees = $wpdb->prefix . 'emp_attendees';

		// Extract basic fields (Name, Email, Org) automatically by scanning form fields
		$name = '';
		$email = '';
		$org = '';
		$phone = '';

		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'name' ) {
				$first = isset( $entry[ $field->id . '.3' ] ) ? $entry[ $field->id . '.3' ] : '';
				$last = isset( $entry[ $field->id . '.6' ] ) ? $entry[ $field->id . '.6' ] : '';
				$name = trim( $first . ' ' . $last );
			} elseif ( $field->type === 'email' ) {
				$email = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
			} elseif ( $field->type === 'phone' || strpos( strtolower( $field->label ), 'phone' ) !== false || strpos( strtolower( $field->label ), 'mobile' ) !== false || strpos( strtolower( $field->label ), 'whatsapp' ) !== false ) {
				$phone = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
			} elseif ( strpos( strtolower( $field->label ), 'organization' ) !== false || strpos( strtolower( $field->label ), 'company' ) !== false ) {
				$org = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
			}
		}

		if ( empty( $name ) ) $name = 'Attendee ' . $entry['id'];
		if ( empty( $email ) ) $email = 'no-reply@example.com';

		// Format Phone with IP-based Country Code
		if ( ! empty( $phone ) && strpos( $phone, '+' ) !== 0 ) {
			$ip = rgar( $entry, 'ip' );
			if ( ! empty( $ip ) ) {
				$prefix = $this->get_calling_code_from_ip( $ip );
				if ( $prefix ) {
					// strip non-digits first just in case
					$phone = $prefix . ltrim( preg_replace( '/\D/', '', $phone ), '0' );
				}
			}
		}

		$qr_token = wp_generate_password( 32, false, false );

		$data = array(
			'event_id'       => $event_id,
			'ticket_type_id' => $ticket_type_id,
			'name'           => $name,
			'email'          => $email,
			'phone'          => $phone,
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

		return $entry;
	}

	public function append_badge_download( $confirmation, $form, $entry, $ajax ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';

		// Try static IDs first (set by auto_create or feed process_feed)
		$attendee_ids = self::$last_attendee_ids;

		// Fallback: check this entry's notes for "Created Attendee ID"
		// The feed's process_feed writes a note to the GF entry when it creates an attendee
		if ( empty( $attendee_ids ) && class_exists( 'GFAPI' ) && ! empty( $entry ) && ! empty( $entry['id'] ) ) {
			$notes = GFAPI::get_notes( array( 'entry_id' => $entry['id'] ) );
			if ( is_array( $notes ) && ! is_wp_error( $notes ) ) {
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

		// Prevent duplicate injection if GF calls this filter multiple times
		if ( is_string( $confirmation ) && strpos( $confirmation, 'emp-instant-download' ) !== false ) {
			return $confirmation;
		}

		$download_html = '<div class="emp-instant-download" style="margin-top: 20px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa; border-radius: 4px;">';
		$download_html .= '<h4>' . __( 'Download Your Badge(s)', 'event-management-plugin' ) . '</h4>';
		$download_html .= '<p style="font-size: 13px; color: #d63638;"><strong>' . __( 'SECURITY NOTICE: You can only download your badge ONCE.', 'event-management-plugin' ) . '</strong> ' . __( 'We recommend downloading it now. If you choose to share it to WhatsApp instead, the link will expire as soon as it is opened.', 'event-management-plugin' ) . '</p>';
		
		foreach ( $attendee_ids as $attendee_id ) {
			$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT name, qr_token, payment_status FROM $table_attendees WHERE id = %d", $attendee_id ) );
			if ( $attendee ) {
				require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
				$comms = new EMP_Communications();
				$whatsapp_link = $comms->get_whatsapp_link( $attendee_id );
				
				$download_url = home_url( '/?emp_download_badge=' . $attendee->qr_token );
				$download_html .= '<p style="margin-bottom: 10px;"><strong>' . esc_html( $attendee->name ) . '</strong></p>';
				$download_html .= '<p>';
				$download_html .= '<a href="' . esc_url( $download_url ) . '" class="button button-primary" style="background:#0073aa; color:#fff; padding:8px 15px; text-decoration:none; border-radius:4px; display:inline-block; margin-right:10px;" target="_blank">' . __( 'Download PDF (Recommended)', 'event-management-plugin' ) . '</a>';
				$download_html .= '<a href="' . esc_url( $whatsapp_link ) . '" class="button button-secondary" style="background:#25D366; color:#fff; padding:8px 15px; text-decoration:none; border-radius:4px; display:inline-block;" target="_blank">' . __( 'Share Link to WhatsApp', 'event-management-plugin' ) . '</a>';
				$download_html .= '</p>';
			}
		}
		
		$download_html .= '</div>';

		return is_string( $confirmation ) ? $confirmation . $download_html : $download_html;
	}

	public function handle_payment_action( $entry, $action ) {
		// $action['type'] can be 'complete_payment', 'refund_payment', 'fail_payment', etc.
		// We need to find the attendee associated with this entry.
		$attendee_id = 0;
		$notes = GFAPI::get_notes( array( 'entry_id' => $entry['id'] ) );
		if ( is_array( $notes ) && ! is_wp_error( $notes ) ) {
			foreach ( $notes as $note ) {
				if ( preg_match( '/Created Attendee ID: (\d+)/', $note->value, $matches ) ) {
					$attendee_id = intval( $matches[1] );
					break;
				}
			}
		}

		if ( ! $attendee_id ) {
			// If payment is complete and attendee doesn't exist, it might have been delayed!
			if ( $action['type'] === 'complete_payment' ) {
				$form = GFAPI::get_form( $entry['form_id'] );
				$entry['payment_status'] = 'Paid';
				$this->auto_create_attendee_for_linked_form( $entry, $form );
			}
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
