<?php
// File: includes/class-ecp-shortcodes.php
/**
 * Registers miscellaneous shortcodes for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 111.0.0 (AJAX Logout Fix)
 * @comment Re-engineered the logout button to use AJAX, preventing "Security check failed" errors caused by aggressive page caching.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Shortcodes
 * Provides shortcodes for dynamic buttons like Login/Logout.
 */
class ECP_Shortcodes {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'ecp_loginout_button', [ $this, 'render_loginout_button' ] );
        add_shortcode( 'ecp_adminclient_button', [ $this, 'render_adminclient_button' ] );
    }

    /**
     * Renders a login or logout button depending on the user's status.
     *
     * @return string The HTML for the button.
     */
    public function render_loginout_button() {
        if ( is_user_logged_in() ) {
            // ** FIX: Use a class for an AJAX-triggered logout instead of a direct link. **
            return '<a href="#" class="button ecp-ajax-logout-btn">'. __( 'Logout', 'ecp' ) . '</a>';
        } else {
            $login_page = get_page_by_path('login');
            if ($login_page) {
                return '<a href="' . esc_url( get_permalink($login_page->ID) ) . '" class="button">' . __( 'Login', 'ecp' ) . '</a>';
            }
            return '';
        }
    }

    /**
     * Renders a button that links to the appropriate dashboard (Admin or Client).
     *
     * @return string The HTML for the button.
     */
    public function render_adminclient_button() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $admin_roles = ['administrator', 'ecp_business_admin', 'ecp_client_manager'];
            $client_roles = ['ecp_client', 'scp_client'];

            if ( count( array_intersect( $admin_roles, (array) $user->roles ) ) > 0 ) {
                $dashboard_page = get_page_by_path('admin-dashboard');
                if ($dashboard_page) {
                    return '<a href="' . esc_url( get_permalink($dashboard_page->ID) ) . '" class="button">'. __( 'Admin Dashboard', 'ecp' ) . '</a>';
                }
            } elseif ( count( array_intersect( $client_roles, (array) $user->roles ) ) > 0 ) {
                 $portal_page = get_page_by_path('client-portal');
                 if ($portal_page) {
                    return '<a href="' . esc_url( get_permalink($portal_page->ID) ) . '" class="button">'. __( 'Client Portal', 'ecp' ) . '</a>';
                 }
            }
        }
        return '';
    }
}

