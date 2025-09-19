<?php
// File: elevate-client-portal/frontend/views/components/file-manager-single-user.php
/**
 * View template for the single-user File Manager component.
 *
 * @package Elevate_Client_Portal
 * @version 67.0.0
 * @comment Fixed a fatal error caused by an incorrect variable call. Changed `$this->file_manager_component->render_table_rows()` to the correct `$this->render_table_rows()` to properly render the user's file list.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the render() method in class-ecp-file-manager.php:
// @var WP_User $user      The user object.
// @var array   $folders   Array of folders for this user.
?>
<div class="ecp-dashboard-view ecp-file-manager" id="ecp-file-manager-view-<?php echo esc_attr($user->ID); ?>">
    <div class="ecp-header"><h3><?php _e('File Manager for:', 'ecp'); ?> <?php echo esc_html($user->display_name); ?></h3><button class="button ecp-back-to-users">&larr; <?php _e('Back to Client List', 'ecp'); ?></button></div>
    <div class="columns-2">
        <div class="ecp-file-manager-list-col">
            <h4><?php _e('Uploaded Files', 'ecp'); ?></h4>

            <?php
            $user_id = $user->ID;
            include $this->plugin_path . 'frontend/views/components/bulk-actions.php';
            ?>

            <div class="ecp-controls" style="margin-bottom: 1em; padding: 10px; background: transparent; border: none; justify-content: flex-start;">
                <div style="flex-grow: 0;">
                    <label for="ecp-admin-folder-filter-<?php echo esc_attr($user->ID); ?>"><?php _e('Filter by Folder', 'ecp'); ?></label>
                    <select class="ecp-admin-folder-filter" id="ecp-admin-folder-filter-<?php echo esc_attr($user->ID); ?>" data-userid="<?php echo esc_attr($user->ID); ?>">
                        <option value="all"><?php _e('All Folders', 'ecp'); ?></option><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { 
                            $folder_name = is_array($folder) ? $folder['name'] : $folder;
                            $folder_location = ( is_array($folder) && ! empty($folder['location']) ) ? ' (' . esc_html($folder['location']) . ')' : '';
                            echo '<option value="'.esc_attr($folder_name).'">'.esc_html($folder_name). esc_html($folder_location) . '</option>'; 
                        } ?>
                    </select>
                </div>
            </div>
            <div class="table-container"><table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th class="ecp-col-checkbox"><input type="checkbox" class="ecp-select-all-files" title="<?php _e('Select all files', 'ecp'); ?>"></th>
                    <th><?php _e('File Name', 'ecp'); ?></th>
                    <th><?php _e('Folder', 'ecp'); ?></th>
                    <th><?php _e('Size', 'ecp'); ?></th>
                    <th><?php _e('Date Uploaded', 'ecp'); ?></th>
                    <th><?php _e('Actions', 'ecp'); ?></th>
                </tr></thead>
                <tbody class="file-list-body"><?php echo $this->render_table_rows($user->ID, 'all'); ?></tbody>
            </table></div>
        </div>
        <div class="ecp-file-manager-forms-col">
            <div class="postbox"><h2 class="hndle"><span><?php _e('Upload New File(s)', 'ecp'); ?></span></h2><div class="inside">
                <form class="ecp-upload-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="ecp_file_manager_actions">
                    <input type="hidden" name="sub_action" value="upload_file">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                    
                    <label for="ecp_file_upload_input_<?php echo esc_attr($user->ID); ?>" class="ecp-dropzone-area"><p><?php _e('Drag & Drop files here or click to select', 'ecp'); ?></p></label>
                    <input type="file" id="ecp_file_upload_input_<?php echo esc_attr($user->ID); ?>" class="ecp-file-upload-input" name="ecp_file_upload" multiple style="display:none;">
                    
                    <div id="ecp-upload-progress-container"></div>

                    <p><label for="ecp-upload-folder-select-<?php echo esc_attr($user->ID); ?>"><?php _e('Assign to Folder:', 'ecp'); ?></label><br>
                    <select name="ecp_file_folder" id="ecp-upload-folder-select-<?php echo esc_attr($user->ID); ?>" class="widefat ecp-upload-folder-select">
                        <option value="/"><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach ($folders as $folder) { 
                            $folder_name = is_array($folder) ? $folder['name'] : $folder;
                            $folder_location = ( is_array($folder) && ! empty($folder['location']) ) ? ' (' . esc_html($folder['location']) . ')' : '';
                             echo '<option value="'.esc_attr($folder_name).'">'.esc_html($folder_name). esc_html($folder_location) . '</option>'; 
                        } ?>
                    </select></p>
                    <p><label><input type="checkbox" name="ecp_notify_client" value="1" checked /> <?php _e('Notify client by email', 'ecp'); ?></label></p>
                    
                    <div class="ecp-encryption-section">
                        <p><label><input type="checkbox" class="ecp-encrypt-toggle" name="ecp_encrypt_toggle" value="1"> <?php _e('Encrypt this file?', 'ecp'); ?></label></p>
                        <div class="ecp-password-fields" style="display:none;">
                            <p><label for="ecp-encrypt-password-<?php echo esc_attr($user->ID); ?>"><?php _e('Encryption Password:', 'ecp'); ?></label><br>
                            <input type="password" id="ecp-encrypt-password-<?php echo esc_attr($user->ID); ?>" name="ecp_encrypt_password" class="widefat" autocomplete="new-password"></p>
                            <p class="description"><?php _e('<strong>Important:</strong> This password is not saved. If you lose it, the file cannot be recovered.', 'ecp'); ?></p>
                        </div>
                    </div>
                </form>
            </div></div>
            
            <?php
            $user_id = $user->ID;
            include $this->plugin_path . 'frontend/views/components/manage-folders.php';
            ?>
        </div>
    </div>
    
    <?php include $this->plugin_path . 'frontend/views/components/files-modal.php'; ?>
</div>

