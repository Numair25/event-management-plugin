<?php
/**
 * AJAX Upload Handler for QR Screenshots
 */
class EMP_QR_Upload_Handler {

	/**
	 * Handle the AJAX request to upload a payment screenshot.
	 */
	public function handle_screenshot_upload() {
		// Verify Nonce
		check_ajax_referer( 'emp_qr_upload_nonce', 'nonce' );

		// Check if file is provided
		if ( ! isset( $_FILES['screenshot'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'event-management-plugin' ) ) );
		}

		$file = $_FILES['screenshot'];

		// Limit file size to 5MB (5 * 1024 * 1024)
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'File size exceeds the 5MB limit.', 'event-management-plugin' ) ) );
		}

		// Ensure wp_handle_upload is defined (needed on frontend AJAX requests)
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Restrict MIME types to standard image types and PDF
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'pdf'          => 'application/pdf',
		);

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);

		// Handle the file upload securely using WordPress API
		$movefile = wp_handle_upload( $file, $upload_overrides );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			wp_send_json_success( array(
				'url' => $movefile['url'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => $movefile['error'],
			) );
		}
	}
}
