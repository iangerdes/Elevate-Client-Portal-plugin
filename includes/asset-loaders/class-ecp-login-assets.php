<?php
// File: elevate-client-portal/includes/asset-loaders/class-ecp-login-assets.php
/**
 * Handles loading of all assets for the [elevate_login] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 101.0.0 (Login Page Fatal Error Fix)
 */
class ECP_Login_Assets {
    
    private $plugin_path;
    private $plugin_url;

    public function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
    }

    public function enqueue_scripts() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
    }

    private function enqueue_style($handle, $path, $deps = []) {
        $file_path = $this->plugin_path . $path;
        $version = file_exists($file_path) ? filemtime($file_path) : ECP_VERSION;
        wp_enqueue_style($handle, $this->plugin_url . $path, $deps, $version);
    }
}
