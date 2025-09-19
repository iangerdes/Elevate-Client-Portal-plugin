<?php
/**
 * Renders the "All Users Files" manager view for the Admin Dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 5.3.3 (Patched)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the render_all_users_files_content() method
// in class-ecp-admin-dashboard.php:
// @var array $folders      Array of all global folders.
// @var array $all_clients  Array of all client user objects.
// @var ECP_Admin_Dashboard $dashboard_class The instance of the dashboard class.
?>
<div class="ecp-dashboard-view ecp-file-manager" id="ecp-file-manager-view-0">
    <div class="ecp-header"><h3><?php _e('File Manager for All Users', 'ecp'); ?></h3><button class="button ecp-back-to-users">&larr; <?php _e('Back to Client List', 'ecp'); ?></button></div>
    <div class="columns-2">
        <div class="ecp-file-manager-list-col">
            <h4><?php _e('Uploaded Files', 'ecp'); ?></h4>

            <div class="ecp-bulk-actions-container">
                <select class="ecp-bulk-action-select">
                    <option value=""><?php _e('Bulk Actions', 'ecp'); ?></option>
                    <option value="delete"><?php _e('Delete Selected', 'ecp'); ?></option>
                    <option value="move"><?php _e('Move Selected', 'ecp'); ?></option>
                    <option value="encrypt"><?php _e('Encrypt Selected', 'ecp'); ?></option>
                    <?php // ** FIX: Added the missing "Decrypt" option ** ?>
                    <option value="decrypt"><?php _e('Decrypt Selected', 'ecp'); ?></option>
                </select>
                <button class="button ecp-bulk-action-apply"><?php _e('Apply', 'ecp'); ?></button>
            </div>

             <div class="ecp-controls" style="margin-bottom: 1em; padding: 10px; background: transparent; border: none; justify-content: flex-start;">
                <div style="flex-grow: 0;">
                    <label for="ecp-admin-folder-filter"><?php _e('Filter by Folder', 'ecp'); ?></label>
                    <select id="ecp-admin-folder-filter" data-userid="0">
                        <option value="all"><?php _e('All Folders', 'ecp'); ?></option><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { echo '<option value="'.esc_attr($folder).'">'.esc_html($folder).'</option>'; } ?>
                    </select>
                </div>
            </div>
            <div class="table-container"><table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th class="ecp-col-checkbox"><input type="checkbox" class="ecp-select-all-files" title="<?php _e('Select all files', 'ecp'); ?>"></th>
                    <th style="width: 40%;"><?php _e('File Name', 'ecp'); ?></th>
                    <th><?php _e('Folder', 'ecp'); ?></th>
                    <th style="width: 80px;"><?php _e('Actions', 'ecp'); ?></th>
                </tr></thead>
                <tbody class="file-list-body"><?php echo $dashboard_class->render_all_users_file_manager_table_rows('all', $all_clients); ?></tbody>
            </table></div>
        </div>
        <div class="ecp-file-manager-forms-col">
            <div class="postbox"><h2 class="hndle"><span><?php _e('Upload New File(s)', 'ecp'); ?></span></h2><div class="inside">
                <form class="ecp-upload-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="user_id" value="0">
                    <?php wp_nonce_field('ecp_ajax_nonce', 'nonce'); ?>
                    <div id="ecp-dropzone" class="ecp-dropzone-area"><p><?php _e('Drag & Drop files here or click to select', 'ecp'); ?></p><input type="file" id="ecp_file_upload_input" name="ecp_file_upload[]" multiple style="display:none;"></div>
                    <div id="ecp-upload-progress-container"></div>
                    <p><label for="ecp-upload-folder-select"><?php _e('Assign to Folder:', 'ecp'); ?></label><br>
                    <select name="ecp_file_folder" id="ecp-upload-folder-select" class="widefat"><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option><?php foreach ($folders as $folder) { echo '<option value="'.esc_attr($folder).'">'.esc_html($folder).'</option>'; } ?></select></p>
                    <div class="ecp-encryption-section">
                        <p><label><input type="checkbox" class="ecp-encrypt-toggle"> <?php _e('Encrypt this file?', 'ecp'); ?></label></p>
                        <div class="ecp-password-fields" style="display:none;">
                            <p><label for="ecp-encrypt-password"><?php _e('Encryption Password:', 'ecp'); ?></label><br>
                            <input type="password" id="ecp-encrypt-password" class="widefat"></p>
                            <p class="description"><?php _e('<strong>Important:</strong> This password is not saved. If you lose it, the file cannot be recovered.', 'ecp'); ?></p>
                        </div>
                    </div>
                </form>
            </div></div>
            <div class="postbox"><h2 class="hndle"><span><?php _e('Manage Folders', 'ecp'); ?></span></h2><div class="inside">
                <form class="ecp-ajax-form" id="ecp-add-folder-form" method="post">
                     <input type="hidden" name="action" value="ecp_file_manager_actions"><input type="hidden" name="sub_action" value="add_folder"><input type="hidden" name="user_id" value="0">
                     <?php wp_nonce_field('ecp_ajax_nonce', 'nonce'); ?>
                    <p><label for="ecp-new-folder-name"><?php _e('New Folder Name:', 'ecp'); ?></label><br><input type="text" id="ecp-new-folder-name" name="folder" class="widefat" required/></p>
                    <p><button type="submit" class="button"><?php _e('Add Folder', 'ecp'); ?></button></p>
                </form>
                <?php if(!empty($folders)): ?><ul class="ecp-folder-list">
                    <?php foreach($folders as $folder) { echo '<li>'.esc_html($folder).' <button class="button-link-delete ecp-delete-link" data-action="delete_folder" data-folder="'.esc_attr($folder).'">(x)</button></li>'; } ?>
                </ul><?php endif; ?>
            </div></div>
        </div>
    </div>
    
    <div id="ecp-move-files-modal" class="ecp-modal-overlay" style="display:none;">
        <div class="ecp-modal-content">
            <h4><?php _e('Move Selected Files', 'ecp'); ?></h4>
            <p><?php _e('Select a destination folder:', 'ecp'); ?></p>
            <select id="ecp-modal-folder-select" class="widefat"></select>
            <div class="ecp-modal-actions">
                <button class="button" id="ecp-modal-cancel-btn"><?php _e('Cancel', 'ecp'); ?></button>
                <button class="button button-primary" id="ecp-modal-confirm-move-btn"><?php _e('Move Files', 'ecp'); ?></button>
            </div>
        </div>
    </div>

</div>
