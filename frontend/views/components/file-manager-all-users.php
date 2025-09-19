<?php
// File: elevate-client-portal/frontend/views/components/file-manager-all-users.php
/**
 * View template for the "All Users" File Manager component.
 *
 * @package Elevate_Client_Portal
 * @version 52.1.0 (Fix Upload Action)
 * @comment Added missing hidden 'action' and 'sub_action' fields to the upload form to fix the 400 Bad Request error on file uploads.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the render_all_users() method
// in class-ecp-file-manager.php:
// @var array $folders      Array of all global folders.
?>
<div class="ecp-dashboard-view ecp-file-manager" id="ecp-file-manager-view-0">
    <div class="ecp-header"><h3><?php _e('File Manager for All Users', 'ecp'); ?></h3><button class="button ecp-back-to-users">&larr; <?php _e('Back to Client List', 'ecp'); ?></button></div>
    <div class="columns-2">
        <div class="ecp-file-manager-list-col">
            <h4><?php _e('Uploaded Files', 'ecp'); ?></h4>

            <?php
            $user_id = 0;
            include $this->plugin_path . 'frontend/views/components/bulk-actions.php';
            ?>

             <div class="ecp-controls" style="margin-bottom: 1em; padding: 10px; background: transparent; border: none; justify-content: flex-start;">
                <div style="flex-grow: 0;">
                    <label for="ecp-admin-folder-filter"><?php _e('Filter by Folder', 'ecp'); ?></label>
                    <select id="ecp-admin-folder-filter" class="ecp-admin-folder-filter" data-userid="0">
                        <option value="all"><?php _e('All Folders', 'ecp'); ?></option><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { 
                            $folder_name = is_array($folder) ? $folder['name'] : $folder;
                            echo '<option value="'.esc_attr($folder_name).'">'.esc_html($folder_name).'</option>'; 
                        } ?>
                    </select>
                </div>
            </div>
            <div class="table-container"><table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th class="ecp-col-checkbox"><input type="checkbox" class="ecp-select-all-files" title="<?php _e('Select all files', 'ecp'); ?>"></th>
                    <th style="width: 35%;"><?php _e('File Name', 'ecp'); ?></th>
                    <th><?php _e('Folder', 'ecp'); ?></th>
                    <th><?php _e('Date', 'ecp'); ?></th>
                    <th><?php _e('Size', 'ecp'); ?></th>
                    <th style="width: 150px;"><?php _e('Actions', 'ecp'); ?></th>
                </tr></thead>
                <tbody class="file-list-body"><?php echo $this->render_all_users_table_rows('all'); ?></tbody>
            </table></div>
        </div>
        <div class="ecp-file-manager-forms-col">
            <div class="postbox"><h2 class="hndle"><span><?php _e('Upload New File(s)', 'ecp'); ?></span></h2><div class="inside">
                <form class="ecp-upload-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ecp_file_manager_actions">
                    <input type="hidden" name="sub_action" value="upload_file">
                    <input type="hidden" name="user_id" value="0">
                    <label for="ecp_file_upload_input_0" class="ecp-dropzone-area"><p><?php _e('Drag & Drop files here or click to select', 'ecp'); ?></p></label>
                    <input type="file" id="ecp_file_upload_input_0" class="ecp-file-upload-input" name="ecp_file_upload" multiple style="display:none;">
                    <div id="ecp-upload-progress-container"></div>
                    <p><label for="ecp-upload-folder-select-0"><?php _e('Assign to Folder:', 'ecp'); ?></label><br>
                    <select name="ecp_file_folder" id="ecp-upload-folder-select-0" class="widefat ecp-upload-folder-select"><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option><?php foreach ($folders as $folder) { $folder_name = is_array($folder) ? $folder['name'] : $folder; echo '<option value="'.esc_attr($folder_name).'">'.esc_html($folder_name).'</option>'; } ?></select></p>
                    <div class="ecp-encryption-section">
                        <p><label><input type="checkbox" class="ecp-encrypt-toggle" name="ecp_encrypt_toggle"> <?php _e('Encrypt this file?', 'ecp'); ?></label></p>
                        <div class="ecp-password-fields" style="display:none;">
                            <p><label for="ecp-encrypt-password-0"><?php _e('Encryption Password:', 'ecp'); ?></label><br>
                            <input type="password" id="ecp-encrypt-password-0" name="ecp_encrypt_password" class="widefat" autocomplete="new-password"></p>
                            <p class="description"><?php _e('<strong>Important:</strong> This password is not saved. If you lose it, the file cannot be recovered.', 'ecp'); ?></p>
                        </div>
                    </div>
                </form>
            </div></div>
            
            <?php
            $user_id = 0;
            include $this->plugin_path . 'frontend/views/components/manage-folders.php';
            ?>

        </div>
    </div>
    
    <?php include $this->plugin_path . 'frontend/views/components/files-modal.php'; ?>
</div>
