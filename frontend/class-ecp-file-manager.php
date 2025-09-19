<?php
// File: frontend/class-ecp-file-manager.php
/**
 * Component for rendering the File Manager in the Admin Dashboard.
 * This class acts as a controller, including view templates and
 * calling component functions for rendering tables.
 *
 * @package Elevate_Client_Portal
 * @version 1.2.0 (Refactored)
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
        
        $user_folders = get_user_meta( $user_id, '_ecp_client_folders', true ) ?: [];
        $all_users_folders = get_option( '_ecp_all_users_folders', [] );
        $folders = array_unique(array_merge($user_folders, $all_users_folders));
        sort($folders);
        
        $file_manager_component = $this;
        ob_start();
        include $this->plugin_path . 'frontend/views/components/file-manager-single-user.php';
        return ob_get_clean();
    }

    /**
     * Renders the file manager for "All Users".
     */
    public function render_all_users() {
        $folders = get_option( '_ecp_all_users_folders', [] );
        sort($folders);
        $all_clients = get_users(['role__in' => ['ecp_client', 'scp_client'], 'orderby' => 'display_name', 'order' => 'ASC']);
        
        $file_manager_component = $this;
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
    public function render_all_users_table_rows($folder_filter = 'all', $all_clients = []) {
        $this->load_tables_component();
        return ecp_render_all_users_table_rows($folder_filter, $all_clients);
    }
}
