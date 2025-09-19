<?php
// File: includes/class-ecp-download-handler.php
/**
 * Handles all secure file download requests.
 *
 * @package Elevate_Client_Portal
 * @version 9.1.0 (Stable)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Download_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'handle_download_request' ] );
    }

    public function handle_download_request() {
        if ( ! isset( $_REQUEST['ecp_action'] ) || ! isset( $_REQUEST['_wpnonce'] ) ) return;
        $action = sanitize_key($_REQUEST['ecp_action']);
        if ( $action === 'download_file' ) $this->process_single_file_download();
    }
    
    private function process_single_file_download() {
        if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'ecp_download_file_nonce' ) ) {
            wp_die( 'Security check failed.', 403 );
        }

        $file_key = isset( $_REQUEST['file_key'] ) ? sanitize_text_field( urldecode( $_REQUEST['file_key'] ) ) : '';
        $target_user_id = isset( $_REQUEST['target_user_id'] ) ? intval( $_REQUEST['target_user_id'] ) : 0;
        $file_info = $this->_find_and_authorize_file( $file_key, $target_user_id );

        if ( ! $file_info ) wp_die( 'File not found or permission denied.', 404 );

        ECP_Audit_Log::log_event( get_current_user_id(), $file_info['name'] );

        if ( ! empty( $file_info['s3_key'] ) ) {
            $this->_redirect_to_s3( $file_info );
        } elseif ( ! empty( $file_info['path'] ) ) {
            $this->_stream_local_file( $file_info );
        } else {
            wp_die( 'File record is invalid.', 500 );
        }
    }
    
    private function _find_and_authorize_file( $file_key, $target_user_id ) {
        if ( ! is_user_logged_in() ) return null;
    
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('edit_users');
        $user_context_id = ($is_admin && $target_user_id > 0) ? $target_user_id : $current_user_id;
        $found_file = ECP_File_Helper::find_file_by_hash( $file_key, $user_context_id, true );
    
        if ( ! $found_file ) return null;
    
        $file_data = $found_file['original_data'];
        $file_owner_id = $found_file['user_id'];
    
        if ( ! $is_admin ) {
            $is_own_file = ($file_owner_id == $current_user_id);
            $is_all_users_file = ($file_owner_id == 0);
            if ( ! $is_own_file && ! $is_all_users_file ) return null;
            if ( $is_all_users_file && in_array( $current_user_id, $file_data['excluded_users'] ?? [] ) ) return null;
        }
    
        return $file_data;
    }

    private function _redirect_to_s3( $file_data ) {
        $s3_handler = ECP_S3::get_instance();
        // ** FIX: Pass both the key and the name to the function. **
        $presigned_url = $s3_handler->get_presigned_url( $file_data['s3_key'], $file_data['name'] );
        if ( is_wp_error( $presigned_url ) ) {
            wp_die( 'Could not generate secure download link: ' . $presigned_url->get_error_message() );
        }
        wp_redirect( $presigned_url );
        exit;
    }

    private function _stream_local_file( $file_data ) {
        if ( ! file_exists( $file_data['path'] ) ) wp_die( 'File not found on server.', 404 );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . esc_attr($file_data['type']) );
        header( 'Content-Disposition: attachment; filename="' . esc_attr($file_data['name']) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $file_data['path'] ) );
        ob_clean();
        flush();
        readfile( $file_data['path'] );
        exit;
    }
}

