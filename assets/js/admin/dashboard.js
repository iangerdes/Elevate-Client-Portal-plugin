// File: elevate-client-portal/assets/js/admin/dashboard.js
/**
 * Handles all core AJAX logic and component interactions for the administrator dashboard.
 * This file is a consolidated version of dashboard.js, file-manager.js, and uploader.js.
 *
 * @package Elevate_Client_Portal
 * @version 127.0.0 (Global Helper Fix)
 * @comment Re-established the global ECP_Admin helper object to resolve "is not defined" errors. All helper functions are now correctly attached to this global object, ensuring they are always available when needed.
 */

// Establish the global helper object to prevent reference errors.
window.ECP_Admin = window.ECP_Admin || {};

jQuery(function($) {
    const adminDashboard = $('.ecp-admin-dashboard');
    if (!adminDashboard.length) return;

    // --- START: Attach Helper Functions to Global Object ---
    if (!adminDashboard.find('.ecp-loader-overlay').length) {
        adminDashboard.append('<div class="ecp-loader-overlay" style="display:none;"><div class="ecp-spinner"></div><p>Processing...</p></div>');
    }

    ECP_Admin.showAdminMessage = function(message, type = 'success') {
        const messageBox = $('#ecp-admin-messages');
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        messageBox.html(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`).fadeIn();
        setTimeout(() => messageBox.fadeOut(500, function() { $(this).html('').show(); }), 5000);
    };

    ECP_Admin.showBlockingLoader = function(message = 'Processing...') {
        $('.ecp-loader-overlay').find('p').text(message).end().css('display', 'flex');
    };

    ECP_Admin.hideBlockingLoader = function() {
        $('.ecp-loader-overlay').fadeOut(200);
    };

    ECP_Admin.refreshFileManager = function(userId) {
        const id = parseInt(userId, 10);
        if (isNaN(id)) return;
        
        const fileManagerView = $(`#ecp-file-manager-view-${id}`);
        if (fileManagerView.length) {
            fileManagerView.css('opacity', 0.5);
            $.post(ecp_ajax.ajax_url, {
                action: 'ecp_get_view',
                nonce: ecp_ajax.nonces.viewNonce,
                view: id === 0 ? 'all_users_files' : 'file_manager',
                user_id: id
            }).done(response => {
                if (response.success) {
                    const newContent = $(response.data).html();
                    fileManagerView.html(newContent).css('opacity', 1);
                } else {
                    ECP_Admin.showAdminMessage(response.data.message || 'Error refreshing file list.', 'error');
                    fileManagerView.css('opacity', 1);
                }
            }).fail(() => {
                ECP_Admin.showAdminMessage('An unexpected server error occurred while refreshing.', 'error');
                fileManagerView.css('opacity', 1);
            });
        }
    };
    // --- END: Helper Functions ---

    // --- START: Main Dashboard View Logic & Navigation ---
    function loadView(viewHtml) {
        $('#ecp-dashboard-main-content').html(viewHtml);
    }

    function loadNamedView(viewName, data = {}) {
        $('#ecp-dashboard-main-content').html('<div id="ecp-loader" style="display:block; position: relative; top: 50px;"><div class="ecp-spinner"></div></div>');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_view',
            nonce: ecp_ajax.nonces.viewNonce,
            view: viewName,
            ...data
        }).done(response => {
            if (response.success) {
                loadView(response.data);
                if (viewName === 'user_list') {
                    fetchUserListTable();
                }
            } else {
                ECP_Admin.showAdminMessage(response.data.message || 'Error loading view.', 'error');
                loadNamedView('user_list');
            }
        }).fail(() => {
            ECP_Admin.showAdminMessage('An unexpected server error occurred.', 'error');
            loadNamedView('user_list');
        });
    }

    function fetchUserListTable(searchTerm = '') {
        const userListContainer = $('#ecp-admin-user-list-container');
        if (!userListContainer.length) return;
        userListContainer.css('opacity', 0.5);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_admin_dashboard_actions',
            nonce: ecp_ajax.nonces.dashboardNonce,
            sub_action: 'search_users',
            search: searchTerm
        }).done(response => {
            userListContainer.html(response.success ? response.data : '<tr><td colspan="5">Error loading users.</td></tr>');
        }).fail(() => userListContainer.html('<tr><td colspan="5">Server error.</td></tr>'))
          .always(() => userListContainer.css('opacity', 1));
    }
    
    // Initial load
    if ($('#ecp-admin-user-list-container').length) {
        fetchUserListTable();
    }

    // --- START: Delegated Event Handlers ---
    adminDashboard.on('click', '.ecp-back-to-users', () => loadNamedView('user_list'));
    adminDashboard.on('click', '#ecp-add-new-client-btn', () => loadNamedView('add_user'));
    adminDashboard.on('click', '#ecp-all-users-files-btn', () => loadNamedView('all_users_files'));
    adminDashboard.on('click', '#ecp-file-summary-btn', () => loadNamedView('file_summary'));
    adminDashboard.on('click', '#ecp-role-permissions-btn', () => loadNamedView('role_permissions'));
    adminDashboard.on('click', '.edit-user-btn', function() { loadNamedView('edit_user', { user_id: $(this).closest('tr').data('userid') }); });
    adminDashboard.on('click', '.manage-files-btn', function() { loadNamedView('file_manager', { user_id: $(this).closest('tr').data('userid') }); });
    
    let adminSearchTimeout;
    adminDashboard.on('keyup', '#ecp-admin-user-search', function() {
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(() => fetchUserListTable($(this).val()), 400);
    });

    adminDashboard.on('submit', '#ecp-user-details-form, #ecp-role-permissions-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const btnHtml = btn.html();
        btn.prop('disabled', true).text('Saving...');
        $.post(ecp_ajax.ajax_url, form.serialize() + '&nonce=' + ecp_ajax.nonces.dashboardNonce)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message || 'Success!', response.success ? 'success' : 'error');
                if (response.success && form.attr('id') === 'ecp-user-details-form') {
                    loadNamedView('user_list');
                }
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown error occurred.', 'error'))
            .always(() => btn.prop('disabled', false).html(btnHtml));
    });

    adminDashboard.on('click', '.ecp-user-action-btn', function() {
        const btn = $(this);
        const action = btn.data('action');
        const userId = btn.closest('tr').data('userid');
        const ajaxData = { action: 'ecp_admin_dashboard_actions', nonce: ecp_ajax.nonces.dashboardNonce, sub_action: action, user_id: userId };
        let confirmMsg = '';
        if (action === 'toggle_status') {
            ajaxData.enable = btn.data('enable');
            confirmMsg = ecp_ajax.strings.confirm_action.replace('%s', ajaxData.enable ? 'enable' : 'disable');
        } else if (action === 'remove_user') {
            confirmMsg = ecp_ajax.strings.confirm_delete_user;
        }
        if (confirmMsg && !confirm(confirmMsg)) return;
        ECP_Admin.showBlockingLoader();
        $.post(ecp_ajax.ajax_url, ajaxData)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
                if (response.success) {
                    fetchUserListTable($('#ecp-admin-user-search').val());
                }
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown server error occurred.', 'error'))
            .always(() => ECP_Admin.hideBlockingLoader());
    });
    
    adminDashboard.on('change', '#ecp_user_role', function() {
        const role = $(this).val();
        const form = $(this).closest('form');
        form.find('.ecp-business-admin-field').toggle(role === 'ecp_business_admin');
        form.find('.ecp-client-field').toggle(role === 'ecp_client' || role === 'scp_client');
    }).find('#ecp_user_role').trigger('change');

    adminDashboard.on('click', '.ecp-ajax-logout-btn', function(e) {
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
                ECP_Admin.showAdminMessage('Could not log out. Please try again.', 'error');
            }
        }).fail(() => {
            btn.prop('disabled', false).text('Logout');
            ECP_Admin.showAdminMessage('An unknown error occurred during logout.', 'error');
        });
    });

    // --- START: File Manager Logic ---
    function executeBulkAction(userId, bulkAction, fileKeys, details = '') {
        ECP_Admin.showBlockingLoader('Applying action...');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: 'bulk_actions',
            user_id: userId,
            file_keys: fileKeys,
            bulk_action: bulkAction,
            details: details
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) ECP_Admin.refreshFileManager(userId);
        }).fail(() => ECP_Admin.showAdminMessage('An unknown server error occurred.', 'error'))
          .always(() => ECP_Admin.hideBlockingLoader());
    }
    
    adminDashboard.on('change', '.ecp-admin-folder-filter', function() {
        const userId = $(this).data('userid');
        const folder = $(this).val();
        const fileListBody = $(this).closest('.ecp-file-manager').find('.file-list-body');
        fileListBody.css('opacity', 0.5);

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: 'filter_files',
            user_id: userId,
            folder: folder
        }).done(response => {
            fileListBody.html(response.success ? response.data : `<tr><td colspan="6">${response.data.message || 'Error.'}</td></tr>`);
        }).fail(() => fileListBody.html('<tr><td colspan="6">Server error.</td></tr>'))
          .always(() => fileListBody.css('opacity', 1));
    });

    adminDashboard.on('change', '.ecp-file-manager .ecp-select-all-files', function() {
        $(this).closest('table').find('.ecp-file-checkbox').prop('checked', $(this).prop('checked')).trigger('change');
    });

    adminDashboard.on('change', '.ecp-file-manager .ecp-file-checkbox', function() {
        const table = $(this).closest('table');
        const allChecked = table.find('.ecp-file-checkbox:checked').length === table.find('.ecp-file-checkbox').length;
        table.find('.ecp-select-all-files').prop('checked', allChecked);
    });

    adminDashboard.on('click', '.ecp-bulk-action-apply', function(e) {
        e.stopImmediatePropagation();
        const fileManager = $(this).closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const action = fileManager.find('.ecp-bulk-action-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();

        if (!action || fileKeys.length === 0) {
            ECP_Admin.showAdminMessage('Please select an action and at least one file.', 'error');
            return;
        }

        if (action === 'encrypt' || action === 'decrypt') {
            const password = prompt(`Please enter a password to ${action} the selected files:`);
            if (password) executeBulkAction(userId, action, fileKeys, password);
        } else if (action === 'delete') {
            if (confirm(ecp_ajax.strings.confirm_delete_file)) {
                executeBulkAction(userId, action, fileKeys);
            }
        } else if (action === 'move') {
            const modal = fileManager.find('#ecp-move-files-modal');
            modal.find('#ecp-modal-folder-select').html(fileManager.find('.ecp-upload-folder-select').html());
            modal.find('.ecp-modal-file-list').html('');
            fileManager.find('.ecp-file-checkbox:checked').each(function() {
                const fileName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
                modal.find('.ecp-modal-file-list').append(`<div>${fileName}</div>`);
            });
            modal.css('display', 'flex');
        }
    });

    adminDashboard.on('click', '.ecp-single-file-action-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const action = btn.data('action');
        const fileManager = btn.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const fileKey = btn.data('filekey');

        if (action === 'encrypt' || action === 'decrypt') {
            const promptMessage = action === 'encrypt' ? ecp_ajax.strings.encrypt_prompt : ecp_ajax.strings.decrypt_prompt;
            const password = prompt(promptMessage);
            if (password) executeBulkAction(userId, action, [fileKey], password);
        } else if (action === 'delete') {
             if (confirm(ecp_ajax.strings.confirm_delete_file.replace('the selected files', 'this file'))) {
                executeBulkAction(userId, 'delete', [fileKey]);
            }
        }
    });

    adminDashboard.on('click', '.ecp-download-encrypted-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const fileKey = btn.data('filekey');
        const fileManager = btn.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');

        const password = prompt(ecp_ajax.strings.decrypt_prompt);
        if (password) {
            const form = $('<form>', {
                'method': 'POST',
                'action': `${ecp_ajax.home_url}?ecp_action=download_decrypted_file`
            }).append(
                $('<input>', { 'type': 'hidden', 'name': 'file_key', 'value': fileKey }),
                $('<input>', { 'type': 'hidden', 'name': 'target_user_id', 'value': userId }),
                $('<input>', { 'type': 'hidden', 'name': 'nonce', 'value': ecp_ajax.nonces.decryptFileNonce }),
                $('<input>', { 'type': 'hidden', 'name': 'password', 'value': password })
            );
            $('body').append(form);
            form.submit().remove();
        }
    });

    adminDashboard.on('click', '#ecp-modal-confirm-move-btn', function() {
        const modal = $(this).closest('.ecp-modal-overlay');
        const fileManager = modal.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const newFolder = modal.find('#ecp-modal-folder-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();
        executeBulkAction(userId, 'move', fileKeys, newFolder);
        modal.fadeOut(200);
    });

    adminDashboard.on('click', '.ecp-modal-cancel-btn', function() {
        $(this).closest('.ecp-modal-overlay').fadeOut(200);
    });

    adminDashboard.on('submit', '#ecp-add-folder-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const userId = form.find('input[name="user_id"]').val();
        ECP_Admin.showBlockingLoader('Saving folder...');
        
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: 'add_folder',
            user_id: userId,
            folder: form.find('input[name="folder"]').val(),
            location: form.find('input[name="location"]').val()
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) {
                form[0].reset();
                ECP_Admin.refreshFileManager(userId);
            }
        }).fail(() => ECP_Admin.showAdminMessage('An error occurred.', 'error'))
        .always(() => ECP_Admin.hideBlockingLoader());
    });

    adminDashboard.on('click', '.ecp-delete-folder-btn', function() {
        if (!confirm(ecp_ajax.strings.confirm_delete_folder)) return;
        const btn = $(this);
        const userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        ECP_Admin.showBlockingLoader('Deleting folder...');
        
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: 'delete_folder',
            user_id: userId,
            folder_name: btn.data('folder')
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) ECP_Admin.refreshFileManager(userId);
        }).fail(() => ECP_Admin.showAdminMessage('An error occurred.', 'error'))
        .always(() => ECP_Admin.hideBlockingLoader());
    });
    
    adminDashboard.on('change', '.ecp-change-category', function() {
        $(this).closest('td').find('.ecp-save-category-btn').fadeIn();
    });

    adminDashboard.on('click', '.ecp-save-category-btn', function() {
        const btn = $(this);
        const select = btn.closest('td').find('.ecp-change-category');
        const fileKey = select.data('filekey');
        const newFolder = select.val();
        const fileManager = btn.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');

        btn.prop('disabled', true);
        ECP_Admin.showBlockingLoader('Updating folder...');

        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: 'update_category',
            user_id: userId,
            file_key: fileKey,
            new_folder: newFolder
        }).done(response => {
            if (response.success) {
                btn.fadeOut();
                 ECP_Admin.showAdminMessage('Folder updated.', 'success');
            } else {
                ECP_Admin.showAdminMessage(response.data.message || 'Error updating folder.', 'error');
            }
        }).fail(() => ECP_Admin.showAdminMessage('A server error occurred.', 'error'))
          .always(() => {
              btn.prop('disabled', false);
              ECP_Admin.hideBlockingLoader();
          });
    });

    // --- START: Uploader Logic ---
    let fileQueue = [];
    let isUploading = false;

    adminDashboard.on('change', '.ecp-file-upload-input', function(e) {
        const form = $(this).closest('form');
        form.find('#ecp-upload-progress-container').show();
        $.each(e.target.files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            addToQueue(file, form);
        });
        if (!isUploading) processQueue(form);
    });

    adminDashboard.on('change', '.ecp-encrypt-toggle', function() {
        $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle($(this).is(':checked'));
    });
    
    adminDashboard.on('dragover', '.ecp-dropzone-area', function(e) {
        e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover');
    });

    adminDashboard.on('dragleave', '.ecp-dropzone-area', function(e) {
        e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
    });

    adminDashboard.on('drop', '.ecp-dropzone-area', function(e) {
        e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
        const form = $(this).closest('form');
        form.find('#ecp-upload-progress-container').show();
        const files = e.originalEvent.dataTransfer.files;
        $.each(files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            addToQueue(file, form);
        });
        if (!isUploading) processQueue(form);
    });
    
    adminDashboard.on('click', '.ecp-dropzone-area', function(e) {
        e.preventDefault();
        $(this).closest('form').find('.ecp-file-upload-input').trigger('click');
    });

    function addToQueue(file, form) {
        fileQueue.push(file);
        const progressContainer = form.find('#ecp-upload-progress-container');
        progressContainer.append(`
            <div class="ecp-upload-item" id="file-${file.queueId}">
                <div class="ecp-upload-filename">${file.name}</div>
                <div class="ecp-progress-bar-outer"><div class="ecp-progress-bar-inner" style="width: 0%;"></div></div>
                <div class="ecp-upload-status">Waiting...</div>
            </div>
        `);
    }

    function processQueue(form) {
        if (isUploading) return;
        
        if (fileQueue.length === 0) {
            if (!isUploading) {
                setTimeout(() => {
                    form.find('#ecp-upload-progress-container').fadeOut(500, function() { $(this).html('').show(); });
                    ECP_Admin.refreshFileManager(form.find('input[name="user_id"]').val());
                }, 1500);
            }
            return;
        }

        isUploading = true;
        const file = fileQueue.shift();
        const progressItem = $(`#file-${file.queueId}`);
        progressItem.find('.ecp-upload-status').text('Uploading...');
        
        const formData = new FormData(form[0]);
        formData.append('ecp_file_upload', file);
        formData.append('original_filename', file.name);
        formData.set('nonce', ecp_ajax.nonces.fileManagerNonce);

        $.ajax({
            url: ecp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        progressItem.find('.ecp-progress-bar-inner').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            }
        }).done(response => {
            const statusIndicator = progressItem.find('.ecp-upload-status');
            if (response.success) {
                statusIndicator.text('Complete!').addClass('success');
            } else {
                statusIndicator.text(response.data.message || 'Failed.').addClass('error');
            }
        }).fail(() => {
            statusIndicator.text('Server Error.').addClass('error');
        }).always(() => {
            isUploading = false;
            processQueue(form);
        });
    }
});

