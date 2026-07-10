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
					<p>Go to the Events tab and add a new Event. On the event creation page, scroll down to the <strong>Event Configuration</strong> box. Here you will define the capacity limit, select the Gravity Form you created in Step 1 from the dropdown menu, and define the physical dimensions for your printed badges.</p>
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
					<p>Go to your Gravity Form's menu, click <strong>Settings</strong> at the top, and select <strong>Event Management</strong>. Create a Feed to tell the plugin which Gravity Form fields correspond to the attendee's Name, Email, and Photo. This step is crucial for automatic badge generation!</p>
					
					<div style="background: #f0f6fc; border-left: 4px solid #0366d6; padding: 10px 15px; margin-top: 15px;">
						<strong>💡 Tip: Using Conditional Logic for Multiple Tickets</strong>
						<p style="margin-bottom: 0;">If your form sells multiple tickets (e.g., General, VIP, Premium), you need to create a Dropdown field in your Gravity Form for the user to select their ticket. Then, under <em>Event Management</em> settings, create a separate Feed for <em>each</em> ticket type. For each feed, enable Gravity Forms' <strong>Conditional Logic</strong> checkbox at the bottom to say: <em>"Only process this feed if the Ticket Dropdown is [VIP]"</em>. This ensures the attendee is assigned the correct Ticket Type!</p>
					</div>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">5</div>
				<div class="emp-step-content">
					<h3>Setup QR Payments (Optional)</h3>
					<p>If you want to accept payments via UPI QR codes on your Gravity Form, go to the <strong>QR Settings</strong> tab. Here you can upload your merchant QR code (the system will automatically extract your UPI ID) and set a payment amount. Once enabled, attendees will see a dynamic QR code generating with the exact amount when they submit the form. You can then review their uploaded screenshots in the <strong>QR Approvals</strong> tab.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-qr-settings'); ?>" class="button button-secondary">Configure QR Settings</a>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-qr-approvals'); ?>" class="button button-secondary">View Pending Approvals</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">6</div>
				<div class="emp-step-content">
					<h3>Design the Physical Badge</h3>
					<p>Use the Badge Designs drag-and-drop builder to layout where text fields and the QR code will print on the badge. You can create a different badge design for each Ticket Type (e.g., a gold badge for VIPs, white for General).</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-badges'); ?>" class="button button-secondary">Design Badges</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">7</div>
				<div class="emp-step-content">
					<h3>Configure Scan Points</h3>
					<p>Set up physical locations where staff will scan QR codes (e.g., Main Entrance, Lunch Hall). You can restrict these scan points so only certain Ticket Types are allowed to enter.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-scan-points'); ?>" class="button button-secondary">Manage Scan Points</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">8</div>
				<div class="emp-step-content">
					<h3>Run Your Event On-Site</h3>
					<p>When the event begins, use the Walk-In Kiosk to quickly register attendees paying at the door and print their badges instantly. Provide your door staff with the URL to the Frontend QR Scanner so they can scan attendees in! The scanner features an instant popup interface, allowing staff to scan continuously without touching the screen.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-kiosk'); ?>" class="button button-primary">Open Walk-In Kiosk</a>
					<a href="<?php echo site_url('/scanner-access/'); ?>" target="_blank" class="button button-secondary">Open QR Scanner Page</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">9</div>
				<div class="emp-step-content">
					<h3>Track Data & Analytics</h3>
					<p>Monitor your event's success in real-time. Use the <strong>Dashboard & Reports</strong> tab for high-level revenue metrics, and use the <strong>Scan Statistics</strong> tab to see exactly how many people have passed through each of your Scan Points (e.g., Food, Main Entrance) on any given date.</p>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-dashboard'); ?>" class="button button-secondary">View Dashboard</a>
					<a href="<?php echo admin_url('edit.php?post_type=emp_event&page=emp-scan-stats'); ?>" class="button button-secondary">View Scan Statistics</a>
				</div>
			</div>

			<div class="emp-step-card">
				<div class="emp-step-number">10</div>
				<div class="emp-step-content">
					<h3>Manage Your Team (User Roles)</h3>
					<p>The plugin includes 3 custom user roles to securely delegate tasks to your staff:</p>
					<ul style="list-style-type: disc; margin-left: 20px; margin-top: 10px;">
						<li><strong>Event Organizer:</strong> Has full access to everything in the plugin (settings, badges, forms, financial dashboards).</li>
						<li><strong>Registration Staff:</strong> Can access the Walk-In Kiosk to register attendees and print badges, but cannot see financial data or change settings.</li>
						<li><strong>Scanning Staff:</strong> Can only access the Frontend QR Scanner to scan attendees at the door. They have no access to the WordPress admin area.</li>
					</ul>
					<p style="margin-top: 15px; font-style: italic; color: #555;">
						<strong>Login Details:</strong> All staff members can log into the system using the standard WordPress login page at: <br/>
						<code><a href="<?php echo wp_login_url(); ?>" target="_blank"><?php echo wp_login_url(); ?></a></code>
					</p>
					<a href="<?php echo admin_url('users.php'); ?>" class="button button-secondary" style="margin-top: 10px;">Manage Users</a>
				</div>
			</div>

		</div>
		<?php
	}
}
