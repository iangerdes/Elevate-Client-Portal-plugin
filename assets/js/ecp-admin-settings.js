// File: assets/js/ecp-admin-settings.js
/**
 * Handles all AJAX logic for the admin settings page.
 *
 * @package Elevate_Client_Portal
 * @version 6.5.2 (Nonce Fix)
 * @comment Corrected the AJAX call to use the properly namespaced nonce provided by the Asset Manager, fixing the S3 connection test.
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
            nonce: ecp_ajax.nonces.adminSettingsNonce // ** FIX: Use the correct nonce key **
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
});

