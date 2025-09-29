<?php
// File: elevate-client-portal/includes/class-ecp-auth-handler.php
/**
 * Handles all authentication-related functionality for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 114.0.0 (Capability-Based Redirect)
 * @comment Re-engineered the post-login redirect to use user capabilities instead of role names. This is a more robust and flexible method that correctly redirects all custom roles, fixing the issue where non-admins were sent back to the login page.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Auth_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // ** REMOVED: Admin bar logic has been moved to the main plugin file to ensure it loads early. **
        add_action( 'template_redirect', [ $this, 'prevent_plugin_page_caching' ] );
        add_filter( 'wp_mail_from', [ $this, 'set_mail_from_address' ] );
        add_filter( 'wp_mail_from_name', [ $this, 'set_mail_from_name' ] );
        add_filter( 'retrieve_password_message', [ $this, 'custom_password_reset_message' ], 10, 4 );
    }

    /**
     * ** REMOVED: This function is now in the main plugin file. **
     */

    /**
     * ** REMOVED: This function is now in the main plugin file. **
     */
    
    public function prevent_plugin_page_caching() {
        $shortcodes_to_check = ['elevate_login', 'client_portal', 'elevate_admin_dashboard'];
        $found_shortcode = false;
        foreach ($shortcodes_to_check as $shortcode) {
            if ( ECP_Shortcode_Helper::page_has_shortcode($shortcode) ) {
                $found_shortcode = true;
                break;
            }
        }
        if ( $found_shortcode ) {
            if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
            nocache_headers();
        }
    }

    public function set_mail_from_address( $original_email_address ) {
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        if (substr($domain, 0, 4) == 'www.') {
            $domain = substr($domain, 4);
        }
        return 'noreply@' . $domain;
    }

    public function set_mail_from_name( $original_email_from ) {
        return get_bloginfo( 'name' );
    }

    public function custom_password_reset_message( $message, $key, $user_login, $user_data ) {
        $login_page = get_page_by_path( 'login' );
        if ( ! $login_page ) {
            return $message;
        }
        $login_page_url = get_permalink( $login_page->ID );
        $reset_url = add_query_arg( [
            'action' => 'rp',
            'key'    => $key,
            'login'  => rawurlencode( $user_login ),
        ], $login_page_url );
        $message = preg_replace( '/<(.+?)>/', '<' . esc_url_raw( $reset_url ) . '>', $message );
        return $message;
    }
}


