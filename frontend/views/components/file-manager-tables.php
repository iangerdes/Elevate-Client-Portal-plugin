<?php
// File: elevate-client-portal/frontend/views/components/file-manager-tables.php
/**
 * Component file for rendering the file manager tables in the admin dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 53.1.0 (Admin View Refinement)
 * @comment Modified ecp_render_single_user_table_rows to fetch ONLY user-specific files, excluding "All Users" files for the admin view.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Renders the HTML table rows for a single user's file list in the ADMIN DASHBOARD.
 * This version ONLY shows files directly assigned to the user.
 */
function ecp_render_single_user_table_rows($user_id, $folder_filter = 'all') {
    // ** CHANGE: Fetch only user meta files, do not include "All Users" files **
    $user_files = get_user_meta( $user_id, '_ecp_client_file', false );
    $client_files = ECP_File_Helper::hydrate_specific_files( $user_files ); // Use the new public hydration method
    // ** END CHANGE **

    $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];

    ob_start();
    if(!empty($client_files)) {
        // Sort files, newest first
        usort($client_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($client_files as $file) {
            // Basic validation
            if(!is_array($file) || !isset($file['name'])) continue;

            // Apply folder filter
            $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
            if ($folder_filter !== 'all' && $current_folder_name !== $folder_filter) { continue; }
            
            $files_found = true;
            // Generate unique key for actions/checkboxes
            $file_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : '');
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
                            $folder_location = ( is_array($folder) && ! empty($folder['location']) ) ? ' (' . esc_html($folder['location']) . ')' : '';
                            echo '<option value="'.esc_attr($folder_name).'" '.selected($current_folder_name, $folder_name, false).'>'.esc_html($folder_name . $folder_location).'</option>'; 
                        } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'] ?? time())); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if ($is_encrypted): ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="decrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Decrypt', 'ecp'); ?></button>
                        <a href="#" class="button button-small ecp-download-encrypted-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Download', 'ecp'); ?></a>
                    <?php else: ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="encrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => urlencode($file_key), 'target_user_id' => $user_id], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <?php endif; ?>
                    <button class="button-link-delete ecp-single-file-action-btn" data-action="delete" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
                </td>
            </tr>
        <?php }
        // Display message if no files match filter
        if (!$files_found) { echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files match your criteria.', 'ecp') . '</td></tr>'; }
    } else {
        // Display message if user has no files at all
        echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files found for this client.', 'ecp') . '</td></tr>';
    }
    return ob_get_clean();
}

/**
 * Renders the HTML table rows for the "All Users" file list in the ADMIN DASHBOARD.
 * This function remains unchanged as it should only show global files.
 */
function ecp_render_all_users_table_rows($folder_filter = 'all') {
    $all_users_files = ECP_File_Helper::get_hydrated_all_users_files();
    $folders = get_option( '_ecp_all_users_folders', [] );
    
    ob_start();
    if(!empty($all_users_files)) {
        // Sort files, newest first
        usort($all_users_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($all_users_files as $file) {
            // Basic validation
            if(!is_array($file) || !isset($file['name'])) continue;

            // Apply folder filter
            $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
            if ($folder_filter !== 'all' && $current_folder_name !== $folder_filter) { continue; }
            
            $files_found = true; 
             // Generate unique key for actions/checkboxes
            $file_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : '');
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
                             $folder_location = ( is_array($folder) && ! empty($folder['location']) ) ? ' (' . esc_html($folder['location']) . ')' : '';
                             echo '<option value="'.esc_attr($folder_name).'" '.selected($current_folder_name, $folder_name, false).'>'.esc_html($folder_name . $folder_location).'</option>'; 
                        } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'] ?? time())); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if ($is_encrypted): ?>
                         <button class="button button-small ecp-single-file-action-btn" data-action="decrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Decrypt', 'ecp'); ?></button>
                        <a href="#" class="button button-small ecp-download-encrypted-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Download', 'ecp'); ?></a>
                    <?php else: ?>
                        <button class="button button-small ecp-single-file-action-btn" data-action="encrypt" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => urlencode($file_key), 'target_user_id' => 0], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <?php endif; ?>
                    <button class="button-link-delete ecp-single-file-action-btn" data-action="delete" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
                </td>
            </tr>
        <?php }
         // Display message if no files match filter
        if (!$files_found) { echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files match your criteria.', 'ecp') . '</td></tr>'; }
    } else {
         // Display message if "All Users" has no files at all
        echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files found for all users.', 'ecp') . '</td></tr>';
    }
    return ob_get_clean();
}
