<?php
// File: admin/includes/class-ecp-file-operations.php
/**
 * Handles server-side logic for single file operations like upload, delete, and metadata updates.
 *
 * @package Elevate_Client_Portal
 * @version 6.9.0 (Stable & Cleaned)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_File_Operations
 * Provides static methods for managing individual files.
 */
class ECP_File_Operations {

    /**
     * Handles the file upload process, routing to either local or S3 storage.
     * This single function handles uploads for both specific users and "All Users".
     *
     * @param int   $user_id The user ID (0 for "All Users").
     * @param array $file    The $_FILES array for the uploaded file.
     * @param array $data    The rest of the $_POST data.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_file_upload( $user_id, $file, $data ) {
        $user_id = intval( $user_id );
        
        $file_data = [
            'name'           => sanitize_file_name( $data['original_filename'] ?? basename( $file['name'] ) ),
            'type'           => $file['type'],
            'timestamp'      => time(),
            'folder'         => sanitize_text_field( $data['ecp_file_folder'] ?? '/' ),
            'is_encrypted'   => false,
            'excluded_users' => [],
        ];

        $s3_handler = ECP_S3::get_instance();
        if ( $s3_handler->is_s3_enabled() ) {
            $upload_result = $s3_handler->upload_file( $file['tmp_name'], $file_data['name'] );
            if ( is_wp_error( $upload_result ) ) {
                return ['success' => false, 'message' => $upload_result->get_error_message()];
            }
            $file_data['s3_key'] = $upload_result['key'];
            $file_data['size']   = $upload_result['size'];
            $file_data['path']   = null;
        } else {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            $upload_overrides = [ 'test_form' => false ];
            $upload_dir       = wp_upload_dir();
            $ecp_dir          = $upload_dir['basedir'] . '/ecp_client_files';
            
            add_filter( 'upload_dir', function( $dirs ) use ( $ecp_dir ) {
                $dirs['path'] = $ecp_dir;
                $dirs['url']  = $dirs['baseurl'] . '/ecp_client_files';
                return $dirs;
            });
            $moved_file = wp_handle_upload( $file, $upload_overrides );
            remove_all_filters( 'upload_dir' );

            if ( ! $moved_file || isset( $moved_file['error'] ) ) {
                return ['success' => false, 'message' => $moved_file['error'] ?? 'Upload failed.'];
            }
            $file_data['path']   = $moved_file['file'];
            $file_data['s3_key'] = null;
        }

        // Save the metadata record.
        if ( $user_id === 0 ) {
            $all_users_files = get_option( '_ecp_all_users_files', [] );
            $all_users_files[] = $file_data;
            update_option( '_ecp_all_users_files', $all_users_files );
        } else {
            add_user_meta( $user_id, '_ecp_client_file', $file_data );
        }

        if ( ! empty( $data['ecp_notify_client'] ) && $user_id > 0 ) {
            // Future notification logic can go here.
        }

        return ['success' => true, 'message' => __( 'File uploaded successfully.', 'ecp' )];
    }
    
    /**
     * Deletes a file from both its physical location (S3 or local) and the database.
     *
     * @param int    $user_id  The user context. 0 for "All Users".
     * @param string $file_key The unique identifier for the file.
     * @return array Result array with 'success' and 'message'.
     */
    public static function handle_file_delete_logic( $user_id, $file_key ) {
        $file_info = ECP_File_Helper::find_file_by_hash( $file_key, $user_id, true );

        if ( ! $file_info ) {
            return ['success' => false, 'message' => __( 'File record not found.', 'ecp' )];
        }

        $file_to_delete = $file_info['original_data'];
        $owner_user_id  = $file_info['user_id'];
        
        $physical_file_deleted = false;
        if ( ! empty( $file_to_delete['s3_key'] ) ) {
            $s3_handler = ECP_S3::get_instance();
            if ( $s3_handler->is_s3_enabled() ) {
                $result = $s3_handler->delete_file( $file_to_delete['s3_key'] );
                $physical_file_deleted = ! is_wp_error( $result );
            }
        } elseif ( ! empty( $file_to_delete['path'] ) && file_exists( $file_to_delete['path'] ) ) {
            $physical_file_deleted = wp_delete_file( $file_to_delete['path'] );
        } else {
            $physical_file_deleted = true; 
        }

        if ( $physical_file_deleted ) {
            if ( $owner_user_id === 0 ) {
                $all_files = get_option( '_ecp_all_users_files', [] );
                $remaining_files = array_filter($all_files, function($file) use ($file_key) {
                    $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : null);
                    return $current_key !== $file_key;
                });
                update_option( '_ecp_all_users_files', array_values($remaining_files) );
            } else {
                $user_files = get_user_meta( $owner_user_id, '_ecp_client_file', false );
                delete_user_meta( $owner_user_id, '_ecp_client_file' );
                foreach ( $user_files as $file_data ) {
                    $current_file_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : null);
                    if ( $current_file_key !== $file_key ) {
                        add_user_meta( $owner_user_id, '_ecp_client_file', $file_data, false );
                    }
                }
            }
            return ['success' => true, 'message' => __( 'File deleted successfully.', 'ecp' )];
        }

        return ['success' => false, 'message' => __( 'The physical file could not be deleted from storage.', 'ecp' )];
    }
}

