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
					
					<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
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
									$form_id = intval( $form['id'] );
									$form_settings = isset( $saved_settings[ $form_id ] ) ? $saved_settings[ $form_id ] : array();
									$enabled = isset( $form_settings['enabled'] ) ? (bool) $form_settings['enabled'] : false;
									$amount = isset( $form_settings['amount'] ) ? floatval( $form_settings['amount'] ) : 0.00;
									$qr_image_url = isset( $form_settings['qr_image_url'] ) ? $form_settings['qr_image_url'] : '';
								?>
									<tr>
										<td style="text-align: center;">
											<input type="checkbox" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][enabled]" value="1" <?php checked( $enabled, true ); ?> />
										</td>
										<td><code><?php echo esc_html( $form_id ); ?></code></td>
										<td><strong><?php echo esc_html( $form['title'] ); ?></strong></td>
										<td>
											<input type="number" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][amount]" value="<?php echo esc_attr( $amount ); ?>" step="0.01" min="0" class="small-text" style="width: 100px;" />
										</td>
										<td>
											<input type="text" id="upi_id_<?php echo esc_attr( $form_id ); ?>" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][upi_id]" value="<?php echo esc_attr( isset( $form_settings['upi_id'] ) ? $form_settings['upi_id'] : '' ); ?>" class="regular-text" style="width: 100%;" placeholder="e.g. name@upi" />
										</td>
										<td>
											<input type="text" id="qr_image_url_<?php echo esc_attr( $form_id ); ?>" name="qr_settings[<?php echo esc_attr( $form_id ); ?>][qr_image_url]" value="<?php echo esc_url( $qr_image_url ); ?>" class="regular-text" style="width: 250px;" />
											<button type="button" class="button emp-upload-qr-btn" data-target="#qr_image_url_<?php echo esc_attr( $form_id ); ?>" data-form-id="<?php echo esc_attr( $form_id ); ?>"><?php _e( 'Upload Image', 'event-management-plugin' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<?php submit_button( __( 'Save QR Settings', 'event-management-plugin' ) ); ?>
				</form>
				
				<script>
				jQuery(document).ready(function($){
					var mediaUploader;
					var activeInputTarget = null;
					var activeFormId = null;
					
					$('.emp-upload-qr-btn').on('click', function(e) {
						e.preventDefault();
						activeInputTarget = $(this).data('target');
						activeFormId = $(this).data('form-id');
						
						if (mediaUploader) {
							mediaUploader.open();
							return;
						}
						
						mediaUploader = wp.media({
							title: '<?php esc_attr_e( 'Select or Upload QR Code Image', 'event-management-plugin' ); ?>',
							button: {
								text: '<?php esc_attr_e( 'Use this image', 'event-management-plugin' ); ?>'
							},
							multiple: false
						});
						
						mediaUploader.on('select', function() {
							var attachment = mediaUploader.state().get('selection').first().toJSON();
							if (activeInputTarget && attachment.url) {
								$(activeInputTarget).val(attachment.url);

								// If UPI ID is empty, try to decode the QR
								var $upiInput = $('#upi_id_' + activeFormId);
								if ($upiInput.val().trim() === '') {
									var img = new Image();
									img.crossOrigin = "Anonymous";
									img.onload = function() {
										var canvas = document.createElement('canvas');
										var context = canvas.getContext('2d');
										canvas.width = img.width;
										canvas.height = img.height;
										context.drawImage(img, 0, 0, img.width, img.height);
										var imageData = context.getImageData(0, 0, canvas.width, canvas.height);
										
										if (typeof jsQR !== 'undefined') {
											var code = jsQR(imageData.data, imageData.width, imageData.height);
											if (code && code.data) {
												// Extract UPI ID from URL: upi://pay?pa=some@upi&...
												if (code.data.indexOf('upi://pay') !== -1) {
													var urlParams = new URLSearchParams(code.data.split('?')[1]);
													if (urlParams.has('pa')) {
														$upiInput.val(urlParams.get('pa'));
													}
												}
											}
										}
									};
									img.src = attachment.url;
								}
							}
						});
						
						mediaUploader.open();
					});
				});
				</script>
			<?php endif; ?>
		</div>
		<?php
	}
}
