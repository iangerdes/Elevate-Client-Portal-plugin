<?php
// File: elevate-client-portal/admin/includes/class-ecp-folder-operations.php
/**
 * Handles the server-side logic for all folder management operations.
 *
 * @package Elevate_Client_Portal
 * @version 12.1.0 (Final Audit & JS Fix)
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
     * @param array $data    The POST data, containing the 'folder' name and 'location'.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_add_folder_logic( $user_id, $data ) {
        if ( empty( $data['folder'] ) ) {
            return ['success' => false, 'message' => __( 'Folder name cannot be empty.', 'ecp' )];
        }
        $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        $new_folder_name = sanitize_text_field( $data['folder'] );
        $new_folder_location = isset($data['location']) ? sanitize_text_field( $data['location'] ) : '';

        // Check for duplicate folder names
        foreach ($folders as $folder) {
            $folder_name = is_array($folder) ? $folder['name'] : $folder;
            if (strcasecmp($folder_name, $new_folder_name) === 0) {
                 return ['success' => false, 'message' => __( 'This folder already exists.', 'ecp' )];
            }
        }
        
        $folders[] = [
            'name' => $new_folder_name,
            'location' => $new_folder_location
        ];

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
    public static function handle_delete_folder_logic( $user_id, $folder_to_delete_name ) {
        $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        $folder_to_delete_name = sanitize_text_field( $folder_to_delete_name );
        
        $updated_folders = [];
        $found = false;
        foreach ($folders as $folder) {
            $current_name = is_array($folder) ? $folder['name'] : $folder;
            if (strcasecmp($current_name, $folder_to_delete_name) !== 0) {
                $updated_folders[] = $folder;
            } else {
                $found = true;
            }
        }

        if ( $found ) {
            update_user_meta( $user_id, '_ecp_client_folders', $updated_folders );

            $files = get_user_meta( $user_id, '_ecp_client_file', false );
            if ( ! empty( $files ) ) {
                delete_user_meta($user_id, '_ecp_client_file');
                foreach ( $files as $file_data ) {
                    if ( isset( $file_data['folder'] ) && $file_data['folder'] === $folder_to_delete_name ) {
                        $file_data['folder'] = '/';
                    }
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
     * @param array $data The POST data, containing the 'folder' name and 'location'.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_all_users_add_folder_logic( $data ) {
        if ( empty( $data['folder'] ) ) {
            return ['success' => false, 'message' => __( 'Folder name cannot be empty.', 'ecp' )];
        }
        $folders = get_option( '_ecp_all_users_folders', [] );
        $new_folder_name = sanitize_text_field( $data['folder'] );
        $new_folder_location = isset($data['location']) ? sanitize_text_field( $data['location'] ) : '';

        foreach ($folders as $folder) {
            $folder_name = is_array($folder) ? $folder['name'] : $folder;
            if (strcasecmp($folder_name, $new_folder_name) === 0) {
                 return ['success' => false, 'message' => __( 'This folder already exists.', 'ecp' )];
            }
        }
        
        $folders[] = [
            'name' => $new_folder_name,
            'location' => $new_folder_location
        ];

        update_option( '_ecp_all_users_folders', $folders );
        return ['success' => true, 'message' => __( 'Folder added successfully.', 'ecp' )];
    }

    /**
     * Deletes a global folder and moves its files to 'Uncategorized'.
     *
     * @param string $folder_to_delete The name of the folder to delete.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_all_users_delete_folder_logic( $folder_to_delete_name ) {
        $folders = get_option( '_ecp_all_users_folders', [] );
        $folder_to_delete_name = sanitize_text_field( $folder_to_delete_name );

        $updated_folders = [];
        $found = false;
        foreach ($folders as $folder) {
            $current_name = is_array($folder) ? $folder['name'] : $folder;
             if (strcasecmp($current_name, $folder_to_delete_name) !== 0) {
                $updated_folders[] = $folder;
            } else {
                $found = true;
            }
        }

        if ( $found ) {
            update_option( '_ecp_all_users_folders', $updated_folders );

            $files = get_option( '_ecp_all_users_files', [] );
            if ( ! empty( $files ) ) {
                foreach ( $files as &$file_data ) {
                    if ( isset( $file_data['folder'] ) && $file_data['folder'] === $folder_to_delete_name ) {
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
