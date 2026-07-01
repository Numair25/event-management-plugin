<?php
/**
 * Gravity Forms Add-On for Event Management Plugin
 */

if ( ! class_exists( 'GFForms' ) ) {
	return;
}

GFForms::include_feed_addon_framework();

class EMP_GF_Addon extends GFFeedAddOn {

	protected $_version = EMP_VERSION;
	protected $_min_gravityforms_version = '2.4';
	protected $_slug = 'event-management-plugin';
	protected $_path = 'event-management-plugin/event-management-plugin.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Event Management & Check-In';
	protected $_short_title = 'Event Management';
	protected $_async_feed_processing = false;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new EMP_GF_Addon();
		}
		return self::$_instance;
	}

	public function init() {
		parent::init();
		// Move feed processing to entry creation so it runs BEFORE gform_confirmation
		remove_action( 'gform_after_submission', array( $this, 'maybe_process_feed' ), 10, 2 );
		add_action( 'gform_entry_post_save', array( $this, 'maybe_process_feed' ), 10, 2 );
	}

	public function feed_settings_fields() {
		return array(
			array(
				'title'  => __( 'Event Settings', 'event-management-plugin' ),
				'fields' => array(
					array(
						'name'    => 'event_id',
						'label'   => __( 'Select Event', 'event-management-plugin' ),
						'type'    => 'select',
						'choices' => $this->get_events_choices(),
						'tooltip' => __( 'Select the event this form is registering for.', 'event-management-plugin' ),
					),
					array(
						'name'    => 'ticket_type_id',
						'label'   => __( 'Select Ticket Type', 'event-management-plugin' ),
						'type'    => 'select',
						'choices' => $this->get_ticket_types_choices(),
						'tooltip' => __( 'Select the ticket type for attendees created from this feed.', 'event-management-plugin' ),
					),
				),
			),
			array(
				'title'  => __( 'Field Mapping', 'event-management-plugin' ),
				'fields' => array(
					array(
						'name'      => 'mappedFields',
						'label'     => __( 'Map Fields', 'event-management-plugin' ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'     => 'name',
								'label'    => __( 'Full Name', 'event-management-plugin' ),
								'required' => true,
							),
							array(
								'name'     => 'email',
								'label'    => __( 'Email Address', 'event-management-plugin' ),
								'required' => true,
							),
							array(
								'name'     => 'phone',
								'label'    => __( 'Phone Number', 'event-management-plugin' ),
								'required' => false,
							),
							array(
								'name'     => 'organization',
								'label'    => __( 'Organization', 'event-management-plugin' ),
								'required' => false,
							),
							array(
								'name'     => 'photo',
								'label'    => __( 'Photo (File Upload)', 'event-management-plugin' ),
								'required' => false,
							),
						),
					),
				),
			),
			array(
				'title'  => __( 'Options', 'event-management-plugin' ),
				'fields' => array(
					array(
						'name'           => 'condition',
						'label'          => __( 'Conditional Logic', 'event-management-plugin' ),
						'type'           => 'feed_condition',
						'checkbox_label' => __( 'Enable Condition', 'event-management-plugin' ),
						'instructions'   => __( 'Process this feed if', 'event-management-plugin' ),
					),
				),
			),
		);
	}

	public function feed_list_columns() {
		return array(
			'feedName' => __( 'Name', 'event-management-plugin' ),
			'event_id' => __( 'Event', 'event-management-plugin' ),
		);
	}

	private function get_events_choices() {
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		$choices = array( array( 'label' => __( 'Select an Event', 'event-management-plugin' ), 'value' => '' ) );
		foreach ( $events as $event ) {
			$choices[] = array( 'label' => $event->post_title, 'value' => $event->ID );
		}
		return $choices;
	}

	private function get_ticket_types_choices() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		// We might want to filter by event_id in a real UI, but for now we list all with event title
		$results = $wpdb->get_results( "SELECT * FROM $table_name" );
		$choices = array( array( 'label' => __( 'Select a Ticket Type', 'event-management-plugin' ), 'value' => '' ) );
		if ( $results ) {
			foreach ( $results as $row ) {
				$event_title = get_the_title( $row->event_id );
				$choices[] = array( 'label' => $event_title . ' - ' . $row->name, 'value' => $row->id );
			}
		}
		return $choices;
	}

	/**
	 * Process the feed
	 */
	public function process_feed( $feed, $entry, $form ) {
		$event_id = rgar( $feed['meta'], 'event_id' );
		$ticket_type_id = rgar( $feed['meta'], 'ticket_type_id' );
		
		if ( empty( $event_id ) || empty( $ticket_type_id ) ) {
			return;
		}

		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );
		
		$name_val = $this->get_field_value( $form, $entry, rgar( $field_map, 'name' ) );
		$email_val = $this->get_field_value( $form, $entry, rgar( $field_map, 'email' ) );
		$phone_val = $this->get_field_value( $form, $entry, rgar( $field_map, 'phone' ) );
		$org_val = $this->get_field_value( $form, $entry, rgar( $field_map, 'organization' ) );
		$photo_val = $this->get_field_value( $form, $entry, rgar( $field_map, 'photo' ) );

		// Check if Name is a serialized array (Gravity Forms List Field)
		$names = is_serialized( $name_val ) ? maybe_unserialize( $name_val ) : ( is_array( $name_val ) ? $name_val : array( $name_val ) );
		$emails = is_serialized( $email_val ) ? maybe_unserialize( $email_val ) : ( is_array( $email_val ) ? $email_val : array( $email_val ) );
		
		// Payment logic for Free Forms
		$total = (float) rgar( $entry, 'payment_amount' );
		if ( $total == 0 ) {
			// Try to calculate from form if payment_amount isn't set yet
			$total = GFCommon::get_order_total( $form, $entry );
		}
		
		$payment_status = 'pending';
		if ( $total == 0 || strtolower( rgar( $entry, 'payment_status' ) ) === 'paid' ) {
			$payment_status = ( $total == 0 ) ? 'comp' : 'paid';
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		// If multiple names, process as Group Registration
		foreach ( $names as $index => $name ) {
			if ( empty( $name ) ) continue;
			
			$email = isset( $emails[$index] ) ? $emails[$index] : ( is_array($emails) && !empty($emails) ? $emails[0] : '' );
			$phone = is_array( $phone_val ) ? ( isset( $phone_val[$index] ) ? $phone_val[$index] : $phone_val[0] ) : $phone_val;
			$organization = is_array( $org_val ) ? ( isset( $org_val[$index] ) ? $org_val[$index] : $org_val[0] ) : $org_val;
			$photo_url = is_array( $photo_val ) ? ( isset( $photo_val[$index] ) ? $photo_val[$index] : $photo_val[0] ) : $photo_val;

			// Handle Photo Download & Validation
			$photo_path = '';
			if ( ! empty( $photo_url ) ) {
				$photo_path = $this->process_photo( $photo_url, $entry['id'] );
			}

			// Generate QR Token
			$qr_token = wp_generate_password( 32, false, false );

			// Waitlist Logic / Capacity Limits
			$status = 'registered';
			$capacity = get_post_meta( $event_id, '_emp_capacity', true );
			if ( ! empty( $capacity ) && $capacity > 0 ) {
				$current_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_attendees WHERE event_id = %d AND status IN ('registered', 'checked-in')", $event_id ) );
				if ( $current_count >= $capacity ) {
					$status = 'waitlisted';
				}
			}

			$data = array(
				'event_id'       => $event_id,
				'ticket_type_id' => $ticket_type_id,
				'name'           => $name,
				'email'          => $email,
				'phone'          => $phone,
				'organization'   => $organization,
				'photo_path'     => $photo_path,
				'qr_token'       => $qr_token,
				'status'         => $status,
				'payment_status' => $payment_status,
				'source'         => 'online',
			);

			$wpdb->insert( $table_attendees, $data );
			$attendee_id = $wpdb->insert_id;

			if ( $attendee_id ) {
				if ( class_exists( 'EMP_GF_Integration' ) ) {
					EMP_GF_Integration::$last_attendee_ids[] = $attendee_id;
				}
				// Trigger confirmation email
				require_once EMP_PLUGIN_DIR . 'services/class-emp-communications.php';
				$comms = new EMP_Communications();
				$comms->send_email( $attendee_id, 'confirmation' );
				
				// Add Note to Entry
				GFAPI::add_note( $entry['id'], 0, 'Event Management', sprintf( 'Created Attendee ID: %d with status: %s', $attendee_id, $status ) );
			}
		}
		
		return $entry;
	}

	private function process_photo( $photo_url, $entry_id ) {
		// Only proceed if the URL is valid
		if ( filter_var( $photo_url, FILTER_VALIDATE_URL ) === false ) {
			return '';
		}

		$upload_dir = wp_upload_dir();
		$emp_uploads = trailingslashit( $upload_dir['basedir'] ) . 'emp_photos';
		if ( ! file_exists( $emp_uploads ) ) {
			wp_mkdir_p( $emp_uploads );
		}

		// Download the image
		$response = wp_remote_get( $photo_url );
		if ( is_wp_error( $response ) ) {
			return '';
		}

		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			return '';
		}

		$filename = 'attendee_' . $entry_id . '_' . uniqid() . '.jpg';
		$file_path = $emp_uploads . '/' . $filename;
		
		file_put_contents( $file_path, $image_data );

		// Resize & Crop (Target: 300x300 for badge)
		$editor = wp_get_image_editor( $file_path );
		if ( ! is_wp_error( $editor ) ) {
			$editor->resize( 300, 300, true );
			$editor->save( $file_path );
		}

		// Return relative path
		return 'emp_photos/' . $filename;
	}
}
