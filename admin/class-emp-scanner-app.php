<?php
/**
 * Admin UI for the web-based Scanner App.
 */
class EMP_Scanner_App {

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Scan Badges', 'event-management-plugin' ),
			__( 'Scan Badges', 'event-management-plugin' ),
			'scan_attendees', // Scanning staff can access
			'emp-scanner',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$nonce = wp_create_nonce( 'wp_rest' );
		$rest_url = esc_url_raw( rest_url( 'emp/v1/' ) );
		
		?>
		<div class="wrap">
			<div id="emp-scanner-app" style="max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
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
					
					<button id="start-scanning-btn" class="button button-primary button-hero" style="width: 100%;" disabled><?php _e( 'Start Scanning', 'event-management-plugin' ); ?></button>
				</div>
				
				<div id="scanner-view" style="display: none;">
					<h3 id="current-station-name"></h3>
					<button id="stop-scanning-btn" class="button" style="margin-bottom: 15px;"><?php _e( 'Change Station', 'event-management-plugin' ); ?></button>
					
					<div id="reader" style="width: 100%; min-height: 300px; border: 2px solid #ccc; margin-bottom: 20px;"></div>
					
					<div id="scan-result" style="padding: 15px; border-radius: 5px; margin-bottom: 20px; display: none;"></div>
					
					<hr/>
					<h4><?php _e( 'Manual Lookup', 'event-management-plugin' ); ?></h4>
					<input type="text" id="manual-query" placeholder="<?php esc_attr_e( 'Search by Name or Email...', 'event-management-plugin' ); ?>" style="width: 70%;" />
					<button id="manual-search-btn" class="button"><?php _e( 'Search', 'event-management-plugin' ); ?></button>
					<div id="manual-results" style="margin-top: 15px; text-align: left;"></div>
				</div>
			</div>
		</div>

		<!-- Load html5-qrcode -->
		<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
		
		<script>
		jQuery(document).ready(function($) {
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
				// handle scan failure, usually better to ignore and keep scanning
			}

			function processToken(token) {
				$('#scan-result').show().removeClass('updated error').css('background', '#fff3cd').html('<h3>Processing...</h3>');
				
				$.ajax({
					url: restUrl + 'scan',
					method: 'POST',
					data: {
						token: token,
						point_id: currentPointId
					},
					beforeSend: function ( xhr ) {
						xhr.setRequestHeader( 'X-WP-Nonce', nonce );
					}
				}).done(function( response ) {
					showResult(response);
				}).fail(function( jqXHR ) {
					$('#scan-result').addClass('error').html('<h3>Error</h3><p>' + jqXHR.responseJSON.message + '</p>');
					resumeScanner();
				});
			}

			function showResult(response) {
				const resultBox = $('#scan-result');
				resultBox.removeClass('updated error');
				
				let html = '<h3>' + (response.success ? 'Access Granted' : 'Access Denied') + '</h3>';
				html += '<p><strong>' + response.message + '</strong></p>';
				
				if (response.attendee) {
					html += '<div style="margin-top:10px; display:flex; align-items:center; justify-content:center;">';
					if (response.attendee.photo_url) {
						html += '<img src="'+response.attendee.photo_url+'" style="width:80px; height:80px; border-radius:50%; margin-right:15px; object-fit:cover;" />';
					}
					html += '<div style="text-align:left;">';
					html += '<strong>Name:</strong> ' + response.attendee.name + '<br/>';
					html += '<strong>Ticket:</strong> ' + response.attendee.ticket_type + '<br/>';
					html += '</div></div>';
				}
				
				if (response.success) {
					resultBox.addClass('updated').css('background', '#d4edda');
					// Play success sound if needed
				} else {
					resultBox.addClass('error').css('background', '#f8d7da');
					// Play error sound
				}
				
				resultBox.html(html);
				
				setTimeout(function() {
					resumeScanner();
				}, 3000);
			}

			function resumeScanner() {
				$('#scan-result').hide();
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
	}
}
