/**
 * Handles all AJAX logic for the front-end administrator dashboard.
 * This file contains the logic for the main dashboard shell and user management.
 * File manager logic is handled by file-manager.js and its component scripts.
 * Version: 5.1.1 (Patched)
 */

jQuery(function ($) {
    const adminDashboard = $('.ecp-admin-dashboard');
    if (!adminDashboard.length) return;

    // The loader overlay is part of the main dashboard template.
    if (!adminDashboard.find('.ecp-loader-overlay').length) {
        adminDashboard.append('<div class="ecp-loader-overlay"><div class="ecp-spinner"></div><p>Processing...</p></div>');
    }
    const loaderOverlay = adminDashboard.find('.ecp-loader-overlay');

    const mainContentArea = $('#ecp-dashboard-main-content');
    const messageBox = $('#ecp-admin-messages');
    
    // Store the initial view's HTML structure generator function instead of static HTML
    const initialViewGenerator = () => {
        return `
            <div class="ecp-dashboard-view" id="ecp-user-list-view">
                <div class="ecp-header">
                    <h2>Client Management</h2>
                    <div>
                        <button id="ecp-file-summary-btn" class="button">File Summary</button>
                        <button id="ecp-role-permissions-btn" class="button">Role Permissions</button>
                        <button id="ecp-all-users-files-btn" class="button">All Users Files</button>
                        <button id="ecp-add-new-client-btn" class="button button-primary">Add New Client</button>
                    </div>
                </div>
                <div class="ecp-controls">
                    <div>
                        <label for="ecp-admin-user-search">Search Clients</label>
                        <input type="search" id="ecp-admin-user-search" placeholder="Search by name or email...">
                    </div>
                </div>
                <div id="ecp-admin-user-list-container"></div>
            </div>
        `;
    };


    /**
     * Displays a success or error message at the top of the dashboard.
     * This function is now also available to component scripts.
     * @param {string} message The message to display.
     * @param {string} type 'success' or 'error'.
     */
    window.showAdminMessage = function(message, type = 'success') {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        messageBox.html('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>').fadeIn();
        setTimeout(() => messageBox.fadeOut(500, function() { $(this).html('').show(); }), 5000);
    }

    /**
     * Replaces the main content area with new HTML.
     * @param {string} viewHtml The new HTML to load.
     */
    function loadView(viewHtml) {
        mainContentArea.html(viewHtml);
    }

    /**
     * Resets the view back to the initial user list.
     */
    function goBackToUserList() {
        // Re-generate the initial view structure and then fetch the user list.
        // This is much safer than reloading stale HTML.
        loadView(initialViewGenerator());
        fetchAdminUserList();
    }
    
    /**
     * Shows a simple spinner inside a container (non-blocking).
     * @param {jQuery} container The jQuery object of the container to show the loader in.
     */
    function showLoader(container) {
        container.html('<div id="ecp-loader" style="display:block; position: relative; top: 50px;"><div class="ecp-spinner"></div></div>');
    }

    /**
     * Fetches and renders the list of users via AJAX.
     */
    function fetchAdminUserList() {
        const userListContainer = $('#ecp-admin-user-list-container');
        if(!userListContainer.length) return;
        showLoader(userListContainer);
        
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_admin_dashboard_actions',
            nonce: ecp_ajax.nonce,
            sub_action: 'search_users',
            search: $('#ecp-admin-user-search').val() || ''
        }).done(function(response) {
            if (response.success) {
                userListContainer.html(response.data);
            } else {
                userListContainer.html('<tr><td colspan="5">Error loading users.</td></tr>');
            }
        }).fail(function() {
            userListContainer.html('<tr><td colspan="5">An unexpected server error occurred.</td></tr>');
        });
    }

    /**
     * Fetches and loads a specific view (like 'edit_user' or 'file_manager') via AJAX.
     * This function is now globally available for components to use.
     * @param {string} viewName The name of the view to load.
     * @param {Object} data Additional data to send with the request (e.g., user_id).
     */
    window.loadNamedView = function(viewName, data = {}) {
        showLoader(mainContentArea);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_view',
            nonce: ecp_ajax.nonce,
            view: viewName,
            ...data
        }).done(function(response) {
            if (response.success) {
                loadView(response.data);
            } else {
                showAdminMessage(response.data.message || 'Error loading view.', 'error');
                goBackToUserList();
            }
        }).fail(function() {
            showAdminMessage('An unexpected server error occurred.', 'error');
            goBackToUserList();
        });
    }
    
    // Make a few functions globally accessible for component scripts
    window.showBlockingLoader = function(message = 'Processing...') {
        loaderOverlay.find('p').text(message);
        loaderOverlay.css('display', 'flex');
    }
    window.hideBlockingLoader = function() {
        loaderOverlay.fadeOut(200);
    }
    window.refreshFileManager = function(userId) {
        const id = parseInt(userId, 10);
        if (isNaN(id)) return;
        
        // A user_id of 0 signifies the "All Users" file manager.
        if (id === 0) {
            loadNamedView('all_users_files');
        } else {
            loadNamedView('file_manager', { user_id: id });
        }
    }


    // --- Event Handlers (Delegated from main content area for AJAX compatibility) ---
    
    // Use a namespace to prevent duplicate event handlers.
    mainContentArea.off('.adminDashboard');

    let adminSearchTimeout;
    mainContentArea.on('keyup.adminDashboard', '#ecp-admin-user-search', function() {
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(fetchAdminUserList, 400);
    });

    // --- Navigation ---
    mainContentArea.on('click.adminDashboard', '.ecp-back-to-users', goBackToUserList);
    mainContentArea.on('click.adminDashboard', '#ecp-add-new-client-btn', () => loadNamedView('add_user'));
    mainContentArea.on('click.adminDashboard', '#ecp-all-users-files-btn', () => loadNamedView('all_users_files'));
    mainContentArea.on('click.adminDashboard', '#ecp-file-summary-btn', () => loadNamedView('file_summary'));
    mainContentArea.on('click.adminDashboard', '#ecp-role-permissions-btn', () => loadNamedView('role_permissions'));
    mainContentArea.on('click.adminDashboard', '.edit-user-btn', function() { loadNamedView('edit_user', { user_id: $(this).closest('tr').data('userid') }); });
    mainContentArea.on('click.adminDashboard', '.manage-files-btn', function() { loadNamedView('file_manager', { user_id: $(this).closest('tr').data('userid') }); });

    // --- Form Submissions ---
    mainContentArea.on('submit.adminDashboard', '#ecp-user-details-form, #ecp-role-permissions-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const originalButtonText = submitButton.text();
        submitButton.prop('disabled', true).text('Saving...');

        $.post(ecp_ajax.ajax_url, form.serialize())
            .done(function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message || 'Success!');
                    if(form.attr('id') === 'ecp-user-details-form') {
                        goBackToUserList();
                    }
                } else {
                    showAdminMessage(response.data.message, 'error');
                }
            })
            .fail(() => showAdminMessage('An unknown error occurred.', 'error'))
            .always(() => submitButton.prop('disabled', false).text(originalButtonText));
    });

    // --- User Actions (Enable/Disable/Remove) ---
    mainContentArea.on('click.adminDashboard', '.ecp-user-action-btn', function() {
        const btn = $(this);
        const action = btn.data('action');
        const userId = btn.closest('tr').data('userid');
        let confirmMessage = ecp_ajax.strings.confirm_delete;
        
        const ajaxData = {
            action: 'ecp_admin_dashboard_actions',
            nonce: ecp_ajax.nonce,
            sub_action: action,
            user_id: userId,
        };

        if (action === 'toggle_status') {
            const enable = btn.data('enable');
            ajaxData.enable = enable;
            confirmMessage = ecp_ajax.strings.confirm_action.replace('%s', enable ? 'enable' : 'disable');
        } else if (action === 'remove_user') {
             confirmMessage = ecp_ajax.strings.confirm_action.replace('%s', 'permanently remove');
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        showBlockingLoader();

        $.post(ecp_ajax.ajax_url, ajaxData)
            .done(function(response) {
                if (response.success) {
                    showAdminMessage(response.data.message);
                    fetchAdminUserList(); // Refresh the list to show changes
                } else {
                    showAdminMessage(response.data.message, 'error');
                }
            })
            .fail(() => showAdminMessage('An unknown error occurred.', 'error'))
            .always(() => hideBlockingLoader());
    });


    // Initial load of the user list
    if ($('#ecp-admin-user-list-container').length) {
        fetchAdminUserList();
    }
});
