## 2026-07-10T13:13:21Z

You are teamwork_preview_explorer.
Your working directory is: c:\xampp\htdocs\event-management\wp-content\plugins\event-management-plugin\.agents\teamwork_preview_explorer_e2e_design
Your mission is to explore the plugin structure, CPTs, database, and Gravity Forms APIs to design the E2E test runner that will be implemented in wp-content/plugins/event-management-plugin/tests/e2e-runner.php.

Specifically, explore and document:
1. How to boot WordPress from a command line PHP script (e.g., using wp-load.php).
2. How to programmatically create/delete forms using GFAPI, including ticket fields, name/email fields, payment amount fields, etc.
3. How event configuration settings are structured (CPT 'emp_event', 'emp_qr_payment_settings' option, ticket types database table 'wp_emp_ticket_types').
4. How to simulate a form submission programmatically (e.g. via GFAPI::submit_form() or by programmatically executing hooks and adding entries) that includes QR transaction ID and screenshot URL fields.
5. How the upload AJAX action (emp_upload_qr_screenshot) is registered, how it can be called programmatically, and how it handles files (mime-type validation, folder paths).
6. How approval and rejection dashboard actions work (bulk actions, updating payment status in Gravity Forms, and creating attendee records in wp_emp_attendees).
7. How to write a robust testing harness in e2e-runner.php (assert functions, reporting, setup/cleanup helpers) without using external test frameworks like PHPUnit.

Write your findings and a step-by-step implementation guide for the worker in c:\xampp\htdocs\event-management\wp-content\plugins\event-management-plugin\.agents\teamwork_preview_explorer_e2e_design\handoff.md.

When finished, reply to this message with a summary and the path to your handoff.md file.
