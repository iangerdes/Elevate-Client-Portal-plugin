<?php
// File: elevate-client-portal/frontend/views/admin-dashboard-summary.php
/**
 * Renders the File Summary view for the Admin Dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 21.0.0 (Final Audit)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$all_files = [];
$total_size = 0;
$processed_files = []; // To track processed files and avoid duplicates

// Get all "All Users" files first
$all_users_hydrated = ECP_File_Helper::get_hydrated_all_users_files();
if ( is_array($all_users_hydrated) ) {
    foreach ($all_users_hydrated as $file) {
        $key = !empty($file['s3_key']) ? $file['s3_key'] : ($file['path'] ?? null);
        if (!$key || isset($processed_files[$key])) continue;

        $file['owner'] = __('All Users', 'ecp');
        $all_files[] = $file;
        $total_size += $file['size'] ?? 0;
        $processed_files[$key] = true;
    }
}

// Get files for each client
$client_users = get_users(['role__in' => ['ecp_client', 'scp_client']]);
foreach ($client_users as $client) {
    // We get hydrated files to ensure S3 file sizes are included
    $client_files = ECP_File_Helper::get_hydrated_files_for_user($client->ID);
    if (is_array($client_files)) {
        foreach ($client_files as $file) {
            $key = !empty($file['s3_key']) ? $file['s3_key'] : ($file['path'] ?? null);
            if (!$key || isset($processed_files[$key])) continue;

            $file['owner'] = $client->display_name;
            $all_files[] = $file;
            $total_size += $file['size'] ?? 0;
            $processed_files[$key] = true;
        }
    }
}

// Sort all files by timestamp, newest first
usort($all_files, function($a, $b) {
    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
});

?>
<div class="ecp-dashboard-view ecp-file-summary-view">
    <div class="ecp-header">
        <h3><?php _e('File Summary', 'ecp'); ?></h3>
        <button class="button ecp-back-to-users">&larr; <?php _e('Back to Client List', 'ecp'); ?></button>
    </div>

    <div class="ecp-summary-stats">
        <p><strong><?php _e('Total Files:', 'ecp'); ?></strong> <?php echo count($all_files); ?></p>
        <p><strong><?php _e('Total Size:', 'ecp'); ?></strong> <?php echo esc_html(ECP_File_Helper::format_file_size($total_size)); ?></p>
    </div>

    <div class="table-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('File Name', 'ecp'); ?></th>
                    <th><?php _e('Assigned To', 'ecp'); ?></th>
                    <th><?php _e('Folder', 'ecp'); ?></th>
                    <th><?php _e('Size', 'ecp'); ?></th>
                    <th><?php _e('Date Uploaded', 'ecp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_files)): ?>
                    <?php foreach ($all_files as $file): 
                        $folder_display = $file['folder'] ?? '/';
                        if ( is_array($folder_display) ) {
                             $folder_display = $folder_display['name'] ?? '/';
                        }
                    ?>
                        <tr>
                            <td data-label="<?php _e('File Name', 'ecp'); ?>"><?php echo esc_html($file['name']); ?></td>
                            <td data-label="<?php _e('Assigned To', 'ecp'); ?>"><?php echo esc_html($file['owner']); ?></td>
                            <td data-label="<?php _e('Folder', 'ecp'); ?>"><?php echo esc_html($folder_display); ?></td>
                            <td data-label="<?php _e('Size', 'ecp'); ?>"><?php echo esc_html(ECP_File_Helper::format_file_size($file['size'] ?? 0)); ?></td>
                            <td data-label="<?php _e('Date Uploaded', 'ecp'); ?>"><?php echo esc_html(date_i18n(get_option('date_format'), $file['timestamp'] ?? time())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 20px;"><?php _e('No files found in the system.', 'ecp'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
