<?php
/**
 * QR Payment Approvals Admin Page
 */
class EMP_QR_Approvals_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Pending QR Approvals', 'event-management-plugin' ),
			__( 'QR Approvals', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-qr-approvals',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-management-plugin' ) );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			echo '<div class="notice notice-warning"><p>Gravity Forms is not active.</p></div>';
			return;
		}

		$this->handle_actions();

		$search_criteria = array(
			'status' => 'active',
			'field_filters' => array(
				array(
					'key'   => 'payment_status',
					'value' => 'Pending',
				),
			)
		);

		$paging = array( 'offset' => 0, 'page_size' => 100 );
		$pending_entries = GFAPI::get_entries( 0, $search_criteria, null, $paging );

		// Filter for only QR ones by checking meta
		$qr_entries = array();
		foreach ( $pending_entries as $entry ) {
			$tx_id = gform_get_meta( $entry['id'], 'emp_qr_transaction_id' );
			if ( ! empty( $tx_id ) ) {
				$qr_entries[] = $entry;
			}
		}

		?>
		<div class="wrap">
			<h1><?php _e( 'Pending QR Payment Approvals', 'event-management-plugin' ); ?></h1>
			<p class="description"><?php _e( 'Review and manually approve pending QR transactions.', 'event-management-plugin' ); ?></p>
			
			<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
				<thead>
					<tr>
						<th style="width: 80px;"><?php _e( 'Entry ID', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Form Title', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Amount', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Transaction ID', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Screenshot', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Actions', 'event-management-plugin' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $qr_entries ) ) : ?>
						<tr>
							<td colspan="6"><?php _e( 'No pending QR approvals found.', 'event-management-plugin' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $qr_entries as $entry ) : 
							$form = GFAPI::get_form( $entry['form_id'] );
							$tx_id = gform_get_meta( $entry['id'], 'emp_qr_transaction_id' );
							$screenshot = gform_get_meta( $entry['id'], 'emp_qr_screenshot_url' );
						?>
							<tr>
								<td><code><?php echo esc_html( $entry['id'] ); ?></code></td>
								<td><strong><?php echo esc_html( isset( $form['title'] ) ? $form['title'] : 'Form ' . $entry['form_id'] ); ?></strong></td>
								<td>₹<?php echo esc_html( isset( $entry['payment_amount'] ) ? $entry['payment_amount'] : '0.00' ); ?></td>
								<td><code><?php echo esc_html( $tx_id ); ?></code></td>
								<td>
									<?php if ( ! empty( $screenshot ) ) : ?>
										<a href="<?php echo esc_url( $screenshot ); ?>" target="_blank" class="button button-secondary"><?php _e( 'View Screenshot', 'event-management-plugin' ); ?></a>
									<?php else : ?>
										<em><?php _e( 'No screenshot', 'event-management-plugin' ); ?></em>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo wp_nonce_url( admin_url( 'edit.php?post_type=emp_event&page=emp-qr-approvals&action=approve&entry_id=' . $entry['id'] ), 'emp_qr_action_' . $entry['id'] ); ?>" class="button button-primary" style="background:#25D366; border-color:#25D366; color:#fff; margin-right:5px;"><?php _e( 'Approve', 'event-management-plugin' ); ?></a>
									<a href="<?php echo wp_nonce_url( admin_url( 'edit.php?post_type=emp_event&page=emp-qr-approvals&action=reject&entry_id=' . $entry['id'] ), 'emp_qr_action_' . $entry['id'] ); ?>" class="button button-secondary" onclick="return confirm('<?php _e( 'Are you sure you want to reject and delete this entry?', 'event-management-plugin' ); ?>');"><?php _e( 'Reject & Delete', 'event-management-plugin' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function handle_actions() {
		if ( isset( $_GET['action'] ) && isset( $_GET['entry_id'] ) ) {
			$action = sanitize_text_field( $_GET['action'] );
			$entry_id = intval( $_GET['entry_id'] );

			if ( ! check_admin_referer( 'emp_qr_action_' . $entry_id ) ) {
				wp_die( 'Security check failed.' );
			}

			if ( $action === 'approve' ) {
				$entry = GFAPI::get_entry( $entry_id );
				if ( ! is_wp_error( $entry ) && $entry['payment_status'] === 'Pending' ) {
					$entry['payment_status'] = 'Paid';
					GFAPI::update_entry( $entry );
					GFAPI::add_note( $entry['id'], 0, 'Event Management', 'QR Payment manually approved by admin.' );
					
					// Trigger complete payment action so that handle_payment_action fires
					// handle_payment_action will run auto_create_attendee_for_linked_form
					do_action( 'gform_post_payment_action', $entry, array( 'type' => 'complete_payment' ) );
					
					echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Entry approved and attendee created successfully.', 'event-management-plugin' ) . '</p></div>';
				}
			} elseif ( $action === 'reject' ) {
				GFAPI::delete_entry( $entry_id );
				echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Entry rejected and deleted.', 'event-management-plugin' ) . '</p></div>';
			}
		}
	}
}
