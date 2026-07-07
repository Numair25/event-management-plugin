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

	public function ajax_global_search() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		if ( empty( $query ) ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		global $wpdb;
		$results = array();
		$search_term = '%' . $wpdb->esc_like( $query ) . '%';

		// 1. Search Attendees (by Name or Email)
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		$attendees = $wpdb->get_results( $wpdb->prepare( 
			"SELECT id, name, email FROM $table_attendees WHERE name LIKE %s OR email LIKE %s LIMIT 10", 
			$search_term, $search_term 
		) );
		foreach ( $attendees as $att ) {
			$results[] = array(
				'type'  => 'Attendee',
				'title' => $att->name . ' (' . $att->email . ')',
				'url'   => admin_url( 'edit.php?post_type=emp_event&page=emp-attendees&search=' . urlencode( $att->email ) )
			);
		}

		// 2. Search Events (by Title)
		$events = get_posts( array(
			'post_type'   => 'emp_event',
			'post_status' => 'any',
			's'           => $query,
			'numberposts' => 5
		) );
		foreach ( $events as $event ) {
			$results[] = array(
				'type'  => 'Event',
				'title' => $event->post_title,
				'url'   => get_edit_post_link( $event->ID, 'url' )
			);
		}

		// 3. Search Ticket Types (by Name)
		$table_tickets = $wpdb->prefix . 'emp_ticket_types';
		$tickets = $wpdb->get_results( $wpdb->prepare( 
			"SELECT id, name, event_id FROM $table_tickets WHERE name LIKE %s LIMIT 5", 
			$search_term 
		) );
		foreach ( $tickets as $ticket ) {
			$event_title = get_the_title( $ticket->event_id );
			$results[] = array(
				'type'  => 'Ticket Type',
				'title' => $event_title . ' - ' . $ticket->name,
				'url'   => admin_url( 'edit.php?post_type=emp_event&page=emp-ticket-types&event_id=' . $ticket->event_id )
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
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
			
			<!-- Global Search UI -->
			<div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,0.04); margin-bottom: 30px; margin-top: 20px;">
				<h2 style="margin-top:0;"><?php _e( 'Global Search', 'event-management-plugin' ); ?></h2>
				<p class="description"><?php _e( 'Search across all Attendees, Events, and Ticket Types within the plugin.', 'event-management-plugin' ); ?></p>
				<input type="text" id="emp-global-search" class="regular-text" placeholder="<?php esc_attr_e( 'Type a name, email, or event title...', 'event-management-plugin' ); ?>" style="padding: 10px; width: 100%; max-width: 600px; font-size: 16px;" />
				<span class="spinner" id="emp-search-spinner" style="float:none; margin-top:10px;"></span>
				
				<div id="emp-search-results" style="margin-top: 15px; max-width: 600px;"></div>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				var searchTimeout;
				$('#emp-global-search').on('input', function() {
					var query = $(this).val().trim();
					var resultsContainer = $('#emp-search-results');
					var spinner = $('#emp-search-spinner');
					
					clearTimeout(searchTimeout);
					
					if (query.length < 2) {
						resultsContainer.empty();
						return;
					}
					
					spinner.addClass('is-active');
					
					searchTimeout = setTimeout(function() {
						$.ajax({
							url: ajaxurl,
							method: 'POST',
							data: {
								action: 'emp_global_search',
								query: query
							}
						}).done(function(response) {
							spinner.removeClass('is-active');
							resultsContainer.empty();
							
							if (response.success && response.data.results.length > 0) {
								var html = '<ul style="margin: 0; padding: 0; list-style: none;">';
								$.each(response.data.results, function(index, item) {
									var badgeColor = '#0073aa';
									if (item.type === 'Event') badgeColor = '#28a745';
									if (item.type === 'Ticket Type') badgeColor = '#dc3545';
									
									html += '<li style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">';
									html += '<div><strong>' + item.title + '</strong></div>';
									html += '<div><span style="background: '+badgeColor+'; color: #fff; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin-right: 15px;">' + item.type + '</span>';
									html += '<a href="' + item.url + '" class="button button-small"><?php _e( 'View', 'event-management-plugin' ); ?></a></div>';
									html += '</li>';
								});
								html += '</ul>';
								resultsContainer.html(html);
							} else {
								resultsContainer.html('<p style="color: #d63638;"><?php _e( 'No results found.', 'event-management-plugin' ); ?></p>');
							}
						});
					}, 500);
				});
			});
			</script>

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
