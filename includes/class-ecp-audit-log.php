<?php
// File: includes/class-ecp-audit-log.php
/**
 * Handles all functionality for the plugin's audit trail.
 *
 * @package Elevate_Client_Portal
 * @version 8.1.0 (New Feature)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Audit_Log
 * Manages the creation of the log table and recording of events.
 */
class ECP_Audit_Log {

    private static $instance;
    const TABLE_NAME = 'ecp_audit_log';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Actions and filters can be added here if needed in the future.
    }

    /**
     * Gets the full name of the audit log table, including the WP prefix.
     * @return string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Creates the custom database table for the audit log on plugin activation.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            file_name varchar(255) NOT NULL,
            download_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            ip_address varchar(100) NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Adds a new entry to the audit log.
     *
     * @param int    $user_id   The ID of the user who performed the action.
     * @param string $file_name The name of the file involved.
     */
    public static function log_event( $user_id, $file_name ) {
        global $wpdb;
        $table_name = self::get_table_name();

        $wpdb->insert(
            $table_name,
            [
                'user_id'       => $user_id,
                'file_name'     => $file_name,
                'download_time' => current_time( 'mysql' ),
                'ip_address'    => self::get_user_ip(),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Retrieves log entries from the database with pagination.
     *
     * @param int $per_page The number of items to show per page.
     * @param int $current_page The current page number.
     * @return array An array containing the log entries and total count.
     */
    public static function get_logs( $per_page = 20, $current_page = 1 ) {
        global $wpdb;
        $table_name = self::get_table_name();
        $offset = ( $current_page - 1 ) * $per_page;

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY download_time DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A );

        return [
            'logs' => $logs,
            'total_items' => $total_items,
        ];
    }

    /**
     * Gets the current user's IP address.
     * @return string The user's IP address.
     */
    private static function get_user_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }
}
