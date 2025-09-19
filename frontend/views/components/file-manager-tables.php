<?php
// File: elevate-client-portal/frontend/views/components/file-manager-tables.php
/**
 * Component file for rendering the file manager tables in the admin dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 53.0.0
 * @comment Corrected the class on the single file delete button from 'ecp-delete-folder-btn' to 'ecp-single-file-action-btn'. This ensures the correct JavaScript handler is triggered and the proper confirmation message for deleting a file (not a folder) is shown.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Renders the HTML table rows for a single user's file list.
 */
function ecp_render_single_user_table_rows($user_id, $folder_filter = 'all') {
    $client_files = ECP_File_Helper::get_hydrated_files_for_user( $user_id );
    $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];

    ob_start();
    if(!empty($client_files)) {
        usort($client_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($client_files as $file) {
            if(!is_array($file) || !isset($file['name'])) continue;

            $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
            if ($folder_filter !== 'all' && $current_folder_name !== $folder_filter) { continue; }
            
            $files_found = true; 
            $file_key = $file['s3_key'] ?? md5($file['path']);
            $is_encrypted = !empty($file['is_encrypted']);
            ?>
            <tr data-is-encrypted="<?php echo $is_encrypted ? 'true' : 'false'; ?>">
                <td class="ecp-col-checkbox"><input type="checkbox" class="ecp-file-checkbox" value="<?php echo esc_attr($file_key); ?>"></td>
                <td data-label="<?php _e('File Name', 'ecp'); ?>">
                    <?php echo esc_html($file['name']); ?>
                    <?php if ($is_encrypted): ?>
                        <span class="ecp-encrypted-icon" title="<?php _e('Encrypted', 'ecp'); ?>"></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php _e('Folder', 'ecp'); ?>" class="ecp-folder-cell">
                     <select class="ecp-change-category" data-filekey="<?php echo esc_attr($file_key); ?>">
                        <option value="/" <?php selected($current_folder_name, '/'); ?>><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { 
                            $folder_name = is_array($folder) ? $folder['name'] : $folder;
                            echo '<option value="'.esc_attr($folder_name).'" '.selected($current_folder_name, $folder_name, false).'>'.esc_html($folder_name).'</option>'; 
                        } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'])); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if ($is_encrypted): ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="decrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Decrypt', 'ecp'); ?></button>
                        <a href="#" class="button button-small ecp-download-encrypted-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Download', 'ecp'); ?></a>
                    <?php else: ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="encrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => $file_key, 'target_user_id' => $user_id], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <?php endif; ?>
                    <button class="button-link-delete ecp-single-file-action-btn" data-action="delete" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
                </td>
            </tr>
        <?php }
        if (!$files_found) { echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files match your criteria.', 'ecp') . '</td></tr>'; }
    } else {
        echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files found for this client.', 'ecp') . '</td></tr>';
    }
    return ob_get_clean();
}

/**
 * Renders the HTML table rows for the "All Users" file list.
 */
function ecp_render_all_users_table_rows($folder_filter = 'all') {
    $all_users_files = ECP_File_Helper::get_hydrated_all_users_files();
    $folders = get_option( '_ecp_all_users_folders', [] );
    
    ob_start();
    if(!empty($all_users_files)) {
        usort($all_users_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($all_users_files as $file) {
            if(!is_array($file) || !isset($file['name'])) continue;

            $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
            if ($folder_filter !== 'all' && $current_folder_name !== $folder_filter) { continue; }
            
            $files_found = true; 
            $file_key = $file['s3_key'] ?? md5($file['path']);
            $is_encrypted = !empty($file['is_encrypted']);
            ?>
             <tr data-is-encrypted="<?php echo $is_encrypted ? 'true' : 'false'; ?>">
                <td class="ecp-col-checkbox"><input type="checkbox" class="ecp-file-checkbox" value="<?php echo esc_attr($file_key); ?>"></td>
                <td data-label="<?php _e('File Name', 'ecp'); ?>">
                    <?php echo esc_html($file['name']); ?>
                     <?php if ($is_encrypted): ?>
                        <span class="ecp-encrypted-icon" title="<?php _e('Encrypted', 'ecp'); ?>"></span>
                    <?php endif; ?>
                </td>
                 <td data-label="<?php _e('Folder', 'ecp'); ?>" class="ecp-folder-cell">
                    <select class="ecp-change-category" data-filekey="<?php echo esc_attr($file_key); ?>">
                        <option value="/" <?php selected($current_folder_name, '/'); ?>><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { 
                             $folder_name = is_array($folder) ? $folder['name'] : $folder;
                             echo '<option value="'.esc_attr($folder_name).'" '.selected($current_folder_name, $folder_name, false).'>'.esc_html($folder_name).'</option>'; 
                        } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'])); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if ($is_encrypted): ?>
                         <button class="button button-small ecp-single-file-action-btn" data-action="decrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Decrypt', 'ecp'); ?></button>
                        <a href="#" class="button button-small ecp-download-encrypted-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Download', 'ecp'); ?></a>
                    <?php else: ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="encrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => $file_key, 'target_user_id' => 0], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <?php endif; ?>
                    <button class="button-link-delete ecp-single-file-action-btn" data-action="delete" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
                </td>
            </tr>
        <?php }
        if (!$files_found) { echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files match your criteria.', 'ecp') . '</td></tr>'; }
    } else {
        echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files found for all users.', 'ecp') . '</td></tr>';
    }
    return ob_get_clean();
}

