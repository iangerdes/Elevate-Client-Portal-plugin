<?php
// File: elevate-client-portal/includes/class-ecp-download-handler.php
/**
 * Main router for all secure file download requests.
 *
 * @package Elevate_Client_Portal
 * @version 18.0.0
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
        add_action( 'init', [ $this, 'route_download_request' ] );
    }

    /**
     * Checks for a download action and routes it to the appropriate handler.
     */
    public function route_download_request() {
        if ( ! isset( $_REQUEST['ecp_action'] ) ) {
            return;
        }

        $action = sanitize_key( $_REQUEST['ecp_action'] );

        switch ( $action ) {
            case 'download_file':
                $handler = new ECP_Standard_File_Handler();
                $handler->process();
                break;

            case 'download_decrypted_file':
                $handler = new ECP_Encrypted_File_Handler();
                $handler->process();
                break;
                
            case 'download_zip':
                $handler = new ECP_Zip_File_Handler();
                $handler->process();
                break;
        }
    }
}

