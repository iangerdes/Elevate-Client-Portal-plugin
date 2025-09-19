// File: assets/js/ecp-admin-settings.js
/**
 * Handles all AJAX logic for the admin settings page.
 *
 * @package Elevate_Client_Portal
 * @version 6.5.1 (Hardcodes License Server URL)
 */
jQuery(function($){
    // Initialize the WordPress color picker for styling options.
    $('.ecp-color-picker').wpColorPicker();

    // --- S3 Connection Test Handler ---
    $('#ecp-test-s3-btn').on('click', function() {
        const btn = $(this);
        const resultsContainer = $('#ecp-s3-test-results');
        resultsContainer.text('Testing...').css('color', '#4A4A4A');
        btn.prop('disabled', true);

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_test_s3_connection',
            nonce: ecp_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                resultsContainer.text(response.data.message).css('color', '#28a745');
            } else {
                resultsContainer.text('Error: ' + response.data.message).css('color', '#d63638');
            }
        })
        .fail(function() {
            resultsContainer.text('An unknown server error occurred.').css('color', '#d63638');
        })
        .always(function() {
            btn.prop('disabled', false);
        });
    });

    // --- License Validation Handler ---
    $('#ecp-validate-license-btn').on('click', function() {
        const btn = $(this);
        const resultsContainer = $('#ecp-license-validation-results');
        const statusDisplay = $('#ecp-license-status-display');
        
        resultsContainer.text('Validating...').css('color', '#4A4A4A');
        btn.prop('disabled', true);
        
        const licenseKey = $('#license_key').val();

        if (!licenseKey) {
            resultsContainer.text('Please enter a License Key.').css('color', '#d63638');
            btn.prop('disabled', false);
            return;
        }

        // ** FIX: The server URL is now hardcoded in the PHP, so we don't send it from JS. **
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_validate_license',
            nonce: ecp_ajax.nonce,
            license_key: licenseKey
        })
        .done(function(response) {
            if (response.success) {
                resultsContainer.text(response.data.message).css('color', '#28a745');
                statusDisplay.text('Active').removeClass('ecp-status-disabled').addClass('ecp-status-enabled');
            } else {
                resultsContainer.text('Error: ' + response.data.message).css('color', '#d63638');
                statusDisplay.text('Inactive').removeClass('ecp-status-enabled').addClass('ecp-status-disabled');
            }
        })
        .fail(function() {
            resultsContainer.text('An unknown server error occurred.').css('color', '#d63638');
        })
        .always(function() {
            btn.prop('disabled', false);
        });
    });
});

