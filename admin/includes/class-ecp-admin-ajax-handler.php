<?php
// File: elevate-client-portal/admin/includes/class-ecp-admin-ajax-handler.php
/**
 * Handles all AJAX requests originating from the Admin Dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 117.0.0 (Admin-Only Permissions)
 * @comment Restricted the Role Permissions feature to administrators only by updating the capability check from 'promote_users' to 'manage_options'.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Admin_Ajax_Handler {

    private static $instance;

    /**
     * ** FIX: Implement the Singleton pattern correctly. **
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ** FIX: Make the constructor private to enforce the Singleton pattern. **
     */
    private function __construct() {
        add_action( 'wp_ajax_ecp_admin_dashboard_actions', [ $this, 'ajax_dashboard_handler' ] );
        add_action( 'wp_ajax_ecp_get_view', [ $this, 'ajax_get_view_handler' ] );
    }

    public function ajax_dashboard_handler() {
        check_ajax_referer( 'ecp_dashboard_nonce', 'nonce' );
        ECP_Permissions_Helper::check_permission_or_die('edit_users');

        $sub_action = isset($_POST['sub_action']) ? sanitize_text_field($_POST['sub_action']) : null;
        $result = ['success' => false, 'message' => __('Invalid action specified.', 'ecp')];

        switch($sub_action) {
            case 'search_users':
                wp_send_json_success($this->render_user_list_table());
                return;
            case 'add_user':
                $result = ECP_User_Manager::add_client_user($_POST);
                break;
            case 'save_user':
                $result = ECP_User_Manager::update_client_user($_POST);
                break;
            case 'toggle_status':
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $result = ECP_User_Manager::toggle_user_status_logic($user_id, isset($_POST['enable']) && $_POST['enable'] == '1');
                break;
            case 'remove_user':
                $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
                $result = ECP_User_Manager::remove_user_logic($user_id);
                break;
            case 'save_role_permissions':
                ECP_Permissions_Helper::check_permission_or_die('manage_options');
                $result = ECP_User_Manager::save_role_permissions_logic($_POST);
                break;
        }
        
        if ($result['success']) wp_send_json_success($result);
        else wp_send_json_error(['message' => $result['message'] ?? __('An unspecified error occurred.', 'ecp')]);
    }
    
    public function ajax_get_view_handler() {
        check_ajax_referer( 'ecp_view_nonce', 'nonce' );
        ECP_Permissions_Helper::check_permission_or_die('edit_users');

        $view = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $dashboard_instance = ECP_Admin_Dashboard::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        $html = '';

        switch($view) {
            case 'user_list':
                $html = $dashboard_instance->render_user_list_component();
                break;
            case 'add_user':
                $html = $dashboard_instance->render_user_form(0);
                break;
            case 'edit_user':
                $html = $dashboard_instance->render_user_form($user_id);
                break;
            case 'file_manager':
                $file_manager = $dashboard_instance->get_file_manager_component();
                $html = $file_manager->render($user_id);
                break;
            case 'all_users_files':
                ECP_Permissions_Helper::check_permission_or_die('ecp_manage_all_users_files');
                $file_manager = $dashboard_instance->get_file_manager_component();
                $html = $file_manager->render_all_users();
                break;
            case 'file_summary': 
                ECP_Permissions_Helper::check_permission_or_die('ecp_view_file_summary');
                $html = $dashboard_instance->render_file_summary_content();
                break;
            case 'role_permissions':
                ECP_Permissions_Helper::check_permission_or_die('manage_options');
                $html = $dashboard_instance->render_role_permissions_content();
                break;
            default:
                wp_send_json_error(['message' => __('Invalid view requested.', 'ecp')]);
        }
        wp_send_json_success($html);
    }

    private function render_user_list_table() {
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $users = ECP_User_Manager::get_client_users($search_term);
        ob_start(); ?>
        <table class="wp-list-table widefat fixed striped ecp-user-list-table">
            <thead><tr><th><?php _e('User', 'ecp'); ?></th><th><?php _e('Role', 'ecp'); ?></th><th><?php _e('Managed By', 'ecp'); ?></th><th><?php _e('Status', 'ecp'); ?></th><th class="actions-col"><?php _e('Actions', 'ecp'); ?></th></tr></thead>
            <tbody>
                <?php if (!empty($users)): foreach ($users as $user):
                    $is_enabled = !get_user_meta($user->ID, 'ecp_user_disabled', true);
                    $user_status = $is_enabled ? '<span class="ecp-status-enabled">' . __('Enabled', 'ecp') . '</span>' : '<span class="ecp-status-disabled">' . __('Disabled', 'ecp') . '</span>';
                    $toggle_label = $is_enabled ? __('Disable', 'ecp') : __('Enable', 'ecp');
                    global $wp_roles;
                    $role_names = array_map(function($role_slug) use ($wp_roles) { return translate_user_role( $wp_roles->role_names[ $role_slug ] ?? $role_slug ); }, $user->roles);
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
}

