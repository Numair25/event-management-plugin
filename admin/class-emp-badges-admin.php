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
							'x'     => floatval( $_POST['text_x'][$key] ),
							'y'     => floatval( $_POST['text_y'][$key] ),
							'w'     => floatval( $_POST['text_w'][$key] ),
							'h'     => floatval( $_POST['text_h'][$key] ),
						);
					}
				}
			}

			$design_config = array(
				'bg_image'   => esc_url_raw( $_POST['bg_image'] ),
				'qr_x'       => floatval( $_POST['qr_x'] ),
				'qr_y'       => floatval( $_POST['qr_y'] ),
				'qr_w'       => floatval( $_POST['qr_w'] ),
				'qr_h'       => floatval( $_POST['qr_h'] ),
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
		echo '<p>' . __( 'Configure badge layouts per ticket type. Use the new visual drag and drop builder to place your fields exactly where you want them on your background template.', 'event-management-plugin' ) . '</p>';
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
		
		$badge_width = get_post_meta( $ticket->event_id, '_emp_badge_width', true ) ?: 100;
		$badge_height = get_post_meta( $ticket->event_id, '_emp_badge_height', true ) ?: 150;

		$design = get_option( 'emp_badge_design_' . $ticket_id );
		if ( ! $design ) {
			$design = array(
				'bg_image'   => '',
				'qr_x'       => 10,
				'qr_y'       => $badge_height - 40,
				'qr_w'       => 30,
				'qr_h'       => 30,
				'text_lines' => array()
			);
		} else {
			// Backwards compatibility for old config
			if ( ! isset( $design['qr_x'] ) ) $design['qr_x'] = 10;
			if ( ! isset( $design['qr_y'] ) ) $design['qr_y'] = $badge_height - 40;
			if ( ! isset( $design['qr_w'] ) ) $design['qr_w'] = isset($design['qr_size']) ? $design['qr_size'] : 30;
			if ( ! isset( $design['qr_h'] ) ) $design['qr_h'] = isset($design['qr_size']) ? $design['qr_size'] : 30;
		}
		
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-resizable' );
		// Need jQuery UI CSS for resizable handles
		wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css' );

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
					// Backwards compat for coordinates
					if ( ! isset($t['x']) ) $t['x'] = 10;
					if ( ! isset($t['y']) ) $t['y'] = 10;
					if ( ! isset($t['w']) ) $t['w'] = 50;
					if ( ! isset($t['h']) ) $t['h'] = 10;
					$saved_fields[ $t['field'] ] = $t;
				}
			}
		}

		?>
		<style>
			#live-preview-box {
				width: 100%;
				aspect-ratio: <?php echo floatval($badge_width); ?>/<?php echo floatval($badge_height); ?>;
				background-color: #fff;
				background-size: 100% 100%;
				background-position: center;
				background-repeat: no-repeat;
				border: 1px solid #ccc;
				box-shadow: 0 4px 8px rgba(0,0,0,0.1);
				position: relative;
				overflow: hidden;
				font-family: sans-serif;
			}
			.draggable-element {
				position: absolute !important;
				border: 1px dashed #0073aa;
				background: rgba(255, 255, 255, 0.7);
				cursor: move;
				display: flex;
				align-items: center;
				justify-content: center;
				text-align: center;
				box-sizing: border-box;
				overflow: hidden;
				font-weight: bold;
				color: #333;
			}
			.draggable-element:hover {
				border: 2px dashed #0073aa;
			}
			.ui-resizable-handle {
				width: 10px;
				height: 10px;
				background-color: #0073aa;
				border: 1px solid #fff;
			}
			.ui-resizable-se {
				right: -5px;
				bottom: -5px;
			}
			.draggable-element[data-type="qr"] {
				background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>');
				background-size: 100% 100%;
				background-repeat: no-repeat;
				background-color: rgba(255,255,255,0.9);
			}
		</style>
		<div class="wrap">
			<h1><?php printf( __( 'Edit Badge Design: %s - %s', 'event-management-plugin' ), $event_title, $ticket->name ); ?></h1>
			<a href="?post_type=emp_event&page=emp-badges">&laquo; Back to List</a>
			
			<div style="display: flex; gap: 30px; margin-top: 20px;">
				<div style="flex: 1; max-width: 600px;">
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
								<th><label><?php _e( 'Badge Background Image', 'event-management-plugin' ); ?></label></th>
								<td>
									<p class="description">Upload a template design (e.g. 100x150mm at 300dpi). It will fill the entire badge.</p>
									<input type="text" id="bg_image" name="bg_image" value="<?php echo esc_attr( $design['bg_image'] ); ?>" class="regular-text" />
									<input type="button" class="button" id="upload_bg_btn" value="Select Media" />
								</td>
							</tr>
							<tr>
								<th colspan="2">
									<h3 style="margin-bottom:0;">QR Code (Drag to position)</h3>
									<p class="description">Drag and resize the QR code over the blank space on your template.</p>
									<!-- QR Coordinates -->
									<input type="hidden" id="qr_x" name="qr_x" value="<?php echo esc_attr( $design['qr_x'] ); ?>" />
									<input type="hidden" id="qr_y" name="qr_y" value="<?php echo esc_attr( $design['qr_y'] ); ?>" />
									<input type="hidden" id="qr_w" name="qr_w" value="<?php echo esc_attr( $design['qr_w'] ); ?>" />
									<input type="hidden" id="qr_h" name="qr_h" value="<?php echo esc_attr( $design['qr_h'] ); ?>" />
								</th>
							</tr>
							<tr>
								<th colspan="2">
									<h3 style="margin-bottom:0;">Dynamic Text Fields</h3>
									<p class="description">Check a box to add it to the canvas. Drag to position and resize.</p>
								</th>
							</tr>
							<tr>
								<td colspan="2" style="padding: 0;">
									<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
										<thead>
											<tr>
												<th style="width: 50px;">Show</th>
												<th>Field Name</th>
											</tr>
										</thead>
										<tbody>
											<?php 
											$index = 0;
											foreach ( $field_options as $val => $label ) : 
												$is_enabled = isset( $saved_fields[$val] );
												$size = $is_enabled ? $saved_fields[$val]['size'] : 12;
												$x = $is_enabled ? $saved_fields[$val]['x'] : 10;
												$y = $is_enabled ? $saved_fields[$val]['y'] : 10 + ($index * 15);
												$w = $is_enabled ? $saved_fields[$val]['w'] : 80;
												$h = $is_enabled ? $saved_fields[$val]['h'] : 10;
												
												if ( $override_gf_id || ( ! $is_enabled && empty( $saved_fields ) ) ) {
													if ( $index < 4 ) {
														$is_enabled = true;
														if ( $index == 0 ) $size = 16;
													} else {
														$is_enabled = false;
													}
												}
											?>
											<tr>
												<td>
													<input type="checkbox" name="text_enabled[<?php echo $index; ?>]" value="1" class="field-toggle" data-index="<?php echo $index; ?>" data-label="<?php echo esc_attr( $label ); ?>" <?php checked( $is_enabled ); ?> />
													<input type="hidden" name="text_field[<?php echo $index; ?>]" value="<?php echo esc_attr( $val ); ?>" />
													<input type="hidden" name="text_label[<?php echo $index; ?>]" value="<?php echo esc_attr( $label ); ?>" />
													<!-- Hidden Coordinate Inputs -->
													<input type="hidden" id="text_size_<?php echo $index; ?>" name="text_size[<?php echo $index; ?>]" value="<?php echo esc_attr( $size ); ?>" />
													<input type="hidden" id="text_x_<?php echo $index; ?>" name="text_x[<?php echo $index; ?>]" value="<?php echo esc_attr( $x ); ?>" />
													<input type="hidden" id="text_y_<?php echo $index; ?>" name="text_y[<?php echo $index; ?>]" value="<?php echo esc_attr( $y ); ?>" />
													<input type="hidden" id="text_w_<?php echo $index; ?>" name="text_w[<?php echo $index; ?>]" value="<?php echo esc_attr( $w ); ?>" />
													<input type="hidden" id="text_h_<?php echo $index; ?>" name="text_h[<?php echo $index; ?>]" value="<?php echo esc_attr( $h ); ?>" />
												</td>
												<td><strong><?php echo esc_html( $label ); ?></strong></td>
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
						<br>
						<?php submit_button( __( 'Save Design', 'event-management-plugin' ) ); ?>
					</form>
				</div>
				
				<div style="flex: 1; max-width: 500px;">
					<h3>Visual Drag & Drop Designer</h3>
					<p>Dimensions: <?php echo floatval($badge_width); ?>mm x <?php echo floatval($badge_height); ?>mm</p>
					
					<div id="live-preview-box">
						<!-- Background image set via JS -->
						<div id="element-qr" class="draggable-element" data-type="qr" style="display:none;"></div>
						<!-- Text elements injected here -->
					</div>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($){
			
			var badgeWidthMm = <?php echo floatval($badge_width); ?>;
			var badgeHeightMm = <?php echo floatval($badge_height); ?>;
			
			function getScale() {
				var previewWidth = $('#live-preview-box').width();
				return previewWidth / badgeWidthMm; // pixels per mm
			}

			// Background Image Upload
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
			
			$('#bg_image').on('change input', function() {
				var url = $(this).val();
				if (url) {
					$('#live-preview-box').css('background-image', 'url(' + url + ')');
				} else {
					$('#live-preview-box').css('background-image', 'none');
				}
			}).trigger('change');

			function applyDraggableResizable($el) {
				if ($el.hasClass('ui-draggable')) {
					return;
				}
				
				$el.draggable({
					containment: "#live-preview-box",
					stop: function(event, ui) {
						updateHiddenInputs($(this));
					}
				}).resizable({
					containment: "#live-preview-box",
					handles: "all", // All sides and corners
					stop: function(event, ui) {
						updateHiddenInputs($(this));
					},
					resize: function(event, ui) {
						if ($(this).data('type') === 'text') {
							var newHeightPx = ui.size.height;
							var scale = getScale();
							var mmHeight = newHeightPx / scale;
							// Match PDF generator logic: cap at 24pt max, 6pt min
							var pt = Math.min(mmHeight * 2.83, 24);
							pt = Math.max(pt, 6);
							$(this).css('font-size', pt + 'pt');
							$(this).find('.text-label').css('font-size', pt + 'pt');
						}
					}
				});
			}

			function updateHiddenInputs($el) {
				var scale = getScale();
				var type = $el.data('type');
				
				var pos = $el.position();
				var w = $el.width();
				var h = $el.height();
				
				var x_mm = pos.left / scale;
				var y_mm = pos.top / scale;
				var w_mm = w / scale;
				var h_mm = h / scale;
				
				if (type === 'qr') {
					$('#qr_x').val(x_mm.toFixed(2));
					$('#qr_y').val(y_mm.toFixed(2));
					$('#qr_w').val(w_mm.toFixed(2));
					$('#qr_h').val(h_mm.toFixed(2));
				} else if (type === 'text') {
					var idx = $el.data('index');
					$('#text_x_' + idx).val(x_mm.toFixed(2));
					$('#text_y_' + idx).val(y_mm.toFixed(2));
					$('#text_w_' + idx).val(w_mm.toFixed(2));
					$('#text_h_' + idx).val(h_mm.toFixed(2));
					
					// Save font size (pt) - match PDF generator caps
					var pt = Math.min(h_mm * 2.83, 24);
					pt = Math.max(pt, 6);
					$('#text_size_' + idx).val(pt.toFixed(1));
				}
			}

			function renderElementsFromInputs() {
				var scale = getScale();
				
				// QR Code
				var qr_x = parseFloat($('#qr_x').val()) * scale;
				var qr_y = parseFloat($('#qr_y').val()) * scale;
				var qr_w = parseFloat($('#qr_w').val()) * scale;
				var qr_h = parseFloat($('#qr_h').val()) * scale;
				
				var $qr = $('#element-qr');
				$qr.css({
					left: qr_x + 'px',
					top: qr_y + 'px',
					width: qr_w + 'px',
					height: qr_h + 'px'
				}).show();
				applyDraggableResizable($qr);

				// Text Fields
				$('.field-toggle').each(function() {
					var idx = $(this).data('index');
					var label = $(this).data('label');
					var isChecked = $(this).is(':checked');
					
					var $existing = $('#element-text-' + idx);
					
					if (isChecked) {
						if ($existing.length === 0) {
							// Create it
							var x = parseFloat($('#text_x_' + idx).val()) * scale;
							var y = parseFloat($('#text_y_' + idx).val()) * scale;
							var w = parseFloat($('#text_w_' + idx).val()) * scale;
							var h = parseFloat($('#text_h_' + idx).val()) * scale;
							var pt = parseFloat($('#text_size_' + idx).val());
							
							var html = '<div id="element-text-' + idx + '" class="draggable-element" data-type="text" data-index="' + idx + '" style="left:' + x + 'px; top:' + y + 'px; width:' + w + 'px; height:' + h + 'px; font-size:' + pt + 'pt;">';
							html += '<span class="text-label">' + label + '</span>';
							html += '</div>';
							
							$('#live-preview-box').append(html);
							applyDraggableResizable($('#element-text-' + idx));
						}
					} else {
						if ($existing.length > 0) {
							$existing.remove();
						}
					}
				});
			}

			$('.field-toggle').on('change', function() {
				if ($('.field-toggle:checked').length > 4) {
					alert('You can select a maximum of 4 text fields.');
					$(this).prop('checked', false);
					return;
				}
				renderElementsFromInputs();
			});

			// On resize of window, re-render to keep scale correct
			var resizeTimer;
			$(window).on('resize', function(e) {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function() {
					// We need to re-apply px values based on mm inputs
					$('#live-preview-box .draggable-element').each(function() {
						var type = $(this).data('type');
						var scale = getScale();
						if (type === 'qr') {
							$(this).css({
								left: (parseFloat($('#qr_x').val()) * scale) + 'px',
								top: (parseFloat($('#qr_y').val()) * scale) + 'px',
								width: (parseFloat($('#qr_w').val()) * scale) + 'px',
								height: (parseFloat($('#qr_h').val()) * scale) + 'px'
							});
						} else {
							var idx = $(this).data('index');
							$(this).css({
								left: (parseFloat($('#text_x_' + idx).val()) * scale) + 'px',
								top: (parseFloat($('#text_y_' + idx).val()) * scale) + 'px',
								width: (parseFloat($('#text_w_' + idx).val()) * scale) + 'px',
								height: (parseFloat($('#text_h_' + idx).val()) * scale) + 'px'
							});
						}
					});
				}, 250);
			});

			// Init
			setTimeout(renderElementsFromInputs, 100);
		});
		</script>
		<?php
	}
}
