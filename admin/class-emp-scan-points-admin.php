<?php
/**
 * Admin UI for managing Scan Points.
 */
class EMP_Scan_Points_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Scan Points', 'event-management-plugin' ),
			__( 'Scan Points', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-scan-points',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_scan_points';

		// Handle Delete
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'delete_scan_point_' . $_GET['id'] );
			$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ) );
			echo '<div class="updated"><p>' . __( 'Scan point deleted.', 'event-management-plugin' ) . '</p></div>';
		}

		// Handle Form Submission
		if ( isset( $_POST['emp_save_scan_point'] ) ) {
			check_admin_referer( 'save_scan_point' );

			$data = array(
				'event_id' => intval( $_POST['event_id'] ),
				'name'     => sanitize_text_field( $_POST['name'] ),
				'mode'     => sanitize_text_field( $_POST['mode'] ),
				'rule'     => sanitize_text_field( $_POST['rule'] ),
			);

			if ( ! empty( $_POST['scan_point_id'] ) ) {
				$wpdb->update( $table_name, $data, array( 'id' => intval( $_POST['scan_point_id'] ) ) );
				echo '<div class="updated"><p>' . __( 'Scan point updated.', 'event-management-plugin' ) . '</p></div>';
			} else {
				$wpdb->insert( $table_name, $data );
				echo '<div class="updated"><p>' . __( 'Scan point created.', 'event-management-plugin' ) . '</p></div>';
			}
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$point_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $action == 'edit' || $action == 'new' ) {
			$this->render_form( $point_id );
		} else {
			$this->render_list();
		}
	}

	private function render_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_scan_points';
		$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY event_id ASC" );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . __( 'Scan Points', 'event-management-plugin' ) . '</h1>';
		echo '<a href="?post_type=emp_event&page=emp-scan-points&action=new" class="page-title-action">' . __( 'Add New', 'event-management-plugin' ) . '</a>';
		echo '<hr class="wp-header-end">';
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . __( 'Event', 'event-management-plugin' ) . '</th><th>' . __( 'Name', 'event-management-plugin' ) . '</th><th>' . __( 'Mode', 'event-management-plugin' ) . '</th><th>' . __( 'Rule', 'event-management-plugin' ) . '</th><th>' . __( 'Actions', 'event-management-plugin' ) . '</th></tr></thead>';
		echo '<tbody>';
		
		if ( $results ) {
			foreach ( $results as $row ) {
				$event_title = get_the_title( $row->event_id );
				$edit_url = wp_nonce_url( "?post_type=emp_event&page=emp-scan-points&action=edit&id={$row->id}", 'edit_scan_point_' . $row->id );
				$delete_url = wp_nonce_url( "?post_type=emp_event&page=emp-scan-points&action=delete&id={$row->id}", 'delete_scan_point_' . $row->id );
				
				echo '<tr>';
				echo '<td>' . esc_html( $event_title ) . '</td>';
				echo '<td>' . esc_html( $row->name ) . '</td>';
				echo '<td>' . esc_html( $row->mode ) . '</td>';
				echo '<td>' . esc_html( $row->rule ) . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'event-management-plugin' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" style="color:red;" onclick="return confirm(\'Are you sure?\');">' . __( 'Delete', 'event-management-plugin' ) . '</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="5">' . __( 'No scan points found.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table></div>';
	}

	private function render_form( $id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_scan_points';
		$point = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) ) : null;
		
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		
		echo '<div class="wrap">';
		echo '<h1>' . ( $id ? __( 'Edit Scan Point', 'event-management-plugin' ) : __( 'Add New Scan Point', 'event-management-plugin' ) ) . '</h1>';
		
		echo '<form method="post" action="?post_type=emp_event&page=emp-scan-points">';
		wp_nonce_field( 'save_scan_point' );
		echo '<input type="hidden" name="emp_save_scan_point" value="1" />';
		if ( $id ) {
			echo '<input type="hidden" name="scan_point_id" value="' . esc_attr( $id ) . '" />';
		}
		
		echo '<table class="form-table">';
		
		echo '<tr><th><label>' . __( 'Event', 'event-management-plugin' ) . '</label></th><td><select name="event_id" required>';
		foreach ( $events as $event ) {
			$selected = ( $point && $point->event_id == $event->ID ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $event->ID ) . '" ' . $selected . '>' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Name', 'event-management-plugin' ) . '</label></th><td><input type="text" name="name" class="regular-text" value="' . ( $point ? esc_attr( $point->name ) : '' ) . '" required /></td></tr>';
		
		echo '<tr><th><label>' . __( 'Mode', 'event-management-plugin' ) . '</label></th><td><select name="mode">';
		echo '<option value="entry" ' . ( $point && $point->mode == 'entry' ? 'selected' : '' ) . '>' . __( 'Entry / Check-In', 'event-management-plugin' ) . '</option>';
		echo '<option value="food" ' . ( $point && $point->mode == 'food' ? 'selected' : '' ) . '>' . __( 'Food / Session', 'event-management-plugin' ) . '</option>';
		echo '<option value="check-out" ' . ( $point && $point->mode == 'check-out' ? 'selected' : '' ) . '>' . __( 'Check-Out', 'event-management-plugin' ) . '</option>';
		echo '</select></td></tr>';
		
		echo '<tr><th><label>' . __( 'Rule', 'event-management-plugin' ) . '</label></th><td><select name="rule">';
		echo '<option value="single" ' . ( $point && $point->rule == 'single' ? 'selected' : '' ) . '>' . __( 'Single Entry (Reject if already scanned)', 'event-management-plugin' ) . '</option>';
		echo '<option value="repeat" ' . ( $point && $point->rule == 'repeat' ? 'selected' : '' ) . '>' . __( 'Repeat Entry (Allow multiple scans)', 'event-management-plugin' ) . '</option>';
		echo '</select></td></tr>';
		
		echo '</table>';
		
		submit_button( __( 'Save Scan Point', 'event-management-plugin' ) );
		echo '</form></div>';
	}
}
