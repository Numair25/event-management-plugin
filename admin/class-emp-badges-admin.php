<?php
/**
 * Admin UI for managing Badge Designs.
 */
class EMP_Badges_Admin {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Badge Designs', 'event-management-plugin' ),
			__( 'Badge Designs', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-badges',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( isset( $_POST['emp_save_badge_design'] ) ) {
			check_admin_referer( 'save_badge_design' );
			
			$ticket_type_id = intval( $_POST['ticket_type_id'] );
			
			$text_lines = array();
			if ( isset( $_POST['text_field'] ) && is_array( $_POST['text_field'] ) ) {
				foreach ( $_POST['text_field'] as $key => $field_val ) {
					if ( isset( $_POST['text_enabled'][$key] ) && $_POST['text_enabled'][$key] == '1' ) {
						$text_lines[] = array(
							'field' => sanitize_text_field( $field_val ),
							'label' => sanitize_text_field( $_POST['text_label'][$key] ),
							'size'  => floatval( $_POST['text_size'][$key] ),
						);
					}
				}
			}

			$design_config = array(
				'bg_image'   => esc_url_raw( $_POST['bg_image'] ),
				'event_logo_width' => floatval( $_POST['event_logo_width'] ),
				'bg_image_width' => floatval( $_POST['bg_image_width'] ),
				'qr_size'    => floatval( $_POST['qr_size'] ),
				'text_lines' => $text_lines,
				'gf_form_id' => isset( $_POST['gf_form_id'] ) ? intval( $_POST['gf_form_id'] ) : 0,
			);
			
			update_option( 'emp_badge_design_' . $ticket_type_id, $design_config );
			echo '<div class="updated"><p>' . __( 'Badge design saved.', 'event-management-plugin' ) . '</p></div>';
		}
		
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$ticket_id = isset( $_GET['ticket_id'] ) ? intval( $_GET['ticket_id'] ) : 0;
		$override_gf_id = isset( $_GET['gf_form_id'] ) ? intval( $_GET['gf_form_id'] ) : 0;
		
		if ( $action == 'edit' && $ticket_id ) {
			$this->render_form( $ticket_id, $override_gf_id );
		} else {
			$this->render_list();
		}
	}

	private function render_list() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		$results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY event_id ASC" );
		
		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . __( 'Badge Designs', 'event-management-plugin' ) . '</h1>';
		echo '<p>' . __( 'Configure badge layouts per ticket type. Artwork should be uploaded at the correct physical dimensions (e.g., A6).', 'event-management-plugin' ) . '</p>';
		echo '<hr class="wp-header-end">';
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>' . __( 'Event', 'event-management-plugin' ) . '</th><th>' . __( 'Ticket Type', 'event-management-plugin' ) . '</th><th>' . __( 'Status', 'event-management-plugin' ) . '</th><th>' . __( 'Actions', 'event-management-plugin' ) . '</th></tr></thead>';
		echo '<tbody>';
		
		if ( $results ) {
			foreach ( $results as $row ) {
				$event_title = get_the_title( $row->event_id );
				$edit_url = wp_nonce_url( "?post_type=emp_event&page=emp-badges&action=edit&ticket_id={$row->id}", 'edit_badge_' . $row->id );
				
				$design = get_option( 'emp_badge_design_' . $row->id );
				$status = $design ? '<span style="color:green;">Configured</span>' : '<span style="color:red;">Not Configured</span>';
				
				echo '<tr>';
				echo '<td>' . esc_html( $event_title ) . '</td>';
				echo '<td>' . esc_html( $row->name ) . '</td>';
				echo '<td>' . $status . '</td>';
				echo '<td><a href="' . esc_url( $edit_url ) . '">' . __( 'Edit Design', 'event-management-plugin' ) . '</a></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="4">' . __( 'No ticket types found.', 'event-management-plugin' ) . '</td></tr>';
		}
		
		echo '</tbody></table></div>';
	}

	private function render_form( $ticket_id, $override_gf_id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'emp_ticket_types';
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $ticket_id ) );
		$event_title = get_the_title( $ticket->event_id );
		
		$design = get_option( 'emp_badge_design_' . $ticket_id );
		if ( ! $design ) {
			$design = array(
				'bg_image'   => '',
				'event_logo_width' => 35,
				'bg_image_width' => 35,
				'qr_size'    => 30,
				'text_lines' => array()
			);
		} else {
			if ( ! isset( $design['event_logo_width'] ) ) $design['event_logo_width'] = isset($design['event_logo_size']) ? $design['event_logo_size'] : 35;
			if ( ! isset( $design['bg_image_width'] ) ) $design['bg_image_width'] = isset($design['bg_image_size']) ? $design['bg_image_size'] : 35;
		}
		
		wp_enqueue_media();
		$gf_form_id = $override_gf_id ? $override_gf_id : (isset( $design['gf_form_id'] ) && $design['gf_form_id'] ? intval( $design['gf_form_id'] ) : get_post_meta( $ticket->event_id, '_emp_gf_form_id', true ));
		
		$field_options = array();

		if ( ! empty( $gf_form_id ) && class_exists( 'GFAPI' ) ) {
			$form = GFAPI::get_form( $gf_form_id );
			if ( $form && isset( $form['fields'] ) ) {
				foreach ( $form['fields'] as $field ) {
					if ( in_array( $field->type, array( 'html', 'section', 'page' ) ) ) continue;
					$field_options['gf_' . $field->id] = $field->label;
				}
			}
		}
		
		$saved_fields = array();
		if ( isset( $design['text_lines'] ) && is_array( $design['text_lines'] ) ) {
			foreach ( $design['text_lines'] as $t ) {
				if ( ! empty( $t['field'] ) ) {
					$saved_fields[ $t['field'] ] = $t;
				}
			}
		}
		
		$event_thumb_id = get_post_thumbnail_id( $ticket->event_id );
		$event_thumb_url = $event_thumb_id ? wp_get_attachment_image_url( $event_thumb_id, 'full' ) : '';

		?>
		<div class="wrap">
			<h1><?php printf( __( 'Edit Badge Design: %s - %s', 'event-management-plugin' ), $event_title, $ticket->name ); ?></h1>
			<a href="?post_type=emp_event&page=emp-badges">&laquo; Back to List</a>
			
			<div style="display: flex; gap: 30px; margin-top: 20px;">
				<div style="flex: 1;">
					<form id="badge-design-form" method="post" action="?post_type=emp_event&page=emp-badges&action=edit&ticket_id=<?php echo $ticket_id; ?>">
						<?php wp_nonce_field( 'save_badge_design' ); ?>
						<input type="hidden" name="emp_save_badge_design" value="1" />
						<input type="hidden" name="ticket_type_id" value="<?php echo $ticket_id; ?>" />
						
						<table class="form-table">
							<tr>
								<th><label><?php _e( 'Gravity Form for Data', 'event-management-plugin' ); ?></label></th>
								<td>
									<?php if ( class_exists( 'GFFormsModel' ) ) : ?>
										<select id="gf_form_id" name="gf_form_id">
											<option value="">-- <?php _e( 'Select a Form', 'event-management-plugin' ); ?> --</option>
											<?php 
											$forms = GFFormsModel::get_forms();
											foreach( $forms as $f ) {
												$selected = ( $f->id == $gf_form_id ) ? 'selected' : '';
												echo '<option value="' . esc_attr( $f->id ) . '" ' . $selected . '>' . esc_html( $f->title ) . '</option>';
											}
											?>
										</select>
										<p class="description">Select the form to pull custom dynamic fields from. <button type="button" class="button button-small" onclick="window.location.href='?post_type=emp_event&page=emp-badges&action=edit&ticket_id=<?php echo $ticket_id; ?>&gf_form_id=' + document.getElementById('gf_form_id').value;">Load Fields</button></p>
									<?php else : ?>
										<p>Gravity Forms not active.</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th><label><?php _e( 'Event Image (Top Left)', 'event-management-plugin' ); ?></label></th>
								<td>
									<p class="description">Automatically uses Event's Featured Image.</p>
									<input type="hidden" id="event_thumb_url" value="<?php echo esc_url($event_thumb_url); ?>" />
									Width (mm): <input type="number" step="0.1" id="event_logo_width" name="event_logo_width" value="<?php echo esc_attr( $design['event_logo_width'] ); ?>" style="width: 70px;" class="live-update" />
								</td>
							</tr>
							<tr>
								<th><label><?php _e( 'Badge Image (Top Right)', 'event-management-plugin' ); ?></label></th>
								<td>
									<input type="text" id="bg_image" name="bg_image" value="<?php echo esc_attr( $design['bg_image'] ); ?>" class="regular-text live-update" />
									<input type="button" class="button" id="upload_bg_btn" value="Select Media" /><br><br>
									Width (mm): <input type="number" step="0.1" id="bg_image_width" name="bg_image_width" value="<?php echo esc_attr( $design['bg_image_width'] ); ?>" style="width: 70px;" class="live-update" />
								</td>
							</tr>
							<tr>
								<th><label><?php _e( 'QR Code Settings', 'event-management-plugin' ); ?></label></th>
								<td>
									Size (mm): <input type="number" step="0.1" id="qr_size" name="qr_size" value="<?php echo esc_attr( $design['qr_size'] ); ?>" style="width: 70px;" class="live-update" />
								</td>
							</tr>
							
							<tr>
								<th colspan="2">
									<h3 style="margin-bottom:0;">Dynamic Text Fields</h3>
									<p class="description">Select which fields to print. Layout is automatic.</p>
								</th>
							</tr>
							
							<tr>
								<td colspan="2" style="padding: 0;">
									<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
										<thead>
											<tr>
												<th style="width: 50px;">Show</th>
												<th>Field Name</th>
												<th style="width: 100px;">Size (pt)</th>
											</tr>
										</thead>
										<tbody>
											<?php 
											$index = 0;
											foreach ( $field_options as $val => $label ) : 
												$is_enabled = isset( $saved_fields[$val] );
												$size = $is_enabled ? $saved_fields[$val]['size'] : 12;
												
												// If they just loaded a new form, or if it's completely empty, default to checking them so they appear in Live Preview
												if ( $override_gf_id || ( ! $is_enabled && empty( $saved_fields ) ) ) {
													$is_enabled = true;
													if ( $index == 0 ) $size = 16;
												}
											?>
											<tr>
												<td>
													<input type="checkbox" name="text_enabled[<?php echo $index; ?>]" value="1" class="live-update live-toggle" data-label="<?php echo esc_attr( $label ); ?>" <?php checked( $is_enabled ); ?> />
													<input type="hidden" name="text_field[<?php echo $index; ?>]" value="<?php echo esc_attr( $val ); ?>" />
													<input type="hidden" name="text_label[<?php echo $index; ?>]" value="<?php echo esc_attr( $label ); ?>" />
												</td>
												<td><strong><?php echo esc_html( $label ); ?></strong></td>
												<td><input type="number" step="0.1" name="text_size[<?php echo $index; ?>]" value="<?php echo esc_attr( $size ); ?>" style="width: 70px;" class="live-update live-size" /></td>
											</tr>
											<?php 
												$index++;
											endforeach; 
											?>
										</tbody>
									</table>
								</td>
							</tr>
							
						</table>
						<?php submit_button( __( 'Save Design', 'event-management-plugin' ) ); ?>
					</form>
				</div>
				
				<div style="flex: 1; max-width: 400px;">
					<h3>Live Preview</h3>
					<div id="live-preview-box" style="width: 100%; aspect-ratio: 2/3; background: #fff; border: 1px solid #ccc; box-shadow: 0 4px 8px rgba(0,0,0,0.1); position: relative; overflow: hidden; padding: 20px; box-sizing: border-box; font-family: sans-serif;">
						<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 10px; border: none;">
							<tr>
								<td align="left" valign="top" id="preview-event-logo" style="border: none;"></td>
								<td align="right" valign="top" id="preview-badge-logo" style="border: none;"></td>
							</tr>
						</table>
						<hr style="border: 0; border-top: 2px solid #eee; margin: 15px 0;">
						<div id="preview-dynamic-fields" style="display: flex; flex-direction: column; gap: 10px;">
							<!-- Dynamic Fields render here -->
						</div>
						<div style="position: absolute; bottom: 20px; left: 0; right: 0; text-align: center;">
							<div id="preview-qr-code" style="display: inline-block; background: #eee;"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($){
			$('#upload_bg_btn').click(function(e) {
				e.preventDefault();
				var image_frame;
				if(image_frame){ image_frame.open(); }
				image_frame = wp.media({ title: 'Select Background', multiple : false, library : { type : 'image' } });
				image_frame.on('close',function() {
					var selection = image_frame.state().get('selection').first().toJSON();
					$('#bg_image').val(selection.url).trigger('change');
				});
				image_frame.open();
			});

			function updatePreview() {
				var previewWidth = $('#live-preview-box').width();
				// Base scaling (assume 100mm = 100% width of the box)
				// So 1mm = (previewWidth / 100) px

				var eventThumbUrl = $('#event_thumb_url').val();
				var eventLogoWidth = $('#event_logo_width').val();
				var eventLogoPx = (eventLogoWidth / 100) * previewWidth;
				if (eventThumbUrl) {
					$('#preview-event-logo').html('<img src="' + eventThumbUrl + '" style="width: ' + eventLogoPx + 'px;" />');
				} else {
					$('#preview-event-logo').html('<div style="width: ' + eventLogoPx + 'px; height: ' + (eventLogoPx/2) + 'px; background:#f0f0f0; text-align:center; font-size:10px; color:#999; display:flex; align-items:center; justify-content:center;">Event Logo</div>');
				}

				var bgImage = $('#bg_image').val();
				var bgImageWidth = $('#bg_image_width').val();
				var bgImagePx = (bgImageWidth / 100) * previewWidth;
				if (bgImage) {
					$('#preview-badge-logo').html('<img src="' + bgImage + '" style="width: ' + bgImagePx + 'px;" />');
				} else {
					$('#preview-badge-logo').html('<div style="width: ' + bgImagePx + 'px; height: ' + (bgImagePx/2) + 'px; background:#f0f0f0; text-align:center; font-size:10px; color:#999; display:flex; align-items:center; justify-content:center;">Badge Image</div>');
				}

				var qrSize = $('#qr_size').val();
				var qrSizePx = (qrSize / 100) * previewWidth;
				$('#preview-qr-code').html('<div style="width: ' + qrSizePx + 'px; height: ' + qrSizePx + 'px; background: url(https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example) center/cover;"></div>');

				var html = '';
				$('.live-toggle').each(function() {
					if ($(this).is(':checked')) {
						var label = $(this).data('label');
						var size = $(this).closest('tr').find('.live-size').val();
						var value = 'Sample ' + label.replace('GF: ', '');
						html += '<div style="margin-bottom: 5px;">';
						html += '<div style="font-size: ' + (size * 0.6) + 'pt; font-weight: bold; color: #555; text-transform: uppercase;">' + label.replace('GF: ', '') + '</div>';
						html += '<div style="font-size: ' + size + 'pt; border: 1px solid #ddd; border-radius: 4px; padding: 5px 10px; margin-top: 3px; background: #fafafa;">' + value + '</div>';
						html += '</div>';
					}
				});
				$('#preview-dynamic-fields').html(html);
			}

			$('.live-update').on('input change', updatePreview);
			updatePreview();
		});
		</script>
		<?php
	}
}
