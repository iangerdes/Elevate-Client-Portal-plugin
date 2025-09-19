<?php
// File: admin/includes/class-ecp-bulk-actions.php
/**
 * Handles the server-side logic for all bulk file actions.
 *
 * @package Elevate_Client_Portal
 * @version 9.1.0 (Stable)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Bulk_Actions {

    public static function handle_bulk_file_actions( $user_id, $file_keys, $bulk_action, $details ) {
        $user_id = intval( $user_id );
        $processed_count = 0;

        switch ( $bulk_action ) {
            case 'delete':
                foreach ( $file_keys as $file_key ) {
                    $result = ECP_File_Operations::handle_file_delete_logic( $user_id, $file_key );
                    if ( $result['success'] ) $processed_count++;
                }
                return ['success' => true, 'message' => sprintf( __( '%d file(s) deleted.', 'ecp' ), $processed_count )];

            case 'move':
                $new_folder = sanitize_text_field( $details );
                 foreach ( $file_keys as $file_key ) {
                    if ( self::update_file_category_logic( $user_id, $file_key, $new_folder )['success'] ) $processed_count++;
                }
                return ['success' => true, 'message' => sprintf( __( '%d file(s) moved to %s.', 'ecp' ), $processed_count, $new_folder)];

            case 'encrypt':
            case 'decrypt':
                $password = $details;
                if ( empty( $password ) ) return ['success' => false, 'message' => __( 'Password cannot be empty.', 'ecp' )];
                
                foreach ( $file_keys as $file_key ) {
                    if ( self::handle_file_encryption_logic( $user_id, $file_key, $password, ($bulk_action === 'encrypt') )['success'] ) $processed_count++;
                }
                $action_past_tense = ($bulk_action === 'encrypt') ? 'encrypted' : 'decrypted';
                return ['success' => true, 'message' => sprintf( __( '%d file(s) %s.', 'ecp' ), $processed_count, $action_past_tense )];
        }

        return ['success' => false, 'message' => __( 'Invalid bulk action.', 'ecp' )];
    }
    
    public static function update_file_category_logic($user_id, $file_key, $new_folder) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) return ['success' => false, 'message' => 'File not found.'];

        $file_data = $file_info['original_data'];
        $file_data['folder'] = $new_folder;
        
        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Could not update file category.'];
    }

    public static function handle_file_encryption_logic( $user_id, $file_key, $password, $encrypt = true ) {
        $file_info = ECP_File_Helper::find_file_by_hash($file_key, $user_id, true);
        if (!$file_info) return ['success' => false, 'message' => 'File not found.'];
        
        $file_data = $file_info['original_data'];
        $file_data['is_encrypted'] = $encrypt;

        if (self::_update_file_meta($file_info['user_id'], $file_key, $file_data)) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Could not update file encryption status.'];
    }

    private static function _update_file_meta( $user_id, $file_key, $new_file_data ) {
        $user_id = intval($user_id);
        $found = false;

        if ($user_id === 0) {
            $all_files = get_option('_ecp_all_users_files', []);
            foreach ($all_files as &$file) {
                if (($file['s3_key'] ?? md5($file['path'] ?? '')) === $file_key) {
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
                if (($file['s3_key'] ?? md5($file['path'] ?? '')) === $file_key) {
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

