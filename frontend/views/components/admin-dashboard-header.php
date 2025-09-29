<?php
// File: elevate-client-portal/frontend/views/components/admin-dashboard-header.php
/**
 * Reusable header component for the Admin Dashboard views.
 *
 * @package Elevate_Client_Portal
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the including view file:
// @var string $title The title for the header.
// @var bool $show_back_button Whether to show the 'Back to User List' button. Defaults to false.
// @var bool $is_main_view Whether this is the main user list view with all navigation buttons. Defaults to false.
?>
<div class="ecp-header">
    <h2><?php echo esc_html( $title ); ?></h2>
    <div>
        <?php if ( ! empty( $is_main_view ) && $is_main_view ) : ?>
            <?php // Main navigation buttons for the primary dashboard view ?>
            <?php if ( current_user_can('ecp_view_file_summary') ) : ?>
                <button id="ecp-file-summary-btn" class="button"><?php _e('File Summary', 'ecp'); ?></button>
            <?php endif; ?>
            <?php if ( current_user_can('manage_options') ) : ?>
                <button id="ecp-role-permissions-btn" class="button"><?php _e('Role Permissions', 'ecp'); ?></button>
            <?php endif; ?>
            <?php if ( current_user_can('ecp_manage_all_users_files') ) : ?>
                <button id="ecp-all-users-files-btn" class="button"><?php _e('All Users Files', 'ecp'); ?></button>
            <?php endif; ?>
            <button id="ecp-add-new-client-btn" class="button button-primary"><?php _e('Add New User', 'ecp'); ?></button>
        <?php elseif ( ! empty( $show_back_button ) && $show_back_button ) : ?>
             <?php // "Back" button for all sub-views ?>
            <button class="button ecp-back-to-users">&larr; <?php _e('Back to User List', 'ecp'); ?></button>
        <?php endif; ?>
        
        <?php // Logout button appears on all views ?>
        <a href="#" class="button ecp-ajax-logout-btn"><?php _e('Logout', 'ecp'); ?></a>
    </div>
</div>

