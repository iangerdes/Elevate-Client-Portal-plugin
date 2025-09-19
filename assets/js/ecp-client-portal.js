/**
 * Handles all AJAX logic for the front-end client portal view.
 * Uses server-side decryption.
 * Version: 5.6.1 (Complete)
 */
jQuery(function ($) {
    const portalWrapper = $('body').find('.ecp-portal-wrapper');
    if (!portalWrapper.length) return;

    // --- Element Selectors ---
    const searchInput = $('#ecp-search-input');
    const sortSelect = $('#ecp-sort-select');
    const folderFilter = $('#ecp-folder-filter');
    const fileListContainer = $('#ecp-file-list-container');
    const loader = $('#ecp-loader');
    const noFilesMessage = $('#ecp-no-files-message');
    const bulkActionsWrapper = $('#ecp-bulk-actions-wrapper');
    const downloadZipBtn = $('#ecp-download-zip-btn');
    const zipPasswordWrapper = $('#ecp-zip-password-wrapper');
    const zipPasswordField = $('#ecp-zip-password');
    const selectionInfo = $('#ecp-selection-info');

    function fetchClientFiles() {
        if (!fileListContainer.length) return;
        loader.show();
        fileListContainer.css('opacity', '0.5');
        noFilesMessage.hide();

        $.ajax({
            url: ecp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ecp_filter_files',
                nonce: ecp_ajax.nonce,
                search: searchInput.val(),
                sort: sortSelect.val(),
                folder: folderFilter.val()
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.length > 0) {
                        response.data.forEach(file => {
                            const encryptedAttr = file.is_encrypted ? `data-encrypted="true"` : '';
                            const encryptedIcon = file.is_encrypted ? `<span class="ecp-encrypted-icon" title="Encrypted"></span>` : '';
                            const checkboxDisabled = file.is_encrypted ? 'disabled' : '';

                            html += `
                                <tr data-mime-type="${file.type || 'application/octet-stream'}">
                                    <td class="ecp-col-checkbox"><input type="checkbox" class="ecp-file-checkbox" value="${file.key}" data-size-bytes="${file.size_bytes}" ${checkboxDisabled}></td>
                                    <td data-label="File Name">${file.name} ${encryptedIcon}</td>
                                    <td data-label="Folder">${file.folder}</td>
                                    <td data-label="Date">${file.date}</td>
                                    <td data-label="Size">${file.size}</td>
                                    <td class="ecp-actions-col"><a href="${file.download_url}" class="button ecp-download-btn" ${encryptedAttr} data-filename="${file.name}">Download</a></td>
                                </tr>
                            `;
                        });
                        fileListContainer.html(html);
                    } else {
                        fileListContainer.empty();
                        noFilesMessage.text('No files match your criteria.').show();
                    }
                } else {
                    fileListContainer.empty();
                    noFilesMessage.text(response.data.message || 'An error occurred while loading files.').show();
                }
            },
            error: function () {
                fileListContainer.empty();
                noFilesMessage.text('An unexpected error occurred. Please try again.').show();
            },
            complete: function () {
                loader.hide();
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
        const checkedBoxes = $('.ecp-file-checkbox:checked');
        checkedBoxes.each(function() {
            totalBytes += parseInt($(this).data('size-bytes'), 10) || 0;
        });

        const fileCount = checkedBoxes.length;
        if (fileCount > 0) {
            selectionInfo.html(`${fileCount} file(s) selected | Total size: ${formatBytes(totalBytes)}`);
            if (totalBytes > 200 * 1024 * 1024) { // 200 MB Warning Threshold
                selectionInfo.addClass('warning');
            } else {
                selectionInfo.removeClass('warning');
            }
        } else {
            selectionInfo.html('');
        }
    }

    function toggleBulkActions() {
        const checkedCount = $('.ecp-file-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkActionsWrapper.slideDown(200);
        } else {
            bulkActionsWrapper.slideUp(200);
            zipPasswordWrapper.hide();
        }
        updateSelectionInfo();
    }

    // --- Event Handlers ---
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
        if (!this.checked) {
            $('#ecp-select-all-files').prop('checked', false);
        }
        toggleBulkActions();
    });

    $('body').on('click', '#ecp-download-zip-btn', function() {
        const btn = $(this);
        btn.prop('disabled', true).text('Generating ZIP...');
        zipPasswordWrapper.slideUp(100);
        
        const fileKeys = $('.ecp-file-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_create_zip',
            nonce: ecp_ajax.nonce,
            file_keys: fileKeys
        })
        .done(function(response) {
            if (response.success) {
                zipPasswordField.text(response.data.password);
                zipPasswordWrapper.slideDown(200);
                window.location.href = response.data.download_url;
            } else {
                alert('Error: ' + (response.data.message || 'Could not create ZIP.'));
            }
        })
        .fail(function() {
            alert(ecp_ajax.strings.error_zip);
        })
        .always(function() {
            btn.prop('disabled', false).text('Download Selected as ZIP');
        });
    });

    $('body').on('click', '#ecp-copy-zip-password', function() {
        const btn = $(this);
        const password = zipPasswordField.text();
        
        const tempTextArea = $('<textarea>');
        $('body').append(tempTextArea);
        tempTextArea.val(password).select();
        
        try {
            document.execCommand('copy');
            const originalText = btn.text();
            btn.text(ecp_ajax.strings.copied || 'Copied!');
            setTimeout(() => btn.text(originalText), 2000);
        } catch (err) {
            console.error('Failed to copy password: ', err);
            alert('Could not copy password.');
        }

        tempTextArea.remove();
    });

    // Download button handler for encrypted files
    $('body').on('click', '.ecp-download-btn[data-encrypted="true"]', function(e) {
        e.preventDefault();
        const btn = $(this);
        const fileKey = btn.closest('tr').find('.ecp-file-checkbox').val();
        
        const password = prompt(ecp_ajax.strings.decrypt_prompt, '');

        if (password) {
            // We create a temporary form to submit the password.
            // This triggers a standard browser download and handles any errors gracefully.
            const form = $('<form>', {
                'method': 'POST',
                'action': ecp_ajax.ajax_url.replace('admin-ajax.php', '') + '?ecp_action=download_decrypted_file&_wpnonce=' + ecp_ajax.nonce
            }).append(
                $('<input>', { 'type': 'hidden', 'name': 'file_key', 'value': fileKey })
            ).append(
                $('<input>', { 'type': 'hidden', 'name': 'password', 'value': password })
            );

            $('body').append(form);
            form.submit();
            form.remove();
        }
    });

    $('body').on('click', '#ecp-contact-manager-toggle', function() {
        $('#ecp-contact-manager-form-wrapper').slideToggle(200);
    });

    $('body').on('submit', '#ecp-contact-manager-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const messageBox = form.find('#ecp-contact-form-messages');

        submitBtn.prop('disabled', true).text('Sending...');
        messageBox.html('').hide();

        $.post(ecp_ajax.ajax_url, form.serialize())
            .done(function(response) {
                if (response.success) {
                    messageBox.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').slideDown();
                    form.find('input[type="text"], textarea').val('');
                    setTimeout(() => {
                        $('#ecp-contact-manager-form-wrapper').slideUp(200);
                        messageBox.html('').hide();
                    }, 4000);
                } else {
                    messageBox.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').slideDown();
                }
            })
            .fail(function() {
                messageBox.html('<div class="notice notice-error"><p>An unknown server error occurred.</p></div>').slideDown();
            })
            .always(function() {
                submitBtn.prop('disabled', false).text('Send Message');
            });
    });

    $('body').on('click', 'a.button[href*="/account"]', function(e) {
        e.preventDefault();
        const button = $(this);
        const accountUrl = button.attr('href'); 
        const mainContentWrapper = button.closest('.ecp-portal-wrapper');

        if (!mainContentWrapper.length) return;

        mainContentWrapper.html('<div id="ecp-loader" style="display:block; position:relative; top:50px; left:50%; transform:translateX(-50%);"><div class="ecp-spinner"></div></div>');

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_account_page',
            nonce: ecp_ajax.nonce
        })
        .done(function(response) {
            if (response.success) {
                const newContent = $(response.data.html);
                if (newContent.length) {
                    mainContentWrapper.replaceWith(newContent);
                } else {
                    alert('Could not parse account page content. Redirecting now.');
                    window.location.href = accountUrl;
                }
            } else {
                alert(response.data.message || 'Could not load account page. Redirecting now.');
                window.location.href = accountUrl;
            }
        })
        .fail(function() {
            alert('A server error occurred while loading the account page. Redirecting now.');
            window.location.href = accountUrl;
        });
    });

    $('body').on('submit', '#ecp-account-details-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const messageBox = $('#ecp-account-messages');
        const originalButtonText = submitBtn.text();

        submitBtn.prop('disabled', true).text('Saving...');
        messageBox.html('').hide();

        $.post(ecp_ajax.ajax_url, form.serialize())
            .done(function(response) {
                const noticeClass = response.success ? 'notice-success' : 'notice-error';
                messageBox.html('<div class="notice ' + noticeClass + '"><p>' + response.data.message + '</p></div>').slideDown();
                
                if(response.success) {
                    form.find('#ecp_user_password, #ecp_user_password_confirm').val('');
                }
            })
            .fail(function() {
                messageBox.html('<div class="notice notice-error"><p>An unknown server error occurred.</p></div>').slideDown();
            })
            .always(function() {
                submitBtn.prop('disabled', false).text(originalButtonText);
                $('html, body').animate({ scrollTop: 0 }, 'slow');
            });
    });
});
