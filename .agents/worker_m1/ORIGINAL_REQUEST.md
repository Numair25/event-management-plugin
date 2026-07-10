## 2026-07-10T07:48:59Z
Apply the following improvements to `admin/class-emp-qr-settings-admin.php`:
1. In the save handler, add a check: if Gravity Forms is inactive, do not process the POST request: `if ( ! class_exists( 'GFAPI' ) ) { return; }`.
2. Skip invalid form IDs in the loop: if `$form_id <= 0`, `continue`.
3. Ensure the amount is non-negative on the server side: `$amount = isset( $form_data['amount'] ) ? max( 0.00, round( floatval( $form_data['amount'] ), 2 ) ) : 0.00;`.
4. Escape the `$form_id` in all HTML templates using `esc_attr()`: change all occurrences of `<?php echo $form_id; ?>` to `<?php echo esc_attr( $form_id ); ?>`.
5. Run `php -l` on the file to verify no syntax errors.
6. Write the results and confirm compliance in `c:\xampp\htdocs\event-management\wp-content\plugins\event-management-plugin\.agents\worker_m1\handoff_hardening.md` and send a message back.
