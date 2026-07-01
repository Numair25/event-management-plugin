<?php
/**
 * Admin UI for managing Ticket Types.
 */
class EMP_Ticket_Types_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Ticket Types', 'event-management-plugin' ),
			__( 'Ticket Types', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-ticket-types',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';

		// Handle Delete
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'delete_ticket_type_' . $_GET['id'] );
			$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ) );
			echo '<div class="updated"><p>' . __( 'Ticket type deleted.', 'event-management-plugin' ) . '</p></div>';
		}

		// Handle Form Submission
		if ( isset( $_POST['emp_save_ticket_type'] ) ) {
			check_admin_referer( 'save_ticket_type' );

			$data = array(
				'event_id'   => intval( $_POST['event_id'] ),
				'name'       => sanitize_text_field( $_POST['name'] ),
				'price'      => floatval( $_POST['price'] ),
				'capacity'   => !empty($_POST['capacity']) ? intval( $_POST['capacity'] ) : null,
				'color_code' => sanitize_hex_color( $_POST['color_code'] ),
				'is_comp'    => isset( $_POST['is_comp'] ) ? 1 : 0,
			);

			if ( ! empty( $_POST['ticket_type_id'] ) ) {
				$wpdb->update( $table_name, $data, array( 'id' => intval( $_POST['ticket_type_id'] ) ) );
				echo '<div class="updated"><p>' . __( 'Ticket type updated.', 'event-management-plugin' ) . '</p></div>';
			} else {
				$wpdb->insert( $table_name, $data );
				echo '<div class="updated"><p>' . __( 'Ticket type created.', 'event-management-plugin' ) . '</p></div>';
			}
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$ticket_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( $action == 'edit' || $action == 'new' ) {
			$this->render_form( $ticket_id );
		} else {
			$this->render_list();
		}
	}

	private function render_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY event_id ASC" );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . __( 'Ticket Types', 'event-management-plugin' ) . '</h1>';
		echo '<a href="?post_type=emp_event&page=emp-ticket-types&action=new" class="page-title-action">' . __( 'Add New', 'event-management-plugin' ) . '</a>';
		echo '<hr class="wp-header-end">';
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . __( 'Event', 'event-management-plugin' ) . '</th><th>' . __( 'Name', 'event-management-plugin' ) . '</th><th>' . __( 'Price', 'event-management-plugin' ) . '</th><th>' . __( 'Capacity', 'event-management-plugin' ) . '</th><th>' . __( 'Comp', 'event-management-plugin' ) . '</th><th>' . __( 'Actions', 'event-management-plugin' ) . '</th></tr></thead>';
		echo '<tbody>';
		
		if ( $results ) {
			foreach ( $results as $row ) {
				$event_title = get_the_title( $row->event_id );
				$edit_url = wp_nonce_url( "?post_type=emp_event&page=emp-ticket-types&action=edit&id={$row->id}", 'edit_ticket_type_' . $row->id );
				$delete_url = wp_nonce_url( "?post_type=emp_event&page=emp-ticket-types&action=delete&id={$row->id}", 'delete_ticket_type_' . $row->id );
				
				echo '<tr>';
				echo '<td>' . esc_html( $event_title ?: 'Unknown (' . $row->event_id . ')' ) . '</td>';
				echo '<td>' . esc_html( $row->name ) . ' <span style="display:inline-block;width:12px;height:12px;background-color:' . esc_attr( $row->color_code ) . '"></span></td>';
				echo '<td>' . esc_html( $row->price ) . '</td>';
				echo '<td>' . esc_html( $row->capacity !== null ? $row->capacity : 'Unlimited' ) . '</td>';
				echo '<td>' . ( $row->is_comp ? 'Yes' : 'No' ) . '</td>';
				
				$bulk_print_url = admin_url( 'admin.php?emp_action=print_badge&ticket_type_id=' . $row->id );
				
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . __( 'Edit', 'event-management-plugin' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" style="color:red;" onclick="return confirm(\'Are you sure?\');">' . __( 'Delete', 'event-management-plugin' ) . '</a> | <a href="' . esc_url( $bulk_print_url ) . '" class="button button-small button-primary">' . __( 'Bulk Print New Badges', 'event-management-plugin' ) . '</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="6">' . __( 'No ticket types found.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table></div>';
	}

	private function render_form( $id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		$ticket = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) ) : null;
		
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		
		echo '<div class="wrap">';
		echo '<h1>' . ( $id ? __( 'Edit Ticket Type', 'event-management-plugin' ) : __( 'Add New Ticket Type', 'event-management-plugin' ) ) . '</h1>';
		
		echo '<form method="post" action="?post_type=emp_event&page=emp-ticket-types">';
		wp_nonce_field( 'save_ticket_type' );
		echo '<input type="hidden" name="emp_save_ticket_type" value="1" />';
		if ( $id ) {
			echo '<input type="hidden" name="ticket_type_id" value="' . esc_attr( $id ) . '" />';
		}
		
		echo '<table class="form-table">';
		
		echo '<tr><th><label for="event_id">' . __( 'Event', 'event-management-plugin' ) . '</label></th><td><select name="event_id" required>';
		foreach ( $events as $event ) {
			$selected = ( $ticket && $ticket->event_id == $event->ID ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $event->ID ) . '" ' . $selected . '>' . esc_html( $event->post_title ) . '</option>';
		}
		echo '</select></td></tr>';
		
		echo '<tr><th><label for="name">' . __( 'Name', 'event-management-plugin' ) . '</label></th><td><input type="text" name="name" class="regular-text" value="' . ( $ticket ? esc_attr( $ticket->name ) : '' ) . '" required /></td></tr>';
		
		echo '<tr><th><label for="price">' . __( 'Price', 'event-management-plugin' ) . '</label></th><td><input type="number" step="0.01" name="price" class="regular-text" value="' . ( $ticket ? esc_attr( $ticket->price ) : '0.00' ) . '" required /></td></tr>';
		
		echo '<tr><th><label for="capacity">' . __( 'Capacity', 'event-management-plugin' ) . '</label></th><td><input type="number" name="capacity" class="regular-text" value="' . ( $ticket ? esc_attr( $ticket->capacity ) : '' ) . '" /><p class="description">Leave blank for unlimited.</p></td></tr>';
		
		echo '<tr><th><label for="color_code">' . __( 'Color Code', 'event-management-plugin' ) . '</label></th><td><input type="color" name="color_code" value="' . ( $ticket ? esc_attr( $ticket->color_code ) : '#000000' ) . '" /></td></tr>';
		
		echo '<tr><th><label for="is_comp">' . __( 'Complimentary', 'event-management-plugin' ) . '</label></th><td><input type="checkbox" name="is_comp" value="1" ' . ( ( $ticket && $ticket->is_comp ) ? 'checked' : '' ) . ' /></td></tr>';
		
		echo '</table>';
		
		submit_button( __( 'Save Ticket Type', 'event-management-plugin' ) );
		echo '</form></div>';
	}
}
