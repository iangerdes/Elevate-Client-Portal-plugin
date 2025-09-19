<?php
// File: elevate-client-portal/frontend/class-ecp-admin-dashboard.php
/**
 * Handles all logic for the [elevate_admin_dashboard] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 80.0.0 (Final Stable Asset Loading)
 * @comment This shortcode now "requests" its scripts from the Asset Manager, which will then reliably load them in the footer. This is the definitive fix for all script loading issues.
 */
class ECP_Admin_Dashboard {

    private static $instance;
    private $plugin_path;
    private $plugin_url;
    private $file_manager_component;

    public static function get_instance( $path, $url ) { 
        if ( null === self::$instance ) { self::$instance = new self( $path, $url ); } 
        return self::$instance; 
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;

        require_once $this->plugin_path . 'frontend/class-ecp-file-manager.php';
        $this->file_manager_component = new ECP_File_Manager_Component($this->plugin_path, $this->plugin_url);

        add_shortcode( 'elevate_admin_dashboard', [ $this, 'render_shortcode' ] );
    }
    
    public function render_shortcode() {
        if ( !is_user_logged_in() || !current_user_can('edit_users') ) {
            return '<p>'.__('Permission Denied.', 'ecp').'</p>';
        }

        // Request the scripts and styles required for this shortcode.
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL )->request_admin_dashboard_suite();

        ob_start(); 
        ?>
        <div class="ecp-admin-dashboard">
            <div id="ecp-admin-messages"></div>
            <div id="ecp-dashboard-main-content">
                <?php echo $this->render_user_list_component(); ?>
            </div>
        </div>
        <?php 
        return ob_get_clean();
    }
    
    public function render_user_list_component() {
        ob_start(); ?>
        <div class="ecp-dashboard-view" id="ecp-user-list-view">
            <div class="ecp-header">
                <h2><?php _e('Client Management', 'ecp'); ?></h2>
                <div>
                    <?php if ( current_user_can('ecp_view_file_summary') ): ?> <button id="ecp-file-summary-btn" class="button"><?php _e('File Summary', 'ecp'); ?></button> <?php endif; ?>
                    <?php if ( current_user_can('promote_users') ): ?> <button id="ecp-role-permissions-btn" class="button"><?php _e('Role Permissions', 'ecp'); ?></button> <?php endif; ?>
                    <?php if ( current_user_can('ecp_manage_all_users_files') ): ?> <button id="ecp-all-users-files-btn" class="button"><?php _e('All Users Files', 'ecp'); ?></button> <?php endif; ?>
                    <button id="ecp-add-new-client-btn" class="button button-primary"><?php _e('Add New User', 'ecp'); ?></button>
                </div>
            </div>
            <div class="ecp-controls"><div><label for="ecp-admin-user-search"><?php _e('Search Clients', 'ecp'); ?></label><input type="search" id="ecp-admin-user-search" placeholder="<?php _e('Search by name or email...', 'ecp'); ?>"></div></div>
            <div id="ecp-admin-user-list-container"><div id="ecp-loader"><div class="ecp-spinner"></div></div></div>
        </div>
        <?php return ob_get_clean();
    }

    public function render_user_form($user_id = 0) {
        $user = $user_id ? get_userdata($user_id) : null;
        $is_new_user = !$user;
        ob_start();
        include $this->plugin_path . 'frontend/views/admin-dashboard-user-form.php';
        return ob_get_clean();
    }

    public function render_file_summary_content() {
        ob_start();
        include $this->plugin_path . 'frontend/views/admin-dashboard-summary.php';
        return ob_get_clean();
    }

    public function render_role_permissions_content() {
        ob_start();
        include $this->plugin_path . 'frontend/views/admin-dashboard-role-permissions.php';
        return ob_get_clean();
    }

    public function get_file_manager_component() {
        return $this->file_manager_component;
    }
}

