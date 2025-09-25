<?php
// File: elevate-client-portal/admin/includes/class-ecp-file-operations.php
/**
 * Handles server-side logic for single file operations like upload, delete, and metadata updates.
 * Also contains core encryption/decryption logic.
 *
 * @package Elevate_Client_Portal
 * @version 31.2.0 (Email From Address Fix)
 * @comment Updated the client notification email to send from a 'noreply@' address instead of the WordPress default.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_File_Operations {

    const ENCRYPTION_METHOD = 'aes-256-cbc';

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

        $temp_file_path = $file['tmp_name'];

        if ( ! empty( $data['ecp_encrypt_toggle'] ) && ! empty( $data['ecp_encrypt_password'] ) ) {
            if ( ! isset( $data['ecp_encrypt_password_confirm'] ) || $data['ecp_encrypt_password'] !== $data['ecp_encrypt_password_confirm'] ) {
                return ['success' => false, 'message' => __('The encryption passwords do not match.', 'ecp')];
            }
            $original_contents = file_get_contents($temp_file_path);
            $password = wp_unslash( $data['ecp_encrypt_password'] );
            $encrypted_contents = self::encrypt_file_contents($original_contents, $password);
            if ($encrypted_contents === false) {
                 return ['success' => false, 'message' => 'Encryption failed.'];
            }
            file_put_contents($temp_file_path, $encrypted_contents);
            $file_data['is_encrypted'] = true;
        }

        $s3_handler = ECP_S3::get_instance();
        if ( $s3_handler->is_s3_enabled() ) {
            $upload_result = $s3_handler->upload_file( $temp_file_path, $file_data['name'] );
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
            
            if (!file_exists($ecp_dir)) { wp_mkdir_p($ecp_dir); }

            $unique_filename = wp_unique_filename($ecp_dir, $file_data['name']);
            $destination_path = $ecp_dir . '/' . $unique_filename;

            if (move_uploaded_file($temp_file_path, $destination_path) === false) {
                 return ['success' => false, 'message' => 'Could not move uploaded file.'];
            }
            
            $file_data['path']   = $destination_path;
            $file_data['s3_key'] = null;
            $file_data['size']   = filesize($destination_path);
        }

        if ( $user_id === 0 ) {
            $all_users_files = get_option( '_ecp_all_users_files', [] );
            $all_users_files[] = $file_data;
            update_option( '_ecp_all_users_files', $all_users_files );
        } else {
            add_user_meta( $user_id, '_ecp_client_file', $file_data );
        }

        if ( ! empty( $data['ecp_notify_client'] ) && $user_id > 0 ) {
            $user = get_userdata($user_id);
            if ($user) {
                $to = $user->user_email;
                $subject = get_bloginfo('name') . ': New File Uploaded';
                $portal_page = get_page_by_path('client-portal');
                $portal_url = $portal_page ? get_permalink($portal_page->ID) : home_url();
                $message = "Hello " . $user->first_name . ",\n\nA new file, '" . $file_data['name'] . "', has been uploaded to your client portal.\n\nYou can view your files here: " . $portal_url . "\n\nThank you,\nThe " . get_bloginfo('name') . " Team";
                
                // Get the site domain to construct the 'From' address.
                $site_url = get_site_url();
                $domain = parse_url($site_url, PHP_URL_HOST);
                if (substr($domain, 0, 4) == 'www.') {
                    $domain = substr($domain, 4);
                }
                $from_email = 'noreply@' . $domain;
                $headers = ['From: ' . get_bloginfo('name') . ' <' . $from_email . '>'];

                wp_mail($to, $subject, $message, $headers);
            }
        }

        return ['success' => true, 'message' => __( 'File uploaded successfully.', 'ecp' )];
    }
    
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
    
    /**
     * Retrieves the raw contents of a file from either local storage or S3.
     *
     * @param array $file_data The file's metadata array.
     * @return string|WP_Error The file contents on success, or a WP_Error on failure.
     */
    public static function get_file_contents( $file_data ) {
        if ( ! is_array( $file_data ) ) {
            return new WP_Error( 'invalid_argument', 'Invalid file data provided.' );
        }

        $s3_handler = ECP_S3::get_instance();
        
        if ( ! empty( $file_data['s3_key'] ) && $s3_handler->is_s3_enabled() ) {
            return $s3_handler->get_file_contents( $file_data['s3_key'] );
        } elseif ( ! empty( $file_data['path'] ) && file_exists( $file_data['path'] ) ) {
            $contents = file_get_contents( $file_data['path'] );
            if ( $contents === false ) {
                return new WP_Error( 'read_error', 'Could not read local file.' );
            }
            return $contents;
        }

        return new WP_Error( 'not_found', 'File source not found in storage.' );
    }
    
    public static function encrypt_file_contents($contents, $password) {
        $key = hash('sha256', $password);
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($contents, self::ENCRYPTION_METHOD, $key, 0, $iv);
        if ($encrypted === false) {
            return false;
        }
        return base64_encode($iv . $encrypted);
    }
    
    public static function decrypt_file_contents($contents, $password) {
        $contents = base64_decode($contents);
        $key = hash('sha256', $password);
        $iv_length = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($contents, 0, $iv_length);
        $encrypted = substr($contents, $iv_length);
        return openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
    }
}

