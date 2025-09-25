<?php
// File: elevate-client-portal/includes/download-handlers/class-ecp-standard-file-handler.php
/**
 * Handles all secure, standard (non-encrypted) file download requests.
 *
 * @package Elevate_Client_Portal
 * @version 62.0.0
 * @comment Added `is_wp_error` check to properly handle cases where the file isn't found or the user lacks permission.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Standard_File_Handler {

    /**
     * Processes the download request for a single, non-encrypted file.
     */
    public function process() {
        if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'ecp_download_file_nonce' ) ) {
            wp_die( 'Security check failed.', 403 );
        }

        $file_key       = isset( $_REQUEST['file_key'] ) ? sanitize_text_field( urldecode( $_REQUEST['file_key'] ) ) : '';
        $target_user_id = isset( $_REQUEST['target_user_id'] ) ? intval( $_REQUEST['target_user_id'] ) : 0;
        $file_info      = ECP_File_Helper::find_and_authorize_file( $file_key, $target_user_id );

        if ( is_wp_error( $file_info ) ) {
            wp_die( $file_info->get_error_message(), 404 );
        }

        if ( ! empty( $file_info['is_encrypted'] ) ) {
             wp_die( 'This file is encrypted and requires a password to download.', 403 );
        }

        ECP_Audit_Log::log_event( get_current_user_id(), $file_info['name'] );

        if ( ! empty( $file_info['s3_key'] ) ) {
            $this->redirect_to_s3( $file_info );
        } elseif ( ! empty( $file_info['path'] ) ) {
            $this->stream_local_file( $file_info );
        } else {
            wp_die( 'File record is invalid.', 500 );
        }
    }
    
    /**
     * Generates a pre-signed S3 URL and redirects the user.
     *
     * @param array $file_data The file's metadata.
     */
    private function redirect_to_s3( $file_data ) {
        $s3_handler = ECP_S3::get_instance();
        $presigned_url = $s3_handler->get_presigned_url( $file_data['s3_key'], $file_data['name'] );
        if ( is_wp_error( $presigned_url ) ) {
            wp_die( 'Could not generate secure download link: ' . $presigned_url->get_error_message() );
        }
        wp_redirect( $presigned_url );
        exit;
    }

    /**
     * Streams a local file directly to the browser.
     *
     * @param array $file_data The file's metadata.
     */
    private function stream_local_file( $file_data ) {
        if ( ! file_exists( $file_data['path'] ) ) {
            wp_die( 'File not found on server.', 404 );
        }
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . esc_attr($file_data['type']) );
        header( 'Content-Disposition: attachment; filename="' . esc_attr($file_data['name']) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $file_data['path'] ) );
        if ( ob_get_level() ) {
            ob_clean();
        }
        flush();
        readfile( $file_data['path'] );
        exit;
    }
}

