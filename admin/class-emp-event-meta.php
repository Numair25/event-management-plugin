<?php
/**
 * Register Meta Boxes for Event CPT.
 */
class EMP_Event_Meta {

	public function register_meta_boxes() {
		add_meta_box(
			'emp_event_config',
			__( 'Event Configuration', 'event-management-plugin' ),
			array( $this, 'render_meta_box' ),
			'emp_event',
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'emp_save_event_meta', 'emp_event_meta_nonce' );
		
		$badge_width = get_post_meta( $post->ID, '_emp_badge_width', true );
		$badge_height = get_post_meta( $post->ID, '_emp_badge_height', true );
		$capacity = get_post_meta( $post->ID, '_emp_capacity', true );
		$gf_form_id = get_post_meta( $post->ID, '_emp_gf_form_id', true );
		$require_payment = get_post_meta( $post->ID, '_emp_require_payment', true );
		
		// Fallbacks
		if ( empty( $badge_width ) ) $badge_width = '100'; // mm
		if ( empty( $badge_height ) ) $badge_height = '150'; // mm
		
		?>
		<table class="form-table">
			<tr>
				<th><label for="emp_capacity"><?php _e( 'Event Capacity', 'event-management-plugin' ); ?></label></th>
				<td>
					<input type="number" id="emp_capacity" name="emp_capacity" value="<?php echo esc_attr( $capacity ); ?>" class="regular-text" />
					<p class="description"><?php _e( 'Maximum number of attendees (0 for unlimited).', 'event-management-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="emp_gf_form_id"><?php _e( 'Gravity Form ID', 'event-management-plugin' ); ?></label></th>
				<td>
					<?php if ( class_exists( 'GFFormsModel' ) ) : ?>
						<select id="emp_gf_form_id" name="emp_gf_form_id">
							<option value="">-- <?php _e( 'Select a Form', 'event-management-plugin' ); ?> --</option>
							<?php 
							$forms = GFFormsModel::get_forms();
							foreach( $forms as $form ) {
								$selected = ( $form->id == $gf_form_id ) ? 'selected' : '';
								echo '<option value="' . esc_attr( $form->id ) . '" ' . $selected . '>' . esc_html( $form->title ) . '</option>';
							}
							?>
						</select>
					<?php else : ?>
						<input type="number" id="emp_gf_form_id" name="emp_gf_form_id" value="<?php echo esc_attr( $gf_form_id ); ?>" class="regular-text" />
					<?php endif; ?>
					<p class="description"><?php _e( 'Select the Gravity Form used for registration.', 'event-management-plugin' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="emp_require_payment"><?php _e( 'Require Payment Confirmation?', 'event-management-plugin' ); ?></label></th>
				<td>
					<input type="checkbox" id="emp_require_payment" name="emp_require_payment" value="1" <?php checked( $require_payment, '1' ); ?> />
					<span class="description"><?php _e( 'Delay attendee creation until Gravity Forms marks the payment as Paid or Approved.', 'event-management-plugin' ); ?></span>
				</td>
			</tr>
			<tr>
				<th><label><?php _e( 'Badge Dimensions (mm)', 'event-management-plugin' ); ?></label></th>
				<td>
					<input type="number" step="0.1" name="emp_badge_width" value="<?php echo esc_attr( $badge_width ); ?>" placeholder="Width" style="width: 80px;" /> x 
					<input type="number" step="0.1" name="emp_badge_height" value="<?php echo esc_attr( $badge_height ); ?>" placeholder="Height" style="width: 80px;" />
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['emp_event_meta_nonce'] ) || ! wp_verify_nonce( $_POST['emp_event_meta_nonce'], 'emp_save_event_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'manage_event_settings', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['emp_capacity'] ) ) {
			update_post_meta( $post_id, '_emp_capacity', sanitize_text_field( $_POST['emp_capacity'] ) );
		}
		if ( isset( $_POST['emp_gf_form_id'] ) ) {
			update_post_meta( $post_id, '_emp_gf_form_id', sanitize_text_field( $_POST['emp_gf_form_id'] ) );
		}
		if ( isset( $_POST['emp_badge_width'] ) ) {
			update_post_meta( $post_id, '_emp_badge_width', sanitize_text_field( $_POST['emp_badge_width'] ) );
		}
		if ( isset( $_POST['emp_badge_height'] ) ) {
			update_post_meta( $post_id, '_emp_badge_height', sanitize_text_field( $_POST['emp_badge_height'] ) );
		}
		
		$require_payment = isset( $_POST['emp_require_payment'] ) ? '1' : '0';
		update_post_meta( $post_id, '_emp_require_payment', $require_payment );
	}
}
