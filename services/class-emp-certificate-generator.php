<?php
/**
 * Generates Certificate PDFs using mPDF.
 */
class EMP_Certificate_Generator {

	public function generate( $attendee_id, $output = 'D' ) {
		if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
			return false;
		}

		global $wpdb;
		$table_attendees = $wpdb->prefix . 'emp_attendees';
		
		$attendee = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_attendees WHERE id = %d", $attendee_id ) );
		if ( ! $attendee ) return false;

		$event_title = get_the_title( $attendee->event_id );
		
		// Optional: Fetch custom certificate design from Event Meta
		// For MVP, we'll hardcode a clean landscape template
		$bg_color = get_post_meta( $attendee->event_id, '_emp_cert_bg_color', true ) ?: '#f9f9f9';
		
		$mpdf = new \Mpdf\Mpdf(array(
			'mode' => 'utf-8',
			'format' => 'A4-L', // Landscape
			'margin_left' => 15,
			'margin_right' => 15,
			'margin_top' => 15,
			'margin_bottom' => 15,
		));

		$html = '
		<div style="background-color: ' . esc_attr( $bg_color ) . '; padding: 50px; text-align: center; border: 5px solid #333; height: 100%; font-family: sans-serif;">
			<h1 style="font-size: 40pt; color: #333; margin-top: 50px;">Certificate of Attendance</h1>
			<p style="font-size: 20pt; margin-top: 50px;">This is to certify that</p>
			<h2 style="font-size: 35pt; color: #0056b3; border-bottom: 2px solid #333; display: inline-block; padding-bottom: 10px; margin-top: 20px;">' . esc_html( $attendee->name ) . '</h2>
			<p style="font-size: 18pt; margin-top: 40px;">has successfully participated in the event</p>
			<h3 style="font-size: 25pt; color: #333; margin-top: 20px;">' . esc_html( $event_title ) . '</h3>
			<p style="font-size: 14pt; margin-top: 100px;">' . date( 'F j, Y' ) . '</p>
		</div>';

		$mpdf->WriteHTML( $html );
		$mpdf->Output( 'certificate_' . $attendee_id . '.pdf', $output );
		return true;
	}
}
