<?php
/**
 * Handles cleaning up abandoned Gravity Forms entries (stuck in Processing for 24+ hours)
 */
class EMP_GF_Cleanup {

	public function init() {
		// Register WP Cron hook
		add_action( 'emp_gf_daily_cleanup', array( $this, 'cleanup_abandoned_entries' ) );

		// Schedule the event if not already scheduled
		if ( ! wp_next_scheduled( 'emp_gf_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'emp_gf_daily_cleanup' );
		}
	}

	public function cleanup_abandoned_entries() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		global $wpdb;

		// We look for entries with payment_status = 'Processing' or 'Pending' created more than 24 hours ago.
		// Since we want to check all forms, we'll use GFAPI::get_entries()

		$search_criteria = array(
			'status' => 'active',
			'field_filters' => array(
				array(
					'key'   => 'payment_status',
					'value' => array( 'Processing', 'Pending' ),
					'operator' => 'in'
				),
				array(
					'key'   => 'date_created',
					'value' => date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
					'operator' => '<'
				)
			)
		);

		$paging = array( 'offset' => 0, 'page_size' => 100 );
		$entries = GFAPI::get_entries( 0, $search_criteria, null, $paging );

		if ( ! empty( $entries ) ) {
			$entry_ids = wp_list_pluck( $entries, 'id' );
			foreach ( $entry_ids as $entry_id ) {
				GFAPI::delete_entry( $entry_id );
			}
		}
	}
}
