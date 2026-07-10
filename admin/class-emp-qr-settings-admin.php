<?php
/**
 * QR Configuration Settings Admin Page
 */
class EMP_QR_Settings_Admin {

	/**
	 * Register the submenu page.
	 */
	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'QR Settings', 'event-management-plugin' ),
			__( 'QR Settings', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-qr-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the settings page and handle saving options.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-management-plugin' ) );
		}

		// Enqueue WP Media Library scripts
		wp_enqueue_media();
		
		// Enqueue jsQR library for decoding uploaded images
		wp_enqueue_script( 'jsqr', EMP_PLUGIN_URL . 'admin/js/jsQR.js', array(), EMP_VERSION, true );

		// Handle saving option
		if ( isset( $_POST['emp_save_qr_settings'] ) && check_admin_referer( 'emp_save_qr_settings_action', 'emp_save_qr_settings_nonce' ) ) {
			if ( ! class_exists( 'GFAPI' ) ) {
				return;
			}
			$settings = array();
			if ( isset( $_POST['qr_settings'] ) && is_array( $_POST['qr_settings'] ) ) {
				foreach ( $_POST['qr_settings'] as $form_id => $form_data ) {
					$form_id = intval( $form_id );
					if ( $form_id <= 0 ) {
						continue;
					}
					$enabled = isset( $form_data['enabled'] ) ? true : false;
					$amount = isset( $form_data['amount'] ) ? max( 0.00, round( floatval( $form_data['amount'] ), 2 ) ) : 0.00;
					$qr_image_url = isset( $form_data['qr_image_url'] ) ? esc_url_raw( $form_data['qr_image_url'] ) : '';
					$upi_id = isset( $form_data['upi_id'] ) ? sanitize_text_field( $form_data['upi_id'] ) : '';

					$settings[ $form_id ] = array(
						'enabled'      => $enabled,
						'amount'       => $amount,
						'qr_image_url' => $qr_image_url,
						'upi_id'       => $upi_id,
					);
				}
			}
			update_option( 'emp_qr_payment_settings', $settings );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'QR Payment Settings saved successfully.', 'event-management-plugin' ) . '</p></div>';
		}

		// Fetch current option values
		$saved_settings = get_option( 'emp_qr_payment_settings', array() );

		// Load Gravity Forms
		$forms = array();
		$gf_active = class_exists( 'GFAPI' );
		if ( $gf_active ) {
			$forms = GFAPI::get_forms();
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'QR Payment Settings', 'event-management-plugin' ); ?></h1>
			<p class="description"><?php _e( 'Configure payment amounts and upload QR images for individual Gravity Forms.', 'event-management-plugin' ); ?></p>
			
			<?php if ( ! $gf_active ) : ?>
				<div class="notice notice-warning"><p><?php _e( 'Gravity Forms is not active. Please install and activate Gravity Forms to configure QR payments.', 'event-management-plugin' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'emp_save_qr_settings_action', 'emp_save_qr_settings_nonce' ); ?>
					<input type="hidden" name="emp_save_qr_settings" value="1" />

					<div style="margin-top: 20px; margin-bottom: 15px; display: flex; gap: 15px; align-items: center;">
						<div>
							<label for="emp_qr_settings_search"><strong><?php _e( 'Search:', 'event-management-plugin' ); ?></strong></label>
							<input type="text" id="emp_qr_settings_search" placeholder="<?php esc_attr_e( 'Search Form Title or ID...', 'event-management-plugin' ); ?>" style="padding: 3px 8px; width: 250px;" />
						</div>
						<div>
							<label for="emp_qr_settings_status_filter"><strong><?php _e( 'Filter by Status:', 'event-management-plugin' ); ?></strong></label>
							<select id="emp_qr_settings_status_filter">
								<option value=""><?php _e( 'All Forms', 'event-management-plugin' ); ?></option>
								<option value="enabled"><?php _e( 'Enabled Only', 'event-management-plugin' ); ?></option>
								<option value="disabled"><?php _e( 'Disabled Only', 'event-management-plugin' ); ?></option>
							</select>
						</div>
					</div>
					
					<table class="wp-list-table widefat fixed striped" id="emp_qr_settings_table">
						<thead>
							<tr>
								<th style="width: 80px; text-align: center;"><?php _e( 'Enabled', 'event-management-plugin' ); ?></th>
								<th style="width: 80px;"><?php _e( 'Form ID', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'Form Title', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'Amount (₹)', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'UPI ID (Optional)', 'event-management-plugin' ); ?></th>
								<th><?php _e( 'QR Image URL / Uploader', 'event-management-plugin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $forms ) ) : ?>
								<tr>
									<td colspan="5"><?php _e( 'No Gravity Forms found.', 'event-management-plugin' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $forms as $form ) : 
									$form_id = $form['id'];
									$is_enabled = isset( $saved_settings[ $form_id ]['enabled'] ) ? $saved_settings[ $form_id ]['enabled'] : false;
									$amount = isset( $saved_settings[ $form_id ]['amount'] ) ? $saved_settings[ $form_id ]['amount'] : '';
									$qr_url = isset( $saved_settings[ $form_id ]['qr_image_url'] ) ? $saved_settings[ $form_id ]['qr_image_url'] : '';
									$upi_id = isset( $saved_settings[ $form_id ]['upi_id'] ) ? $saved_settings[ $form_id ]['upi_id'] : '';
									
									$status_class = $is_enabled ? 'status-enabled' : 'status-disabled';
								?>
									<tr class="emp-qr-settings-row <?php echo esc_attr( $status_class ); ?>">
										<td style="text-align: center;">
											<input type="checkbox" class="emp-qr-enabled-cb" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][enabled]" value="1" <?php checked( $is_enabled, true ); ?> />
										</td>
										<td><code><?php echo esc_html( $form_id ); ?></code></td>
										<td><strong><?php echo esc_html( $form['title'] ); ?></strong></td>
										<td>
											<input type="number" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][amount]" value="<?php echo esc_attr( $amount ); ?>" step="0.01" min="0" class="small-text" style="width: 100px;" />
										</td>
										<td>
											<input type="text" id="upi_id_<?php echo esc_attr( $form_id ); ?>" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][upi_id]" value="<?php echo esc_attr( $upi_id ); ?>" class="regular-text emp-qr-upi-input" style="width: 100%;" placeholder="e.g. name@upi" />
										</td>
										<td>
											<input type="text" id="qr_image_url_<?php echo esc_attr( $form_id ); ?>" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][qr_image_url]" value="<?php echo esc_url( $qr_url ); ?>" class="regular-text emp-qr-url-input" style="width: 250px;" />
											<button type="button" class="button emp-qr-upload-button" data-target="#qr_image_url_<?php echo esc_attr( $form_id ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>"><?php _e( 'Upload', 'event-management-plugin' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<?php submit_button( __( 'Save QR Settings', 'event-management-plugin' ) ); ?>
				</form>
				
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Real-time table filter logic
						function filterSettingsTable() {
							var searchTerm = $('#emp_qr_settings_search').val().toLowerCase();
							var statusFilter = $('#emp_qr_settings_status_filter').val();

							$('#emp_qr_settings_table tbody tr.emp-qr-settings-row').each(function() {
								var row = $(this);
								// Only search in Form ID and Form Title columns (index 1 and 2)
								var idText = row.find('td:eq(1)').text().toLowerCase();
								var titleText = row.find('td:eq(2)').text().toLowerCase();
								var rowText = idText + " " + titleText;
								
								var textMatches = rowText.indexOf(searchTerm) > -1;
								
								// Determine dynamic status based on checkbox state
								var isEnabled = row.find('.emp-qr-enabled-cb').is(':checked');
								var statusMatches = true;
								if (statusFilter === 'enabled' && !isEnabled) statusMatches = false;
								if (statusFilter === 'disabled' && isEnabled) statusMatches = false;

								if (textMatches && statusMatches) {
									row.show();
								} else {
									row.hide();
								}
							});
						}

						$('#emp_qr_settings_search').on('keyup', filterSettingsTable);
						$('#emp_qr_settings_status_filter').on('change', filterSettingsTable);
						$('.emp-qr-enabled-cb').on('change', filterSettingsTable); 

						// Uploader Logic
						$('.emp-qr-upload-button').click(function(e) {
							e.preventDefault();
							var button = $(this);
							var inputField = $(button.data('target'));
							var formId = button.data('form-id');
							var upiField = $('#upi_id_' + formId);
							
							var customUploader = wp.media({
								title: 'Select QR Code Image',
								button: { text: 'Use Image' },
								multiple: false
							}).on('select', function() {
								var attachment = customUploader.state().get('selection').first().toJSON();
								inputField.val(attachment.url);

								// Only try to extract if UPI field is empty
								if (upiField.val() === '') {
									extractUpiFromImage(attachment.url, upiField);
								}

							}).open();
						});

						function extractUpiFromImage(imageUrl, upiField) {
							if (typeof jsQR !== 'function') return;

							var img = new Image();
							img.crossOrigin = "Anonymous";
							img.onload = function() {
								var canvas = document.createElement('canvas');
								var context = canvas.getContext('2d');
								canvas.width = img.width;
								canvas.height = img.height;
								context.drawImage(img, 0, 0, img.width, img.height);
								
								var imageData = context.getImageData(0, 0, canvas.width, canvas.height);
								var code = jsQR(imageData.data, imageData.width, imageData.height);
								
								if (code && code.data.indexOf('upi://pay') !== -1) {
									var urlParams = new URLSearchParams(code.data.substring(code.data.indexOf('?')));
									if (urlParams.has('pa')) {
										upiField.val(urlParams.get('pa'));
									}
								}
							};
							img.src = imageUrl;
						}
					});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
}
