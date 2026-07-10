<?php
/**
 * Frontend QR Loader Class
 */
class EMP_QR_Frontend {

	/**
	 * Enqueue CSS and JS assets conditionally for QR-enabled forms.
	 *
	 * @param array $form The Gravity Form array.
	 * @param bool  $is_ajax Whether the form is AJAX-enabled.
	 */
	public function enqueue_assets( $form, $is_ajax ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$form_id = intval( $form['id'] );
		$settings = get_option( 'emp_qr_payment_settings', array() );

		// Check if QR payment is enabled for this form
		if ( isset( $settings[ $form_id ] ) && ! empty( $settings[ $form_id ]['enabled'] ) ) {
			// Enqueue CSS
			wp_enqueue_style(
				'emp-qr-payment-css',
				EMP_PLUGIN_URL . 'public/css/emp-qr-payment.css',
				array(),
				EMP_VERSION
			);

			// Enqueue JS
			wp_enqueue_script(
				'emp-qr-payment-js',
				EMP_PLUGIN_URL . 'public/js/emp-qr-payment.js',
				array( 'jquery' ),
				EMP_VERSION,
				true
			);
			
			// Enqueue QRCode generator
			wp_enqueue_script(
				'emp-qrcode-js',
				EMP_PLUGIN_URL . 'public/js/qrcode.min.js',
				array(),
				'1.0.0',
				true
			);

			// Prepare settings for all enabled forms to keep it robust and merge-safe
			$enabled_forms = array();
			foreach ( $settings as $id => $data ) {
				if ( ! empty( $data['enabled'] ) ) {
					$enabled_forms[ $id ] = array(
						'form_id'      => $id,
						'amount'       => floatval( $data['amount'] ),
						'qr_image_url' => esc_url_raw( $data['qr_image_url'] ),
						'upi_id'       => isset( $data['upi_id'] ) ? sanitize_text_field( $data['upi_id'] ) : '',
					);
				}
			}

			// Localize script
			wp_localize_script(
				'emp-qr-payment-js',
				'empQrConfig',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'emp_qr_upload_nonce' ),
					'forms'    => $enabled_forms,
				)
			);
		}
	}
}
