<?php
/**
 * Renders the My Account page for the Client Portal.
 * This view is included by class-ecp-client-portal.php
 *
 * @package Elevate_Client_Portal
 * @version 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// @var WP_User $user The current user object.
?>
<div class="ecp-portal-wrapper ecp-account-page">
    <div class="ecp-header">
        <h3><?php _e('My Account', 'ecp'); ?></h3>
        <a href="<?php echo esc_url(home_url('/client-portal')); ?>" class="button">&larr; <?php _e('Back to Files', 'ecp'); ?></a>
    </div>

    <div id="ecp-account-messages"></div>

    <div class="ecp-account-form-container">
        <form id="ecp-account-details-form" class="ecp-ajax-form" method="post">
            <h4><?php _e('Personal Details', 'ecp'); ?></h4>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="ecp_user_title"><?php _e('Title', 'ecp'); ?></label></th>
                        <td>
                            <select name="ecp_user_title" id="ecp_user_title" class="regular-text">
                                <?php 
                                $titles = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr']; 
                                $current_title = get_user_meta($user->ID, 'ecp_user_title', true);
                                foreach($titles as $title) {
                                    echo '<option value="' . esc_attr($title) . '" ' . selected($current_title, $title, false) . '>' . esc_html($title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ecp_user_firstname"><?php _e('First Name', 'ecp'); ?></label></th>
                        <td><input type="text" id="ecp_user_firstname" name="ecp_user_firstname" class="regular-text" value="<?php echo esc_attr($user->first_name); ?>" required /></td>
                    </tr>
                    <tr>
                        <th><label for="ecp_user_surname"><?php _e('Surname', 'ecp'); ?></label></th>
                        <td><input type="text" id="ecp_user_surname" name="ecp_user_surname" class="regular-text" value="<?php echo esc_attr($user->last_name); ?>" required /></td>
                    </tr>
                     <tr>
                        <th><label for="ecp_user_mobile"><?php _e('Mobile', 'ecp'); ?></label></th>
                        <td><input type="tel" name="ecp_user_mobile" id="ecp_user_mobile" class="regular-text" value="<?php echo esc_attr(get_user_meta($user->ID, 'ecp_user_mobile', true)); ?>" /></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php _e('Change Email', 'ecp'); ?></h4>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="ecp_user_email"><?php _e('Email Address', 'ecp'); ?></label></th>
                        <td><input type="email" id="ecp_user_email" name="ecp_user_email" class="regular-text" value="<?php echo esc_attr($user->user_email); ?>" required /></td>
                    </tr>
                </tbody>
            </table>

            <h4><?php _e('Change Password', 'ecp'); ?></h4>
             <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="ecp_user_password"><?php _e('New Password', 'ecp'); ?></label></th>
                        <td><input type="password" name="ecp_user_password" id="ecp_user_password" class="regular-text" placeholder="<?php _e('Leave blank to keep current', 'ecp'); ?>" autocomplete="new-password" /></td>
                    </tr>
                     <tr>
                        <th><label for="ecp_user_password_confirm"><?php _e('Confirm New Password', 'ecp'); ?></label></th>
                        <td><input type="password" name="ecp_user_password_confirm" id="ecp_user_password_confirm" class="regular-text" autocomplete="new-password" /></td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <input type="hidden" name="action" value="ecp_update_account">
                <?php wp_nonce_field('ecp_update_account_nonce', 'nonce'); ?>
                <button type="submit" class="button button-primary"><?php _e('Save Changes', 'ecp'); ?></button>
            </p>
        </form>
    </div>
</div>
