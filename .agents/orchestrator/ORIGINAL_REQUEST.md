# Original User Request

## Initial Request — 2026-07-10T13:10:45+05:30

Implement a manual QR code payment flow for Gravity Forms submissions within the Event Management Plugin. Event organizers can upload a payment QR code and set a price for a specific form. Frontend users must scan the QR, pay, and upload a transaction ID/screenshot to submit via a JavaScript-intercepted modal. The submission is placed in a pending state and requires manual admin approval from a custom dashboard before issuing the badge download link.

Requirements:
- R1. QR Payment Configuration: Admin page to manage QR payment settings per form (QR image upload, payment amount).
- R2. Frontend Modal Interception: Intercept GF submission, display QR modal with amount, require transaction ID / screenshot upload.
- R3. Custom Approval Dashboard: Admin dashboard to review pending approvals and approve entries.
- R4. Badge Issuance on Approval: Set entry status to Paid/Approved on approval, register attendee in `wp_emp_attendees`, send confirmation email. No download link on initial confirmation.
- R5. Backward Compatibility: Forms without QR configured or free forms bypass modal and register instantly.
