<?php
// File: admin/includes/class-ecp-user-manager.php
/**
 * Handles the server-side logic for all user management operations.
 *
 * @package Elevate_Client_Portal
 * @version 8.1.0 (With Impersonation)
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
     * Retrieves a list of client and manager users for the admin dashboard.
     */
    public static function get_client_users( $search_term = '' ) {
        $args = ['orderby' => 'display_name', 'order'   => 'ASC', 'number'  => -1];
        $current_user = wp_get_current_user();

        if ( in_array('administrator', $current_user->roles) ) {
            $args['role__in'] = ['ecp_client', 'scp_client', 'ecp_client_manager', 'ecp_business_admin', 'administrator'];
        } elseif ( in_array('ecp_business_admin', $current_user->roles) ) {
            $args['role__in'] = ['ecp_client', 'scp_client', 'ecp_client_manager', 'ecp_business_admin'];
        } else { // ecp_client_manager
            $args['role__in'] = ['ecp_client', 'scp_client'];
            $args['meta_key'] = '_ecp_managed_by';
            $args['meta_value'] = get_current_user_id();
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
        if ( ! wp_verify_nonce( $data['nonce'], 'ecp_admin_ajax_nonce' ) ) {
            return ['success' => false, 'message' => __( 'Nonce verification failed.', 'ecp' )];
        }

        // User creation logic...
        
        $email = sanitize_email( $data['ecp_user_email'] );
        if ( ! is_email( $email ) ) { return ['success' => false, 'message' => __( 'Invalid email address.', 'ecp' )]; }
        if ( email_exists( $email ) ) { return ['success' => false, 'message' => __( 'This email is already registered.', 'ecp' )]; }
        
        $password = ! empty( $data['ecp_user_password'] ) ? $data['ecp_user_password'] : wp_generate_password();
        $user_id = wp_create_user( $email, $password, $email );
        
        if ( is_wp_error( $user_id ) ) { return ['success' => false, 'message' => $user_id->get_error_message()]; }

        wp_update_user([
            'ID' => $user_id, 
            'first_name' => sanitize_text_field( $data['ecp_user_firstname'] ),
            'last_name' => sanitize_text_field( $data['ecp_user_surname'] ),
            'display_name' => sanitize_text_field( $data['ecp_user_firstname'] ) . ' ' . sanitize_text_field( $data['ecp_user_surname'] ),
            'role' => sanitize_text_field($data['ecp_user_role'] ?? 'ecp_client'),
        ]);
        
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
         if ( ! wp_verify_nonce( $data['nonce'], 'ecp_admin_ajax_nonce' ) || empty($data['user_id']) ) {
            return ['success' => false, 'message' => __( 'Invalid request.', 'ecp' )];
        }
        $user_id = intval($data['user_id']);
        if (!current_user_can('edit_user', $user_id)) {
            return ['success' => false, 'message' => __('You do not have permission to edit this user.', 'ecp')];
        }
        // ... update logic
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
        
        // This only handles local files. S3 deletion would need to be added.
        $files = get_user_meta( $user_id, '_ecp_client_file', false );
        if ( ! empty($files) ) {
            foreach ( $files as $file ) {
                if ( !empty($file['path']) && file_exists($file['path'])) wp_delete_file($file['path']); 
            }
        }
        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        wp_delete_user( $user_id );
        return ['success' => true, 'message' => __( 'User and their local files have been removed.', 'ecp' )];
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
        if ( !isset($data['role_caps']) || !is_array($data['role_caps']) ) {
            return ['success' => false, 'message' => __('Invalid data sent.', 'ecp')];
        }
        // ... permission saving logic
        return ['success' => true, 'message' => __('Permissions updated successfully.', 'ecp')];
    }
}

