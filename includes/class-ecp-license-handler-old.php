<?php
// File: includes/class-ecp-license-handler.php
/**
 * Handles all logic related to license key validation.
 *
 * @package Elevate_Client_Portal
 * @version 6.2.2 (Singleton Fix)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_License_Handler
 *
 * Manages the validation of the license key by communicating with a remote server.
 */
class ECP_License_Handler {

    private static $instance;

    /**
     * Gets the single instance of the class.
     *
     * This is a standard singleton pattern to ensure the class is only instantiated once.
     *
     * @return ECP_License_Handler The single instance of the class.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * Hooks into WordPress actions for AJAX handling and admin notices.
     */
    private function __construct() {
        add_action( 'wp_ajax_ecp_validate_license', [ $this, 'ajax_validate_license' ] );
        add_action( 'admin_notices', [ $this, 'show_license_inactive_notice' ] );
    }

    /**
     * Checks if the license is currently active.
     *
     * @return bool True if the license status is 'Active', false otherwise.
     */
    public function is_license_active() {
        return get_option( 'ecp_license_status' ) === 'Active';
    }

    /**
     * Displays an admin notice if the license is not active.
     *
     * This notice is only shown to users who can manage options.
     *
     * @return void
     */
    public function show_license_inactive_notice() {
        if ( ! $this->is_license_active() && current_user_can( 'manage_options' ) ) {
            $settings_url = admin_url( 'edit.php?post_type=ecp_client&page=ecp-settings' );
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__( 'Elevate Client Portal:', 'ecp' ) . '</strong> ' .
                 esc_html__( 'Your license key is not active. Please', 'ecp' ) .
                 ' <a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'visit the settings page', 'ecp' ) . '</a> ' .
                 esc_html__( 'to activate it.', 'ecp' );
            echo '</p></div>';
        }
    }

    /**
     * Handles the AJAX request to validate the license key.
     *
     * This function communicates with the remote license server and updates
     * the license status based on the response.
     *
     * @return void
     */
    public function ajax_validate_license() {
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
        $server_url = 'https://elevateclientportal.co.uk/licensing/license-manager.php';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => 'License key is required.' ] );
        }

        $response = wp_remote_post( $server_url, [
            'body' => [
                'license_key' => $license_key,
                'domain'      => home_url(),
            ],
            'timeout' => 15, // Set a timeout for the request
        ]);

        if ( is_wp_error( $response ) ) {
            update_option( 'ecp_license_status', 'Inactive' );
            wp_send_json_error( [ 'message' => 'Could not connect to the license server: ' . $response->get_error_message() ] );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            update_option( 'ecp_license_status', 'Inactive' );
            wp_send_json_error( [ 'message' => 'Invalid response from the license server.' ] );
        }

        if ( isset( $data['success'] ) && $data['success'] === true ) {
            update_option( 'ecp_license_status', 'Active' );
            wp_send_json_success( [ 'message' => $data['message'] ?? 'License validated successfully.' ] );
        } else {
            update_option( 'ecp_license_status', 'Inactive' );
            wp_send_json_error( [ 'message' => $data['message'] ?? 'License validation failed.' ] );
        }
    }
}

