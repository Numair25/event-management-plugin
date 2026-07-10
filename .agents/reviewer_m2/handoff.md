# Milestone 2 Review & Critique Report

## 1. Observation
I have performed a static code analysis on the files created or modified for Milestone 2:
- **Frontend QR Loader Class**: `wp-content/plugins/event-management-plugin/public/class-emp-qr-frontend.php`
- **AJAX Upload Handler**: `wp-content/plugins/event-management-plugin/services/class-emp-qr-upload-handler.php`
- **Frontend JS Script**: `wp-content/plugins/event-management-plugin/public/js/emp-qr-payment.js`
- **Frontend CSS Styling**: `wp-content/plugins/event-management-plugin/public/css/emp-qr-payment.css`
- **Core Hook Registration**: `wp-content/plugins/event-management-plugin/includes/class-emp-core.php`

### Direct Observations of Interest:
- **AJAX Nonce & CSFR Verification**:
  - `class-emp-qr-frontend.php`, Line 58:
    ```php
    'nonce'    => wp_create_nonce( 'emp_qr_upload_nonce' ),
    ```
  - `class-emp-qr-upload-handler.php`, Line 12:
    ```php
    check_ajax_referer( 'emp_qr_upload_nonce', 'nonce' );
    ```
  - `emp-qr-payment.js`, Line 213:
    ```javascript
    formData.append('nonce', empQrConfig.nonce);
    ```
- **File Upload Restrictions**:
  - `class-emp-qr-upload-handler.php`, Lines 22-25 (Backend 5MB limit):
    ```php
    $max_size = 5 * 1024 * 1024;
    if ( $file['size'] > $max_size ) {
        wp_send_json_error( array( 'message' => __( 'File size exceeds the 5MB limit.', 'event-management-plugin' ) ) );
    }
    ```
  - `emp-qr-payment.js`, Line 166 (Frontend 5MB limit):
    ```javascript
    var maxSizeBytes = 5 * 1024 * 1024; // 5MB
    ```
  - `class-emp-qr-upload-handler.php`, Lines 33-38 (Backend MIME limits):
    ```php
    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'pdf'          => 'application/pdf',
    );
    ```
- **Interception Mechanism**:
  - `emp-qr-payment.js`, Lines 58-103 (Capturing phase + form ID validation):
    ```javascript
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form.id || !form.id.startsWith('gform_')) {
            return;
        }
        ...
    }, true); // useCapture = true
    ```
  - `emp-qr-payment.js`, Lines 94-96 (Propagation prevention):
    ```javascript
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    ```
- **sessionStorage Recovery Logic**:
  - `emp-qr-payment.js`, Lines 85-91:
    ```javascript
    var savedTxId = sessionStorage.getItem('emp_qr_tx_id_' + formId);
    var savedUrl = sessionStorage.getItem('emp_qr_screenshot_' + formId);
    if (savedTxId && savedUrl) {
        appendFieldsAndSubmit(form, savedTxId, savedUrl);
        return;
    }
    ```
- **Modal Close State Mutation**:
  - `emp-qr-payment.js`, Lines 122-126:
    ```javascript
    function closeModal() {
        $('#emp-qr-modal').hide();
        activeForm = null;
        activeFormId = null;
    }
    ```
  - `emp-qr-payment.js`, Lines 232-243 (AJAX success callback):
    ```javascript
    success: function(response) {
        if (response.success) {
            var screenshotUrl = response.data.url;
            sessionStorage.setItem('emp_qr_tx_id_' + activeFormId, transactionId);
            sessionStorage.setItem('emp_qr_screenshot_' + activeFormId, screenshotUrl);
            var currentForm = activeForm;
            closeModal();
            appendFieldsAndSubmit(currentForm, transactionId, screenshotUrl);
        }
    ```

---

## 2. Logic Chain
1. **CSRF Nonce**: The frontend localizes `emp_qr_upload_nonce` as `nonce`, appends it to form data as `nonce`, and the backend handles it using `check_ajax_referer('emp_qr_upload_nonce', 'nonce')`. The logic is aligned.
2. **File Restriction Alignment**: Frontend and backend both strictly enforce a `5MB` limit (5242880 bytes). Backend enforces standard extension and MIME mapping in `wp_handle_upload` overrides via `mimes` array mapping, while the frontend does pre-validation. The logic is aligned and secure.
3. **Robust Interception**: The `submit` listener is attached to `document` with `useCapture = true` to fire before bubble-phase listeners (like Gravity Forms'). It correctly filters forms starting with `gform_` and prevents standard submit behavior and propagation. The logic is aligned.
4. **JS TypeError via Modal Cancellation**: 
   - A user submits the Gravity Form.
   - The payment modal opens, they upload a screenshot, and click submit.
   - The AJAX request is fired. While it is processing, the user clicks "Cancel" (or the close icon).
   - This fires `closeModal()`, setting `activeForm` and `activeFormId` to `null`.
   - When the AJAX upload finishes successfully, the callback attempts to read/write using `activeFormId` (`null`) and submits using `activeForm` (`null`).
   - `appendFieldsAndSubmit(null, ...)` calls `null.setAttribute()`, throwing a critical `TypeError` that crashes the execution flow.
5. **sessionStorage Abuse / Reuse**:
   - `sessionStorage` retrieves previously entered payment credentials.
   - Upon successful form submission, there is no listener to clear the cache.
   - If the user reloads the form to register another attendee, the listener retrieves the stale transaction details and automatically bypasses the modal. This allows submitting multiple entries under a single transaction ID / screenshot.

---

## 3. Caveats
- **CLI/E2E Execution**: I attempted to run `php tests/e2e-runner.php` but the command-line verification timed out due to the OS environment/user permission constraints. Therefore, test cases were evaluated via extensive manual static code analysis.

---

## 4. Conclusion
The implementation of Milestone 2 meets most structural and security requirements, but contains one critical code bug (JS race condition on modal cancel) and one major functional/integrity vulnerability (persistent sessionStorage causing automatic submission bypass on subsequent forms).
Therefore, the verdict is **REQUEST_CHANGES**.

---

## 5. Verification Method
1. Inspect the JS error by opening the form, uploading a file, immediately clicking "Cancel" before upload completes, and monitoring the browser developer tools console.
2. Confirm the `sessionStorage` bypass by submitting a form successfully, then loading the form again in the same tab and attempting a new submission. Note that it skips the modal and submits instantly with the previous transaction metadata.
3. Verify compile/syntax correctness of the PHP files using standard syntax checkers.

---

## Review Summary

**Verdict**: REQUEST_CHANGES

## Findings

### [Critical] Finding 1: Javascript TypeError on Modal Cancellation during AJAX Upload
- **What**: If a user cancels the modal during an active AJAX screenshot upload, the success callback throws a javascript error.
- **Where**: `public/js/emp-qr-payment.js`, lines 232-243.
- **Why**: `closeModal()` resets `activeForm` and `activeFormId` to `null`. When AJAX succeeds, `activeFormId` is used to build the key for `sessionStorage.setItem`, and `activeForm` is passed as `form` to `appendFieldsAndSubmit()`, which attempts to set attributes on `null`.
- **Suggestion**:
  - Abort the active AJAX request upon modal closure.
  - Or, add a check at the beginning of the AJAX callbacks:
    ```javascript
    if (!activeForm || !activeFormId) {
        return;
    }
    ```

### [Major] Finding 2: Re-submission Bypass via uncleared sessionStorage
- **What**: `sessionStorage` values are not cleared upon successful form submission.
- **Where**: `public/js/emp-qr-payment.js`, lines 85-91.
- **Why**: If a user registers multiple times, subsequent registrations will automatically reuse the cached payment screenshot and transaction ID without prompting the user.
- **Suggestion**:
  - Listen for the `gform_confirmation_loaded` event and clear the `sessionStorage` cache:
    ```javascript
    $(document).on('gform_confirmation_loaded', function(event, formId) {
        sessionStorage.removeItem('emp_qr_tx_id_' + formId);
        sessionStorage.removeItem('emp_qr_screenshot_' + formId);
    });
    ```
  - For non-AJAX forms, check on page load if the form is present but has no validation errors, and clear the sessionStorage values for that form.

---

## Verified Claims

- CSRF Nonce Validation → verified via static inspection of AJAX handler → **PASS**
- 5MB file size limit enforced on backend/frontend → verified via manual review of limits → **PASS**
- MIME types checked correctly on backend using `$upload_overrides['mimes']` → verified via handler code → **PASS**
- Script localization output escaping → verified `esc_url_raw` used for URLs → **PASS**
- Capturing phase submit listener & gform_ prefix validation → verified JS listeners → **PASS**
- Loader hook registration inside `includes/class-emp-core.php` → verified loaders use `$this->loader->add_action` → **PASS**

## Coverage Gaps
- E2E tests execution — Risk level: Low — Verification skipped due to command execution timing out.

---

## Challenge Summary

**Overall risk assessment**: MEDIUM

## Challenges

### [High] Challenge 1: Single-Payment Reuse
- **Assumption challenged**: sessionStorage recovery should be persistent until manually overridden.
- **Attack scenario**: An attacker pays once, gets a valid transaction ID and screenshot url, submits the form, and then re-submits the form multiple times in the same session to get free registrations.
- **Blast radius**: Allows multiple unpaid registrations to be submitted and marked as "pending" or "processing" using a single payment reference.
- **Mitigation**: Clear sessionStorage upon successful form completion.

### [Medium] Challenge 2: Client State Desynchronization on Modal Cancel
- **Assumption challenged**: The user will only interact with the modal state sequentially.
- **Attack scenario**: A user starts a submission upload, cancels it, and submits a different form or acts on other page elements.
- **Blast radius**: The AJAX request continues in the background and causes a script execution halt (`TypeError`) on completion, potentially blocking other AJAX or jQuery scripts on the page.
- **Mitigation**: Store the AJAX promise/jqXHR and call `.abort()` when closing the modal.
