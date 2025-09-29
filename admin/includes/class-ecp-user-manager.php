<?php
// File: elevate-client-portal/admin/includes/class-ecp-user-manager.php
/**
 * Handles the server-side logic for all user management operations.
 *
 * @package Elevate_Client_Portal
 * @version 127.5.0 (Message Client Fix)
 * @comment Renamed message_client_logic to send_client_message for clarity and consistency.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_User_Manager
 *
 * Provides static methods for creating, updating, deleting, and retrieving users.
 */
class ECP_User_Manager {

    /**
     * Registers the custom user roles for the plugin and ensures Administrators have all capabilities.
     * This function is safe to call multiple times as add_role() and add_cap() check for existence.
     */
    public static function register_custom_roles() {
        // Business Admin Role
        add_role('ecp_business_admin', __('Business Admin', 'ecp'), [
            'read' => true,
            'create_users' => true,
            'edit_users' => true,
            'delete_users' => true,
            'promote_users' => true,
            'ecp_manage_user_files' => true,
            'ecp_manage_all_users_files' => true,
            'ecp_view_file_summary' => true,
        ]);

        // Client Manager Role
        add_role('ecp_client_manager', __('Client Manager', 'ecp'), [
            'read' => true,
            'create_users' => true,
            'edit_users' => true,
            'ecp_manage_user_files' => true,
        ]);

        // Client Role
        add_role('ecp_client', __('Client', 'ecp'), [
            'read' => true,
        ]);

        $admin_role = get_role('administrator');
        if ($admin_role) {
            $all_caps = self::get_ecp_capabilities();
            foreach ($all_caps as $group) {
                foreach ($group as $cap_slug => $cap_name) {
                    $admin_role->add_cap($cap_slug);
                }
            }
        }
    }

    /**
     * Retrieves a list of client and manager users for the admin dashboard, respecting the current user's role.
     */
    public static function get_client_users( $search_term = '' ) {
        $args = ['orderby' => 'display_name', 'order'   => 'ASC', 'number'  => -1];
        $current_user_id = get_current_user_id();
        $current_user = get_userdata($current_user_id);

        if ( in_array('administrator', $current_user->roles) ) {
            $args['role__in'] = ['ecp_client', 'scp_client', 'ecp_client_manager', 'ecp_business_admin', 'administrator'];
        } elseif ( in_array('ecp_business_admin', $current_user->roles) ) {
            $args['role__in'] = ['ecp_client', 'scp_client', 'ecp_client_manager', 'ecp_business_admin'];
        } elseif ( in_array('ecp_client_manager', $current_user->roles) ) {
            $args['role__in'] = ['ecp_client', 'scp_client'];
            $args['meta_query'] = [
                [
                    'key' => '_ecp_managed_by',
                    'value' => $current_user_id,
                    'compare' => '='
                ]
            ];
        } else {
            return [];
        }

        if (!empty($search_term)) {
            $args['search'] = '*' . esc_attr($search_term) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        return get_users($args);
    }
    
    /**
     * Generates a display string indicating who manages a user.
     */
    public static function get_manager_display_string( $user ) {
        $managed_by_str = '';
        if ( in_array('ecp_business_admin', $user->roles) ) {
            $limit = get_user_meta($user->ID, '_ecp_user_limit', true);
            $count = count(get_users(['meta_key' => '_ecp_managed_by', 'meta_value' => $user->ID]));
            $managed_by_str = sprintf('Manages %d / %s users', $count, ($limit ? esc_html($limit) : '&#8734;'));
        } else {
            $manager_id = get_user_meta( $user->ID, '_ecp_managed_by', true ) ?: get_user_meta( $user->ID, '_ecp_created_by', true );
            if ( $manager_id && ($manager = get_userdata($manager_id)) ) { 
                $managed_by_str = esc_html($manager->display_name); 
            }
        }
        return $managed_by_str;
    }

    /**
     * Handles the creation of a new client user.
     */
    public static function add_client_user( $data ) {
        $email = sanitize_email( $data['ecp_user_email'] );
        if ( ! is_email( $email ) ) { return ['success' => false, 'message' => __( 'Invalid email address.', 'ecp' )]; }
        if ( email_exists( $email ) ) { return ['success' => false, 'message' => __( 'This email is already registered.', 'ecp' )]; }
        
        $password = ! empty( $data['ecp_user_password'] ) ? $data['ecp_user_password'] : wp_generate_password();
        $user_id = wp_create_user( $email, $password, $email );
        
        if ( is_wp_error( $user_id ) ) { return ['success' => false, 'message' => $user_id->get_error_message()]; }
        
        $creator_id = get_current_user_id();
        $role = sanitize_text_field($data['ecp_user_role'] ?? 'ecp_client');

        wp_update_user([
            'ID' => $user_id, 
            'first_name' => sanitize_text_field( $data['ecp_user_firstname'] ),
            'last_name' => sanitize_text_field( $data['ecp_user_surname'] ),
            'display_name' => sanitize_text_field( $data['ecp_user_firstname'] ) . ' ' . sanitize_text_field( $data['ecp_user_surname'] ),
            'role' => $role,
        ]);

        update_user_meta( $user_id, '_ecp_created_by', $creator_id );
        update_user_meta($user_id, 'ecp_user_title', sanitize_text_field($data['ecp_user_title']));
        update_user_meta($user_id, 'ecp_user_address', sanitize_textarea_field($data['ecp_user_address']));
        update_user_meta($user_id, 'ecp_user_mobile', sanitize_text_field($data['ecp_user_mobile']));
        
        if ($role === 'ecp_business_admin' && isset($data['ecp_user_limit'])) {
            update_user_meta($user_id, '_ecp_user_limit', intval($data['ecp_user_limit']));
        }
        if ($role === 'ecp_client' || $role === 'scp_client') {
            $manager_id = isset($data['ecp_managed_by']) ? intval($data['ecp_managed_by']) : $creator_id;
            update_user_meta($user_id, '_ecp_managed_by', $manager_id);
        }
        
        if ( ! empty( $data['ecp_send_notification'] ) ) {
            wp_send_new_user_notifications( $user_id, 'both' );
            return ['success' => true, 'message' => __( 'User created and notification sent.', 'ecp' )];
        }
        
        return ['success' => true, 'message' => __( 'User created successfully! Password: ', 'ecp' ) . '<strong>' . esc_html( $password ) . '</strong>'];
    }

    /**
     * Handles updating an existing client user.
     */
    public static function update_client_user( $data ) {
         if ( empty($data['user_id']) ) {
            return ['success' => false, 'message' => __( 'Invalid request.', 'ecp' )];
        }
        $user_id = intval($data['user_id']);
        if (!current_user_can('edit_user', $user_id)) {
            return ['success' => false, 'message' => __('You do not have permission to edit this user.', 'ecp')];
        }

        $user_data = [ 'ID' => $user_id ];

        if (isset($data['ecp_user_firstname'])) $user_data['first_name'] = sanitize_text_field($data['ecp_user_firstname']);
        if (isset($data['ecp_user_surname'])) $user_data['last_name'] = sanitize_text_field($data['ecp_user_surname']);
        if (isset($user_data['first_name']) && isset($user_data['last_name'])) {
            $user_data['display_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
        }

        if (isset($data['ecp_user_email']) && current_user_can('promote_users')) {
            $new_email = sanitize_email($data['ecp_user_email']);
            if (!is_email($new_email)) return ['success' => false, 'message' => __('The new email address is not valid.', 'ecp')];
            if (email_exists($new_email) && email_exists($new_email) != $user_id) return ['success' => false, 'message' => __('That email address is already in use.', 'ecp')];
            $user_data['user_email'] = $new_email;
        }

        if (isset($data['ecp_user_role'])) $user_data['role'] = sanitize_text_field($data['ecp_user_role']);

        $result = wp_update_user($user_data);
        if (is_wp_error($result)) return ['success' => false, 'message' => $result->get_error_message()];

        if (isset($data['ecp_user_title'])) update_user_meta($user_id, 'ecp_user_title', sanitize_text_field($data['ecp_user_title']));
        if (isset($data['ecp_user_address'])) update_user_meta($user_id, 'ecp_user_address', sanitize_textarea_field($data['ecp_user_address']));
        if (isset($data['ecp_user_mobile'])) update_user_meta($user_id, 'ecp_user_mobile', sanitize_text_field($data['ecp_user_mobile']));

        if (isset($data['ecp_user_role'])) {
            if ($data['ecp_user_role'] === 'ecp_business_admin' && isset($data['ecp_user_limit'])) {
                update_user_meta($user_id, '_ecp_user_limit', intval($data['ecp_user_limit']));
            }
            if (($data['ecp_user_role'] === 'ecp_client' || $data['ecp_user_role'] === 'scp_client') && isset($data['ecp_managed_by'])) {
                update_user_meta($user_id, '_ecp_managed_by', intval($data['ecp_managed_by']));
            }
        }
        
        if ( ! empty( $data['ecp_user_password'] ) ) {
            if ( ! isset( $data['ecp_user_password_confirm'] ) || $data['ecp_user_password'] !== $data['ecp_user_password_confirm'] ) {
                return ['success' => false, 'message' => __( 'The new passwords do not match.', 'ecp' )];
            }
            wp_set_password( $data['ecp_user_password'], $user_id );
        }

        return ['success' => true, 'message' => __( 'User updated successfully.', 'ecp' )];
    }
    
    /**
     * Toggles a user's status between enabled and disabled.
     */
    public static function toggle_user_status_logic( $user_id, $enable = true ) {
        if ( get_current_user_id() == $user_id ) {
            return ['success' => false, 'message' => __( "You can't disable your own account.", 'ecp' )];
        }
        if ( $enable ) {
            delete_user_meta( $user_id, 'ecp_user_disabled' );
            return ['success' => true, 'message' => __( 'User has been enabled.', 'ecp' )];
        } else {
            update_user_meta( $user_id, 'ecp_user_disabled', true );
            return ['success' => true, 'message' => __( 'User has been disabled.', 'ecp' )];
        }
    }

    /**
     * Permanently removes a user and their associated files.
     */
    public static function remove_user_logic( $user_id ) {
        if ( ! current_user_can('delete_users') ) { return ['success' => false, 'message' => __( 'Permission denied.', 'ecp' )]; }
        if ( get_current_user_id() == $user_id ) { return ['success' => false, 'message' => __( "You can't remove your own account.", 'ecp' )]; }
        
        $files = get_user_meta( $user_id, '_ecp_client_file', false );
        if ( ! empty($files) ) {
            foreach ( $files as $file ) {
                if ( !empty($file['s3_key']) ) {
                    ECP_S3::get_instance()->delete_file($file['s3_key']);
                }
                if ( !empty($file['path']) && file_exists($file['path'])) {
                    wp_delete_file($file['path']); 
                }
            }
        }
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        wp_delete_user( $user_id );
        return ['success' => true, 'message' => __( 'User and their files have been removed.', 'ecp' )];
    }

    /**
     * Handles sending a direct message from an admin to a client.
     */
    public static function send_client_message( $data ) {
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
        $subject = isset($data['subject']) ? sanitize_text_field($data['subject']) : '';
        $message = isset($data['message']) ? sanitize_textarea_field($data['message']) : '';

        if (!$user_id || empty($subject) || empty($message)) {
            return ['success' => false, 'message' => __('Please fill out all fields.', 'ecp')];
        }

        $client_user = get_userdata($user_id);
        if (!$client_user) {
            return ['success' => false, 'message' => __('Client not found.', 'ecp')];
        }

        $admin_user = wp_get_current_user();
        
        $to = $client_user->user_email;
        $email_subject = sprintf(__('[%s] Message from your Account Manager: %s', 'ecp'), get_bloginfo('name'), $subject);
        $body = "Hello " . $client_user->first_name . ",\n\n" .
                "You have received a new message from your account manager, " . $admin_user->display_name . ".\n\n" .
                "Message:\n\n" . $message . "\n\n" .
                "You can reply directly to this email to respond.";

        $headers = [ 'Reply-To: ' . $admin_user->display_name . ' <' . $admin_user->user_email . '>' ];

        if ( wp_mail( $to, $email_subject, $body, $headers ) ) {
            return ['success' => true, 'message' => __('Your message has been sent successfully.', 'ecp')];
        } else {
            return ['success' => false, 'message' => __('There was an error sending your message. Please check your server\'s email configuration.', 'ecp')];
        }
    }

    /**
     * Gets the list of custom capabilities for the plugin.
     */
    public static function get_ecp_capabilities() {
        return [
            'Core Access' => [
                'read' => __('View Client Portal (Core)', 'ecp'),
            ],
            'User Management' => [
                'create_users' => __('Add New Users', 'ecp'),
                'edit_users'   => __('Edit Users & Their Profile', 'ecp'),
                'delete_users' => __('Delete Users', 'ecp'),
                'promote_users' => __('Change User Roles & Permissions', 'ecp'),
            ],
            'File Management' => [
                'ecp_manage_user_files' => __('Manage Files for Assigned Users', 'ecp'),
                'ecp_manage_all_users_files' => __('Manage Files for ALL Users', 'ecp'),
                'ecp_view_file_summary' => __('View Global File Summary', 'ecp'),
            ]
        ];
    }

    /**
     * Saves the role permissions from the settings page.
     */
    public static function save_role_permissions_logic($data) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return ['success' => false, 'message' => __('You do not have permission to manage role capabilities.', 'ecp')];
        }
        
        if ( !isset($data['role_caps']) || !is_array($data['role_caps']) ) {
            return ['success' => false, 'message' => __('Invalid data sent.', 'ecp')];
        }
        
        $editable_roles = ['ecp_business_admin', 'ecp_client_manager', 'ecp_client'];
        $all_caps = self::get_ecp_capabilities();
        
        foreach ($editable_roles as $role_slug) {
            $role = get_role($role_slug);
            if (!$role) continue;

            $new_caps_for_role = $data['role_caps'][$role_slug] ?? [];

            foreach ($all_caps as $group) {
                foreach ($group as $cap_slug => $cap_name) {
                    if ($role_slug === 'ecp_client' && $cap_slug === 'read') {
                        $role->add_cap('read');
                        continue;
                    }

                    if (isset($new_caps_for_role[$cap_slug])) {
                        $role->add_cap($cap_slug);
                    } else {
                        $role->remove_cap($cap_slug);
                    }
                }
            }
        }
        
        return ['success' => true, 'message' => __('Permissions updated successfully.', 'ecp')];
    }
}

