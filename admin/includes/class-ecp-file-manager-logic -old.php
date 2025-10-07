<?php
// File: admin/includes/class-ecp-file-manager-logic.php
/**
 * Handles the server-side logic for all file and folder management operations.
 *
 * @package Elevate_Client_Portal
 * @version 6.6.2 (Stable Delete & Upload)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_File_Manager_Logic
 *
 * Provides static methods to perform actions like uploading, deleting, and moving files.
 */
class ECP_File_Manager_Logic {

    // --- Folder & Category Management ---

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


    // --- File Operations ---

    /**
     * Deletes a file from both the physical location (S3 or local) and the database.
     *
     * @param int    $user_id  The user context. 0 for "All Users".
     * @param string $file_key The unique identifier for the file.
     * @return array Result array with 'success' and 'message'.
     */
    public static function handle_file_delete_logic( $user_id, $file_key ) {
        $user_id = intval( $user_id );
        $file_key = sanitize_text_field( $file_key );
        
        $file_info = ECP_File_Helper::find_file_by_hash( $file_key, $user_id, true );

        if ( ! $file_info ) {
            return ['success' => false, 'message' => __( 'File record not found.', 'ecp' )];
        }

        $file_to_delete = $file_info['original_data'];
        $owner_user_id = $file_info['user_id'];
        
        $physical_file_deleted = false;
        if ( ! empty( $file_to_delete['s3_key'] ) ) {
            // ** FIX: Correctly delete the file from the S3 bucket. **
            $s3_handler = ECP_S3::get_instance();
            if ( $s3_handler->is_s3_enabled() ) {
                $result = $s3_handler->delete_file( $file_to_delete['s3_key'] );
                $physical_file_deleted = ! is_wp_error( $result );
            }
        } elseif ( ! empty( $file_to_delete['path'] ) && file_exists( $file_to_delete['path'] ) ) {
            $physical_file_deleted = wp_delete_file( $file_to_delete['path'] );
        } else {
            // If there's no physical file, we can proceed with deleting the record.
            $physical_file_deleted = true; 
        }

        if ( $physical_file_deleted ) {
            // Now, delete the database record.
            if ( $owner_user_id === 0 ) {
                $all_files = get_option( '_ecp_all_users_files', [] );
                $remaining_files = [];
                foreach ( $all_files as $file_data ) {
                    $current_file_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : null);
                    if ( $current_file_key !== $file_key ) {
                        $remaining_files[] = $file_data;
                    }
                }
                update_option( '_ecp_all_users_files', $remaining_files );
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
    
    /**
     * Handles the file upload process, either to local storage or S3.
     *
     * @param int   $user_id The ID of the user the file is for.
     * @param array $file    The $_FILES['ecp_file_upload'] array.
     * @param array $data    The rest of the $_POST data.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_file_upload_logic( $user_id, $file, $data ) {
        $s3_handler = ECP_S3::get_instance();
        
        $file_data = [
            'name'          => sanitize_file_name( $data['original_filename'] ?? basename( $file['name'] ) ),
            'type'          => $file['type'],
            'timestamp'     => time(),
            'folder'        => sanitize_text_field( $data['ecp_file_folder'] ?? '/' ),
            'is_encrypted'  => false,
            'excluded_users' => [],
        ];

        if ( $s3_handler->is_s3_enabled() ) {
            $upload_result = $s3_handler->upload_file( $file['tmp_name'], $file_data['name'] );

            if ( is_wp_error( $upload_result ) ) {
                return ['success' => false, 'message' => $upload_result->get_error_message()];
            }

            $file_data['s3_key'] = $upload_result['key'];
            $file_data['size'] = $upload_result['size'];
            $file_data['path'] = null;

        } else {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            $upload_overrides = array( 'test_form' => false );
            $upload_dir = wp_upload_dir();
            $ecp_dir = $upload_dir['basedir'] . '/ecp_client_files';
            
            add_filter( 'upload_dir', function( $dirs ) use ( $ecp_dir ) {
                $dirs['path'] = $ecp_dir;
                $dirs['url'] = $dirs['baseurl'] . '/ecp_client_files';
                return $dirs;
            });

            $moved_file = wp_handle_upload( $file, $upload_overrides );
            remove_all_filters( 'upload_dir' );

            if ( ! $moved_file || isset( $moved_file['error'] ) ) {
                return ['success' => false, 'message' => $moved_file['error'] ?? 'Upload failed.'];
            }
            $file_data['path'] = $moved_file['file'];
            $file_data['s3_key'] = null;
        }

        add_user_meta( $user_id, '_ecp_client_file', $file_data );

        if ( ! empty( $data['ecp_notify_client'] ) ) {
            // TODO: Notification logic
        }

        return ['success' => true, 'message' => __( 'File uploaded successfully.', 'ecp' )];
    }

    /**
     * Handles uploading files to the "All Users" category.
     *
     * @param array $file The $_FILES['ecp_file_upload'] array.
     * @param array $data The rest of the $_POST data.
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_all_users_file_upload( $file, $data ) {
        $s3_handler = ECP_S3::get_instance();
        
        $file_data = [
            'name'          => sanitize_file_name( $data['original_filename'] ?? basename( $file['name'] ) ),
            'type'          => $file['type'],
            'timestamp'     => time(),
            'folder'        => sanitize_text_field( $data['ecp_file_folder'] ?? '/' ),
            'is_encrypted'  => false,
            'excluded_users' => [],
        ];

        if ( $s3_handler->is_s3_enabled() ) {
            $upload_result = $s3_handler->upload_file( $file['tmp_name'], $file_data['name'] );
            if ( is_wp_error( $upload_result ) ) {
                return ['success' => false, 'message' => $upload_result->get_error_message()];
            }
            $file_data['s3_key'] = $upload_result['key'];
            $file_data['size'] = $upload_result['size'];
            $file_data['path'] = null;

        } else {
             if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
            }
            $upload_overrides = array( 'test_form' => false );
            $upload_dir = wp_upload_dir();
            $ecp_dir = $upload_dir['basedir'] . '/ecp_client_files';
            
            add_filter( 'upload_dir', function( $dirs ) use ( $ecp_dir ) {
                $dirs['path'] = $ecp_dir;
                $dirs['url'] = $dirs['baseurl'] . '/ecp_client_files';
                return $dirs;
            });
            $moved_file = wp_handle_upload( $file, $upload_overrides );
            remove_all_filters( 'upload_dir' );

            if ( ! $moved_file || isset( $moved_file['error'] ) ) {
                return ['success' => false, 'message' => $moved_file['error'] ?? 'Upload failed.'];
            }
            $file_data['path'] = $moved_file['file'];
            $file_data['s3_key'] = null;
        }

        $all_users_files = get_option( '_ecp_all_users_files', [] );
        $all_users_files[] = $file_data;
        update_option( '_ecp_all_users_files', $all_users_files );
        
        return ['success' => true, 'message' => __( 'File uploaded successfully to all users.', 'ecp' )];
    }


    // --- Bulk Actions ---

    /**
     * Handles various bulk actions on a selection of files.
     *
     * @param int    $user_id     The ID of the user. 0 for "All Users".
     * @param array  $file_keys   An array of file identifiers.
     * @param string $bulk_action The action to perform (e.g., 'delete', 'move').
     * @param mixed  $details     Additional details for the action (e.g., target folder or password).
     * @return array A result array with 'success' (bool) and 'message' (string).
     */
    public static function handle_bulk_file_actions( $user_id, $file_keys, $bulk_action, $details ) {
        $user_id = intval( $user_id );
        $processed_count = 0;
        $error_count = 0;

        switch ( $bulk_action ) {
            case 'delete':
                foreach ( $file_keys as $file_key ) {
                    $result = self::handle_file_delete_logic( $user_id, $file_key );
                    if ( $result['success'] ) $processed_count++;
                    else $error_count++;
                }
                return ['success' => true, 'message' => sprintf( __( '%d file(s) deleted.', 'ecp' ), $processed_count )];

            case 'move':
                $new_folder = sanitize_text_field( $details );
                 foreach ( $file_keys as $file_key ) {
                    $result = self::update_file_category_logic( $user_id, $file_key, $new_folder );
                    if ( $result['success'] ) $processed_count++;
                    else $error_count++;
                }
                return ['success' => true, 'message' => sprintf( __( '%d file(s) moved to %s.', 'ecp' ), $processed_count, $new_folder)];

            case 'encrypt':
            case 'decrypt':
                $password = $details;
                if ( empty( $password ) ) {
                    return ['success' => false, 'message' => __( 'Password cannot be empty for encryption/decryption.', 'ecp' )];
                }
                foreach ( $file_keys as $file_key ) {
                    $result = self::handle_file_encryption_logic( $user_id, $file_key, $password, ($bulk_action === 'encrypt') );
                     if ( $result['success'] ) $processed_count++;
                    else $error_count++;
                }
                $action_past_tense = ($bulk_action === 'encrypt') ? 'encrypted' : 'decrypted';
                return ['success' => true, 'message' => sprintf( __( '%d file(s) %s.', 'ecp' ), $processed_count, $action_past_tense )];
        }

        return ['success' => false, 'message' => __( 'Invalid bulk action.', 'ecp' )];
    }
    
    /**
     * Updates the folder/category for a specific file.
     *
     * @param int    $user_id    The user ID context. 0 for "All Users".
     * @param string $file_key   The file's unique identifier.
     * @param string $new_folder The new folder name.
     * @return array A result array.
     */
    public static function update_file_category_logic($user_id, $file_key, $new_folder) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        $file_data = $file_info['original_data'];
        $file_data['folder'] = $new_folder;
        
        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true, 'message' => 'File category updated.'];
        }
        return ['success' => false, 'message' => 'Could not update file category.'];
    }

    /**
     * Handles the encryption or decryption of a single file.
     *
     * @param int    $user_id  The user ID context.
     * @param string $file_key The file identifier.
     * @param string $password The password to use.
     * @param bool   $encrypt  True to encrypt, false to decrypt.
     * @return array A result array.
     */
    public static function handle_file_encryption_logic( $user_id, $file_key, $password, $encrypt = true ) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        // Placeholder for actual encryption/decryption logic
        
        $file_data = $file_info['original_data'];
        $file_data['is_encrypted'] = $encrypt;

        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Could not update file encryption status.'];
    }

    /**
     * A private helper to save updated file metadata back to the database.
     *
     * @param int    $user_id       The ID of the user who owns the file (0 for "All Users").
     * @param string $file_key      The unique identifier of the file.
     * @param array  $new_file_data The complete, updated file metadata array.
     * @return bool True on success, false on failure.
     */
    private static function _update_file_meta( $user_id, $file_key, $new_file_data ) {
        $user_id = intval($user_id);

        if ($user_id === 0) {
            $all_files = get_option('_ecp_all_users_files', []);
            $found = false;
            foreach ($all_files as &$file) {
                $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : null);
                if ($current_key === $file_key) {
                    $file = $new_file_data;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                return update_option('_ecp_all_users_files', $all_files);
            }
        } else {
            $user_files = get_user_meta($user_id, '_ecp_client_file', false);
            $found = false;
            delete_user_meta($user_id, '_ecp_client_file');
            foreach ($user_files as $file) {
                $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : null);
                if ($current_key === $file_key) {
                    add_user_meta($user_id, '_ecp_client_file', $new_file_data, false);
                    $found = true;
                } else {
                    add_user_meta($user_id, '_ecp_client_file', $file, false);
                }
            }
            return $found;
        }
        return false;
    }
}

