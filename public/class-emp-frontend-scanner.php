<?php
/**
 * Frontend Scanning Page (Shortcode).
 */
class EMP_Frontend_Scanner {

	public function register_shortcodes() {
		add_shortcode( 'emp_frontend_scanner', array( $this, 'render_scanner' ) );
	}

	public function render_scanner( $atts ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'scan_attendees' ) ) {
			return '<p>' . __( 'You must be logged in as Scanning Staff to view this page.', 'event-management-plugin' ) . '</p>';
		}

		wp_enqueue_script( 'jquery' );

		$nonce = wp_create_nonce( 'wp_rest' );
		$rest_url = esc_url_raw( rest_url( 'emp/v1/' ) );
		
		ob_start();
		?>
		<div class="emp-frontend-scanner-wrapper" style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
			<h1><?php _e( 'Event Check-in Scanner', 'event-management-plugin' ); ?></h1>
			
			<div id="setup-view">
				<h3><?php _e( 'Select Event', 'event-management-plugin' ); ?></h3>
				<select id="event-select" style="width: 100%; padding: 10px; margin-bottom: 20px;">
					<option value="">-- <?php _e( 'Select an Event', 'event-management-plugin' ); ?> --</option>
				</select>

				<div id="scan-point-wrapper" style="display: none;">
					<h3><?php _e( 'Select Scan Point (Station)', 'event-management-plugin' ); ?></h3>
					<select id="scan-point-select" style="width: 100%; padding: 10px; margin-bottom: 20px;"></select>
				</div>
				
				<button id="start-scanning-btn" class="button button-primary" style="width: 100%; padding: 15px; font-size: 18px;" disabled><?php _e( 'Start Scanning', 'event-management-plugin' ); ?></button>
			</div>
			
			<div id="scanner-view" style="display: none;">
				<h3 id="current-station-name"></h3>
				<button id="stop-scanning-btn" class="button" style="margin-bottom: 15px;"><?php _e( 'Change Station', 'event-management-plugin' ); ?></button>
				
				<div id="reader" style="width: 100%; min-height: 300px; border: 2px solid #ccc; margin-bottom: 20px;"></div>
				
				<div id="scan-result" style="padding: 15px; border-radius: 5px; margin-bottom: 20px; display: none;"></div>
				
				<hr/>
				<h4><?php _e( 'Manual Lookup', 'event-management-plugin' ); ?></h4>
				<input type="text" id="manual-query" placeholder="<?php esc_attr_e( 'Search by Name or Email...', 'event-management-plugin' ); ?>" style="width: 70%; padding: 10px;" />
				<button id="manual-search-btn" class="button" style="padding: 10px;"><?php _e( 'Search', 'event-management-plugin' ); ?></button>
				<div id="manual-results" style="margin-top: 15px; text-align: left;"></div>
			</div>
		</div>

		<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
		<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
		
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			if (typeof jQuery === 'undefined') return;
			const $ = jQuery;
			
			const restUrl = '<?php echo $rest_url; ?>';
			const nonce = '<?php echo $nonce; ?>';
			let html5QrcodeScanner = null;
			let currentPointId = null;
			let isProcessing = false;

			let allPoints = [];

			// Fetch Scan Points
			$.ajax({
				url: restUrl + 'scan-points',
				method: 'GET',
				beforeSend: function ( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', nonce );
				}
			}).done(function( points ) {
				allPoints = points;
				const eventSelect = $('#event-select');
				let events = {};
				
				points.forEach(function(p) {
					if (!events[p.event_id]) {
						events[p.event_id] = p.event_title;
						eventSelect.append('<option value="'+p.event_id+'">'+p.event_title+'</option>');
					}
				});
			});

			$('#event-select').change(function() {
				const eventId = $(this).val();
				const select = $('#scan-point-select');
				select.empty();
				select.append('<option value="">-- Select a Station --</option>');
				
				if (eventId) {
					$('#scan-point-wrapper').show();
					allPoints.forEach(function(p) {
						if (p.event_id == eventId) {
							select.append('<option value="'+p.id+'">'+p.name+' ('+p.mode+')</option>');
						}
					});
				} else {
					$('#scan-point-wrapper').hide();
					$('#start-scanning-btn').prop('disabled', true);
				}
			});

			$('#scan-point-select').change(function() {
				if ($(this).val()) {
					$('#start-scanning-btn').prop('disabled', false);
				} else {
					$('#start-scanning-btn').prop('disabled', true);
				}
			});

			$('#start-scanning-btn').click(function() {
				currentPointId = $('#scan-point-select').val();
				if(!currentPointId) return alert('Please select a station.');
				
				$('#current-station-name').text( $('#scan-point-select option:selected').text() );
				$('#setup-view').hide();
				$('#scanner-view').show();
				
				startScanner();
			});

			$('#stop-scanning-btn').click(function() {
				if (html5QrcodeScanner) {
					html5QrcodeScanner.clear();
				}
				$('#scanner-view').hide();
				$('#setup-view').show();
			});

			function startScanner() {
				html5QrcodeScanner = new Html5QrcodeScanner( "reader", { fps: 10, qrbox: {width: 250, height: 250} }, false );
				html5QrcodeScanner.render(onScanSuccess, onScanFailure);
			}

			function onScanSuccess(decodedText, decodedResult) {
				if (isProcessing) return;
				isProcessing = true;
				
				if (html5QrcodeScanner) {
					html5QrcodeScanner.pause();
				}

				processToken(decodedText);
			}
			
			function onScanFailure(error) {
				// quiet ignore
			}

			function processToken(token) {
				Swal.fire({
					title: 'Processing...',
					allowOutsideClick: false,
					didOpen: () => {
						Swal.showLoading();
					}
				});
				
				$.ajax({
					url: restUrl + 'scan',
					method: 'POST',
					data: { token: token, point_id: currentPointId },
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					}
				}).done(function( response ) {
					showResult(response);
				}).fail(function( jqXHR ) {
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: jqXHR.responseJSON ? jqXHR.responseJSON.message : 'Connection Error',
						willClose: () => {
							resumeScanner();
						}
					});
				});
			}

			function showResult(response) {
				let icon = response.success ? 'success' : 'error';
				let title = response.success ? 'Access Granted' : 'Access Denied';
				
				let html = '<p><strong>' + response.message + '</strong></p>';
				if (response.attendee) {
					html += '<div style="margin-top:15px; display:flex; align-items:center; justify-content:center; text-align:left; background: #f8f9fa; padding: 15px; border-radius: 8px;">';
					if (response.attendee.photo_url) {
						html += '<img src="'+response.attendee.photo_url+'" style="width:80px; height:80px; border-radius:50%; margin-right:15px; object-fit:cover; border: 2px solid #ddd;" />';
					}
					html += '<div>';
					html += '<strong>Name:</strong> ' + response.attendee.name + '<br/>';
					html += '<strong>Ticket:</strong> ' + response.attendee.ticket_type;
					html += '</div></div>';
				}
				
				Swal.fire({
					icon: icon,
					title: title,
					html: html,
					timer: 3000,
					timerProgressBar: true,
					showConfirmButton: false,
					willClose: () => {
						resumeScanner();
					}
				});
			}

			function resumeScanner() {
				if (html5QrcodeScanner) {
					html5QrcodeScanner.resume();
				}
				isProcessing = false;
			}

			// Manual Lookup
			$('#manual-search-btn').click(function() {
				const query = $('#manual-query').val();
				if (query.length < 3) return alert('Enter at least 3 characters.');
				
				$('#manual-results').html('Searching...');
				
				$.ajax({
					url: restUrl + 'manual-lookup',
					method: 'POST',
					data: { query: query },
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					}
				}).done(function( attendees ) {
					let html = '';
					if (attendees.length === 0) {
						html = 'No results found.';
					} else {
						attendees.forEach(function(a) {
							html += '<div style="border:1px solid #ddd; padding:10px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">';
							html += '<div><strong>'+a.name+'</strong><br/>'+a.email+'<br/>Status: '+a.status+'</div>';
							html += '<button class="button manual-checkin-btn" data-token="'+a.token+'">Check-in</button>';
							html += '</div>';
						});
					}
					$('#manual-results').html(html);
				});
			});

			$(document).on('click', '.manual-checkin-btn', function() {
				const token = $(this).data('token');
				$('#manual-results').empty();
				$('#manual-query').val('');
				processToken(token);
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
}
