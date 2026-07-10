# Handoff Report: Milestone 2 — Frontend Modal Interception

This report contains findings and a complete design plan for intercepting Gravity Forms submissions on the frontend, displaying a custom QR payment verification modal, performing an AJAX screenshot upload, and recovering gracefully from validation failures.

---

## 1. Observation
During our exploration of the codebase, we observed the following:
* **Plugin Structure**: 
  - The main plugin file is `event-management-plugin.php` which defines `EMP_VERSION`, `EMP_PLUGIN_DIR`, and `EMP_PLUGIN_URL`.
  - The core class is `includes/class-emp-core.php`. Its `define_public_hooks()` method is where frontend components are loaded using the loader.
  - The loader class `includes/class-emp-loader.php` provides `$this->loader->add_action( $hook, $component, $callback, $priority, $accepted_args )` for registering hooks.
* **Settings Option**: 
  - QR settings are saved in `admin/class-emp-qr-settings-admin.php` under the WordPress option `'emp_qr_payment_settings'`. The structure is a serialized array:
    ```php
    array(
        $form_id => array(
            'enabled'      => (bool) $enabled,
            'amount'       => (float) $amount,
            'qr_image_url' => (string) $qr_image_url,
        )
    )
    ```
* **Gravity Forms Hooks**: 
  - `services/class-emp-gf-integration.php` contains active Gravity Forms integrations.
  - The `public/` directory contains class files like `class-emp-feedback-portal.php` and `class-emp-frontend-scanner.php` but currently does not contain any JS or CSS files.

---

## 2. Logic Chain
We analyzed the requirements and established the following design decisions:
1. **Asset Loading Conditionality**: 
   - Instead of enqueuing scripts globally on every page (which increases load time), we hook into Gravity Forms' `gform_enqueue_scripts` hook. This ensures our script and stylesheet are loaded only on pages rendering a Gravity Form.
   - We check if the current `$form['id']` is present and enabled in the `'emp_qr_payment_settings'` option before enqueuing assets.
2. **Script Localization**: 
   - We use `wp_localize_script()` to pass settings to the frontend. To make the localization robust and avoid duplicate or overridden variables when multiple forms are present, we localize the entire list of enabled forms as a single object `empQrConfig.forms`. The script can then lookup details using `empQrConfig.forms[formId]`.
3. **Submission Interception**:
   - Gravity Forms AJAX submissions bind directly to the form's `submit` event. In standard DOM propagation, direct listeners on an element execute *before* delegated listeners on the `document` level. A standard bubble-phase listener on the `document` would execute too late.
   - To intercept the submission before Gravity Forms' own handlers can prevent default or submit AJAX requests, we register our submit handler on the `document` using the **capturing phase** (`useCapture = true`).
   - If a submission is captured and the form has QR enabled (and is not already approved), we stop the propagation of the event using `e.stopPropagation()` and `e.stopImmediatePropagation()`, and prevent the default submit using `e.preventDefault()`.
4. **UX Recovery Caching**:
   - If a user submits the payment modal but the Gravity Form fails server-side validation (e.g. they forgot to fill out a required email field), the page will reload or AJAX will re-render the form. The dynamically appended fields would be lost, forcing the user to pay again.
   - To avoid this, upon successful upload of the screenshot, we store the `transactionId` and `screenshotUrl` in `sessionStorage` (scoped to the tab/form ID).
   - In our capturing submit handler, we check if valid credentials exist in `sessionStorage` for that form. If they do, we bypass the modal overlay, append the hidden fields, and allow the submission to proceed directly.
5. **Security/Upload Handling**:
   - The frontend script uploads screenshots via `wp_ajax_` actions to `admin-ajax.php`.
   - On the backend, we must check if `wp_handle_upload` is defined, and load `wp-admin/includes/file.php` if it is not (as it is not loaded by default for frontend AJAX calls).
   - We validate file size (max 5MB) both on the client-side (before uploading) and the server-side.
   - We enforce strict MIME type validation (JPG, JPEG, PNG, GIF, PDF) on the server using `wp_handle_upload`'s `'mimes'` override parameter.

---

## 3. Caveats
* **Fixed QR Amount**: We assume that the QR payment amount is a fixed amount configured by the administrator in the QR Settings page, rather than a dynamic amount calculated from Gravity Form pricing fields.
* **Session Storage Scope**: Caching is tab-scoped. If the user opens the registration page in a new tab, they will be prompted to make a payment. This is appropriate as it represents a new submission session.
* **Compatibility with Third-Party Addons**: Since we intercept using the native capture phase, our handler runs before all jQuery bubble-phase listeners. This ensures maximum compatibility with Gravity Forms and other third-party add-ons.

---

## 4. Conclusion
We have completed the design for the three main components. Below are the proposed files and integration locations.

### Integration in `includes/class-emp-core.php`
We need to register both classes in the `define_public_hooks()` method:
```php
		// QR Frontend Script Loader
		require_once EMP_PLUGIN_DIR . 'public/class-emp-qr-frontend.php';
		$plugin_qr_frontend = new EMP_QR_Frontend();
		$this->loader->add_action( 'gform_enqueue_scripts', $plugin_qr_frontend, 'enqueue_assets', 10, 2 );

		// QR Screenshot AJAX Upload Handler
		require_once EMP_PLUGIN_DIR . 'services/class-emp-qr-upload-handler.php';
		$plugin_qr_upload_handler = new EMP_QR_Upload_Handler();
		$this->loader->add_action( 'wp_ajax_nopriv_emp_upload_qr_screenshot', $plugin_qr_upload_handler, 'handle_screenshot_upload' );
		$this->loader->add_action( 'wp_ajax_emp_upload_qr_screenshot', $plugin_qr_upload_handler, 'handle_screenshot_upload' );
```

---

### Component 1: Script Loader (`public/class-emp-qr-frontend.php`)
```php
<?php
/**
 * Frontend QR Loader Class
 */
class EMP_QR_Frontend {

	/**
	 * Enqueue CSS and JS assets conditionally for QR-enabled forms.
	 *
	 * @param array $form The Gravity Form array.
	 * @param bool  $is_ajax Whether the form is AJAX-enabled.
	 */
	public function enqueue_assets( $form, $is_ajax ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$form_id = intval( $form['id'] );
		$settings = get_option( 'emp_qr_payment_settings', array() );

		// Check if QR payment is enabled for this form
		if ( isset( $settings[ $form_id ] ) && ! empty( $settings[ $form_id ]['enabled'] ) ) {
			// Enqueue CSS
			wp_enqueue_style(
				'emp-qr-payment-css',
				EMP_PLUGIN_URL . 'public/css/emp-qr-payment.css',
				array(),
				EMP_VERSION
			);

			// Enqueue JS
			wp_enqueue_script(
				'emp-qr-payment-js',
				EMP_PLUGIN_URL . 'public/js/emp-qr-payment.js',
				array( 'jquery' ),
				EMP_VERSION,
				true
			);

			// Prepare settings for all enabled forms to keep it robust and merge-safe
			$enabled_forms = array();
			foreach ( $settings as $id => $data ) {
				if ( ! empty( $data['enabled'] ) ) {
					$enabled_forms[ $id ] = array(
						'form_id'      => $id,
						'amount'       => floatval( $data['amount'] ),
						'qr_image_url' => esc_url_raw( $data['qr_image_url'] ),
					);
				}
			}

			// Localize script
			wp_localize_script(
				'emp-qr-payment-js',
				'empQrConfig',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'emp_qr_upload_nonce' ),
					'forms'    => $enabled_forms,
				)
			);
		}
	}
}
```

---

### Component 2: AJAX Upload Endpoint (`services/class-emp-qr-upload-handler.php`)
```php
<?php
/**
 * AJAX Upload Handler for QR Screenshots
 */
class EMP_QR_Upload_Handler {

	/**
	 * Handle the AJAX request to upload a payment screenshot.
	 */
	public function handle_screenshot_upload() {
		// Verify Nonce
		check_ajax_referer( 'emp_qr_upload_nonce', 'nonce' );

		// Check if file is provided
		if ( ! isset( $_FILES['screenshot'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'event-management-plugin' ) ) );
		}

		$file = $_FILES['screenshot'];

		// Limit file size to 5MB (5 * 1024 * 1024)
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'File size exceeds the 5MB limit.', 'event-management-plugin' ) ) );
		}

		// Ensure wp_handle_upload is defined (needed on frontend AJAX requests)
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Restrict MIME types to standard image types and PDF
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'pdf'          => 'application/pdf',
		);

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);

		// Handle the file upload securely using WordPress API
		$movefile = wp_handle_upload( $file, $upload_overrides );

		if ( $movefile && ! isset( $movefile['error'] ) ) {
			wp_send_json_success( array(
				'url' => $movefile['url'],
			) );
		} else {
			wp_send_json_error( array(
				'message' => $movefile['error'],
			) );
		}
	}
}
```

---

### Component 3: Frontend Script (`public/js/emp-qr-payment.js`)
```javascript
/**
 * Frontend QR Payment Interception for Gravity Forms
 */
(function($) {
    'use strict';

    if (typeof empQrConfig === 'undefined' || !empQrConfig.forms) {
        return;
    }

    // Modal HTML Template
    var modalHtml = 
        '<div id="emp-qr-modal" class="emp-qr-modal-overlay" style="display:none;">' +
        '    <div class="emp-qr-modal-content">' +
        '        <span class="emp-qr-modal-close">&times;</span>' +
        '        <h3>Payment Verification</h3>' +
        '        <p>Please scan the QR code below to make a payment of <strong>₹<span class="emp-qr-amount"></span></strong>.</p>' +
        '        <div class="emp-qr-image-container">' +
        '            <img src="" class="emp-qr-image" alt="QR Code Payment" />' +
        '        </div>' +
        '        <form id="emp-qr-modal-form">' +
        '            <div class="emp-qr-field-group">' +
        '                <label for="emp-qr-transaction-id">Transaction ID / Reference Number <span class="required">*</span></label>' +
        '                <input type="text" id="emp-qr-transaction-id" required placeholder="Enter Transaction ID" />' +
        '            </div>' +
        '            <div class="emp-qr-field-group">' +
        '                <label for="emp-qr-screenshot">Upload Payment Screenshot <span class="required">*</span></label>' +
        '                <input type="file" id="emp-qr-screenshot" accept="image/*,application/pdf" required />' +
        '                <div class="emp-qr-upload-progress" style="display:none;">' +
        '                    <div class="emp-qr-progress-bar"></div>' +
        '                </div>' +
        '                <span class="emp-qr-file-error" style="color:#d63638; display:none; font-size:12px; margin-top:5px; font-weight:600;"></span>' +
        '            </div>' +
        '            <div class="emp-qr-action-buttons">' +
        '                <button type="submit" class="emp-qr-btn-submit">Confirm & Submit Form</button>' +
        '                <button type="button" class="emp-qr-btn-cancel">Cancel</button>' +
        '            </div>' +
        '        </form>' +
        '    </div>' +
        '</div>';

    // Inject modal to body on ready
    $(function() {
        if ($('#emp-qr-modal').length === 0) {
            $('body').append(modalHtml);
        }

        // Close modal events
        $(document).on('click', '.emp-qr-modal-close, .emp-qr-btn-cancel', function() {
            closeModal();
        });
    });

    var activeForm = null;
    var activeFormId = null;

    // Use capturing phase on document submit to intercept before GF's bubble-phase handlers
    document.addEventListener('submit', function(e) {
        var form = e.target;
        
        // Check if it is a Gravity Form
        if (!form.id || !form.id.startsWith('gform_')) {
            return;
        }

        var formId = form.id.replace('gform_', '');
        
        // Check if QR payment is enabled for this form
        if (!empQrConfig.forms[formId]) {
            return;
        }

        // Check if form is already approved (we previously intercepted and uploaded screenshot)
        if (form.getAttribute('data-qr-approved') === 'true') {
            return;
        }

        // Ensure this is the final page submission (not next/previous on a multi-page form)
        var targetPageField = document.getElementById('gform_target_page_number_' + formId);
        var isFinalSubmit = !targetPageField || targetPageField.value === '0';
        if (!isFinalSubmit) {
            return;
        }

        // Check UX recovery (sessionStorage cache)
        var savedTxId = sessionStorage.getItem('emp_qr_tx_id_' + formId);
        var savedUrl = sessionStorage.getItem('emp_qr_screenshot_' + formId);
        if (savedTxId && savedUrl) {
            appendFieldsAndSubmit(form, savedTxId, savedUrl);
            return;
        }

        // Intercept: halt propagation and default submit
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        activeForm = form;
        activeFormId = formId;

        // Open verification modal
        openModal(formId);
    }, true); // useCapture = true

    function openModal(formId) {
        var settings = empQrConfig.forms[formId];
        var modal = $('#emp-qr-modal');

        modal.find('.emp-qr-amount').text(settings.amount.toFixed(2));
        modal.find('.emp-qr-image').attr('src', settings.qr_image_url);
        
        // Reset modal fields
        modal.find('#emp-qr-transaction-id').val('');
        modal.find('#emp-qr-screenshot').val('');
        modal.find('.emp-qr-file-error').hide().text('');
        modal.find('.emp-qr-upload-progress').hide();
        modal.find('.emp-qr-progress-bar').css('width', '0%');

        modal.css('display', 'flex');
    }

    function closeModal() {
        $('#emp-qr-modal').hide();
        activeForm = null;
        activeFormId = null;
    }

    function appendFieldsAndSubmit(form, transactionId, screenshotUrl) {
        var $form = $(form);

        // Remove existing hidden fields if any (to avoid duplicates)
        $form.find('input[name="emp_qr_transaction_id"]').remove();
        $form.find('input[name="emp_qr_screenshot_url"]').remove();

        // Append inputs
        $('<input>').attr({
            type: 'hidden',
            name: 'emp_qr_transaction_id',
            value: transactionId
        }).appendTo($form);

        $('<input>').attr({
            type: 'hidden',
            name: 'emp_qr_screenshot_url',
            value: screenshotUrl
        }).appendTo($form);

        // Mark form as approved
        form.setAttribute('data-qr-approved', 'true');

        // Submit form via jQuery
        $form.trigger('submit');
    }

    // Client-side file type and size validation
    $(document).on('change', '#emp-qr-screenshot', function() {
        var fileInput = this;
        var errorSpan = $('.emp-qr-file-error');
        errorSpan.hide().text('');

        if (fileInput.files && fileInput.files[0]) {
            var file = fileInput.files[0];
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            var maxSizeBytes = 5 * 1024 * 1024; // 5MB

            if (allowedTypes.indexOf(file.type) === -1) {
                errorSpan.text('Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.').show();
                $(fileInput).val('');
                return;
            }

            if (file.size > maxSizeBytes) {
                errorSpan.text('File size exceeds the 5MB limit.').show();
                $(fileInput).val('');
                return;
            }
        }
    });

    // Handle AJAX upload and submission on modal submit
    $(document).on('submit', '#emp-qr-modal-form', function(e) {
        e.preventDefault();

        if (!activeForm || !activeFormId) {
            return;
        }

        var transactionId = $.trim($('#emp-qr-transaction-id').val());
        var fileInput = document.getElementById('emp-qr-screenshot');

        if (!transactionId) {
            alert('Please enter a Transaction ID.');
            return;
        }

        if (!fileInput.files || fileInput.files.length === 0) {
            alert('Please upload a payment screenshot.');
            return;
        }

        var file = fileInput.files[0];
        
        // Show progress indicator
        var progressContainer = $('.emp-qr-upload-progress');
        var progressBar = $('.emp-qr-progress-bar');
        progressContainer.show();
        progressBar.css('width', '0%');

        var formData = new FormData();
        formData.append('action', 'emp_upload_qr_screenshot');
        formData.append('nonce', empQrConfig.nonce);
        formData.append('screenshot', file);

        $.ajax({
            url: empQrConfig.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        progressBar.css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    var screenshotUrl = response.data.url;

                    // Cache in sessionStorage for UX validation recovery
                    sessionStorage.setItem('emp_qr_tx_id_' + activeFormId, transactionId);
                    sessionStorage.setItem('emp_qr_screenshot_' + activeFormId, screenshotUrl);

                    // Close modal and submit
                    var currentForm = activeForm;
                    closeModal();
                    appendFieldsAndSubmit(currentForm, transactionId, screenshotUrl);
                } else {
                    alert('Upload failed: ' + (response.data.message || 'Unknown error'));
                    progressContainer.hide();
                    progressBar.css('width', '0%');
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred during file upload. Please try again.');
                progressContainer.hide();
                progressBar.css('width', '0%');
            }
        });
    });

})(jQuery);
```

---

### Component 4: Modal Styling (`public/css/emp-qr-payment.css`)
```css
/* QR Payment Modal Styling */
.emp-qr-modal-overlay {
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
}
.emp-qr-modal-content {
    background-color: #ffffff;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 460px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    position: relative;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.emp-qr-modal-close {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #aaaaaa;
    cursor: pointer;
    line-height: 1;
}
.emp-qr-modal-close:hover {
    color: #333333;
}
.emp-qr-modal-content h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 20px;
    color: #2c3338;
}
.emp-qr-image-container {
    text-align: center;
    margin: 20px 0;
}
.emp-qr-image {
    max-width: 200px;
    height: auto;
    border: 1px solid #dddddd;
    padding: 5px;
    background: #ffffff;
}
.emp-qr-field-group {
    margin-bottom: 18px;
    text-align: left;
}
.emp-qr-field-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    font-size: 14px;
    color: #3c434a;
}
.emp-qr-field-group input[type="text"],
.emp-qr-field-group input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 14px;
}
.emp-qr-field-group input[type="text"]:focus {
    border-color: #2271b1;
    outline: none;
}
.emp-qr-action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
}
.emp-qr-btn-submit {
    background-color: #2271b1;
    color: #ffffff;
    border: none;
    padding: 12px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}
.emp-qr-btn-submit:hover {
    background-color: #135e96;
}
.emp-qr-btn-cancel {
    background-color: #ffffff;
    color: #2271b1;
    border: 1px solid #2271b1;
    padding: 12px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
}
.emp-qr-btn-cancel:hover {
    background-color: #f0f0f1;
}
.emp-qr-upload-progress {
    width: 100%;
    background-color: #f0f0f1;
    border-radius: 4px;
    height: 8px;
    margin-top: 8px;
    overflow: hidden;
}
.emp-qr-progress-bar {
    height: 100%;
    width: 0%;
    background-color: #2271b1;
    transition: width 0.1s ease;
}
.emp-qr-field-group label .required {
    color: #d63638;
}
```

---

## 5. Verification Method
1. **Verification of Files**:
   Ensure the following files are written exactly as designed:
   - `public/class-emp-qr-frontend.php`
   - `public/js/emp-qr-payment.js`
   - `public/css/emp-qr-payment.css`
   - `services/class-emp-qr-upload-handler.php`
   - Verify that these files are properly integrated and called inside `includes/class-emp-core.php`.
2. **Behavioral Testing**:
   - Navigate to the admin Settings page, click 'QR Settings', enable a form, set an amount (e.g. `1200.00`) and upload a test QR image. Save the settings.
   - Embed this form in a public WordPress page.
   - Attempt to submit the form. Verify that the browser displays the QR payment modal overlay with the correct amount and QR image.
   - Try to submit the modal with an empty Transaction ID or no upload. Verify that HTML5 form validation displays error alerts.
   - Choose a file larger than 5MB or an invalid format (e.g. `.zip`). Verify that the client-side validation stops the action and displays an error message.
   - Upload a valid file (<5MB JPG/PNG/PDF) and provide a Transaction ID. Verify that:
     1. The AJAX request is sent to `admin-ajax.php` with action `emp_upload_qr_screenshot`.
     2. The progress bar transitions smoothly from `0%` to `100%`.
     3. The backend saves the file and returns a JSON success response with the URL.
     4. The modal closes, `emp_qr_transaction_id` and `emp_qr_screenshot_url` hidden inputs are appended to the main form, and the main form is successfully submitted.
3. **Validation Failure / Recovery Testing**:
   - Intentionally leave a required Gravity Form field (like Name or Email) empty.
   - Complete the QR payment modal and submit.
   - After validation fails, the page/form will re-render showing the error.
   - Fill in the missing required fields and submit the form again.
   - Verify that the form submits immediately *without* presenting the QR modal again, recovering successfully using the values stored in `sessionStorage`.
