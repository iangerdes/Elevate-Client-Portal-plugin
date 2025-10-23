<?php
// File: elevate-client-portal/includes/class-ecp-cron-handler.php
/**
 * Handles scheduled tasks (WP-Cron) for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 96.0.0 (New Feature)
 * @comment This new class manages the cleanup of expired temporary ZIP files.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Cron_Handler {

    const CRON_HOOK = 'ecp_hourly_cleanup_hook';
    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'cleanup_expired_zips' ] );
    }

    /**
     * Schedules the hourly cleanup event if it's not already scheduled.
     */
    public static function schedule_events() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Unschedules the cleanup event.
     */
    public static function unschedule_events() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * The main cleanup function executed by the cron job.
     */
    public function cleanup_expired_zips() {
        $users = get_users( [ 'fields' => 'ID' ] );
        $expiration_time = 24 * HOUR_IN_SECONDS;
        $upload_dir = wp_upload_dir();
        $tmp_dir = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';

        foreach ( $users as $user_id ) {
            $user_zips = get_user_meta( $user_id, '_ecp_ready_zips', true );
            if ( ! empty( $user_zips ) && is_array( $user_zips ) ) {
                $updated_zips = [];
                foreach ( $user_zips as $filename => $data ) {
                    if ( ( time() - $data['timestamp'] ) > $expiration_time ) {
                        // Expired, delete the file and don't add to updated list
                        $file_path = $tmp_dir . $filename;
                        if ( file_exists( $file_path ) ) {
                            wp_delete_file( $file_path );
                        }
                    } else {
                        // Not expired, keep it
                        $updated_zips[ $filename ] = $data;
                    }
                }
                update_user_meta( $user_id, '_ecp_ready_zips', $updated_zips );
            }
        }
    }
}

