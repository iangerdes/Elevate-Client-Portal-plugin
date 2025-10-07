<?php
// File: elevate-client-portal/includes/helpers/class-ecp-permissions-helper.php
/**
 * A dedicated helper class for managing user capabilities and permissions checks.
 *
 * @package Elevate_Client_Portal
 * @version 71.0.0 (Final Audit)
 * @comment Final Audit: Corrected a logic error in `can_manage_user_files` to properly check if a manager is assigned to a specific client.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Permissions_Helper {

    /**
     * Checks if the current user has the required capability.
     * Sends a JSON error and dies if the check fails.
     *
     * @param string $capability The capability to check (e.g., 'edit_users').
     * @param string $error_message The message to send on failure.
     */
    public static function check_permission_or_die( $capability, $error_message = 'Permission Denied.' ) {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => __( $error_message, 'ecp' ) ] );
        }
    }

    /**
     * Checks if a user has permission to manage a specific user's files.
     *
     * @param int $target_user_id The ID of the user whose files are being accessed.
     * @return bool True if the current user has permission, false otherwise.
     */
    public static function can_manage_user_files( $target_user_id ) {
        // Site admins and users with the 'manage all' capability can always access.
        if ( current_user_can( 'ecp_manage_all_users_files' ) ) {
            return true;
        }

        // Users who can only manage their assigned users.
        if ( current_user_can( 'ecp_manage_user_files' ) ) {
            // Get the ID of the manager assigned to the target user.
            $manager_id = get_user_meta( $target_user_id, '_ecp_managed_by', true );
            
            // The current user has permission if they are the assigned manager.
            return (int) get_current_user_id() === (int) $manager_id;
        }

        return false;
    }
}
