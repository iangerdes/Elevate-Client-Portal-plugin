<?php
// File: admin/views/admin-s3-browser-page.php
/**
 * View template for the S3 File Browser page.
 *
 * @package Elevate_Client_Portal
 * @version 6.6.2 (Use Standardized File List)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// This class is required to format the file size.
require_once ECP_PLUGIN_PATH . 'admin/includes/class-ecp-file-helper.php';

?>
<div class="wrap">
    <h1><?php _e( 'S3 File Browser', 'ecp' ); ?></h1>
    <p><?php _e( 'This page shows a direct listing of all files currently stored in your configured S3 bucket under the <code>client-files/</code> directory.', 'ecp' ); ?></p>
    
    <?php
    $s3_handler = ECP_S3::get_instance();
    $all_files = $s3_handler->get_all_s3_files();

    if ( is_wp_error( $all_files ) ) :
    ?>
        <div class="notice notice-error">
            <p><strong><?php _e( 'Error:', 'ecp' ); ?></strong> <?php echo esc_html( $all_files->get_error_message() ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e( 'File Name', 'ecp' ); ?></th>
                    <th><?php _e( 'Size', 'ecp' ); ?></th>
                    <th><?php _e( 'Last Modified (UTC)', 'ecp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $all_files ) ) : ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 20px;"><?php _e( 'No files found in S3 bucket.', 'ecp' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $all_files as $file_object ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( basename( $file_object['key'] ) ); ?></code></td>
                            <td><?php echo esc_html( ECP_File_Helper::format_file_size( $file_object['size'] ) ); ?></td>
                            <td>
                                <?php
                                if ( $file_object['last_modified'] instanceof DateTimeInterface ) {
                                    echo esc_html( $file_object['last_modified']->format('Y-m-d H:i:s') );
                                } else {
                                    _e( 'N/A', 'ecp' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

