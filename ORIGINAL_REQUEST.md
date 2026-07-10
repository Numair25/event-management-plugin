# Original User Request

## Initial Request — 2026-07-10T13:10:27+05:30

Implement a manual QR code payment flow for Gravity Forms submissions within the Event Management Plugin. Event organizers can upload a payment QR code and set a price for a specific form. Frontend users must scan the QR, pay, and upload a transaction ID/screenshot to submit via a JavaScript-intercepted modal. The submission is placed in a pending state and requires manual admin approval from a custom dashboard before issuing the badge download link.

Working directory: c:\xampp\htdocs\event-management\wp-content\plugins\event-management-plugin
Integrity mode: demo

## Requirements

### R1. QR Payment Configuration
Create an admin page to manage QR payment settings. Admins must be able to select a Gravity Form, upload a QR code image, and set a required payment amount.

### R2. Frontend Modal Interception
If QR payment is enabled for a form, intercept the normal Gravity Forms submission using JavaScript. Display a modern, beautifully styled modal popup to the user showing the QR code and the required payment amount. The modal must require them to input a transaction ID or upload a screenshot before allowing the final Gravity Forms submission to proceed.

### R3. Custom Approval Dashboard
Save the entry with the transaction details in a pending state. Create a custom admin dashboard page specifically for "Pending QR Approvals" where organizers can review the transaction details/screenshots and manually approve the entries in bulk or individually.

### R4. Badge Issuance on Approval
Only upon manual admin approval from the dashboard should the Gravity Forms entry's payment status be updated to Paid/Approved. The attendee must then be fully registered in the `wp_emp_attendees` table and sent the confirmation email with their badge download link. The instant download button must NOT be shown on the initial confirmation page for these pending entries.

### R5. Backward Compatibility
The existing flow must remain entirely unbroken. Forms without QR payment enabled, or free forms, must continue to show the instant download button upon submission and instantly register the attendee.

## Acceptance Criteria

### QR Configuration
- [ ] Admin can upload a QR image and set a price mapped to a specific Gravity Form.

### Modal Interception
- [ ] Submitting the mapped GF form stops the default submission and shows the QR modal.
- [ ] The modal enforces the user to provide a transaction ID or screenshot.
- [ ] Upon completing the modal, the Gravity Form successfully submits and stores the transaction data.

### Approval Workflow
- [ ] The custom "Pending QR Approvals" dashboard lists pending entries and their uploaded screenshots/IDs.
- [ ] Admin can click "Approve", which updates the Gravity Forms entry's payment status to Paid, creates the Attendee record, and sends the confirmation email.

### Regression Testing
- [ ] Submitting a form that does *not* have QR payment enabled bypasses the modal entirely, registers the attendee instantly, and shows the instant download button on the confirmation screen.
