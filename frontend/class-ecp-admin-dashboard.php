<?php
// File: elevate-client-portal/frontend/class-ecp-admin-dashboard.php
/**
 * Handles all logic for the [elevate_admin_dashboard] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 122.0.0 (JS Loading Fix)
 * @comment Removed direct script enqueuing from the shortcode render function. Asset loading is now handled globally by the ECP_Asset_Manager via the 'wp_enqueue_scripts' hook.
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

        // ** REMOVED: Script loading is now handled by the ECP_Asset_Manager. **

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
            <?php
            $title = __('Client Management', 'ecp');
            $is_main_view = true;
            $show_back_button = false;
            include $this->plugin_path . 'frontend/views/components/admin-dashboard-header.php';
            ?>
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

