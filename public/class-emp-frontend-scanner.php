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
			$login_url = wp_login_url( home_url( $_SERVER['REQUEST_URI'] ) );
			$message = is_user_logged_in() 
				? __( 'You are logged in, but you do not have permission to scan attendees. Please log in as Scanning Staff.', 'event-management-plugin' )
				: __( 'You must be logged in as Scanning Staff to view this page.', 'event-management-plugin' );
				
			return '
			<div style="max-width: 500px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; border: 1px solid #ccd0d4;">
				<h2 style="margin-top: 0; color: #d63638;">' . __( 'Access Denied', 'event-management-plugin' ) . '</h2>
				<p style="font-size: 16px; color: #3c434a; margin-bottom: 25px;">' . $message . '</p>
				<a href="' . esc_url( $login_url ) . '" style="background: #0073aa; color: #fff; text-decoration: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; display: inline-block;">' . __( 'Log In to Scanner', 'event-management-plugin' ) . '</a>
			</div>';
		}

		wp_enqueue_script( 'jquery' );

		$nonce = wp_create_nonce( 'wp_rest' );
		$rest_url = esc_url_raw( rest_url( 'emp/v1/' ) );
		
		ob_start();
		?>
		<style>
			.emp-frontend-scanner-wrapper {
				max-width: 600px;
				margin: 40px auto;
				background: #ffffff;
				padding: 30px;
				border-radius: 12px;
				box-shadow: 0 4px 15px rgba(0,0,0,0.05);
				text-align: center;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			}
			.emp-frontend-scanner-wrapper h1 {
				margin-top: 0;
				font-size: 24px;
				color: #2c3338;
				margin-bottom: 25px;
			}
			.emp-frontend-scanner-wrapper select, 
			.emp-frontend-scanner-wrapper input[type="text"] {
				width: 100%;
				padding: 12px 15px;
				margin-bottom: 20px;
				border: 1px solid #ccd0d4;
				border-radius: 6px;
				font-size: 16px;
				box-sizing: border-box;
			}
			.emp-frontend-scanner-wrapper .emp-btn {
				background: #0073aa;
				color: #ffffff;
				border: none;
				border-radius: 6px;
				padding: 14px 20px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				width: 100%;
				transition: background 0.3s;
			}
			.emp-frontend-scanner-wrapper .emp-btn:hover:not(:disabled) {
				background: #005177;
			}
			.emp-frontend-scanner-wrapper .emp-btn:disabled {
				background: #a0a5aa;
				cursor: not-allowed;
			}
			.emp-frontend-scanner-wrapper .emp-btn-secondary {
				background: #f0f0f1;
				color: #2c3338;
				border: 1px solid #8c8f94;
			}
			.emp-frontend-scanner-wrapper .emp-btn-secondary:hover {
				background: #dcdcde;
			}
			.emp-manual-result-card {
				border: 1px solid #e2e4e7;
				padding: 15px;
				margin-bottom: 15px;
				border-radius: 8px;
				display: flex;
				justify-content: space-between;
				align-items: center;
				background: #fafafa;
				text-align: left;
			}
			.emp-manual-result-card-info strong {
				font-size: 16px;
				color: #1d2327;
			}
			.emp-manual-result-card-info span {
				display: block;
				font-size: 13px;
				color: #646970;
				margin-top: 4px;
			}
			#reader {
				border: none !important;
				border-radius: 12px;
				overflow: hidden;
				box-shadow: 0 2px 8px rgba(0,0,0,0.1);
				margin-bottom: 25px;
			}
		</style>
		<div class="emp-frontend-scanner-wrapper">
			<h1><?php _e( 'Event Check-in Scanner', 'event-management-plugin' ); ?></h1>
			
			<div id="setup-view">
				<h3 style="margin-bottom: 10px; font-size: 16px; color: #3c434a; text-align: left;"><?php _e( 'Select Event', 'event-management-plugin' ); ?></h3>
				<select id="event-select">
					<option value="">-- <?php _e( 'Select an Event', 'event-management-plugin' ); ?> --</option>
				</select>

				<div id="scan-point-wrapper" style="display: none;">
					<h3 style="margin-bottom: 10px; font-size: 16px; color: #3c434a; text-align: left;"><?php _e( 'Select Scan Point (Station)', 'event-management-plugin' ); ?></h3>
					<select id="scan-point-select"></select>
				</div>
				
				<button id="start-scanning-btn" class="emp-btn" disabled><?php _e( 'Start Scanning', 'event-management-plugin' ); ?></button>
			</div>
			
			<div id="scanner-view" style="display: none;">
				<h3 id="current-station-name" style="margin-top:0; color:#0073aa;"></h3>
				<button id="stop-scanning-btn" class="emp-btn emp-btn-secondary" style="margin-bottom: 20px; width: auto; padding: 8px 15px; font-size: 14px;"><?php _e( 'Change Station', 'event-management-plugin' ); ?></button>
				
				<div id="reader" style="width: 100%; min-height: 300px; background:#000;"></div>
				
				<hr style="border: 0; border-top: 1px solid #ccd0d4; margin: 30px 0;"/>
				
				<h4 style="text-align: left; margin-bottom: 15px; font-size: 18px;"><?php _e( 'Manual Lookup', 'event-management-plugin' ); ?></h4>
				<div style="display: flex; gap: 10px; margin-bottom: 20px;">
					<input type="text" id="manual-query" placeholder="<?php esc_attr_e( 'Search by Name or Email...', 'event-management-plugin' ); ?>" style="margin-bottom: 0;" />
					<button id="manual-search-btn" class="emp-btn" style="width: auto; padding: 12px 20px;"><?php _e( 'Search', 'event-management-plugin' ); ?></button>
				</div>
				<div id="manual-results"></div>
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
			let html5QrCode = null;
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
				if (html5QrCode) {
					html5QrCode.stop().then(() => {
						html5QrCode.clear();
					}).catch(err => {
						console.log("Failed to stop scanner", err);
					});
				}
				$('#scanner-view').hide();
				$('#setup-view').show();
			});

			function startScanner() {
				html5QrCode = new Html5Qrcode("reader");
				html5QrCode.start(
					{ facingMode: "environment" }, 
					{
						fps: 10, 
						qrbox: { width: 250, height: 250 }
					},
					onScanSuccess,
					onScanFailure
				).catch(err => {
					console.log("Error starting scanner", err);
					alert("Could not start camera. Please ensure camera permissions are granted.");
				});
			}

			function onScanSuccess(decodedText, decodedResult) {
				if (isProcessing) return;
				isProcessing = true;
				
				if (html5QrCode) {
					html5QrCode.pause();
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
				if (html5QrCode) {
					html5QrCode.resume();
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
						html = '<p style="color: #646970;">No results found.</p>';
					} else {
						attendees.forEach(function(a) {
							html += '<div class="emp-manual-result-card">';
							html += '<div class="emp-manual-result-card-info"><strong>'+a.name+'</strong><span>'+a.email+'</span><span>Status: '+a.status+'</span></div>';
							html += '<button class="emp-btn manual-checkin-btn" style="width: auto; padding: 10px 15px;" data-token="'+a.token+'">Check-in</button>';
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
