<?php
/**
 * Renders the Add/Edit User form for the Admin Dashboard.
 * This view is included by class-ecp-admin-dashboard.php
 *
 * @package Elevate_Client_Portal
 * @version 5.2.6 (Patched)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the render_user_form() method
// in class-ecp-admin-dashboard.php:
// @var int    $user_id      The ID of the user being edited, or 0 for a new user.
// @var WP_User|null $user   The user object, or null for a new user.
// @var bool   $is_new_user  True if this is a new user form.
?>
<div class="ecp-dashboard-view ecp-form-view" id="ecp-user-form-view">
    <div class="ecp-header">
        <h2><?php echo $is_new_user ? __('Add New User', 'ecp') : __('Edit User:', 'ecp') . ' ' . esc_html($user->display_name); ?></h2>
        <button class="button ecp-back-to-users">&larr; <?php _e('Back to User List', 'ecp'); ?></button>
    </div>
    <form id="ecp-user-details-form" class="ecp-ajax-form" method="post">
        <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
        <input type="hidden" name="action" value="ecp_admin_dashboard_actions">
        <input type="hidden" name="sub_action" value="<?php echo $is_new_user ? 'add_user' : 'save_user'; ?>">
        <?php wp_nonce_field('ecp_ajax_nonce', 'nonce'); ?>
        <table class="form-table"><tbody>
            <tr><th scope="row"><label for="ecp_user_title"><?php _e('Title', 'ecp'); ?></label></th><td><select name="ecp_user_title" id="ecp_user_title" class="regular-text"><?php $titles=['Mr','Mrs','Miss','Ms','Dr']; $current_title = $user ? get_user_meta($user_id,'ecp_user_title',true) : ''; foreach($titles as $title){echo '<option value="'.esc_attr($title).'" '.selected($current_title,$title,false).'>'.esc_html($title).'</option>';}?></select></td></tr>
            <tr><th scope="row"><label for="ecp_user_firstname"><?php _e('First Name', 'ecp'); ?></label></th><td><input type="text" id="ecp_user_firstname" name="ecp_user_firstname" class="regular-text" value="<?php echo $user ? esc_attr($user->first_name) : ''; ?>" required /></td></tr>
            <tr><th scope="row"><label for="ecp_user_surname"><?php _e('Surname', 'ecp'); ?></label></th><td><input type="text" id="ecp_user_surname" name="ecp_user_surname" class="regular-text" value="<?php echo $user ? esc_attr($user->last_name) : ''; ?>" required /></td></tr>
            
            <?php // ** FIX STARTS HERE: Allow email to be edited ** ?>
            <?php // The email field is now editable for existing users, but only for admins with the 'promote_users' capability. ?>
            <tr>
                <th scope="row"><label for="ecp_user_email"><?php _e('Email', 'ecp'); ?></label></th>
                <td><input type="email" id="ecp_user_email" name="ecp_user_email" class="regular-text" value="<?php echo $user ? esc_attr($user->user_email) : ''; ?>" <?php if ($is_new_user) echo 'required'; ?> <?php if (!$is_new_user && !current_user_can('promote_users')) echo 'disabled'; ?> />
                <?php if (!$is_new_user && !current_user_can('promote_users')): ?>
                    <p class="description"><?php _e('Only Administrators can change a user\'s email address.', 'ecp'); ?></p>
                <?php endif; ?>
                </td>
            </tr>
            <?php // ** FIX ENDS HERE ** ?>
            
            <?php 
            $current_user_roles = wp_get_current_user()->roles;
            if ( in_array('administrator', $current_user_roles) || in_array('ecp_business_admin', $current_user_roles) ): 
            ?>
                <tr><th scope="row"><label for="ecp_user_role"><?php _e('Role', 'ecp'); ?></label></th><td>
                    <select name="ecp_user_role" id="ecp_user_role">
                        <option value="ecp_client" <?php if($user) selected('ecp_client', $user->roles[0]); ?>><?php _e('Client', 'ecp'); ?></option>
                        <option value="ecp_client_manager" <?php if($user) selected('ecp_client_manager', $user->roles[0]); ?>><?php _e('Client Manager', 'ecp'); ?></option>
                        <option value="ecp_business_admin" <?php if($user) selected('ecp_business_admin', $user->roles[0]); ?>><?php _e('Business Admin', 'ecp'); ?></option>
                    </select>
                </td></tr>
                <tr class="ecp-business-admin-field" style="display:none;"><th scope="row"><label for="ecp_user_limit"><?php _e('Client Limit', 'ecp'); ?></label></th><td>
                    <input type="number" name="ecp_user_limit" id="ecp_user_limit" value="<?php echo $user ? esc_attr(get_user_meta($user_id, '_ecp_user_limit', true)) : '0'; ?>" class="small-text" />
                    <p class="description"><?php _e('The number of clients this Business Admin can create. 0 for unlimited.', 'ecp'); ?></p>
                </td></tr>
                <?php
                $is_client = $user && (in_array('ecp_client', $user->roles) || in_array('scp_client', $user->roles));
                if ( $is_client ):
                    $managers = get_users(['role__in' => ['administrator', 'ecp_business_admin', 'ecp_client_manager'], 'orderby' => 'display_name', 'order' => 'ASC']);
                    $current_manager_id = get_user_meta($user_id, '_ecp_managed_by', true) ?: get_user_meta($user_id, '_ecp_created_by', true);
                ?>
                    <tr class="ecp-client-field"><th scope="row"><label for="ecp_managed_by"><?php _e('Managed By', 'ecp'); ?></label></th><td>
                        <select name="ecp_managed_by" id="ecp_managed_by">
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo esc_attr($manager->ID); ?>" <?php selected($current_manager_id, $manager->ID); ?>>
                                    <?php echo esc_html($manager->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Assign a Business Admin or Administrator to manage this client.', 'ecp'); ?></p>
                    </td></tr>
                <?php endif; ?>
            <?php endif; ?>

            <tr><th scope="row"><label for="ecp_user_password"><?php _e('Password', 'ecp'); ?></label></th><td><input type="password" name="ecp_user_password" id="ecp_user_password" class="regular-text" placeholder="<?php echo $is_new_user ? __('Auto-generated if blank', 'ecp') : __('Leave blank to keep current', 'ecp'); ?>" autocomplete="new-password" /></td></tr>
            <tr><th scope="row"><label for="ecp_user_address"><?php _e('Address', 'ecp'); ?></label></th><td><textarea name="ecp_user_address" id="ecp_user_address" class="large-text" rows="3"><?php echo $user ? esc_textarea(get_user_meta($user_id, 'ecp_user_address', true)) : ''; ?></textarea></td></tr>
            <tr><th scope="row"><label for="ecp_user_mobile"><?php _e('Mobile', 'ecp'); ?></label></th><td><input type="tel" name="ecp_user_mobile" id="ecp_user_mobile" class="regular-text" value="<?php echo $user ? esc_attr(get_user_meta($user_id, 'ecp_user_mobile', true)) : ''; ?>" /></td></tr>
            <?php if($is_new_user): ?>
                <tr><th scope="row"><?php _e('Notifications', 'ecp'); ?></th><td><fieldset><label><input type="checkbox" name="ecp_send_notification" value="1" checked /> <?php _e('Send login credentials to new user', 'ecp'); ?></label></fieldset></td></tr>
            <?php endif; ?>
        </tbody></table>
        <p class="submit"><button type="submit" class="button button-primary"><?php echo $is_new_user ? __('Add User', 'ecp') : __('Save Changes', 'ecp'); ?></button></p>
    </form>
</div>
<script>
    jQuery(document).ready(function($) {
        function toggleFields() {
            var selectedRole = $('#ecp_user_role').val();
            if ( selectedRole === 'ecp_business_admin' ) {
                $('.ecp-business-admin-field').show();
                $('.ecp-client-field').hide();
            } else if ( selectedRole === 'ecp_client' || selectedRole === 'scp_client' ) {
                 $('.ecp-business-admin-field').hide();
                $('.ecp-client-field').show();
            } else {
                $('.ecp-business-admin-field').hide();
                $('.ecp-client-field').hide();
            }
        }
        toggleFields();
        $('#ecp_user_role').on('change', toggleFields);
    });
</script>
