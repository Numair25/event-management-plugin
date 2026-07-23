<?php
require_once( dirname(__DIR__, 3) . '/wp-load.php' ); // Load WP core
global $wpdb;
$table_attendees = $wpdb->prefix . 'emp_attendees';

$log = "Starting sync...\n";
if ( ! class_exists( 'GFAPI' ) ) {
	file_put_contents('sync.log', "Gravity Forms not active.\n");
	exit;
}

$attendees = $wpdb->get_results( "SELECT * FROM $table_attendees WHERE phone IS NULL OR phone = ''" );
$log .= "Found " . count($attendees) . " attendees without phone numbers.\n";

$updated_count = 0;
foreach ( $attendees as $att ) {
	$event_id = $att->event_id;
	$email = $att->email;
	$form_id = get_post_meta( $event_id, '_emp_gf_form_id', true );
	if ( ! $form_id ) continue;
	
	$entries = GFAPI::get_entries( $form_id, array( 'status' => 'active' ), null, array( 'offset' => 0, 'page_size' => 200 ) );
	$phone = '';
	if ( ! is_wp_error( $entries ) && ! empty( $entries ) ) {
		$form = GFAPI::get_form( $form_id );
		$phone_field_id = null;
		$email_field_id = null;
		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'phone' || strpos( strtolower( $field->label ), 'phone' ) !== false ) {
				$phone_field_id = strval( $field->id );
			}
			if ( $field->type === 'email' || strpos( strtolower( $field->label ), 'email' ) !== false ) {
				$email_field_id = strval( $field->id );
			}
		}
		if ( $phone_field_id && $email_field_id ) {
			foreach ( $entries as $entry ) {
				if ( strtolower( trim( rgar( $entry, $email_field_id ) ) ) === strtolower( trim( $email ) ) ) {
					$phone = rgar( $entry, $phone_field_id );
					break;
				}
			}
		}
	}
	
	if ( ! empty( $phone ) ) {
		$wpdb->update( $table_attendees, array( 'phone' => $phone ), array( 'id' => $att->id ) );
		$updated_count++;
		$log .= "Updated Attendee ID {$att->id} with phone: {$phone}\n";
	}
}

$log .= "Done! Updated {$updated_count} attendees.\n";
file_put_contents('sync.log', $log);
