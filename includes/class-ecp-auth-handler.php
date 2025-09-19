<?php
// File: includes/class-ecp-auth-handler.php
/**
 * Handles all authentication-related functionality for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 8.1.0 (Stable with Impersonation)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Auth_Handler
 * Manages user login, logout, redirection, and access control.
 */
class ECP_Auth_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'after_setup_theme', [ $this, 'ecp_remove_admin_bar' ] );
        add_filter( 'login_redirect', [ $this, 'client_login_redirect' ], 10, 3 );
        add_action( 'wp_authenticate_user', [ $this, 'check_if_user_is_disabled' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'force_custom_login_redirect' ] );
        add_action( 'init', [ $this, 'handle_custom_logout' ] );
    }

    /**
     * Removes the WordPress admin bar for non-admin users on the frontend.
     */
    public function ecp_remove_admin_bar() {
        if ( ! current_user_can('administrator') && ! is_admin() && ! ECP_Impersonation_Handler::is_impersonating() ) {
            show_admin_bar(false);
        }
    }

    /**
     * Redirects users to the appropriate dashboard after logging in.
     */
    public function client_login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            if ( !empty($request) && $request !== home_url('/') && $request !== admin_url() ) {
                if (wp_validate_redirect($request, home_url())) {
                    return $request;
                }
            }
            if ( in_array( 'ecp_business_admin', $user->roles ) || in_array( 'administrator', $user->roles ) || in_array( 'ecp_client_manager', $user->roles ) ) {
                return home_url('/admin-dashboard');
            }
            if ( in_array( 'ecp_client', $user->roles ) || in_array( 'scp_client', $user->roles ) ) {
                return home_url('/client-portal');
            }
        }
        return $redirect_to;
    }

    /**
     * Prevents disabled users from logging in.
     */
    public function check_if_user_is_disabled( $user, $password ) {
        if ( isset($user->ID) && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

    /**
     * Handles the custom logout action to redirect to the login page.
     */
    public function handle_custom_logout() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'ecp_logout' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ecp_logout_nonce' ) ) {
                wp_die( 'Security check failed.' );
            }
            wp_logout();
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }
    }

    /**
     * Forces unauthenticated users to the login page if they try to access a protected page.
     */
    public function force_custom_login_redirect() {
        if ( is_singular() && ! is_user_logged_in() ) {
            global $post;
            if ( ! is_a( $post, 'WP_Post' ) || empty( $post->post_content ) ) {
                return;
            }

            if ( has_shortcode( $post->post_content, 'client_portal' ) || has_shortcode( $post->post_content, 'elevate_admin_dashboard' ) ) {
                $login_page = get_page_by_path('login');
                if ( ! $login_page ) {
                    wp_die('Elevate Client Portal Error: The "login" page could not be found. Please ensure a page with the slug "login" exists and contains the [elevate_login] shortcode.');
                }
                $login_url = get_permalink( $login_page->ID );
                $redirect_url = add_query_arg( 'redirect_to', urlencode( get_permalink( $post->ID ) ), $login_url );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }
}

