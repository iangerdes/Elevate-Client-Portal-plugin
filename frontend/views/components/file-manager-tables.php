<?php
// File: frontend/views/components/file-manager-tables.php
/**
 * Component file for rendering the file manager tables in the admin dashboard.
 * This file contains the presentation logic for the file lists.
 *
 * @package Elevate_Client_Portal
 * @version 6.4.0 (UI and Logic Fixes)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Renders the HTML table rows for a single user's file list.
 *
 * @param int $user_id The user ID.
 * @param string $folder_filter The folder to filter by.
 * @return string The HTML for the table body.
 */
function ecp_render_single_user_table_rows($user_id, $folder_filter = 'all') {
    $client_files = ECP_File_Helper::get_hydrated_files_for_user( $user_id );
    $user_folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
    $all_users_folders = get_option( '_ecp_all_users_folders', [] );
    $folders = array_unique( array_merge( $user_folders, $all_users_folders ) );
    sort($folders);

    ob_start();
    if(!empty($client_files)) {
        usort($client_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($client_files as $file) {
            if(!is_array($file) || !isset($file['name'])) continue;

            $current_folder = $file['folder'] ?? '/';
            if ($folder_filter !== 'all' && $current_folder !== $folder_filter) { continue; }
            
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
                        <option value="/" <?php selected($current_folder, '/'); ?>><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { echo '<option value="'.esc_attr($folder).'" '.selected($current_folder, $folder, false).'>'.esc_html($folder).'</option>'; } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'])); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if (!$is_encrypted): ?>
                        <button class="button button-small ecp-encrypt-file-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => $file_key, 'target_user_id' => $user_id], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <button class="button-link-delete ecp-delete-link" data-action="delete_file" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
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
 *
 * @param string $folder_filter The folder to filter by.
 * @param array $all_clients An array of all client user objects (used for exclusions).
 * @return string The HTML for the table body.
 */
function ecp_render_all_users_table_rows($folder_filter = 'all', $all_clients = []) {
    $all_users_files = ECP_File_Helper::get_hydrated_all_users_files();
    $folders = get_option( '_ecp_all_users_folders', [] );
    sort($folders);
    
    ob_start();
    if(!empty($all_users_files)) {
        usort($all_users_files, function($a, $b) { return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0); });
        
        $files_found = false;
        foreach($all_users_files as $file) {
            if(!is_array($file) || !isset($file['name'])) continue;

            $current_folder = $file['folder'] ?? '/';
            if ($folder_filter !== 'all' && $current_folder !== $folder_filter) { continue; }
            
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
                        <option value="/" <?php selected($current_folder, '/'); ?>><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php foreach($folders as $folder) { echo '<option value="'.esc_attr($folder).'" '.selected($current_folder, $folder, false).'>'.esc_html($folder).'</option>'; } ?>
                    </select>
                    <button class="button button-small ecp-save-category-btn" style="display:none;"><?php _e('Save', 'ecp'); ?></button>
                </td>
                <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo ECP_File_Helper::format_file_size($file['size']); ?></td>
                <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'])); ?></td>
                <td data-label="<?php _e('Actions', 'ecp'); ?>" class="ecp-actions-cell">
                    <?php if (!$is_encrypted): ?>
                        <button class="button button-small ecp-encrypt-file-btn" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Encrypt', 'ecp'); ?></button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['ecp_action' => 'download_file', 'file_key' => $file_key, 'target_user_id' => 0], home_url()), 'ecp_download_file_nonce')); ?>" class="button button-small"><?php _e('Download', 'ecp'); ?></a>
                    <button class="button-link-delete ecp-delete-link" data-action="delete_file" data-filekey="<?php echo esc_attr($file_key); ?>"><?php _e('Delete', 'ecp'); ?></button>
                </td>
            </tr>
        <?php }
        if (!$files_found) { echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files match your criteria.', 'ecp') . '</td></tr>'; }
    } else {
        echo '<tr><td colspan="6" style="text-align:center; padding: 20px;">' . __('No files found for all users.', 'ecp') . '</td></tr>';
    }
    return ob_get_clean();
}

