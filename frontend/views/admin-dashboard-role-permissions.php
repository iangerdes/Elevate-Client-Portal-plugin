<?php
// File: elevate-client-portal/frontend/views/admin-dashboard-role-permissions.php
/**
 * Renders the Role Permissions view for the Admin Dashboard.
 *
 * @package Elevate_Client_Portal
 * @version 118.0.0 (Role Existence Check)
 * @comment Added checks to ensure user roles exist before accessing their properties. This prevents PHP warnings and fatal errors if roles are not yet registered when the page loads.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$all_capabilities_grouped = ECP_User_Manager::get_ecp_capabilities();
$editable_roles = ['ecp_business_admin', 'ecp_client_manager', 'ecp_client'];
global $wp_roles;
?>
<div class="ecp-dashboard-view ecp-role-permissions-view">
    <?php
    $title = __('Role Permissions', 'ecp');
    $is_main_view = false;
    $show_back_button = true;
    include ECP_PLUGIN_PATH . 'frontend/views/components/admin-dashboard-header.php';
    ?>

    <form id="ecp-role-permissions-form" class="ecp-ajax-form" method="post">
        <input type="hidden" name="action" value="ecp_admin_dashboard_actions">
        <input type="hidden" name="sub_action" value="save_role_permissions">

        <p class="description"><?php _e('Use the checkboxes below to control what each user role can do. Changes apply to all users with that role.', 'ecp'); ?></p>

        <table class="wp-list-table widefat fixed striped ecp-permissions-table">
            <thead>
                <tr>
                    <th id="capability-col"><?php _e('Capability', 'ecp'); ?></th>
                    <?php foreach ($editable_roles as $role_slug): ?>
                        <th class="ecp-role-col">
                            <?php 
                            // ** FIX: Check if the role exists before trying to access its name. **
                            $role_name = isset($wp_roles->roles[$role_slug]) ? $wp_roles->roles[$role_slug]['name'] : $role_slug;
                            echo esc_html(translate_user_role($role_name)); 
                            ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            
            <?php foreach ($all_capabilities_grouped as $group_name => $capabilities): ?>
                <tbody class="ecp-permissions-group">
                    <tr>
                        <th colspan="<?php echo count($editable_roles) + 1; ?>">
                            <h4><?php echo esc_html($group_name); ?></h4>
                        </th>
                    </tr>
                    <?php foreach ($capabilities as $cap_slug => $cap_name): ?>
                        <tr>
                            <td data-label="<?php _e('Capability', 'ecp'); ?>">
                                <strong><?php echo esc_html($cap_name); ?></strong>
                                <small>(<?php echo esc_html($cap_slug); ?>)</small>
                            </td>
                            <?php foreach ($editable_roles as $role_slug):
                                $role = get_role($role_slug);
                                // ** FIX: Add a check for a valid role object before proceeding. **
                                if ( ! $role ) {
                                    echo '<td>' . esc_html__('Role not found', 'ecp') . '</td>';
                                    continue;
                                }
                                $is_disabled = ($role_slug === 'ecp_client' && $cap_slug === 'read') || ($role_slug === 'administrator' && $cap_slug === 'promote_users');
                                $has_cap = !empty($role->capabilities[$cap_slug]);
                                $role_name = isset($wp_roles->roles[$role_slug]) ? $wp_roles->roles[$role_slug]['name'] : $role_slug;
                            ?>
                                <td data-label="<?php echo esc_html(translate_user_role($role_name)); ?>">
                                    <label class="ecp-checkbox-label">
                                        <input type="checkbox" name="role_caps[<?php echo esc_attr($role_slug); ?>][<?php echo esc_attr($cap_slug); ?>]" value="1" <?php checked($has_cap); ?> <?php disabled($is_disabled); ?>>
                                        <span class="screen-reader-text"><?php printf( 'Allow %s for %s', esc_html($cap_name), esc_html($role_slug) ); ?></span>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endforeach; ?>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php _e('Save Permissions', 'ecp'); ?></button>
        </p>
    </form>
</div>

