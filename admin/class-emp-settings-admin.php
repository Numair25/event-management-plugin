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

		// 4. Search Scan Points (by Name)
		$table_scan_points = $wpdb->prefix . 'emp_scan_points';
		$scan_points = $wpdb->get_results( $wpdb->prepare( 
			"SELECT id, name, event_id FROM $table_scan_points WHERE name LIKE %s LIMIT 5", 
			$search_term 
		) );
		foreach ( $scan_points as $point ) {
			$event_title = get_the_title( $point->event_id );
			$results[] = array(
				'type'  => 'Scan Point',
				'title' => $event_title . ' - ' . $point->name,
				'url'   => admin_url( 'edit.php?post_type=emp_event&page=emp-scan-points' ) // Settings are event global
			);
		}

		// 5. Search Static Admin Pages
		$admin_pages = array(
			'Get Started' => admin_url( 'edit.php?post_type=emp_event&page=emp-get-started' ),
			'Dashboard & Reports' => admin_url( 'edit.php?post_type=emp_event&page=emp-dashboard' ),
			'All Events' => admin_url( 'edit.php?post_type=emp_event' ),
			'Add New Event' => admin_url( 'post-new.php?post_type=emp_event' ),
			'Ticket Types' => admin_url( 'edit.php?post_type=emp_event&page=emp-ticket-types' ),
			'Attendees' => admin_url( 'edit.php?post_type=emp_event&page=emp-attendees' ),
			'Badge Designs' => admin_url( 'edit.php?post_type=emp_event&page=emp-badges' ),
			'Walk-in Kiosk' => admin_url( 'edit.php?post_type=emp_event&page=emp-kiosk' ),
			'Import Attendees' => admin_url( 'edit.php?post_type=emp_event&page=emp-import-attendees' ),
			'Scan Points' => admin_url( 'edit.php?post_type=emp_event&page=emp-scan-points' ),
			'Scan Badges (Frontend)' => site_url( '/scanner-access/' ),
			'Communications (WhatsApp/Email)' => admin_url( 'edit.php?post_type=emp_event&page=emp-communications' ),
			'Scan Statistics' => admin_url( 'edit.php?post_type=emp_event&page=emp-scan-stats' ),
			'Audit Log' => admin_url( 'edit.php?post_type=emp_event&page=emp-audit-log' ),
			'Settings' => admin_url( 'edit.php?post_type=emp_event&page=emp-settings' ),
		);

		$query_lower = strtolower( $query );
		foreach ( $admin_pages as $page_title => $url ) {
			if ( strpos( strtolower( $page_title ), $query_lower ) !== false ) {
				$results[] = array(
					'type'  => 'Page',
					'title' => $page_title,
					'url'   => $url
				);
			}
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_event_settings' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'event-management-plugin' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

		if ( isset( $_POST['emp_save_settings'] ) && check_admin_referer( 'emp_save_settings_action', 'emp_save_settings_nonce' ) ) {
			if ( $active_tab == 'general' ) {
				$force_inr = isset( $_POST['emp_gf_force_inr'] ) ? 1 : 0;
				update_option( 'emp_gf_force_inr', $force_inr );
			} elseif ( $active_tab == 'validation' ) {
				$rules = get_option( 'emp_gf_unique_validation_rules', array() );
				$form_id = intval( $_POST['validation_form_id'] );
				if ( $form_id ) {
					$enabled = isset( $_POST['emp_validation_enabled'] ) ? 1 : 0;
					$field_id = sanitize_text_field( $_POST['emp_validation_field_id'] );
					$rules[ $form_id ] = array(
						'enabled' => $enabled,
						'field_id' => $field_id
					);
					update_option( 'emp_gf_unique_validation_rules', $rules );
				}
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved successfully.', 'event-management-plugin' ) . '</p></div>';
		}

		$force_inr = get_option( 'emp_gf_force_inr', 0 );
		?>
		<div class="wrap">
			<h1><?php _e( 'Event Management Settings', 'event-management-plugin' ); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?post_type=emp_event&page=emp-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e( 'General', 'event-management-plugin' ); ?></a>
				<a href="?post_type=emp_event&page=emp-settings&tab=validation" class="nav-tab <?php echo $active_tab == 'validation' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Form Validation', 'event-management-plugin' ); ?></a>
			</h2>
			
			<?php if ( $active_tab == 'general' ) : ?>
			
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
									var badgeColor = '#0073aa'; // Default for Attendee
									if (item.type === 'Event') badgeColor = '#28a745';
									else if (item.type === 'Ticket Type') badgeColor = '#dc3545';
									else if (item.type === 'Scan Point') badgeColor = '#6f42c1';
									else if (item.type === 'Page') badgeColor = '#17a2b8';
									
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
			<?php elseif ( $active_tab == 'validation' ) : 
				$forms = class_exists( 'GFAPI' ) ? GFAPI::get_forms() : array();
				$selected_form_id = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : ( !empty($forms) ? $forms[0]['id'] : 0 );
				$rules = get_option( 'emp_gf_unique_validation_rules', array() );
				$current_rule = isset( $rules[ $selected_form_id ] ) ? $rules[ $selected_form_id ] : array( 'enabled' => 0, 'field_id' => '' );
			?>
			<form method="post" action="">
				<?php wp_nonce_field( 'emp_save_settings_action', 'emp_save_settings_nonce' ); ?>
				<input type="hidden" name="emp_save_settings" value="1" />
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e( 'Select Gravity Form', 'event-management-plugin' ); ?></th>
						<td>
							<select id="emp_validation_form_select" name="validation_form_id">
								<?php foreach ( $forms as $form ) : ?>
									<option value="<?php echo esc_attr( $form['id'] ); ?>" <?php selected( $selected_form_id, $form['id'] ); ?>><?php echo esc_html( $form['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<script>
							jQuery('#emp_validation_form_select').change(function(){
								window.location.href = '?post_type=emp_event&page=emp-settings&tab=validation&form_id=' + jQuery(this).val();
							});
							</script>
						</td>
					</tr>
					<?php if ( $selected_form_id ) : 
						$form = GFAPI::get_form( $selected_form_id );
					?>
					<tr>
						<th scope="row"><?php _e( 'Unique Validation', 'event-management-plugin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="emp_validation_enabled" value="1" <?php checked( $current_rule['enabled'], 1 ); ?> />
								<?php _e( 'Enable unique validation for this form', 'event-management-plugin' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Unique Field', 'event-management-plugin' ); ?></th>
						<td>
							<select name="emp_validation_field_id">
								<option value=""><?php _e( '-- Select Field --', 'event-management-plugin' ); ?></option>
								<?php foreach ( $form['fields'] as $field ) : 
									if ( in_array( $field->type, array( 'html', 'section', 'page' ) ) ) continue;
								?>
									<option value="<?php echo esc_attr( $field->id ); ?>" <?php selected( $current_rule['field_id'], $field->id ); ?>><?php echo esc_html( $field->label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php _e( 'Select the field that must be unique (e.g., Email, Employee ID). If someone submits the form and this field matches an existing attendee in the system, it will be rejected.', 'event-management-plugin' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
