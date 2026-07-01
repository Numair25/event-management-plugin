<?php
/**
 * Settings Admin Page
 */
class EMP_Settings_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Settings', 'event-management-plugin' ),
			__( 'Settings', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-settings',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-management-plugin' ) );
		}

		if ( isset( $_POST['emp_save_settings'] ) && check_admin_referer( 'emp_save_settings_action', 'emp_save_settings_nonce' ) ) {
			$force_inr = isset( $_POST['emp_gf_force_inr'] ) ? 1 : 0;
			update_option( 'emp_gf_force_inr', $force_inr );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved successfully.', 'event-management-plugin' ) . '</p></div>';
		}

		$force_inr = get_option( 'emp_gf_force_inr', 0 );
		?>
		<div class="wrap">
			<h1><?php _e( 'Event Management Settings', 'event-management-plugin' ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'emp_save_settings_action', 'emp_save_settings_nonce' ); ?>
				<input type="hidden" name="emp_save_settings" value="1" />
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Currency Settings', 'event-management-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="emp_gf_force_inr" value="1" <?php checked( $force_inr, 1 ); ?> />
								<?php _e( 'Enable INR (₹) as the default currency in Gravity Forms', 'event-management-plugin' ); ?>
							</label>
							<p class="description"><?php _e( 'If checked, Gravity Forms will automatically use the Indian Rupee (₹) currency for all transactions.', 'event-management-plugin' ); ?></p>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
