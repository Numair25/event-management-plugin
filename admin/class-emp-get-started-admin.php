<?php
/**
 * Get Started Page in Admin
 */

class EMP_Get_Started_Admin {

	public function register_menu() {
		// Register as the very first submenu item under Events
		add_submenu_page(
			'edit.php?post_type=emp_event',
			__( 'Get Started', 'event-management-plugin' ),
			__( 'Get Started', 'event-management-plugin' ),
			'manage_event_settings',
			'emp-get-started',
			array( $this, 'render_page' ),
			0 // Top position
		);
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php _e( 'Event Management: Get Started', 'event-management-plugin' ); ?></h1>
			<p class="about-description"><?php _e( 'Follow this workflow to set up your first event, configure Gravity Forms, design badges, and manage check-ins.', 'event-management-plugin' ); ?></p>
			
			<style>
				.emp-step-card {
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 4px;
					padding: 20px;
					margin-bottom: 20px;
					box-shadow: 0 1px 1px rgba(0,0,0,0.04);
					display: flex;
					align-items: flex-start;
				}
				.emp-step-number {
					background: #0073aa;
					color: #fff;
					font-size: 24px;
					font-weight: bold;
					width: 50px;
					height: 50px;
					display: flex;
					align-items: center;
					justify-content: center;
					border-radius: 50%;
					flex-shrink: 0;
					margin-right: 20px;
				}
				.emp-step-content h3 {
					margin-top: 0;
					font-size: 1.2em;
				}
				.emp-step-content p {
					font-size: 14px;
					color: #3c434a;
				}
				.emp-step-content a.button {
					margin-top: 10px;
				}
			</style>

			<div class="emp-step-card">
				<div class="emp-step-number">1</div>
				<div class="emp-step-content">
					<h3>Create a Gravity Form (Optional but Recommended)</h3>
					<p>Create a Gravity Form to collect attendee data such as Name, Email, Organization, and any custom questions you want to ask attendees. If you don't use Gravity Forms, you can manually import attendees via CSV.</p>
					<a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-secondary">Create Form</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">2</div>
				<div class="emp-step-content">
					<h3>Create Your Event</h3>
					<p>Go to the Events tab and add a new Event. You will define the capacity limit, select the Gravity Form you created in Step 1, and define the physical dimensions for your printed badges.</p>
					<a href="<?php echo admin_url('post-new.php?post_type=emp_event'); ?>" class="button button-primary">Add New Event</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">3</div>
				<div class="emp-step-content">
					<h3>Create Ticket Types</h3>
					<p>Set up different ticket tiers for your event (e.g., General Admission, VIP). You can assign different prices to these tickets. Every attendee must be linked to a Ticket Type.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-ticket-types'); ?>" class="button button-secondary">Manage Ticket Types</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">4</div>
				<div class="emp-step-content">
					<h3>Link Gravity Forms to Event Fields</h3>
					<p>Go to your Gravity Form's settings menu, select <strong>EMP Integration</strong>, and create a Feed. This tells the plugin which Gravity Form fields correspond to the attendee's Name, Email, and Photo. This step is crucial for automatic badge generation!</p>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">5</div>
				<div class="emp-step-content">
					<h3>Design the Physical Badge</h3>
					<p>Use the Badge Designs drag-and-drop builder to layout where text fields and the QR code will print on the badge. You can create a different badge design for each Ticket Type (e.g., a gold badge for VIPs, white for General).</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-badge-designs'); ?>" class="button button-secondary">Design Badges</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">6</div>
				<div class="emp-step-content">
					<h3>Configure Scan Points</h3>
					<p>Set up physical locations where staff will scan QR codes (e.g., Main Entrance, Lunch Hall). You can restrict these scan points so only certain Ticket Types are allowed to enter.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-scan-points'); ?>" class="button button-secondary">Manage Scan Points</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">7</div>
				<div class="emp-step-content">
					<h3>Run Your Event On-Site</h3>
					<p>When the event begins, use the Walk-In Kiosk to quickly register attendees paying at the door and print their badges instantly. Provide your door staff with the URL to the Frontend QR Scanner so they can scan attendees in! The scanner features an instant popup interface, allowing staff to scan continuously without touching the screen.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-kiosk'); ?>" class="button button-primary">Open Walk-In Kiosk</a>
					<a href="<?php echo site_url('/scanner-access/'); ?>" target="_blank" class="button button-secondary">Open QR Scanner Page</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">8</div>
				<div class="emp-step-content">
					<h3>Track Data & Analytics</h3>
					<p>Monitor your event's success in real-time. Use the <strong>Dashboard & Reports</strong> tab for high-level revenue metrics, and use the <strong>Scan Statistics</strong> tab to see exactly how many people have passed through each of your Scan Points (e.g., Food, Main Entrance) on any given date.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-dashboard'); ?>" class="button button-secondary">View Dashboard</a>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-scan-stats'); ?>" class="button button-secondary">View Scan Statistics</a>
				</div>
			</div>

		</div>
		<?php
	}
}
