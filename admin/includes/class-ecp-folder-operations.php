<?php
// File: admin/includes/class-ecp-folder-operations.php
/**
 * Handles the server-side logic for all folder management operations.
 *
 * @package Elevate_Client_Portal
 * @version 6.8.0 (Refactored)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Folder_Operations
 * Provides static methods for creating and deleting folders.
 */
class ECP_Folder_Operations {

    /**
     * Adds a new folder for a specific user.
     *
     * @param int   $user_id The ID of the user.
     * @param array $data    The POST data, containing the 'folder' name.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_add_folder_logic( $user_id, $data ) {
        if ( empty( $data['folder'] ) ) {
            return ['success' => false, 'message' => __( 'Folder name cannot be empty.', 'ecp' )];
        }
        $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        $new_folder = sanitize_text_field( $data['folder'] );
        if ( in_array( $new_folder, $folders ) ) {
            return ['success' => false, 'message' => __( 'This folder already exists.', 'ecp' )];
        }
        $folders[] = $new_folder;
        update_user_meta( $user_id, '_ecp_client_folders', $folders );
        return ['success' => true, 'message' => __( 'Folder added successfully.', 'ecp' )];
    }

    /**
     * Deletes a folder for a specific user and moves its files to 'Uncategorized'.
     *
     * @param int    $user_id          The ID of the user.
     * @param string $folder_to_delete The name of the folder to delete.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_delete_folder_logic( $user_id, $folder_to_delete ) {
        $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        $folder_to_delete = sanitize_text_field( $folder_to_delete );
        
        if ( ( $key = array_search( $folder_to_delete, $folders ) ) !== false ) {
            unset( $folders[$key] );
            update_user_meta( $user_id, '_ecp_client_folders', array_values($folders) );

            $files = get_user_meta( $user_id, '_ecp_client_file', false );
            if ( ! empty( $files ) ) {
                $updated_files = [];
                foreach ( $files as $file_data ) {
                    if ( isset( $file_data['folder'] ) && $file_data['folder'] === $folder_to_delete ) {
                        $file_data['folder'] = '/';
                    }
                    $updated_files[] = $file_data;
                }
                delete_user_meta($user_id, '_ecp_client_file');
                foreach($updated_files as $file_data) {
                    add_user_meta($user_id, '_ecp_client_file', $file_data, false);
                }
            }
            return ['success' => true, 'message' => __( 'Folder deleted. Files moved to Uncategorized.', 'ecp' )];
        }
        return ['success' => false, 'message' => __( 'Folder not found.', 'ecp' )];
    }

    /**
     * Adds a new global folder for "All Users".
     *
     * @param array $data The POST data, containing the 'folder' name.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_all_users_add_folder_logic( $data ) {
        if ( empty( $data['folder'] ) ) {
            return ['success' => false, 'message' => __( 'Folder name cannot be empty.', 'ecp' )];
        }
        $folders = get_option( '_ecp_all_users_folders', [] );
        $new_folder = sanitize_text_field( $data['folder'] );
        if ( in_array( $new_folder, $folders ) ) {
            return ['success' => false, 'message' => __( 'This folder already exists.', 'ecp' )];
        }
        $folders[] = $new_folder;
        update_option( '_ecp_all_users_folders', $folders );
        return ['success' => true, 'message' => __( 'Folder added successfully.', 'ecp' )];
    }

    /**
     * Deletes a global folder and moves its files to 'Uncategorized'.
     *
     * @param string $folder_to_delete The name of the folder to delete.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_all_users_delete_folder_logic( $folder_to_delete ) {
        $folders = get_option( '_ecp_all_users_folders', [] );
        $folder_to_delete = sanitize_text_field( $folder_to_delete );

        if ( ( $key = array_search( $folder_to_delete, $folders ) ) !== false ) {
            unset( $folders[$key] );
            update_option( '_ecp_all_users_folders', array_values($folders) );

            $files = get_option( '_ecp_all_users_files', [] );
            if ( ! empty( $files ) ) {
                foreach ( $files as $file_key => &$file_data ) {
                    if ( isset( $file_data['folder'] ) && $file_data['folder'] === $folder_to_delete ) {
                        $file_data['folder'] = '/';
                    }
                }
                update_option( '_ecp_all_users_files', $files );
            }
            return ['success' => true, 'message' => __( 'Folder deleted. Files moved to Uncategorized.', 'ecp' )];
        }
        return ['success' => false, 'message' => __( 'Folder not found.', 'ecp' )];
    }
}
