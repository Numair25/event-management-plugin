<?php
/**
 * Audit Logger Service.
 */
class EMP_Audit_Logger {

	public static function log( $action, $target, $summary ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_audit_logs';

		$user_id = get_current_user_id();

		$wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'action'  => sanitize_text_field( $action ),
				'target'  => sanitize_text_field( $target ),
				'summary' => sanitize_textarea_field( $summary ),
			)
		);
	}
}
