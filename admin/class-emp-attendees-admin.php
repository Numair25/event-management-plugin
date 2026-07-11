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

	public function export_csv() {
		if ( ! current_user_can( 'manage_attendees' ) ) {
			wp_die( __( 'You do not have permission to export attendees.', 'event-management-plugin' ) );
		}

		$event_id = isset( $_POST['export_event_id'] ) ? intval( $_POST['export_event_id'] ) : 0;
		if ( ! $event_id ) {
			wp_die( __( 'Please select an event to export.', 'event-management-plugin' ) );
		}

		$event_title = get_the_title( $event_id );
		if ( ! $event_title ) {
			wp_die( __( 'Invalid event selected.', 'event-management-plugin' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_attendees';
		
		// Get all attendees for this event
		$attendees = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE event_id = %d ORDER BY created_at DESC", $event_id ) );

		// Clean output buffer
		if ( ob_get_length() ) {
			ob_end_clean();
		}

		$filename = 'attendees-' . sanitize_title( $event_title ) . '-' . date('Y-m-d') . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		
		// Add BOM to fix UTF-8 in Excel
		fputs( $output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );

		// Headers
		fputcsv( $output, array( 
			'ID', 
			'Name', 
			'Email', 
			'Phone', 
			'Organization', 
			'Ticket Type ID', 
			'Status', 
			'Payment Status', 
			'Source', 
			'Registration Date' 
		) );

		if ( $attendees ) {
			foreach ( $attendees as $att ) {
				fputcsv( $output, array(
					$att->id,
					$att->name,
					$att->email,
					$att->phone,
					$att->organization,
					$att->ticket_type_id,
					ucfirst( $att->status ),
					ucfirst( $att->payment_status ),
					ucfirst( $att->source ),
					$att->created_at
				) );
			}
		}

		fclose( $output );
		
		if ( class_exists( 'EMP_Audit_Logger' ) ) {
			EMP_Audit_Logger::log( 'export_attendees', "Event ID: $event_id", "Exported attendees list to CSV" );
		}

		exit;
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
			} elseif ( $_GET['action'] == 'delete' ) {
				check_admin_referer( 'delete_' . $id );
				if ( current_user_can( 'manage_attendees' ) ) {
					// Delete related records
					$wpdb->delete( $wpdb->prefix . 'emp_scan_logs', array( 'attendee_id' => $id ) );
					$wpdb->delete( $wpdb->prefix . 'emp_payments', array( 'attendee_id' => $id ) );
					$wpdb->delete( $wpdb->prefix . 'emp_communications', array( 'attendee_id' => $id ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emp_audit_logs WHERE target = %s OR summary LIKE %s", 'Attendee', '%Attendee ID ' . $id . '%' ) );
					
					// Delete the attendee
					$wpdb->delete( $table_name, array( 'id' => $id ) );
					
					echo '<div class="updated"><p>' . __( 'Attendee permanently deleted.', 'event-management-plugin' ) . '</p></div>';
					
					if ( class_exists( 'EMP_Audit_Logger' ) ) {
						EMP_Audit_Logger::log( 'delete_attendee', "Attendee ID: $id", "Manually deleted attendee and related records" );
					}
				} else {
					echo '<div class="error"><p>' . __( 'You do not have permission to delete attendees.', 'event-management-plugin' ) . '</p></div>';
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
		
		$where = array("1=1");
		$args = array();

		// Event Filter
		if ( ! empty( $_GET['filter_event'] ) ) {
			$where[] = "event_id = %d";
			$args[] = intval( $_GET['filter_event'] );
		}
		
		// Status Filter
		if ( ! empty( $_GET['filter_status'] ) ) {
			$where[] = "status = %s";
			$args[] = sanitize_text_field( $_GET['filter_status'] );
		}

		// Payment Status Filter
		if ( ! empty( $_GET['filter_payment'] ) ) {
			$where[] = "payment_status = %s";
			$args[] = sanitize_text_field( $_GET['filter_payment'] );
		}

		// Date Registered Filter
		if ( ! empty( $_GET['filter_date'] ) ) {
			$date = sanitize_text_field( $_GET['filter_date'] );
			$where[] = "DATE(created_at) = %s";
			$args[] = $date;
		}

		// Search
		if ( ! empty( $_GET['s'] ) ) {
			$search = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%';
			$where[] = "(name LIKE %s OR email LIKE %s OR phone LIKE %s)";
			$args[] = $search;
			$args[] = $search;
			$args[] = $search;
		}

		$where_sql = implode( ' AND ', $where );
		
		if ( ! empty( $args ) ) {
			$total_items = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE $where_sql", $args ) );
			$query_args = array_merge( $args, array( $per_page, $offset ) );
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d", $query_args ) );
		} else {
			$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		}
		
		$total_pages = ceil( $total_items / $per_page );
		
		// Fetch active events for dropdown
		$events = get_posts( array( 'post_type' => 'emp_event', 'numberposts' => -1, 'post_status' => 'any' ) );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . __( 'Attendees', 'event-management-plugin' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		
		// Render Filter Form
		$current_event = isset($_GET['filter_event']) ? $_GET['filter_event'] : '';
		$current_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
		$current_payment = isset($_GET['filter_payment']) ? $_GET['filter_payment'] : '';
		$current_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
		$current_search = isset($_GET['s']) ? $_GET['s'] : '';
		
		echo '<form method="get" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
		echo '<input type="hidden" name="post_type" value="emp_event">';
		echo '<input type="hidden" name="page" value="emp-attendees">';
		
		// Search
		echo '<input type="text" name="s" placeholder="' . esc_attr__( 'Search name, email, phone...', 'event-management-plugin' ) . '" value="' . esc_attr($current_search) . '">';
		
		// Event Dropdown
		echo '<select name="filter_event">';
		echo '<option value="">' . __( 'All Events', 'event-management-plugin' ) . '</option>';
		foreach ( $events as $ev ) {
			echo '<option value="' . esc_attr($ev->ID) . '" ' . selected($current_event, $ev->ID, false) . '>' . esc_html($ev->post_title) . '</option>';
		}
		echo '</select>';
		
		// Status Dropdown
		echo '<select name="filter_status">';
		echo '<option value="">' . __( 'All Statuses', 'event-management-plugin' ) . '</option>';
		echo '<option value="registered" ' . selected($current_status, 'registered', false) . '>' . __( 'Registered', 'event-management-plugin' ) . '</option>';
		echo '<option value="checked-in" ' . selected($current_status, 'checked-in', false) . '>' . __( 'Checked-In', 'event-management-plugin' ) . '</option>';
		echo '<option value="waitlisted" ' . selected($current_status, 'waitlisted', false) . '>' . __( 'Waitlisted', 'event-management-plugin' ) . '</option>';
		echo '<option value="cancelled" ' . selected($current_status, 'cancelled', false) . '>' . __( 'Cancelled', 'event-management-plugin' ) . '</option>';
		echo '</select>';
		
		// Payment Status
		echo '<select name="filter_payment">';
		echo '<option value="">' . __( 'All Payments', 'event-management-plugin' ) . '</option>';
		echo '<option value="paid" ' . selected($current_payment, 'paid', false) . '>' . __( 'Paid', 'event-management-plugin' ) . '</option>';
		echo '<option value="comp" ' . selected($current_payment, 'comp', false) . '>' . __( 'Comp', 'event-management-plugin' ) . '</option>';
		echo '<option value="pending" ' . selected($current_payment, 'pending', false) . '>' . __( 'Pending', 'event-management-plugin' ) . '</option>';
		echo '<option value="refunded" ' . selected($current_payment, 'refunded', false) . '>' . __( 'Refunded', 'event-management-plugin' ) . '</option>';
		echo '</select>';
		
		// Date
		echo '<input type="date" name="filter_date" value="' . esc_attr($current_date) . '">';
		
		echo '<input type="submit" class="button" value="' . __( 'Filter', 'event-management-plugin' ) . '">';
		if ( !empty($current_event) || !empty($current_status) || !empty($current_payment) || !empty($current_date) || !empty($current_search) ) {
			echo '<a href="?post_type=emp_event&page=emp-attendees" class="button">' . __( 'Clear', 'event-management-plugin' ) . '</a>';
		}
		echo '</form>';
		
		// Export CSV Form
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">';
		echo '<input type="hidden" name="action" value="emp_export_attendees">';
		echo '<select name="export_event_id" required>';
		echo '<option value="">' . __( 'Select Event to Export...', 'event-management-plugin' ) . '</option>';
		foreach ( $events as $ev ) {
			echo '<option value="' . esc_attr($ev->ID) . '">' . esc_html($ev->post_title) . '</option>';
		}
		echo '</select>';
		echo '<button type="submit" class="button button-primary">' . __( 'Export CSV', 'event-management-plugin' ) . '</button>';
		echo '</form>';
		
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
					echo '<a href="' . esc_url( $print_url ) . '" class="button button-small button-primary">' . __( 'Print Badge', 'event-management-plugin' ) . '</a> ';
				}
				
				if ( current_user_can( 'manage_attendees' ) ) {
					$delete_url = wp_nonce_url( "?post_type=emp_event&page=emp-attendees&action=delete&id={$row->id}", 'delete_' . $row->id );
					echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" style="color:#b32d2e; border-color:#b32d2e;" onclick="return confirm(\'Are you sure you want to permanently delete this attendee? This action cannot be undone.\');">' . __( 'Delete', 'event-management-plugin' ) . '</a>';
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
