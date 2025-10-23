<?php
// File: elevate-client-portal/frontend/class-ecp-file-manager.php
/**
 * Component for rendering the File Manager in the Admin Dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 23.0.0 (Final Audit & Encryption Fix)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_File_Manager_Component {

    private $plugin_path;
    private $plugin_url;
    private $tables_component_loaded = false;

    public function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
    }

    private function load_tables_component() {
        if ( ! $this->tables_component_loaded ) {
            require_once $this->plugin_path . 'frontend/views/components/file-manager-tables.php';
            $this->tables_component_loaded = true;
        }
    }

    /**
     * Renders the file manager for a specific user.
     */
    public function render( $user_id ) {
        $user = get_userdata($user_id);
        if(!$user) { return '<div class="notice notice-error"><p>'.__('User not found.', 'ecp').'</p></div>'; }
        
        // Individual users should only see their own folders.
        $folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        
        ob_start();
        include $this->plugin_path . 'frontend/views/components/file-manager-single-user.php';
        return ob_get_clean();
    }

    /**
     * Renders the file manager for "All Users".
     */
    public function render_all_users() {
        // "All Users" should only see global folders.
        $folders = get_option( '_ecp_all_users_folders', [] );
        
        ob_start();
        include $this->plugin_path . 'frontend/views/components/file-manager-all-users.php';
        return ob_get_clean();
    }

    /**
     * Renders the HTML table rows for a single user's file list.
     */
    public function render_table_rows($user_id, $folder_filter = 'all') {
        $this->load_tables_component();
        return ecp_render_single_user_table_rows($user_id, $folder_filter);
    }

    /**
     * Renders the HTML table rows for the "All Users" file list.
     */
    public function render_all_users_table_rows($folder_filter = 'all') {
        $this->load_tables_component();
        return ecp_render_all_users_table_rows($folder_filter);
    }
}

