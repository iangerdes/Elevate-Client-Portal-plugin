<?php
// File: elevate-client-portal/includes/asset-loaders/class-ecp-admin-dashboard-assets.php
/**
 * Handles loading of all CSS and JavaScript assets for the Admin Dashboard shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 111.1.0 (Parse Error Fix)
 * @comment Corrected a PHP parse error caused by an incomplete array in the previous update.
 */

if ( ! defined( 'WPINC') ) {
    die;
}

class ECP_Admin_Dashboard_Assets {

    private $plugin_path;
    private $plugin_url;

    public function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
    }

    /**
     * Enqueues all the scripts and styles needed for the admin dashboard.
     */
    public function enqueue_scripts() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
        
        // Enqueue the separated component scripts
        $this->enqueue_script('ecp-admin-dashboard-core', 'assets/js/admin/dashboard.js', ['jquery']);
      //  $this->enqueue_script('ecp-admin-dashboard-actions', 'assets/js/admin/file-manager.js', ['jquery', 'ecp-admin-dashboard-core']);
      //  $this->enqueue_script('ecp-admin-dashboard-uploader', 'assets/js/admin/uploader.js', ['jquery', 'ecp-admin-dashboard-core']);

        // Localize data for all dashboard scripts, attached to the core script.
        wp_localize_script( 'ecp-admin-dashboard-core', 'ecp_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'home_url' => home_url('/'),
            'nonces'   => ECP_Security_Helper::get_script_nonces(['dashboard', 'view', 'file_manager', 'decrypt_file', 'logout']),
            'strings'  => [
                'confirm_action'        => __('Are you sure you want to %s this user?', 'ecp'),
                'confirm_delete_user'   => __('Are you sure you want to permanently remove this user and all their files?', 'ecp'),
                'confirm_delete_file'   => __('Are you sure you want to delete the selected files? This cannot be undone.', 'ecp'),
                'confirm_delete_folder' => __('Are you sure you want to delete this folder? All files inside will be moved to Uncategorized.', 'ecp'),
                'encrypt_prompt'        => __('Please enter a password to encrypt this file:', 'ecp'),
                'decrypt_prompt'        => __('Please enter the password to decrypt this file:', 'ecp'),
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

