<?php
/**
 * Shortcode to render Feedback & Certificate Portal.
 */
class EMP_Feedback_Portal {

	public function register_shortcodes() {
		add_shortcode( 'emp_feedback_portal', array( $this, 'render_portal' ) );
		add_action( 'init', array( $this, 'handle_certificate_download' ) );
	}

	public function render_portal( $atts ) {
		if ( empty( $_GET['token'] ) ) {
			return '<p>' . __( 'Invalid access token.', 'event-management-plugin' ) . '</p>';
		}

		$token = sanitize_text_field( $_GET['token'] );
		
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE qr_token = %s", $token ) );
		if ( ! $attendee ) {
			return '<p>' . __( 'Attendee not found.', 'event-management-plugin' ) . '</p>';
		}

		// Handle Form Submission
		if ( isset( $_POST['emp_submit_feedback'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'emp_feedback_' . $attendee->id ) ) {
			$rating = intval( $_POST['rating'] );
			$comments = sanitize_textarea_field( $_POST['comments'] );
			
			$table_feedback = $wpdb->prefix . 'emp_feedback';
			
			// Check if already submitted
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_feedback WHERE attendee_id = %d", $attendee->id ) );
			
			if ( ! $exists ) {
				$wpdb->insert( $table_feedback, array(
					'event_id'    => $attendee->event_id,
					'attendee_id' => $attendee->id,
					'rating'      => $rating,
					'comments'    => $comments,
				) );
			}
			echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px;">' . __( 'Thank you for your feedback! Your certificate is now unlocked.', 'event-management-plugin' ) . '</div>';
		}

		// Check if feedback is completed
		$table_feedback = $wpdb->prefix . 'emp_feedback';
		$has_feedback = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_feedback WHERE attendee_id = %d", $attendee->id ) );

		ob_start();
		?>
		<div class="emp-portal-wrapper" style="max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-family: sans-serif;">
			
			<h2 style="margin-top:0;">Welcome, <?php echo esc_html( $attendee->name ); ?>!</h2>
			<p>Event: <?php echo esc_html( get_the_title( $attendee->event_id ) ); ?></p>
			
			<?php if ( $has_feedback ) : ?>
				
				<div style="text-align: center; margin-top: 30px; padding: 30px; background: #f8f9fa; border-radius: 8px; border: 1px solid #ddd;">
					<h3 style="margin-top:0; color:#28a745;">&#10004; Feedback Completed</h3>
					<p>You can now download your Certificate of Attendance.</p>
					<a href="?token=<?php echo esc_attr( $token ); ?>&emp_action=download_cert" style="display:inline-block; background:#0073aa; color:#fff; padding:12px 24px; text-decoration:none; border-radius:4px; font-weight:bold; margin-top:15px;">Download Certificate (PDF)</a>
				</div>

			<?php else : ?>
				
				<div style="margin-top: 30px;">
					<h3>Event Feedback</h3>
					<p>Please complete this quick survey to unlock your Certificate of Attendance.</p>
					
					<form method="post" action="">
						<?php wp_nonce_field( 'emp_feedback_' . $attendee->id ); ?>
						<input type="hidden" name="emp_submit_feedback" value="1" />
						
						<div style="margin-bottom: 20px;">
							<label style="display:block; font-weight:bold; margin-bottom:10px;">Overall Experience (1-5)</label>
							<select name="rating" style="width:100%; padding:10px;" required>
								<option value="">-- Select --</option>
								<option value="5">5 - Excellent</option>
								<option value="4">4 - Good</option>
								<option value="3">3 - Average</option>
								<option value="2">2 - Poor</option>
								<option value="1">1 - Terrible</option>
							</select>
						</div>
						
						<div style="margin-bottom: 20px;">
							<label style="display:block; font-weight:bold; margin-bottom:10px;">Any other comments?</label>
							<textarea name="comments" rows="4" style="width:100%; padding:10px;"></textarea>
						</div>
						
						<button type="submit" style="background:#0073aa; color:#fff; border:none; padding:12px 24px; border-radius:4px; font-weight:bold; cursor:pointer;">Submit & Unlock Certificate</button>
					</form>
				</div>

			<?php endif; ?>
			
		</div>
		<?php
		return ob_get_clean();
	}

	public function handle_certificate_download() {
		if ( isset( $_GET['emp_action'] ) && $_GET['emp_action'] == 'download_cert' && ! empty( $_GET['token'] ) ) {
			$token = sanitize_text_field( $_GET['token'] );
			
			global $wpdb;
			$table_attendees = $wpdb->prefix . 'emp_attendees';
			$table_feedback = $wpdb->prefix . 'emp_feedback';
			
			$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table_attendees WHERE qr_token = %s", $token ) );
			
			if ( $attendee ) {
				// Verify they completed feedback
				$has_feedback = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_feedback WHERE attendee_id = %d", $attendee->id ) );
				if ( $has_feedback ) {
					require_once EMP_PLUGIN_DIR . 'services/class-emp-certificate-generator.php';
					$generator = new EMP_Certificate_Generator();
					$generator->generate( $attendee->id, 'D' );
					exit;
				} else {
					wp_die( __( 'You must complete the feedback form first.', 'event-management-plugin' ) );
				}
			}
		}
	}
}
