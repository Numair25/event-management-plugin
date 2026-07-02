# Event Management & On-Site Check-In Plugin for WordPress

A comprehensive WordPress plugin for managing physical events, handling walk-in registrations, badge printing, and on-site QR code scanning. Built with seamless integration for Gravity Forms.

## Key Features

1. **Event & Capacity Management**
   - Create unlimited events as Custom Post Types.
   - Set maximum capacity constraints for each event.
   - Assign multi-tier ticket types with different prices.

2. **Gravity Forms Integration**
   - Automatically ingest attendees upon Gravity Form submission.
   - Map core attendee data (Name, Email, Organization) and capture all custom fields.
   - Generate custom physical badges dynamically populated with Gravity Forms data.

3. **Dynamic Badge Designer**
   - Visual drag-and-drop badge builder in the backend.
   - Set physical badge dimensions (in millimeters).
   - Insert custom fields, attendee photos, and scannable QR codes onto the badge.
   - Auto-generate PDF badges upon registration.

4. **On-Site Walk-In Kiosk**
   - Fast, streamlined interface for registering walk-in attendees at the door.
   - Automatically renders associated Gravity Form fields dynamically.
   - Supports live webcam photo capture from tablets or laptops.
   - Instantly marks attendees as paid and prints their badge.

5. **Access Control & QR Scanning**
   - Secure frontend QR Scanner accessible only to designated "Scanner" roles.
   - Supports multiple Scan Points (e.g., Main Entrance, VIP Lounge, Lunch Hall).
   - Real-time validation prevents double-entry and validates ticket types against scan points.
   - Emits audible success/failure beeps.

6. **Analytics & Dashboard**
   - Visual dashboard with Chart.js powered graphs showing Peak Entry Times.
   - Real-time attendance tracking (registered vs. checked-in) and revenue aggregation.
   - Dedicated **Scan Statistics** menu: advanced filtering by Event, Date, and specific Scan Point to view precise scan volumes.
   - Dedicated **Live Scan Audit Log** tab with advanced filtering by Event, Station, Scan Result (Pass/Fail), and Attendee Search.
   - Full CSV export and import support for migrating attendee lists.

## Getting Started Workflow

To run your first event, follow this flow:

### 1. Create a Gravity Form
Create a form in Gravity Forms to collect attendee data (Name, Email, etc.). You don't need to configure any feeds just yet.

### 2. Create an Event
Go to **Events > Add New Event**. Give it a title, set the capacity, select your Gravity Form from the dropdown, and define your physical Badge Dimensions (e.g., 100x150 mm). Publish the event.

### 3. Create Ticket Types
Go to **Events > Ticket Types**. Create one or more ticket types (e.g., "General Admission", "VIP") and link them to your event.

### 4. Link Gravity Forms (Optional but Recommended)
Go to your Gravity Form's settings and click the **EMP Integration** tab. Create a feed mapping the form's Name, Email, Organization, and Photo Upload fields to the plugin's core fields.

### 5. Design Your Badge
Go to **Events > Badge Designs**. Create a new design, link it to your Ticket Type, and use the visual builder to place text fields and the QR code exactly where you want them.

### 6. Set Up Scan Points
Go to **Events > Scan Points**. Create zones where attendees will be scanned (e.g., "Main Entrance"). Restrict them by Ticket Type if necessary. 

### 7. Run the Event!
- Give your door staff the "Scanner" role and direct them to the **Scanner Access** page to scan QR codes on phones/badges.
- Use the **Walk-in Kiosk** to instantly register and print badges for attendees paying at the door.

### 8. Track Data & Analytics
- Go to **Events > Dashboard & Reports** for high-level revenue and registration metrics.
- Go to **Events > Scan Statistics** for granular breakdown of scan volumes by date and specific scan points.

## Technical Notes
- Requires PHP 7.4+
- Requires Gravity Forms (Optional for basic features, but highly recommended).
- PDF Generation utilizes mPDF.
- Audit logs track all financial markings and manual check-ins in the `wp_emp_audit_logs` table.
