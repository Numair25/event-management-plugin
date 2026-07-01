<?php
/**
 * Generates Badge PDFs using mPDF and QRCode.
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class EMP_Badge_Generator {

	public function generate_individual( $attendee_id, $output = 'D' ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE id = %d", $attendee_id ) );
		if ( ! $attendee ) return false;
		
		return $this->generate_pdf( array( $attendee ), 'badge_' . $attendee_id . '.pdf', $output );
	}

	public function generate_bulk( $ticket_type_id, $output = 'D' ) {
		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		// Only unprinted, paid/comp/pending (exclude refunded/cancelled)
		$attendees = $wpdb->get_results( $wpdb->prepare( 
			"SELECT * FROM $table_attendees WHERE ticket_type_id = %d AND printed_status = 0 AND status != 'cancelled'", 
			$ticket_type_id 
		) );
		
		if ( empty( $attendees ) ) return false;
		
		return $this->generate_pdf( $attendees, 'bulk_badges_' . $ticket_type_id . '.pdf', $output );
	}

	private function generate_pdf( $attendees, $filename, $output ) {
		if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
			return false;
		}

		$first_attendee = $attendees[0];
		$event_id = $first_attendee->event_id;
		$ticket_type_id = $first_attendee->ticket_type_id;

		$badge_width = get_post_meta( $event_id, '_emp_badge_width', true ) ?: 100;
		$badge_height = get_post_meta( $event_id, '_emp_badge_height', true ) ?: 150;
		
		$design = get_option( 'emp_badge_design_' . $ticket_type_id );
		if ( ! $design ) {
			wp_die( 'Badge design not configured for this ticket type.' );
		}

		$mpdf = new \Mpdf\Mpdf(array(
			'mode' => 'utf-8',
			'format' => array( $badge_width, $badge_height ),
			'margin_left' => 0,
			'margin_right' => 0,
			'margin_top' => 0,
			'margin_bottom' => 0,
			'margin_header' => 0,
			'margin_footer' => 0,
		));

		$qr_options = new QROptions([
			'version'    => 5,
			'outputType' => QRCode::OUTPUT_IMAGE_PNG,
			'eccLevel'   => QRCode::ECC_L,
		]);
		$qrcode = new QRCode($qr_options);

		$upload_dir = wp_upload_dir();
		
		foreach ( $attendees as $index => $attendee ) {
			if ( $index > 0 ) {
				$mpdf->AddPage();
			}

			// Generate QR Base64 Image
			$qr_base64 = $qrcode->render( $attendee->qr_token );

			// Determine Photo Path
			$photo_html = '';
			if ( ! empty( $attendee->photo_path ) ) {
				$absolute_photo_path = $upload_dir['basedir'] . '/' . str_replace( 'emp_photos/', '', $attendee->photo_path );
				// Adjust path logic in case it's just the file name
				if ( file_exists( $absolute_photo_path ) ) {
					$photo_html = '<img src="' . $absolute_photo_path . '" style="width:' . $design['photo_size'] . 'mm; height:' . $design['photo_size'] . 'mm;" />';
				} else {
					$photo_html = '<img src="' . $upload_dir['basedir'] . '/' . $attendee->photo_path . '" style="width:' . $design['photo_size'] . 'mm; height:' . $design['photo_size'] . 'mm;" />';
				}
			}

			// Event Logo (Featured Image of Event)
			$event_logo_html = '';
			$event_thumb_id = get_post_thumbnail_id( $event_id );
			if ( $event_thumb_id ) {
				$event_thumb_url = wp_get_attachment_image_url( $event_thumb_id, 'full' );
				if ( $event_thumb_url ) {
					$event_logo_width = isset( $design['event_logo_width'] ) ? $design['event_logo_width'] : 35;
					$event_logo_html = '<img src="' . esc_url( $event_thumb_url ) . '" style="width: ' . $event_logo_width . 'mm; background-color: #fff;" />';
				}
			}

			// Badge Artwork / Background Image
			$bg_html = '';
			if ( ! empty( $design['bg_image'] ) ) {
				$bg_width = isset( $design['bg_image_width'] ) ? $design['bg_image_width'] : 35;
				$bg_html = '<img src="' . esc_url( $design['bg_image'] ) . '" style="width: ' . $bg_width . 'mm; background-color: #fff;" />';
			}
			
			$dynamic_text_html = '';
			if ( isset( $design['text_lines'] ) && is_array( $design['text_lines'] ) ) {
				foreach ( $design['text_lines'] as $line ) {
					if ( empty( $line['field'] ) ) continue;
					
					$value = '';
					
					if ( strpos( $line['field'], 'gf_' ) === 0 ) {
						// It's a Gravity Form field
						$field_id = str_replace( 'gf_', '', $line['field'] );
						$gf_form_id = isset( $design['gf_form_id'] ) && $design['gf_form_id'] ? intval( $design['gf_form_id'] ) : get_post_meta( $attendee->event_id, '_emp_gf_form_id', true );
						
						if ( $gf_form_id && class_exists( 'GFAPI' ) ) {
							global $wpdb;
							$entry_table = class_exists('GFFormsModel') ? GFFormsModel::get_entry_meta_table_name() : $wpdb->prefix . 'gf_entry_meta';
							
							$entry_id = $wpdb->get_var( $wpdb->prepare( "
								SELECT entry_id FROM $entry_table 
								WHERE form_id = %d AND meta_value = %s
								LIMIT 1
							", $gf_form_id, $attendee->email ) );
							
							if ( $entry_id ) {
								$entry = GFAPI::get_entry( $entry_id );
								if ( ! is_wp_error( $entry ) ) {
									$values = array();
									if ( isset( $entry[ $field_id ] ) && $entry[ $field_id ] !== '' ) {
										$values[] = $entry[ $field_id ];
									} else {
										// Complex fields like Name/Address store values in sub-keys e.g., "1.3", "1.6"
										foreach ( $entry as $k => $v ) {
											if ( strpos( (string)$k, $field_id . '.' ) === 0 && $v !== '' ) {
												$values[] = $v;
											}
										}
									}
									
									if ( ! empty( $values ) ) {
										$value = implode( ' ', $values );
									}
								}
							}
						}
					} else {
						switch ( $line['field'] ) {
							case 'name':
								$value = $attendee->name;
								break;
							case 'email':
								$value = $attendee->email;
								break;
							case 'organization':
								$value = $attendee->organization;
								break;
							case 'ticket_type':
								global $wpdb;
								$table_ticket_types = $wpdb->prefix . 'emp_ticket_types';
								$value = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM $table_ticket_types WHERE id = %d", $attendee->ticket_type_id ) );
								break;
							case 'phone':
								$value = $attendee->phone;
								break;
						}
					}
					
					if ( $value ) {
						// Process Gravity Forms file uploads (JSON arrays or URLs)
						if ( is_string( $value ) ) {
							$decoded = json_decode( $value, true );
							if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
								$value = $decoded[0]; // Take first file
							}
							
							if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
								$ext = strtolower( pathinfo( parse_url( $value, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
								if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) ) ) {
									$value = '<img src="' . esc_url( $value ) . '" style="max-height: 25mm; max-width: 100%; display: block;" />';
								} else {
									$value = esc_html( basename( parse_url( $value, PHP_URL_PATH ) ) );
								}
							} else {
								$value = esc_html( $value );
							}
						} else {
							$value = esc_html( $value );
						}

						$label_text = isset($line['label']) ? str_replace('GF: ', '', $line['label']) : $line['field'];
						$size = $line['size'];
						$label_size = $size * 0.6;
						$dynamic_text_html .= '<div style="margin-bottom: 2mm;">';
						$dynamic_text_html .= '<div style="font-size: ' . $label_size . 'pt; font-weight: bold; color: #555; text-transform: uppercase;">' . esc_html( $label_text ) . '</div>';
						$dynamic_text_html .= '<div style="font-size: ' . $size . 'pt; border: 1px solid #ddd; border-radius: 4px; padding: 1mm 2mm; margin-top: 1mm; background: #fafafa; color: #000;">' . $value . '</div>';
						$dynamic_text_html .= '</div>';
					}
				}
			}

			$qr_size = isset( $design['qr_size'] ) ? $design['qr_size'] : 30;

			$html = '
				<div style="position: relative; width: ' . $badge_width . 'mm; height: ' . $badge_height . 'mm; overflow: hidden; font-family: sans-serif; background: #fff; padding: 5mm; box-sizing: border-box;">
					<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 2mm;">
						<tr>
							<td align="left" valign="top">' . $event_logo_html . '</td>
							<td align="right" valign="top">' . $bg_html . '</td>
						</tr>
					</table>
					<div style="border-top: 1px solid #eee; margin-bottom: 3mm;"></div>
					
					<div style="width: 100%;">
						' . $dynamic_text_html . '
					</div>

					<div style="text-align: center; margin-top: 3mm; width: 100%;">
						<img src="' . $qr_base64 . '" style="width: ' . $qr_size . 'mm; height: ' . $qr_size . 'mm;" />
					</div>
				</div>
			';

			$mpdf->WriteHTML( $html );

			// Mark as printed if not previewing
			if ( $output == 'D' || $output == 'F' ) {
				global $wpdb;
				$table_attendees = $wpdb->prefix . 'emp_attendees';
				$wpdb->update( $table_attendees, array( 'printed_status' => 1 ), array( 'id' => $attendee->id ) );
			}
		}

		$mpdf->Output( $filename, $output );
		return true;
	}
}
