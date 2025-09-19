// File: elevate-client-portal/assets/js/ecp-admin-dashboard.js
/**
 * Handles all AJAX logic for the administrator dashboard.
 * This is a combined file including the main dashboard, file actions, and uploader logic.
 *
 * @package Elevate_Client_Portal
 * @version 74.3.0 (Fix Move Modal Button)
 * @comment Fixed the 'Move Files' button in the bulk action modal by correcting the jQuery selector from a class to an ID.
 */

// Define a global object to hold shared functions.
window.ECP_Admin = {
    showAdminMessage: function(message, type = 'success') {
        const messageBox = jQuery('#ecp-admin-messages');
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        messageBox.html(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`).fadeIn();
        setTimeout(() => messageBox.fadeOut(500, function() { jQuery(this).html('').show(); }), 5000);
    },
    showBlockingLoader: function(message = 'Processing...') {
        jQuery('.ecp-loader-overlay').find('p').text(message).end().css('display', 'flex');
    },
    hideBlockingLoader: function() {
        jQuery('.ecp-loader-overlay').fadeOut(200);
    },
    refreshFileManager: function(userId) {
        const id = parseInt(userId, 10);
        if (isNaN(id)) return;
        
        const fileManagerView = jQuery(`#ecp-file-manager-view-${id}`);
        if (fileManagerView.length) {
            fileManagerView.css('opacity', 0.5);
            jQuery.post(ecp_ajax.ajax_url, {
                action: 'ecp_get_view',
                nonce: ecp_ajax.nonces.viewNonce,
                view: id === 0 ? 'all_users_files' : 'file_manager',
                user_id: id
            }).done(response => {
                if (response.success) {
                    const newContent = jQuery(response.data).html();
                    fileManagerView.html(newContent).css('opacity', 1);
                } else {
                    this.showAdminMessage(response.data.message || 'Error refreshing file list.', 'error');
                    fileManagerView.css('opacity', 1);
                }
            }).fail(() => {
                this.showAdminMessage('An unexpected server error occurred while refreshing.', 'error');
                fileManagerView.css('opacity', 1);
            });
        }
    }
};

jQuery(function($) {
    const adminDashboard = $('.ecp-admin-dashboard');
    if (!adminDashboard.length) return;

    const mainContentArea = $('#ecp-dashboard-main-content');

    // === START: Core Dashboard Logic ===
    if (!adminDashboard.find('.ecp-loader-overlay').length) {
        adminDashboard.append('<div class="ecp-loader-overlay" style="display:none;"><div class="ecp-spinner"></div><p>Processing...</p></div>');
    }

    function loadView(viewHtml) { mainContentArea.html(viewHtml); }

    function loadNamedView(viewName, data = {}) {
        mainContentArea.html('<div id="ecp-loader" style="display:block; position: relative; top: 50px;"><div class="ecp-spinner"></div></div>');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_view', nonce: ecp_ajax.nonces.viewNonce, view: viewName, ...data
        }).done(response => {
            if (response.success) {
                loadView(response.data);
                if (viewName === 'user_list') fetchUserListTable();
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
            action: 'ecp_admin_dashboard_actions', nonce: ecp_ajax.nonces.dashboardNonce, sub_action: 'search_users', search: searchTerm
        }).done(response => userListContainer.html(response.success ? response.data : '<tr><td colspan="5">Error loading users.</td></tr>'))
          .fail(() => userListContainer.html('<tr><td colspan="5">Server error.</td></tr>'))
          .always(() => userListContainer.css('opacity', 1));
    }

    mainContentArea.on('click.adminDashboard', '.ecp-back-to-users', () => loadNamedView('user_list'));
    mainContentArea.on('click.adminDashboard', '#ecp-add-new-client-btn', () => loadNamedView('add_user'));
    mainContentArea.on('click.adminDashboard', '#ecp-all-users-files-btn', () => loadNamedView('all_users_files'));
    mainContentArea.on('click.adminDashboard', '#ecp-file-summary-btn', () => loadNamedView('file_summary'));
    mainContentArea.on('click.adminDashboard', '#ecp-role-permissions-btn', () => loadNamedView('role_permissions'));
    mainContentArea.on('click.adminDashboard', '.edit-user-btn', function() { loadNamedView('edit_user', { user_id: $(this).closest('tr').data('userid') }); });
    mainContentArea.on('click.adminDashboard', '.manage-files-btn', function() { loadNamedView('file_manager', { user_id: $(this).closest('tr').data('userid') }); });
    
    let adminSearchTimeout;
    mainContentArea.on('keyup.adminDashboard', '#ecp-admin-user-search', function() {
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(() => fetchUserListTable($(this).val()), 400);
    });

    mainContentArea.on('submit.adminDashboard', '#ecp-user-details-form, #ecp-role-permissions-form', function(e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), btnHtml = btn.html();
        btn.prop('disabled', true).text('Saving...');
        $.post(ecp_ajax.ajax_url, form.serialize() + '&nonce=' + ecp_ajax.nonces.dashboardNonce)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message || 'Success!', response.success ? 'success' : 'error');
                if (response.success && form.attr('id') === 'ecp-user-details-form') loadNamedView('user_list');
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown error occurred.', 'error'))
            .always(() => btn.prop('disabled', false).html(btnHtml));
    });

    mainContentArea.on('click.adminDashboard', '.ecp-user-action-btn', function() {
        const btn = $(this), action = btn.data('action'), userId = btn.closest('tr').data('userid');
        const ajaxData = { action: 'ecp_admin_dashboard_actions', nonce: ecp_ajax.nonces.dashboardNonce, sub_action: action, user_id: userId };
        let confirmMsg = '';
        if (action === 'toggle_status') {
            ajaxData.enable = btn.data('enable');
            confirmMsg = ecp_ajax.strings.confirm_action.replace('%s', ajaxData.enable ? 'enable' : 'disable');
        } else if (action === 'remove_user') {
            confirmMsg = ecp_ajax.strings.confirm_delete_user;
        }
        if (!confirm(confirmMsg)) return;
        ECP_Admin.showBlockingLoader();
        $.post(ecp_ajax.ajax_url, ajaxData)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
                if (response.success) fetchUserListTable($('#ecp-admin-user-search').val());
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown server error occurred.', 'error'))
            .always(() => ECP_Admin.hideBlockingLoader());
    });
    
    mainContentArea.on('change.adminDashboard', '#ecp_user_role', function() {
        const role = $(this).val(), form = $(this).closest('form');
        form.find('.ecp-business-admin-field').toggle(role === 'ecp_business_admin');
        form.find('.ecp-client-field').toggle(role === 'ecp_client' || role === 'scp_client');
    }).trigger('change');

    if ($('#ecp-admin-user-list-container').length) fetchUserListTable();
    // === END: Core Dashboard Logic ===

    // === START: File Manager Actions Logic ===
    function executeBulkAction(userId, bulkAction, fileKeys, details = '') {
        ECP_Admin.showBlockingLoader('Applying action...');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions', nonce: ecp_ajax.nonces.fileManagerNonce, sub_action: 'bulk_actions',
            user_id: userId, file_keys: fileKeys, bulk_action: bulkAction, details: details
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) ECP_Admin.refreshFileManager(userId);
        }).fail(() => ECP_Admin.showAdminMessage('An unknown server error occurred.', 'error'))
          .always(() => ECP_Admin.hideBlockingLoader());
    }
    
    mainContentArea.on('change.ecpFileManagerActions', '.ecp-admin-folder-filter', function() {
        const userId = $(this).data('userid'), folder = $(this).val();
        const fileListBody = mainContentArea.find(`#ecp-file-manager-view-${userId} .file-list-body`);
        fileListBody.css('opacity', 0.5);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions', nonce: ecp_ajax.nonces.fileManagerNonce, sub_action: 'filter_files',
            user_id: userId, folder: folder
        }).done(response => {
            fileListBody.html(response.success ? response.data : `<tr><td colspan="6">${response.data.message || 'Error.'}</td></tr>`);
        }).fail(() => fileListBody.html('<tr><td colspan="6">Server error.</td></tr>'))
          .always(() => fileListBody.css('opacity', 1));
    });

    mainContentArea.on('change.ecpFileManagerActions', '.ecp-file-manager .ecp-select-all-files', function() {
        $(this).closest('table').find('.ecp-file-checkbox').prop('checked', $(this).prop('checked')).trigger('change');
    });

    mainContentArea.on('change.ecpFileManagerActions', '.ecp-file-manager .ecp-file-checkbox', function() {
        const table = $(this).closest('table');
        const allChecked = table.find('.ecp-file-checkbox:checked').length === table.find('.ecp-file-checkbox').length;
        table.find('.ecp-select-all-files').prop('checked', allChecked);
    });

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-bulk-action-apply', function(e) {
        e.stopImmediatePropagation();
        const fileManager = $(this).closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const action = fileManager.find('.ecp-bulk-action-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();

        if (!action || fileKeys.length === 0) {
            ECP_Admin.showAdminMessage('Please select an action and at least one file.', 'error'); return;
        }
        if (action === 'encrypt' || action === 'decrypt') {
            const password = prompt(`Please enter a password to ${action} the selected files:`);
            if (password) executeBulkAction(userId, action, fileKeys, password);
        } else if (action === 'delete') {
            if (confirm(ecp_ajax.strings.confirm_delete_file)) executeBulkAction(userId, action, fileKeys);
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

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-single-file-action-btn', function(e) {
        e.stopImmediatePropagation(); e.preventDefault();
        const btn = $(this), action = btn.data('action');
        const fileManager = btn.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const fileKey = btn.data('filekey');
        if (action === 'encrypt' || action === 'decrypt') {
            const password = prompt(action === 'encrypt' ? ecp_ajax.strings.encrypt_prompt : ecp_ajax.strings.decrypt_prompt);
            if (password) executeBulkAction(userId, action, [fileKey], password);
        } else if (action === 'delete') {
            if (confirm(ecp_ajax.strings.confirm_delete_file.replace('the selected files', 'this file'))) executeBulkAction(userId, 'delete', [fileKey]);
        }
    });
    
    mainContentArea.on('click.ecpFileManagerActions', '.ecp-download-encrypted-btn', function(e) {
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

    // ** FIX: Changed selector from a class to an ID to correctly bind the event. **
    mainContentArea.on('click.ecpFileManagerActions', '#ecp-modal-confirm-move-btn', function() {
        const modal = $(this).closest('.ecp-modal-overlay');
        const fileManager = modal.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const newFolder = modal.find('#ecp-modal-folder-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();
        executeBulkAction(userId, 'move', fileKeys, newFolder);
        modal.fadeOut(200);
    });

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-modal-cancel-btn', () => $('.ecp-modal-overlay').fadeOut(200));

    mainContentArea.on('submit.ecpFileManagerActions', '#ecp-add-folder-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const userId = form.find('input[name="user_id"]').val();
        ECP_Admin.showBlockingLoader('Saving folder...');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions', nonce: ecp_ajax.nonces.fileManagerNonce, sub_action: 'add_folder',
            user_id: userId, folder: form.find('input[name="folder"]').val(), location: form.find('input[name="location"]').val()
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) {
                form[0].reset();
                ECP_Admin.refreshFileManager(userId);
            }
        }).fail(() => ECP_Admin.showAdminMessage('An error occurred.', 'error'))
        .always(() => ECP_Admin.hideBlockingLoader());
    });

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-delete-folder-btn', function() {
        if (!confirm(ecp_ajax.strings.confirm_delete_folder)) return;
        const btn = $(this);
        const userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        ECP_Admin.showBlockingLoader('Deleting folder...');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions', nonce: ecp_ajax.nonces.fileManagerNonce, sub_action: 'delete_folder',
            user_id: userId, folder_name: btn.data('folder')
        }).done(response => {
            ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) ECP_Admin.refreshFileManager(userId);
        }).fail(() => ECP_Admin.showAdminMessage('An error occurred.', 'error'))
        .always(() => ECP_Admin.hideBlockingLoader());
    });
    // === END: File Manager Actions Logic ===

    // === START: File Uploader Logic ===
    let fileQueue = [];
    let isUploading = false;
    function setupUploaderEvents(context) {
        const uploaderForms = $(context).find('.ecp-upload-form');
        uploaderForms.off('.uploader');
        mainContentArea.off('.uploaderDropzone');
        uploaderForms.on('change.uploader', '.ecp-file-upload-input', function(e) {
            const form = $(this).closest('form');
            form.find('#ecp-upload-progress-container').show();
            $.each(e.target.files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, form);
            });
            if (!isUploading) processQueue(form);
        });
        uploaderForms.on('change.uploader', '.ecp-encrypt-toggle', function() {
            $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle($(this).is(':checked'));
        });
        mainContentArea.on('dragover.uploaderDropzone', '.ecp-dropzone-area', function(e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover'); });
        mainContentArea.on('dragleave.uploaderDropzone', '.ecp-dropzone-area', function(e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover'); });
        mainContentArea.on('drop.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
            const form = $(this).closest('form');
            form.find('#ecp-upload-progress-container').show();
            $.each(e.originalEvent.dataTransfer.files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, form);
            });
            if (!isUploading) processQueue(form);
        });
        mainContentArea.on('click.uploaderDropzone', '.ecp-dropzone-area', function() { $(this).closest('form').find('.ecp-file-upload-input').trigger('click'); });
    }

    function addToQueue(file, form) {
        fileQueue.push(file);
        form.find('#ecp-upload-progress-container').append(`<div class="ecp-upload-item" id="file-${file.queueId}"><div class="ecp-upload-filename">${file.name}</div><div class="ecp-progress-bar-outer"><div class="ecp-progress-bar-inner" style="width: 0%;"></div></div><div class="ecp-upload-status">Waiting...</div></div>`);
    }

    function processQueue(form) {
        if (isUploading) return;
        if (fileQueue.length === 0) {
            setTimeout(() => {
                form.find('#ecp-upload-progress-container').fadeOut(500, function() { $(this).html('').show(); });
                ECP_Admin.refreshFileManager(form.find('input[name="user_id"]').val());
            }, 1500);
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
            url: ecp_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', e => {
                    if (e.lengthComputable) progressItem.find('.ecp-progress-bar-inner').css('width', (e.loaded / e.total * 100) + '%');
                }, false);
                return xhr;
            }
        }).done(response => {
            const status = progressItem.find('.ecp-upload-status');
            status.text(response.success ? 'Complete!' : (response.data.message || 'Failed.')).addClass(response.success ? 'success' : 'error');
        }).fail(() => {
            progressItem.find('.ecp-upload-status').text('Server Error.').addClass('error');
        }).always(() => {
            isUploading = false;
            processQueue(form);
        });
    }

    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            if (mutation.addedNodes.length && $(mutation.target).find('.ecp-file-manager').length) {
                setupUploaderEvents(mutation.target); break;
            }
        }
    });
    if (mainContentArea.length) observer.observe(mainContentArea[0], { childList: true, subtree: true });
    setupUploaderEvents(mainContentArea);
    // === END: File Uploader Logic ===
});

