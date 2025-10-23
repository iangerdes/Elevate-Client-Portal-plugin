<?php
// File: elevate-client-portal/admin/includes/class-ecp-bulk-actions.php
/**
 * Handles the server-side logic for all bulk file actions.
 *
 * @package Elevate_Client_Portal
 * @version 31.1.0 (Decryption Fix)
 * @comment Fixed a critical bug where the password was not unslashed before encryption, causing a mismatch and failure during decryption.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Bulk_Actions {

    public static function handle_bulk_file_actions( $user_id, $file_keys, $bulk_action, $details ) {
        $user_id = intval( $user_id );
        $processed_count = 0;
        $error_count = 0;
        $error_messages = [];

        foreach ( $file_keys as $file_key ) {
            $result = null;
            switch ( $bulk_action ) {
                case 'delete':
                    $result = ECP_File_Operations::handle_file_delete_logic( $user_id, $file_key );
                    break;
                case 'move':
                    $new_folder = sanitize_text_field( $details );
                    $result = self::update_file_category_logic( $user_id, $file_key, $new_folder );
                    break;
                case 'encrypt':
                case 'decrypt':
                    $password = wp_unslash( $details ); // FIX: Unslash password before use
                    if ( empty( $password ) ) {
                        $result = ['success' => false, 'message' => __( 'Password cannot be empty for all files.', 'ecp' )];
                        break; 
                    }
                    $result = self::handle_file_encryption_logic( $user_id, $file_key, $password, ($bulk_action === 'encrypt') );
                    break;
            }

            if ( isset($result) && $result['success'] ) {
                $processed_count++;
            } else {
                $error_count++;
                if(isset($result['message'])) {
                    $error_messages[] = $result['message'];
                }
            }
        }
        
        $message = self::generate_response_message($bulk_action, $processed_count, $error_count, $error_messages, $details);

        return ['success' => $processed_count > 0, 'message' => $message];
    }
    
    private static function generate_response_message($action, $success_count, $error_count, $errors, $details) {
        $message = '';
        $unique_errors = array_unique($errors);

        switch($action) {
            case 'delete':
                if ($success_count > 0) $message .= sprintf( __( '%d file(s) deleted successfully.', 'ecp' ), $success_count );
                break;
            case 'move':
                 if ($success_count > 0) $message .= sprintf( __( '%d file(s) moved to %s.', 'ecp' ), $success_count, esc_html($details) );
                break;
            case 'encrypt':
            case 'decrypt':
                $action_past = ($action === 'encrypt') ? 'encrypted' : 'decrypted';
                 if ($success_count > 0) $message .= sprintf( __( '%d file(s) %s successfully.', 'ecp' ), $success_count, $action_past );
                break;
        }

        if ($error_count > 0) {
            $message .= ' ';
            if ($success_count > 0) {
                 $message .= __( 'However, some errors occurred:', 'ecp' );
            } else {
                 $message .= __( 'The following errors occurred:', 'ecp' );
            }
            $message .= ' ' . implode(', ', $unique_errors);
        }
        
        return $message ?: __('Invalid bulk action specified.', 'ecp');
    }

    public static function update_file_category_logic($user_id, $file_key, $new_folder) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) return ['success' => false, 'message' => 'File not found.'];

        $file_data = $file_info['original_data'];
        $file_data['folder'] = $new_folder;
        
        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true, 'message' => 'File moved successfully.'];
        }
        return ['success' => false, 'message' => 'Could not update file category.'];
    }

    public static function handle_file_encryption_logic( $user_id, $file_key, $password, $encrypt = true ) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) return ['success' => false, 'message' => 'File not found.'];
        
        $file_data = $file_info['original_data'];
        $s3_handler = ECP_S3::get_instance();
        
        $current_contents = null;
        if (!empty($file_data['s3_key']) && $s3_handler->is_s3_enabled()) {
            $current_contents = $s3_handler->get_file_contents($file_data['s3_key']);
        } elseif (!empty($file_data['path']) && file_exists($file_data['path'])) {
            $current_contents = file_get_contents($file_data['path']);
        }

        if (is_wp_error($current_contents) || $current_contents === null) {
            return ['success' => false, 'message' => 'Could not read original file contents.'];
        }

        $new_contents = $encrypt ? ECP_File_Operations::encrypt_file_contents($current_contents, $password) : ECP_File_Operations::decrypt_file_contents($current_contents, $password);

        if ($new_contents === false) {
            $action = $encrypt ? 'encryption' : 'decryption';
            return ['success' => false, 'message' => "File {$action} failed. Check password or file integrity."];
        }

        $update_result = null;
        if (!empty($file_data['s3_key']) && $s3_handler->is_s3_enabled()) {
            $update_result = $s3_handler->update_file_contents($file_data['s3_key'], $new_contents);
        } elseif (!empty($file_data['path']) && file_exists($file_data['path'])) {
            $update_result = file_put_contents($file_data['path'], $new_contents);
        }

        if (is_wp_error($update_result) || $update_result === false) {
             return ['success' => false, 'message' => 'Could not write new file contents to storage.'];
        }

        $file_data['is_encrypted'] = $encrypt;
        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'Could not update file encryption status in database.'];
    }

    private static function _update_file_meta( $user_id, $file_key, $new_file_data ) {
        $user_id = intval($user_id);
        $found = false;

        if (!empty($new_file_data['path']) && file_exists($new_file_data['path'])) {
            $new_file_data['size'] = filesize($new_file_data['path']);
        } elseif(!empty($new_file_data['s3_key']) && ECP_S3::get_instance()->is_s3_enabled()) {
            $meta = ECP_S3::get_instance()->get_file_metadata($new_file_data['s3_key']);
            if(!is_wp_error($meta)) {
                $new_file_data['size'] = $meta['size'];
            }
        }

        if ($user_id === 0) {
            $all_files = get_option('_ecp_all_users_files', []);
            foreach ($all_files as &$file) {
                $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : null);
                if ($current_key === $file_key) {
                    $file = $new_file_data;
                    $found = true;
                    break;
                }
            }
            if ($found) return update_option('_ecp_all_users_files', $all_files);
        } else {
            $user_files = get_user_meta($user_id, '_ecp_client_file', false);
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
