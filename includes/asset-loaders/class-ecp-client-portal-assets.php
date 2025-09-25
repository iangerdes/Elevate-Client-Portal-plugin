<?php
// File: elevate-client-portal/includes/asset-loaders/class-ecp-client-portal-assets.php
/**
 * Handles loading of all assets for the [client_portal] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 111.0.0 (AJAX Logout Fix)
 * @comment Added a 'logout' nonce to enable the new secure, cache-proof AJAX logout functionality.
 */
class ECP_Client_Portal_Assets {
    
    private $plugin_path;
    private $plugin_url;

    public function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
    }

    public function enqueue_scripts() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
        $this->enqueue_script('ecp-client-portal', 'assets/js/ecp-client-portal.js', ['jquery']);
        
        wp_localize_script( 'ecp-client-portal', 'ecp_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'home_url' => home_url('/'),
            // ** FIX: Added a new nonce for the AJAX login functionality. **
            'nonces'   => ECP_Security_Helper::get_script_nonces(['client_portal', 'decrypt_file', 'contact_manager', 'update_account', 'zip_prepare', 'zip_get_list', 'zip_delete', 'logout', 'ajax_login']),
            'strings'  => [
                'error_zip'          => __('An unknown error occurred while creating the ZIP file.', 'ecp'),
                'copied'             => __('Copied!', 'ecp'),
                'decrypt_prompt'     => __('This file is encrypted. Please enter the password to download:', 'ecp'),
                'confirm_delete_zip' => __('Are you sure you want to delete this ZIP file? This action cannot be undone.', 'ecp'),
            ]
        ]);
    }

    private function enqueue_script($handle, $path, $deps = [], $in_footer = true) {
        $file_path = $this->plugin_path . $path;
        $version = file_exists($file_path) ? filemtime($file_path) : ECP_VERSION;
        wp_enqueue_script($handle, $this->plugin_url . $path, $deps, $version, $in_footer);
    }

    private function enqueue_style($handle, $path, $deps = []) {
        $file_path = $this->plugin_path . $path;
        $version = file_exists($file_path) ? filemtime($file_path) : ECP_VERSION;
        wp_enqueue_style($handle, $this->plugin_url . $path, $deps, $version);
    }
}
