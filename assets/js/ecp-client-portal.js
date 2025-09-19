// File: elevate-client-portal/assets/js/ecp-client-portal.js
/**
 * Handles all AJAX logic for the front-end client portal view.
 *
 * @package Elevate_Client_Portal
 * @version 64.0.0 (Error Checking & Stability)
 * @comment Added a global flag to confirm script loading and corrected the nonce for ZIP creation to resolve security errors.
 */

// ** NEW: Global flag to indicate that this script has loaded successfully. **
window.ECP_Client_Portal_Loaded = true;

jQuery(function ($) {
    const portalWrapper = $('body').find('.ecp-portal-wrapper');
    if (!portalWrapper.length) return;

    function fetchClientFiles() {
        const fileListContainer = $('#ecp-file-list-container');
        if (!fileListContainer.length) return;

        $('#ecp-loader').show();
        fileListContainer.css('opacity', '0.5');
        $('#ecp-no-files-message').hide();

        $.ajax({
            url: ecp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_filter_files',
                nonce: ecp_ajax.nonces.clientPortalNonce,
                search: $('#ecp-search-input').val(),
                sort: $('#ecp-sort-select').val(),
                folder: $('#ecp-folder-filter').val()
            },
            success: function (response) {
                let html = '';
                if (response.success && response.data.length > 0) {
                    response.data.forEach(file => {
                        const encryptedIcon = file.is_encrypted ? `<span class="ecp-encrypted-icon" title="Encrypted"></span>` : '';
                        const downloadAction = file.is_encrypted 
                            ? `href="#" class="button ecp-download-encrypted-btn" data-filekey="${file.key}"`
                            : `href="${file.download_url}" class="button"`;

                        html += `
                            <tr>
                                <td class="ecp-col-checkbox"><input type="checkbox" class="ecp-file-checkbox" value="${file.key}" data-size-bytes="${file.size_bytes}" ${file.is_encrypted ? 'disabled' : ''}></td>
                                <td data-label="File Name">${file.name} ${encryptedIcon}</td>
                                <td data-label="Folder">${file.folder}</td>
                                <td data-label="Date">${file.date}</td>
                                <td data-label="Size">${file.size}</td>
                                <td class="ecp-actions-col"><a ${downloadAction}>Download</a></td>
                            </tr>
                        `;
                    });
                    fileListContainer.html(html);
                } else {
                    fileListContainer.empty();
                    $('#ecp-no-files-message').text('No files match your criteria.').show();
                }
            },
            error: () => $('#ecp-no-files-message').text('An unexpected error occurred. Please try again.').show(),
            complete: () => {
                $('#ecp-loader').hide();
                fileListContainer.css('opacity', '1');
                $('#ecp-select-all-files').prop('checked', false);
                toggleBulkActions();
            }
        });
    }

    function toggleBulkActions() {
        const checkedCount = $('.ecp-file-checkbox:checked').length;
        const bulkActionsWrapper = $('#ecp-bulk-actions-wrapper');
        if (checkedCount > 0) {
            bulkActionsWrapper.slideDown(200);
        } else {
            bulkActionsWrapper.slideUp(200);
            $('#ecp-zip-password-wrapper').hide();
        }
    }

    if (portalWrapper.is(':not(.ecp-account-page)')) {
        fetchClientFiles();
    }

    let clientSearchTimeout;
    $('body').on('keyup', '#ecp-search-input', function () {
        clearTimeout(clientSearchTimeout);
        clientSearchTimeout = setTimeout(fetchClientFiles, 400);
    });
    
    $('body').on('change', '#ecp-sort-select, #ecp-folder-filter', fetchClientFiles);
    
    $('body').on('change', '#ecp-select-all-files', function() {
        $('.ecp-file-checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
        toggleBulkActions();
    });

    $('body').on('change', '.ecp-file-checkbox', function() {
        if (!this.checked) $('#ecp-select-all-files').prop('checked', false);
        toggleBulkActions();
    });

    $('body').on('click', '#ecp-download-zip-btn', function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Generating ZIP...');
        $('#ecp-zip-password-wrapper').slideUp(100);
        
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_create_zip',
            // ** FIX: Use the correct nonce for the zip action **
            nonce: ecp_ajax.nonces.zipNonce, 
            file_keys: $('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get()
        }).done(response => {
            if (response.success) {
                // Password display logic is now handled in the PHP response
                window.location.href = response.data.download_url;
            } else {
                alert('Error: ' + (response.data.message || 'Could not create ZIP.'));
            }
        }).fail(() => alert(ecp_ajax.strings.error_zip))
          .always(() => btn.prop('disabled', false).text('Download Selected as ZIP'));
    });

    $('body').on('click', '#ecp-copy-zip-password', function() {
        const passwordText = $('#ecp-zip-password').text();
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(passwordText).select();
        document.execCommand('copy');
        tempInput.remove();

        const originalText = $(this).text();
        $(this).text(ecp_ajax.strings.copied);
        setTimeout(() => $(this).text(originalText), 1500);
    });

    $('body').on('click', '.ecp-download-encrypted-btn', function(e) {
        e.preventDefault();
        const password = prompt(ecp_ajax.strings.decrypt_prompt);
        if (password) {
            const fileKey = $(this).data('filekey');
            const form = $('<form>', {
                'method': 'POST',
                'action': `${ecp_ajax.home_url}?ecp_action=download_decrypted_file`
            })
            .append($('<input>', { 'type': 'hidden', 'name': 'file_key', 'value': fileKey }))
            .append($('<input>', { 'type': 'hidden', 'name': 'password', 'value': password }))
            .append($('<input>', { 'type': 'hidden', 'name': 'nonce', 'value': ecp_ajax.nonces.decryptFileNonce }));
            
            $('body').append(form);
            form.submit().remove();
        }
    });

    $('body').on('click', '#ecp-contact-manager-toggle', () => $('#ecp-contact-manager-form-wrapper').slideToggle(200));
    $('body').on('submit', '#ecp-contact-manager-form', function(e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), msgBox = form.find('#ecp-contact-form-messages');
        btn.prop('disabled', true).text('Sending...');
        msgBox.html('').hide();
        $.post(ecp_ajax.ajax_url, form.serialize() + `&action=ecp_send_manager_email&nonce=${ecp_ajax.nonces.contactManagerNonce}`)
            .done(response => {
                const notice = `<div class="notice notice-${response.success ? 'success' : 'error'}"><p>${response.data.message}</p></div>`;
                msgBox.html(notice).slideDown();
                if(response.success) {
                    form[0].reset();
                    setTimeout(() => $('#ecp-contact-manager-form-wrapper').slideUp(200), 4000);
                }
            })
            .fail(() => msgBox.html('<div class="notice notice-error"><p>An unknown server error occurred.</p></div>').slideDown())
            .always(() => btn.prop('disabled', false).text('Send Message'));
    });
    $('body').on('submit', '#ecp-account-details-form', function(e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), msgBox = $('#ecp-account-messages'), btnHtml = btn.html();
        btn.prop('disabled', true).text('Saving...');
        msgBox.html('').hide();
        $.post(ecp_ajax.ajax_url, form.serialize() + `&action=ecp_update_account&nonce=${ecp_ajax.nonces.updateAccountNonce}`)
            .done(response => {
                const notice = `<div class="notice notice-${response.success ? 'success' : 'error'}"><p>${response.data.message}</p></div>`;
                msgBox.html(notice).slideDown();
                if(response.success) form.find('input[type="password"]').val('');
            })
            .fail(() => msgBox.html('<div class="notice notice-error"><p>An unknown server error occurred.</p></div>').slideDown())
            .always(() => {
                btn.prop('disabled', false).html(btnHtml);
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            });
    });
});

