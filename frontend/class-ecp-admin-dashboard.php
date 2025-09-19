<?php
// File: frontend/class-ecp-admin-dashboard.php
/**
 * Handles all logic for the [elevate_admin_dashboard] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 6.9.0 (Stable & Cleaned)
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

        require_once $this->plugin_path . 'admin/includes/class-ecp-user-manager.php';
        require_once $this->plugin_path . 'admin/includes/class-ecp-file-operations.php';
        require_once $this->plugin_path . 'admin/includes/class-ecp-folder-operations.php';
        require_once $this->plugin_path . 'admin/includes/class-ecp-bulk-actions.php';

        add_shortcode( 'elevate_admin_dashboard', [ $this, 'render_shortcode' ] );
        add_action( 'wp_ajax_ecp_admin_dashboard_actions', [ $this, 'ajax_handler' ] );
        add_action( 'wp_ajax_ecp_get_view', [ $this, 'ajax_get_view_handler' ] );
        add_action( 'wp_ajax_ecp_file_manager_actions', [ $this, 'ajax_file_manager_actions' ] );
    }
    
    /**
     * Main AJAX handler for user-related actions from the dashboard.
     */
    public function ajax_handler() {
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_users' ) ) {
            wp_send_json_error(['message' => __('Permission Denied.', 'ecp')]);
        }

        $sub_action = isset($_POST['sub_action']) ? sanitize_text_field($_POST['sub_action']) : null;
        switch($sub_action) {
            case 'search_users': wp_send_json_success($this->render_user_list_table()); break;
            case 'add_user':
                $result = ECP_User_Manager::add_client_user($_POST);
                if($result['success']) { wp_send_json_success($result); } else { wp_send_json_error(['message' => $result['message']]); }
                break;
            case 'save_user':
                 $result = ECP_User_Manager::update_client_user($_POST);
                if($result['success']) { wp_send_json_success(['message' => $result['message']]); } else { wp_send_json_error(['message' => $result['message']]); }
                break;
            case 'toggle_status':
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $result = ECP_User_Manager::toggle_user_status_logic($user_id, isset($_POST['enable']) && $_POST['enable'] == '1');
                if($result['success']) { wp_send_json_success(['message' => $result['message']]); } else { wp_send_json_error(['message' => $result['message']]); }
                break;
            case 'remove_user':
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $result = ECP_User_Manager::remove_user_logic($user_id);
                if($result['success']) { wp_send_json_success(['message' => $result['message']]); } else { wp_send_json_error(['message' => $result['message']]); }
                break;
            case 'save_role_permissions':
                if ( !current_user_can('promote_users') ) {
                    wp_send_json_error(['message' => __('Permission Denied.', 'ecp')]);
                }
                $result = ECP_User_Manager::save_role_permissions_logic($_POST);
                if($result['success']) { wp_send_json_success($result); } else { wp_send_json_error(['message' => $result['message']]); }
                break;
            default: wp_send_json_error(['message' => __('Invalid action specified.', 'ecp')]); break;
        }
        wp_die();
    }
    
    /**
     * AJAX handler for loading different views/pages within the dashboard.
     */
    public function ajax_get_view_handler() {
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );
         if ( ! current_user_can( 'edit_users' ) ) {
            wp_send_json_error(['message' => __('Permission Denied.', 'ecp')]);
        }

        $view = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        switch($view) {
            case 'add_user': wp_send_json_success($this->render_user_form(0)); break;
            case 'edit_user': wp_send_json_success($this->render_user_form($user_id)); break;
            case 'file_manager': wp_send_json_success($this->file_manager_component->render($user_id)); break;
            case 'all_users_files':
                if ( !current_user_can('ecp_manage_all_users_files') ) {
                    wp_send_json_error(['message' => __('You do not have permission to manage all user files.', 'ecp')]);
                }
                wp_send_json_success($this->file_manager_component->render_all_users());
                break;
            case 'file_summary': wp_send_json_success($this->render_file_summary_content()); break;
            case 'role_permissions':
                if ( !current_user_can('promote_users') ) {
                    wp_send_json_error(['message' => __('You do not have permission to manage roles.', 'ecp')]);
                }
                wp_send_json_success($this->render_role_permissions_content());
                break;
            default: wp_send_json_error(['message' => __('Invalid view requested.', 'ecp')]);
        }
        wp_die();
    }
    
    /**
     * Main AJAX handler for all file-related actions.
     */
    public function ajax_file_manager_actions() {
        check_ajax_referer('ecp_admin_ajax_nonce', 'nonce');
         if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error(['message' => __('Permission Denied.', 'ecp')]);
        }

        $user_id_str = $_POST['user_id'] ?? '0';
        $user_id = intval($user_id_str);
        $sub_action = $_POST['sub_action'] ?? '';
        $result = ['success' => false, 'message' => __('Invalid action.', 'ecp')];

        switch($sub_action) {
            case 'bulk_actions':
                $file_keys = $_POST['file_keys'] ?? [];
                $bulk_action = $_POST['bulk_action'] ?? '';
                $details = $_POST['details'] ?? '';
                $result = ECP_Bulk_Actions::handle_bulk_file_actions($user_id, $file_keys, $bulk_action, $details);
                break;
            case 'upload_file':
                // ** FIX: Use a static key for the uploaded file in $_FILES. **
                if ( ! empty( $_FILES['ecp_file_upload'] ) ) {
                    $result = ECP_File_Operations::handle_file_upload($user_id, $_FILES['ecp_file_upload'], $_POST);
                } else {
                    $result = ['success' => false, 'message' => __('No file was uploaded.', 'ecp')];
                }
                break;
            case 'add_folder':
                $result = ($user_id === 0) ? ECP_Folder_Operations::handle_all_users_add_folder_logic($_POST) : ECP_Folder_Operations::handle_add_folder_logic($user_id, $_POST);
                break;
            case 'delete_folder':
                $result = ($user_id === 0) ? ECP_Folder_Operations::handle_all_users_delete_folder_logic($_POST['folder']) : ECP_Folder_Operations::handle_delete_folder_logic($user_id, $_POST['folder']);
                break;
            case 'delete_file':
                $result = ECP_File_Operations::handle_file_delete_logic($user_id, $_POST['file_key']);
                break;
            case 'update_category':
                $result = ECP_Bulk_Actions::update_file_category_logic($user_id, $_POST['file_key'], $_POST['new_folder']);
                break;
            case 'filter_files':
                $folder = $_POST['folder'] ?? 'all';
                if ($user_id === 0) {
                    $all_clients = get_users(['role__in' => ['ecp_client', 'scp_client'], 'orderby' => 'display_name', 'order' => 'ASC']);
                    wp_send_json_success($this->file_manager_component->render_all_users_table_rows($folder, $all_clients));
                } else {
                    wp_send_json_success($this->file_manager_component->render_table_rows($user_id, $folder));
                }
                return; // Prevent fall-through to error handler
        }

        if($result['success']) { wp_send_json_success(['message' => $result['message']]); } 
        else { wp_send_json_error(['message' => $result['message']]); }
    }


    public function render_shortcode() {
        if ( !is_user_logged_in() || !current_user_can('edit_users') ) {
            return '<p>'.__('Permission Denied.', 'ecp').'</p>';
        }

        ob_start(); ?>
        <div class="ecp-admin-dashboard">
            <div id="ecp-admin-messages"></div>
            <div id="ecp-dashboard-main-content">
                <?php echo $this->render_user_list_component(); ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }
    
    private function render_user_list_component() {
        ob_start(); ?>
        <div class="ecp-dashboard-view" id="ecp-user-list-view">
            <div class="ecp-header">
                <h2><?php _e('Client Management', 'ecp'); ?></h2>
                <div>
                    <?php if ( current_user_can('ecp_view_file_summary') ): ?>
                        <button id="ecp-file-summary-btn" class="button"><?php _e('File Summary', 'ecp'); ?></button>
                    <?php endif; ?>
                    <?php if ( current_user_can('promote_users') ): ?>
                        <button id="ecp-role-permissions-btn" class="button"><?php _e('Role Permissions', 'ecp'); ?></button>
                    <?php endif; ?>
                    <?php if ( current_user_can('ecp_manage_all_users_files') ): ?>
                        <button id="ecp-all-users-files-btn" class="button"><?php _e('All Users Files', 'ecp'); ?></button>
                    <?php endif; ?>
                    <button id="ecp-add-new-client-btn" class="button button-primary"><?php _e('Add New Client', 'ecp'); ?></button>
                </div>
            </div>
            <div class="ecp-controls"><div><label for="ecp-admin-user-search"><?php _e('Search Clients', 'ecp'); ?></label><input type="search" id="ecp-admin-user-search" placeholder="<?php _e('Search by name or email...', 'ecp'); ?>"></div></div>
            <div id="ecp-admin-user-list-container"><div id="ecp-loader"><div class="ecp-spinner"></div></div></div>
        </div>
        <?php return ob_get_clean();
    }

    private function render_user_list_table() {
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $users = ECP_User_Manager::get_client_users($search_term);

        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped ecp-user-list-table">
            <thead><tr><th><?php _e('User', 'ecp'); ?></th><th><?php _e('Role', 'ecp'); ?></th><th><?php _e('Managed By', 'ecp'); ?></th><th><?php _e('Status', 'ecp'); ?></th><th class="actions-col"><?php _e('Actions', 'ecp'); ?></th></tr></thead>
            <tbody>
                <?php if (!empty($users)): foreach ($users as $user):
                    $is_enabled = !get_user_meta($user->ID, 'ecp_user_disabled', true);
                    $user_status = $is_enabled ? '<span class="ecp-status-enabled">' . __('Enabled', 'ecp') . '</span>' : '<span class="ecp-status-disabled">' . __('Disabled', 'ecp') . '</span>';
                    $toggle_label = $is_enabled ? __('Disable', 'ecp') : __('Enable', 'ecp');
                    
                    global $wp_roles;
                    $role_names = array_map(function($role_slug) use ($wp_roles) {
                        return translate_user_role( $wp_roles->role_names[ $role_slug ] ?? $role_slug );
                    }, $user->roles);
                    $role_display = !empty($role_names) ? esc_html(implode(', ', $role_names)) : __('N/A', 'ecp');

                    $managed_by_str = ECP_User_Manager::get_manager_display_string($user);
                    ?>
                    <tr data-userid="<?php echo esc_attr($user->ID); ?>">
                        <td data-label="<?php _e('User', 'ecp'); ?>"><?php echo esc_html($user->display_name); ?><br><small><?php echo esc_html($user->user_email); ?></small></td>
                        <td data-label="<?php _e('Role', 'ecp'); ?>"><?php echo $role_display; ?></td>
                        <td data-label="<?php _e('Managed By', 'ecp'); ?>"><?php echo $managed_by_str ?: 'N/A'; ?></td>
                        <td data-label="<?php _e('Status', 'ecp'); ?>"><?php echo $user_status; ?></td>
                        <td class="actions-col">
                            <button class="button manage-files-btn" data-userid="<?php echo esc_attr($user->ID); ?>"><?php _e('Files', 'ecp'); ?></button>
                            <button class="button edit-user-btn" data-userid="<?php echo esc_attr($user->ID); ?>"><?php _e('Edit', 'ecp'); ?></button>
                            <button class="button ecp-user-action-btn" data-action="toggle_status" data-enable="<?php echo $is_enabled ? '0' : '1'; ?>"><?php echo esc_html($toggle_label); ?></button>
                            <?php if (current_user_can('delete_users', $user->ID) && get_current_user_id() != $user->ID && !in_array('administrator', $user->roles)): ?>
                                <button class="button button-link-delete ecp-user-action-btn" data-action="remove_user"><?php _e('Remove', 'ecp'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: echo '<tr><td colspan="5" style="text-align:center; padding: 20px;">' . __('No users match your criteria.', 'ecp') . '</td></tr>'; endif; ?>
            </tbody>
        </table>
        <?php return ob_get_clean();
    }
    
    private function render_user_form($user_id = 0) {
        $user = $user_id ? get_userdata($user_id) : null;
        $is_new_user = !$user;
        ob_start();
        include $this->plugin_path . 'frontend/views/admin-dashboard-user-form.php';
        return ob_get_clean();
    }

    private function render_file_summary_content() {
        ob_start();
        include $this->plugin_path . 'frontend/views/admin-dashboard-summary.php';
        return ob_get_clean();
    }

    private function render_role_permissions_content() {
        ob_start();
        $all_capabilities_grouped = ECP_User_Manager::get_ecp_capabilities();
        include $this->plugin_path . 'frontend/views/admin-dashboard-role-permissions.php';
        return ob_get_clean();
    }
}

