<?php
/**
 * Handles cleaning up abandoned Gravity Forms entries (stuck in Processing for 24+ hours)
 */
class EMP_GF_Cleanup {

	public function init() {
		// Clear any existing scheduled event so it stops running
		$timestamp = wp_next_scheduled( 'emp_gf_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'emp_gf_daily_cleanup' );
		}
	}

	public function cleanup_abandoned_entries() {
		// Intentionally left blank.
		// We no longer automatically delete 'Pending' or 'Processing' Gravity Form entries,
		// because doing so also wipes out the synced Attendee records for on-site/manual payments.
	}
}
