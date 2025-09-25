<?php
// File: elevate-client-portal/includes/download-handlers/class-ecp-zip-file-handler.php
/**
 * Handles all secure ZIP archive download requests.
 *
 * @package Elevate_Client_Portal
 * @version 89.0.0 (ZIP Download Nonce Fix)
 * @comment Now uses a dedicated, consistent nonce for verifying ZIP download links to fix security errors.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Zip_File_Handler {

    /**
     * Processes the download request for a temporary ZIP archive.
     */
    public function process() {
        // ** FIX: Use the Security Helper to verify the new dedicated nonce. **
        if ( ! ECP_Security_Helper::verify_nonce('zip_download', '_wpnonce') ) {
            wp_die( 'Security check failed.', 403 );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to download files.', 403 );
        }

        $zip_filename = isset( $_GET['zip_file'] ) ? sanitize_file_name( urldecode( $_GET['zip_file'] ) ) : '';
        if ( empty( $zip_filename ) ) {
            wp_die( 'Invalid request.', 400 );
        }

        $upload_dir   = wp_upload_dir();
        $tmp_dir      = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';
        $zip_filepath = $tmp_dir . $zip_filename;
        
        // Security check: ensure the file is within the intended directory
        if ( strpos( realpath( $zip_filepath ), realpath( $tmp_dir ) ) !== 0 ) {
            wp_die( 'Invalid file path.', 400 );
        }

        if ( ! file_exists( $zip_filepath ) ) {
            wp_die( 'The ZIP archive has expired or could not be found.', 404 );
        }

        ECP_Audit_Log::log_event( get_current_user_id(), $zip_filename );

        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . esc_attr( $zip_filename ) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $zip_filepath ) );
        
        if ( ob_get_level() ) {
            ob_clean();
        }
        flush();
        readfile( $zip_filepath );
        
        exit;
    }
}

