// File: elevate-client-portal/assets/js/ecp-client-portal.js
/**
 * Handles all AJAX logic for the front-end client portal view.
 *
 * @package Elevate_Client_Portal
 * @version 111.0.0 (AJAX Logout Fix)
 * @comment Added an AJAX handler for the logout button to create a cache-proof logout process.
 */

window.ECP_Client_Portal_Loaded = true;

jQuery(function ($) {
    const portalWrapper = $('body').find('.ecp-portal-wrapper');
    if (!portalWrapper.length && !$('body').find('.ecp-login-form-wrapper').length) return;
    
    const ZIP_SIZE_LIMIT_BYTES = 250 * 1024 * 1024; 

    // --- START: ZIP Polling Logic ---
    let zipPollingInterval = null;
    let zipPollingAttempts = 0;
    const MAX_POLLING_ATTEMPTS = 24; // 24 attempts * 5 seconds = 2 minutes timeout

    function stopZipPolling() {
        if (zipPollingInterval) {
            clearInterval(zipPollingInterval);
            zipPollingInterval = null;
            zipPollingAttempts = 0;
        }
    }

    function startZipPolling() {
        stopZipPolling(); 

        const initialZipCount = $('#ecp-ready-downloads-list tr:not(#ecp-zip-processing-indicator)').length;

        zipPollingInterval = setInterval(() => {
            zipPollingAttempts++;
            
            $.post(ecp_ajax.ajax_url, {
                action: 'ecp_get_ready_downloads',
                nonce: ecp_ajax.nonces.zipGetListNonce
            }).done(response => {
                if (response.success) {
                    const newZipCount = response.data.length;
                    if (newZipCount > initialZipCount) {
                        $('#ecp-zip-processing-indicator').remove();
                        fetchReadyDownloads(); 
                        stopZipPolling();
                    }
                }
            });

            if (zipPollingAttempts >= MAX_POLLING_ATTEMPTS) {
                stopZipPolling();
                $('#ecp-zip-processing-indicator').remove();
                showClientMessage('The ZIP creation process is taking longer than expected. The file may still be created. The list will update on your next page load.', 'error');
            }

        }, 5000); 
    }
    // --- END: ZIP Polling Logic ---


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

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function updateSelectionInfo() {
        let totalBytes = 0;
        const checkedFiles = $('.ecp-file-checkbox:checked:not(:disabled)');
        const selectionInfo = $('#ecp-selection-info');
        const zipButton = $('#ecp-create-zip-btn');

        checkedFiles.each(function() {
            totalBytes += parseInt($(this).data('size-bytes'), 10) || 0;
        });

        if (checkedFiles.length > 0) {
            selectionInfo.text(`Selected ${checkedFiles.length} file(s) (${formatBytes(totalBytes)})`);
            if (totalBytes > ZIP_SIZE_LIMIT_BYTES) {
                selectionInfo.addClass('warning').text(selectionInfo.text() + ` - Limit of ${formatBytes(ZIP_SIZE_LIMIT_BYTES)} exceeded.`);
                zipButton.prop('disabled', true);
            } else {
                selectionInfo.removeClass('warning');
                zipButton.prop('disabled', false);
            }
        } else {
            selectionInfo.text('');
        }
    }

    function toggleBulkActions() {
        const wrapper = $('#ecp-bulk-actions-wrapper');
        const checkedCount = $('.ecp-file-checkbox:checked').length;
        if (checkedCount > 0) {
            wrapper.slideDown(200);
        } else {
            wrapper.slideUp(200);
        }
        updateSelectionInfo();
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
                if ($('#ecp-zip-processing-indicator').length === 0) {
                    container.html('');
                    wrapper.slideUp();
                }
            }
        });
    }

    // Initial setup
    if (portalWrapper.is(':not(.ecp-account-page)')) {
        $('#ecp-bulk-actions-wrapper').hide();
        fetchClientFiles();
        fetchReadyDownloads();
    }

    // Event Handlers
    let clientSearchTimeout;
    $('body').on('keyup', '#ecp-search-input', function () {
        clearTimeout(clientSearchTimeout);
        clientSearchTimeout = setTimeout(fetchClientFiles, 400);
    });
    
    $('body').on('change', '#ecp-sort-select, #ecp-folder-filter', fetchClientFiles);
    
    // ** START: Updated Select All Logic **
    // Desktop "Select All"
    $('body').on('change', '#ecp-select-all-files', function() {
        const isChecked = $(this).prop('checked');
        $('#ecp-mobile-select-all-files').prop('checked', isChecked);
        $('.ecp-file-checkbox:not(:disabled)').prop('checked', isChecked);
        toggleBulkActions();
    });

    // Mobile "Select All"
    $('body').on('change', '#ecp-mobile-select-all-files', function() {
        const isChecked = $(this).prop('checked');
        $('#ecp-select-all-files').prop('checked', isChecked);
        $('.ecp-file-checkbox:not(:disabled)').prop('checked', isChecked);
        toggleBulkActions();
    });


    // Individual file checkbox
    $('body').on('change', '.ecp-file-checkbox', function() {
        if (!this.checked) {
            $('#ecp-select-all-files').prop('checked', false);
            $('#ecp-mobile-select-all-files').prop('checked', false);
        }
        toggleBulkActions();
    });
    // ** END: Updated Select All Logic **

    $('body').on('click', '#ecp-create-zip-btn', function() {
        const btn = $(this);
        const originalText = btn.text();
        btn.prop('disabled', true).text('Preparing...');

        const downloadsWrapper = $('#ecp-ready-downloads-wrapper');
        const downloadsList = $('#ecp-ready-downloads-list');
        
        $('#ecp-zip-processing-indicator').remove();

        const indicatorHtml = `
            <tr id="ecp-zip-processing-indicator">
                <td colspan="4" style="text-align: center; padding: 20px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px; color: #555;">
                        <div class="ecp-spinner" style="border: 3px solid rgba(0,0,0,0.1); border-left-color: var(--ecp-primary-color); width: 20px; height: 20px; display: inline-block;"></div>
                        <span>Your ZIP file is being prepared... This list will refresh automatically when it's ready.</span>
                    </div>
                </td>
            </tr>
        `;
        downloadsList.prepend(indicatorHtml);
        downloadsWrapper.slideDown();
        
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
                startZipPolling(); 
            } else {
                $('#ecp-zip-processing-indicator').remove();
            }
        }).fail(function(jqXHR) {
            let errorMessage = 'An unknown error occurred while creating the ZIP file.';
            if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                errorMessage = jqXHR.responseJSON.data.message;
            }
            showClientMessage(errorMessage, 'error');
            $('#ecp-zip-processing-indicator').remove();
        }).always(() => btn.prop('disabled', false).text(originalText));
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

    $('body').on('click', '.ecp-ajax-logout-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        btn.prop('disabled', true).text('Logging out...');

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_logout_user',
            nonce: ecp_ajax.nonces.logoutNonce
        }).done(response => {
            if (response.success && response.data.redirect_url) {
                window.location.href = response.data.redirect_url;
            } else {
                btn.prop('disabled', false).text('Logout');
                showClientMessage('Could not log out. Please try again.', 'error');
            }
        }).fail(() => {
            btn.prop('disabled', false).text('Logout');
            showClientMessage('An unknown error occurred during logout.', 'error');
        });
    });

    // ** NEW: AJAX Login Handler **
    $('body').on('submit', '#ecp-ajax-login-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('#ecp-login-submit-btn');
        const errorContainer = $('#ecp-login-error-container');
        
        btn.prop('disabled', true).text('Logging In...');
        errorContainer.slideUp().html('');

        $.post(ecp_ajax.ajax_url, form.serialize() + `&action=ecp_ajax_login&nonce=${ecp_ajax.nonces.ajaxLoginNonce}`)
            .done(response => {
                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    errorContainer.html(response.data.message).slideDown();
                    btn.prop('disabled', false).text('Log In');
                }
            })
            .fail(() => {
                errorContainer.html('An unknown server error occurred. Please try again.').slideDown();
                btn.prop('disabled', false).text('Log In');
            });
    });
});

