<?php
// File: admin/includes/class-ecp-impersonation-handler.php
/**
 * Handles the "Login as User" functionality for administrators.
 *
 * @package Elevate_Client_Portal
 * @version 8.1.0 (New Feature)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Impersonation_Handler
 * Manages the process of an admin logging in as another user.
 */
class ECP_Impersonation_Handler {

    private static $instance;
    const COOKIE_NAME = 'ecp_impersonator';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'handle_impersonation_request' ] );
        add_action( 'wp_footer', [ $this, 'display_impersonation_notice' ] );
        add_action( 'admin_footer', [ $this, 'display_impersonation_notice' ] );
    }

    /**
     * Checks if an admin is currently impersonating a user.
     *
     * @return bool True if impersonating, false otherwise.
     */
    public static function is_impersonating() {
        return isset( $_COOKIE[ self::COOKIE_NAME ] );
    }

    /**
     * Handles the start and stop of an impersonation session.
     */
    public function handle_impersonation_request() {
        if ( isset( $_GET['ecp_action'] ) ) {
            if ( $_GET['ecp_action'] === 'impersonate_user' && isset( $_GET['_wpnonce'] ) ) {
                $this->start_impersonation();
            }
            if ( $_GET['ecp_action'] === 'stop_impersonating' && isset( $_GET['_wpnonce'] ) ) {
                $this->stop_impersonation();
            }
        }
    }

    /**
     * Starts the impersonation session.
     */
    private function start_impersonation() {
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ecp_impersonate_nonce' ) ) {
            wp_die('Security check failed.');
        }

        $admin_id = get_current_user_id();
        if ( ! current_user_can( 'edit_users' ) ) {
            wp_die('You do not have permission to do this.');
        }

        $user_to_impersonate = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
        if ( ! $user_to_impersonate ) {
            wp_die('No user specified.');
        }

        // Set a cookie to remember the admin. Expires in 1 hour.
        setcookie( self::COOKIE_NAME, $admin_id, time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
        
        wp_set_current_user( $user_to_impersonate );
        wp_set_auth_cookie( $user_to_impersonate );

        wp_redirect( home_url() );
        exit;
    }

    /**
     * Stops the impersonation session and logs the admin back in.
     */
    private function stop_impersonation() {
        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ecp_stop_impersonate_nonce' ) ) {
            wp_die('Security check failed.');
        }

        if ( ! self::is_impersonating() ) {
            wp_die('No active impersonation session.');
        }

        $admin_id = $_COOKIE[ self::COOKIE_NAME ];
        
        // Clear the impersonation cookie.
        setcookie( self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
        
        wp_set_current_user( $admin_id );
        wp_set_auth_cookie( $admin_id );

        wp_redirect( admin_url() );
        exit;
    }

    /**
     * Displays a notice bar when an admin is impersonating a user.
     */
    public function display_impersonation_notice() {
        if ( self::is_impersonating() ) {
            $admin_id = $_COOKIE[ self::COOKIE_NAME ];
            $admin_user = get_userdata( $admin_id );
            $logout_url = wp_nonce_url( add_query_arg( 'ecp_action', 'stop_impersonating' ), 'ecp_stop_impersonate_nonce' );
            ?>
            <div style="position: fixed; bottom: 0; left: 0; width: 100%; background: #d9534f; color: white; padding: 10px; text-align: center; z-index: 99999; font-size: 16px;">
                You are currently logged in as another user. 
                <a href="<?php echo esc_url( $logout_url ); ?>" style="color: white; font-weight: bold; text-decoration: underline;">Return to your admin account (<?php echo esc_html( $admin_user->user_login ); ?>)</a>
            </div>
            <?php
        }
    }
}
