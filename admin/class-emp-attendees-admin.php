<?php
/**
 * Admin UI for managing Attendees.
 */
class EMP_Attendees_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Attendees', 'event-management-plugin' ),
			__( 'Attendees', 'event-management-plugin' ),
			'manage_attendees',
			'emp-attendees',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_attendees';

		// Handle Actions
		if ( isset( $_GET['action'] ) && isset( $_GET['id'] ) ) {
			$id = intval( $_GET['id'] );
			
			if ( $_GET['action'] == 'mark_paid' ) {
				check_admin_referer( 'mark_paid_' . $id );
				if ( current_user_can( 'manage_finances' ) ) {
					$wpdb->update( $table_name, array( 'payment_status' => 'paid' ), array( 'id' => $id ) );
					echo '<div class="updated"><p>' . __( 'Attendee marked as paid.', 'event-management-plugin' ) . '</p></div>';
					
					// Log manual payment
					$table_payments = $wpdb->prefix . 'emp_payments';
					$wpdb->insert( $table_payments, array(
						'attendee_id' => $id,
						'amount'      => 0, // Manual typically has amount specified elsewhere, simplified here
						'method'      => 'manual',
						'reference'   => 'Manual Admin Update',
					) );
					
					if ( class_exists( 'EMP_Audit_Logger' ) ) {
						EMP_Audit_Logger::log( 'mark_paid', "Attendee ID: $id", "Manually marked attendee as paid" );
					}
				} else {
					echo '<div class="error"><p>' . __( 'You do not have permission to manage finances.', 'event-management-plugin' ) . '</p></div>';
				}
			} elseif ( $_GET['action'] == 'refund' ) {
				check_admin_referer( 'refund_' . $id );
				if ( current_user_can( 'manage_finances' ) ) {
					// Invalidate token on refund
					$wpdb->update( $table_name, array( 
						'payment_status' => 'refunded',
						'status'         => 'cancelled',
						'qr_token'       => 'invalidated_' . time() . '_' . $id
					), array( 'id' => $id ) );
					echo '<div class="updated"><p>' . __( 'Attendee refunded and token invalidated.', 'event-management-plugin' ) . '</p></div>';
					
					$table_payments = $wpdb->prefix . 'emp_payments';
					$wpdb->insert( $table_payments, array(
						'attendee_id' => $id,
						'amount'      => 0,
						'method'      => 'manual_refund',
						'reference'   => 'Manual Admin Refund',
					) );
					
					if ( class_exists( 'EMP_Audit_Logger' ) ) {
						EMP_Audit_Logger::log( 'refund', "Attendee ID: $id", "Manually refunded/cancelled attendee ticket" );
					}
				} else {
					echo '<div class="error"><p>' . __( 'You do not have permission to manage finances.', 'event-management-plugin' ) . '</p></div>';
				}
			}
		}

		$this->render_list();
	}

	private function render_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_attendees';
		
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;
		
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		
		$total_pages = ceil( $total_items / $per_page );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . __( 'Attendees', 'event-management-plugin' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . __( 'Name / Email', 'event-management-plugin' ) . '</th>';
		echo '<th>' . __( 'Event', 'event-management-plugin' ) . '</th>';
		echo '<th>' . __( 'Status', 'event-management-plugin' ) . '</th>';
		echo '<th>' . __( 'Payment Status', 'event-management-plugin' ) . '</th>';
		echo '<th>' . __( 'Actions', 'event-management-plugin' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		
		if ( $results ) {
			foreach ( $results as $row ) {
				$event_title = get_the_title( $row->event_id );
				
				$mark_paid_url = wp_nonce_url( "?post_type=emp_event&page=emp-attendees&action=mark_paid&id={$row->id}", 'mark_paid_' . $row->id );
				$refund_url = wp_nonce_url( "?post_type=emp_event&page=emp-attendees&action=refund&id={$row->id}", 'refund_' . $row->id );
				
				echo '<tr>';
				echo '<td><strong>' . esc_html( $row->name ) . '</strong><br/>' . esc_html( $row->email ) . '</td>';
				echo '<td>' . esc_html( $event_title ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $row->status ) ) . '</td>';
				echo '<td>' . esc_html( ucfirst( $row->payment_status ) ) . '</td>';
				
				echo '<td>';
				if ( current_user_can( 'manage_finances' ) ) {
					if ( $row->payment_status !== 'paid' ) {
						echo '<a href="' . esc_url( $mark_paid_url ) . '" class="button button-small">' . __( 'Mark Paid', 'event-management-plugin' ) . '</a> ';
					}
					if ( $row->payment_status !== 'refunded' && $row->payment_status !== 'cancelled' ) {
						echo '<a href="' . esc_url( $refund_url ) . '" class="button button-small" onclick="return confirm(\'Refund and invalidate ticket?\');">' . __( 'Refund/Cancel', 'event-management-plugin' ) . '</a> ';
					}
				}
				
				if ( $row->status !== 'cancelled' && $row->status !== 'waitlisted' && $row->payment_status !== 'refunded' ) {
					$print_url = admin_url( 'admin.php?emp_action=print_badge&attendee_id=' . $row->id );
					echo '<a href="' . esc_url( $print_url ) . '" class="button button-small button-primary">' . __( 'Print Badge', 'event-management-plugin' ) . '</a>';
				}
				
				echo '</td>';
				
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="5">' . __( 'No attendees found.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table>';
		
		// Pagination
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => __( '&laquo;' ),
				'next_text' => __( '&raquo;' ),
				'total'     => $total_pages,
				'current'   => $page
			) );
			echo '</div></div>';
		}
		
		echo '</div>';
	}
}
