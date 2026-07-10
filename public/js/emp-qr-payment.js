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

    function bindQrInterceptor() {
        $('form').each(function() {
            var form = this;
            var $form = $(form);
            
            // Check if it is a Gravity Form
            if (!form.id || !form.id.startsWith('gform_')) {
                return;
            }

            var formId = form.id.replace('gform_', '');
            
            // Check if QR payment is enabled for this form
            if (!empQrConfig.forms[formId]) {
                return;
            }

            // Unbind previous to avoid duplicates, then bind
            $form.off('submit.empQr').on('submit.empQr', function(e) {
                // Check if form is already approved (we previously intercepted and uploaded screenshot)
                if (form.getAttribute('data-qr-approved') === 'true') {
                    return; // let it submit naturally
                }

                // Ensure this is the final page submission
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
                e.stopImmediatePropagation();

                // Reset GF's internal submitting flag so they can submit again if they cancel
                if (typeof window['gf_submitting_' + formId] !== 'undefined') {
                    window['gf_submitting_' + formId] = false;
                }

                activeForm = form;
                activeFormId = formId;

                // Open verification modal
                openModal(formId);
            });
        });
    }

    $(document).ready(bindQrInterceptor);
    $(document).on('gform_post_render', bindQrInterceptor);

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

        // Submit form via Gravity Forms standard trigger to resume its process
        if (typeof gf_do_action !== 'undefined') {
            // Trigger GF's internal submission process
            $form.trigger('submit');
        } else {
            // Fallback native submit
            HTMLFormElement.prototype.submit.call(form);
        }
    }

    // Client-side file type and size validation
    $(document).on('change', '#emp-qr-screenshot', function() {
        var fileInput = this;
        var errorSpan = $('.emp-qr-file-error');
        errorSpan.hide().text('');

        if (fileInput.files && fileInput.files[0]) {
            var file = fileInput.files[0];
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            var fileExtension = file.name.split('.').pop().toLowerCase();
            var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            var maxSizeBytes = 5 * 1024 * 1024; // 5MB

            if (allowedTypes.indexOf(file.type) === -1 && allowedExtensions.indexOf(fileExtension) === -1) {
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
