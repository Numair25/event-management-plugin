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
			
			<div style="margin-top: 20px; margin-bottom: 15px; display: flex; gap: 15px; align-items: center;">
				<div>
					<label for="emp_qr_approvals_search"><strong><?php _e( 'Search:', 'event-management-plugin' ); ?></strong></label>
					<input type="text" id="emp_qr_approvals_search" placeholder="<?php esc_attr_e( 'Search ID, Form, Tx...', 'event-management-plugin' ); ?>" style="padding: 3px 8px; width: 250px;" />
				</div>
				<div>
					<label for="emp_qr_approvals_form_filter"><strong><?php _e( 'Filter by Form:', 'event-management-plugin' ); ?></strong></label>
					<select id="emp_qr_approvals_form_filter">
						<option value=""><?php _e( 'All Forms', 'event-management-plugin' ); ?></option>
						<?php
						$forms = GFAPI::get_forms();
						foreach ( $forms as $form ) {
							echo '<option value="' . esc_attr( $form['id'] ) . '">' . esc_html( $form['title'] ) . '</option>';
						}
						?>
					</select>
				</div>
			</div>

			<table class="wp-list-table widefat fixed striped" id="emp_qr_approvals_table">
				<thead>
					<tr>
						<th style="width: 80px;"><?php _e( 'Entry ID', 'event-management-plugin' ); ?></th>
						<th><?php _e( 'Name / Contact', 'event-management-plugin' ); ?></th>
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
							
							// Extract Name, Email, Phone
							$name = '';
							$email = '';
							$phone = '';
							if ( $form && isset( $form['fields'] ) ) {
								foreach ( $form['fields'] as $field ) {
									if ( $field->type == 'name' ) {
										$first = rgar( $entry, $field->id . '.3' );
										$last = rgar( $entry, $field->id . '.6' );
										if ( ! empty( $first ) || ! empty( $last ) ) {
											$name = trim( $first . ' ' . $last );
										} elseif ( empty( $name ) ) {
											$name = rgar( $entry, strval( $field->id ) );
										}
									} elseif ( $field->type == 'email' || stripos( $field->label, 'email' ) !== false ) {
										if ( empty( $email ) ) $email = rgar( $entry, strval( $field->id ) );
									} elseif ( $field->type == 'phone' || stripos( $field->label, 'phone' ) !== false || stripos( $field->label, 'whatsapp' ) !== false || stripos( $field->label, 'mobile' ) !== false ) {
										if ( empty( $phone ) ) $phone = rgar( $entry, strval( $field->id ) );
									}
								}
							}
							
							// Fallback if name is empty
							if ( empty( $name ) ) {
								$name = 'Attendee';
							}
						?>
							<tr class="emp-qr-approval-row" data-form-id="<?php echo esc_attr( $entry['form_id'] ); ?>">
								<td><code><?php echo esc_html( $entry['id'] ); ?></code></td>
								<td>
									<strong><?php echo esc_html( $name ); ?></strong><br>
									<?php if ( ! empty( $email ) ) : ?>
										<a href="mailto:<?php echo esc_attr( $email ); ?>" style="text-decoration:none; color:#555; font-size:12px;"><?php echo esc_html( $email ); ?></a><br>
									<?php endif; ?>
									<?php if ( ! empty( $phone ) ) : ?>
										<span style="color:#555; font-size:12px;"><?php echo esc_html( $phone ); ?></span>
									<?php endif; ?>
								</td>
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

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				function filterTable() {
					var searchTerm = $('#emp_qr_approvals_search').val().toLowerCase();
					var formFilter = $('#emp_qr_approvals_form_filter').val();

					$('#emp_qr_approvals_table tbody tr.emp-qr-approval-row').each(function() {
						var row = $(this);
						var rowText = row.text().toLowerCase();
						var rowFormId = row.data('form-id');

						var textMatches = rowText.indexOf(searchTerm) > -1;
						var formMatches = (formFilter === '' || rowFormId == formFilter);

						if (textMatches && formMatches) {
							row.show();
						} else {
							row.hide();
						}
					});
				}

				$('#emp_qr_approvals_search').on('keyup', filterTable);
				$('#emp_qr_approvals_form_filter').on('change', filterTable);
			});
		</script>
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
