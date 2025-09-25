// File: elevate-client-portal/assets/js/components/file-manager-actions.js
/**
 * Handles all actions within the file manager component, such as bulk actions,
 * folder management, and single file operations like delete and encrypt.
 *
 * @package Elevate_Client_Portal
 * @version 72.1.0 (Audit Fix)
 * @comment Corrected all calls to shared helper functions (e.g., showAdminMessage) to use the global ECP_Admin object, resolving "is not defined" errors. Removed redundant function definitions.
 */

jQuery(function ($) {
    const mainContentArea = $('#ecp-dashboard-main-content');

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
    
    // --- EVENT HANDLERS ---
    mainContentArea.off('.ecpFileManagerActions');

    mainContentArea.on('change.ecpFileManagerActions', '.ecp-admin-folder-filter', function() {
        const userId = $(this).data('userid');
        const folder = $(this).val();
        const fileListBody = mainContentArea.find(`#ecp-file-manager-view-${userId} .file-list-body`);
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
            const folderSelect = fileManager.find('.ecp-upload-folder-select');
            const fileList = modal.find('.ecp-modal-file-list');
            
            fileList.html('');
            fileManager.find('.ecp-file-checkbox:checked').each(function() {
                const fileName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
                fileList.append(`<div>${fileName}</div>`);
            });

            modal.find('#ecp-modal-folder-select').html(folderSelect.html());
            modal.css('display', 'flex');
        }
    });

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-single-file-action-btn', function(e) {
        e.stopImmediatePropagation();
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

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-download-encrypted-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const fileKey = btn.data('filekey');
        const password = prompt(ecp_ajax.strings.decrypt_prompt);
        if (password) {
            const form = $('<form>', {
                'method': 'POST',
                'action': `${ecp_ajax.home_url}?ecp_action=download_decrypted_file`
            }).append(
                $('<input>', { 'type': 'hidden', 'name': 'file_key', 'value': fileKey }),
                $('<input>', { 'type': 'hidden', 'name': 'nonce', 'value': ecp_ajax.nonces.decryptFileNonce }),
                $('<input>', { 'type': 'hidden', 'name': 'password', 'value': password })
            );
            $('body').append(form);
            form.submit().remove();
        }
    });

    mainContentArea.on('click.ecpFileManagerActions', '#ecp-modal-confirm-move-btn', function() {
        const modal = $(this).closest('.ecp-modal-overlay');
        const fileManager = modal.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const newFolder = modal.find('#ecp-modal-folder-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();
        executeBulkAction(userId, 'move', fileKeys, newFolder);
        modal.fadeOut(200);
    });

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-modal-cancel-btn', function() {
        $(this).closest('.ecp-modal-overlay').fadeOut(200);
    });

    mainContentArea.on('submit.ecpFileManagerActions', '#ecp-add-folder-form', function(e) {
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

    mainContentArea.on('click.ecpFileManagerActions', '.ecp-delete-folder-btn', function() {
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
});
