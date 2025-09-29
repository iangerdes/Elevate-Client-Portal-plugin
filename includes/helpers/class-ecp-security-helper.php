<?php
// File: elevate-client-portal/includes/helpers/class-ecp-security-helper.php
/**
 * A dedicated helper class for handling all security-related functionality,
 * primarily WordPress nonces for verifying AJAX requests.
 *
 * @package Elevate_Client_Portal
 * @version 89.0.0 (ZIP Download Nonce Fix)
 * @comment Added a dedicated 'zip_download' nonce to resolve security check failures.
 */
 
if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Security_Helper {

    private static $nonces = [
        'dashboard' => 'ecp_dashboard_nonce',
        'view' => 'ecp_view_nonce',
        'file_manager' => 'ecp_file_manager_nonce',
        'decrypt_file' => 'ecp_decrypt_file_nonce',
        'client_portal' => 'ecp_client_ajax_nonce',
        'admin_settings' => 'ecp_admin_ajax_nonce',
        'update_account' => 'ecp_update_account_nonce',
        'contact_manager' => 'ecp_contact_manager_nonce',
        'zip_prepare' => 'ecp_zip_prepare_nonce',
        'zip_get_list' => 'ecp_zip_get_list_nonce',
        'zip_delete' => 'ecp_zip_delete_nonce',
        'zip_download' => 'ecp_zip_download_nonce', // ** NEW: Dedicated nonce for downloads **
    ];

    /**
     * Creates a WordPress nonce for a specific action.
     *
     * @param string $action The key for the action (e.g., 'dashboard', 'view').
     * @return string The generated nonce.
     */
    public static function create_nonce( $action ) {
        return wp_create_nonce( self::$nonces[ $action ] ?? 'ecp_general_nonce' );
    }

    /**
     * Verifies a WordPress nonce from a request.
     *
     * @param string $action  The key for the action to verify.
     * @param string $nonce_key The key in the request data (e.g., $_POST) where the nonce is stored.
     * @return bool True if the nonce is valid, false otherwise.
     */
    public static function verify_nonce( $action, $nonce_key = 'nonce' ) {
        $nonce_value = $_REQUEST[ $nonce_key ] ?? '';
        return wp_verify_nonce( $nonce_value, self::$nonces[ $action ] ?? 'ecp_general_nonce' );
    }

    /**
     * Verifies a nonce and sends a JSON error on failure.
     */
    public static function verify_nonce_or_die( $action, $nonce_key = 'nonce' ) {
        if ( ! self::verify_nonce($action, $nonce_key) ) {
            wp_send_json_error(['message' => __('Security check failed.', 'ecp')]);
        }
    }

    /**
     * Generates an array of all nonces needed for a frontend script.
     *
     * @param array $actions A list of action keys to generate nonces for.
     * @return array An associative array of nonces.
     */
    public static function get_script_nonces( $actions ) {
        $nonces = [];
        foreach ( $actions as $action ) {
            // Converts 'some_action' to 'someActionNonce' for JavaScript.
            $key = lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $action ) ) ) ) . 'Nonce';
            $nonces[ $key ] = self::create_nonce( $action );
        }
        return $nonces;
    }
}
