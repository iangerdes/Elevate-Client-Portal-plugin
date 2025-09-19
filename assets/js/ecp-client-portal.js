// File: elevate-client-portal/assets/js/ecp-client-portal.js
/**
 * Handles all AJAX logic for the front-end client portal view.
 *
 * @package Elevate_Client_Portal
 * @version 96.0.0 (ZIP Creation & Listing Fix)
 * @comment Re-engineered the ZIP creation process for stability and added the necessary functions to list and manage ready downloads.
 */

window.ECP_Client_Portal_Loaded = true;

jQuery(function ($) {
    const portalWrapper = $('body').find('.ecp-portal-wrapper');
    if (!portalWrapper.length) return;

    function showClientMessage(message, type = 'success') {
        const messageBox = $('#ecp-client-messages');
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
        messageBox.html(notice).slideDown();
        setTimeout(() => notice.fadeOut(500, function() { $(this).remove(); }), 5000);
    }

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
        const wrapper = $('#ecp-bulk-actions-wrapper');
        const checkedCount = $('.ecp-file-checkbox:checked').length;
        if (checkedCount > 0) {
            wrapper.slideDown(200);
        } else {
            wrapper.slideUp(200);
        }
    }

    function fetchReadyDownloads() {
        const container = $('#ecp-ready-downloads-list');
        const wrapper = $('#ecp-ready-downloads-wrapper');
        if (!container.length) return;

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_ready_downloads',
            nonce: ecp_ajax.nonces.zipGetListNonce
        }).done(response => {
            if (response.success && response.data.length > 0) {
                let html = '';
                response.data.forEach(zip => {
                    html += `
                        <tr>
                            <td data-label="File Name">${zip.filename}</td>
                            <td data-label="Date Created">${zip.date}</td>
                            <td data-label="Password">
                                <code>${zip.password}</code>
                                <button class="button button-small ecp-copy-zip-password" data-password="${zip.password}">Copy</button>
                            </td>
                            <td class="ecp-actions-col">
                                <a href="${zip.download_url}" class="button button-primary">Download</a>
                                <button class="button button-link-delete ecp-delete-zip-btn" data-filename="${zip.filename}">Delete</button>
                            </td>
                        </tr>
                    `;
                });
                container.html(html);
                wrapper.slideDown();
            } else {
                container.html('');
                wrapper.slideUp();
            }
        });
    }

    // Initial setup
    if (portalWrapper.is(':not(.ecp-account-page)')) {
        $('#ecp-bulk-actions-wrapper').hide();
        fetchClientFiles();
        // The initial list is now rendered by PHP, but we still call this to be safe
        // in case the PHP-rendered list is empty and needs to be hidden.
        fetchReadyDownloads();
    }

    // Event Handlers
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

    $('body').on('click', '#ecp-create-zip-btn', function() {
        const btn = $(this);
        const originalText = btn.text();
        btn.prop('disabled', true).text('Preparing...');
        
        const fileKeys = $('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get().join(',');

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_prepare_zip',
            nonce: ecp_ajax.nonces.zipPrepareNonce,
            file_keys: fileKeys
        }).done(response => {
            showClientMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) {
                $('.ecp-file-checkbox:checked').prop('checked', false);
                toggleBulkActions();
                setTimeout(fetchReadyDownloads, 500); 
            }
        }).fail(() => showClientMessage('An unknown error occurred while creating the ZIP file.', 'error'))
          .always(() => btn.prop('disabled', false).text(originalText));
    });

    $('body').on('click', '.ecp-copy-zip-password', function() {
        const passwordText = $(this).data('password');
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(passwordText).select();
        document.execCommand('copy');
        tempInput.remove();

        const originalText = $(this).text();
        $(this).text('Copied!');
        setTimeout(() => $(this).text(originalText), 1500);
    });
    
    $('body').on('click', '.ecp-delete-zip-btn', function() {
        if (!confirm(ecp_ajax.strings.confirm_delete_zip)) return;
        const btn = $(this);
        const filename = btn.data('filename');
        btn.prop('disabled', true);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_delete_ready_zip',
            nonce: ecp_ajax.nonces.zipDeleteNonce,
            filename: filename
        }).done(response => {
            if (response.success) {
                fetchReadyDownloads();
            } else {
                showClientMessage(response.data.message, 'error');
                btn.prop('disabled', false);
            }
        }).fail(() => btn.prop('disabled', false));
    });


    $('body').on('click', '.ecp-download-encrypted-btn', function(e) {
        e.preventDefault();
        const password = prompt('This file is encrypted. Please enter the password to download:');
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

