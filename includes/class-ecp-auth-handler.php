<?php
// File: elevate-client-portal/includes/class-ecp-auth-handler.php
/**
 * Handles all authentication-related functionality for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 43.1.0 (Login Form Fix)
 * @comment Added an explicit login form processor to robustly handle submissions from the [elevate_login] shortcode, fixing cases where login would fail.
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
        add_action( 'init', [ $this, 'process_custom_login' ] ); // Handle the form submission
        add_action( 'after_setup_theme', [ $this, 'remove_admin_bar' ] );
        add_filter( 'login_redirect', [ $this, 'login_redirect' ], 10, 3 );
        add_filter( 'wp_authenticate_user', [ $this, 'check_if_user_is_disabled' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'force_login_redirect' ] );
        add_action( 'init', [ $this, 'handle_custom_logout' ] );
    }

    /**
     * Processes the custom login form submission from the [elevate_login] shortcode.
     */
    public function process_custom_login() {
        if ( isset( $_POST['elevate_login_form'] ) && isset( $_POST['ecp_login_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['ecp_login_nonce'], 'ecp-login-nonce-action' ) ) {
                wp_die( 'Security check failed.' );
            }

            $creds = [
                'user_login'    => isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ) ) : '',
                'user_password' => isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '',
                'remember'      => isset( $_POST['rememberme'] ),
            ];

            $user = wp_signon( $creds, is_ssl() );

            if ( is_wp_error( $user ) ) {
                $login_page = get_page_by_path('login');
                $login_page_url = $login_page ? get_permalink($login_page->ID) : home_url('/');

                $error_code = 'failed';
                if ( empty($creds['user_login']) || empty($creds['user_password']) ) {
                    $error_code = 'empty';
                }
                if ($user->get_error_code() === 'user_disabled') {
                     $error_code = 'disabled';
                }
                
                $redirect_args = ['login' => $error_code];
                if ( ! empty($_POST['redirect_to']) ) {
                    $redirect_args['redirect_to'] = urlencode($_POST['redirect_to']);
                }

                $redirect_url = add_query_arg( $redirect_args, $login_page_url );
                wp_redirect( $redirect_url );
                exit;
            } else {
                $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(urldecode($_POST['redirect_to'])) : home_url();
                wp_safe_redirect($redirect_to);
                exit;
            }
        }
    }

    public function remove_admin_bar() {
        if ( ! current_user_can('administrator') && ! is_admin() && ! ECP_Impersonation_Handler::is_impersonating() ) {
            show_admin_bar(false);
        }
    }

    public function login_redirect( $redirect_to, $request, $user ) {
        if ( isset( $user->roles ) && is_array( $user->roles ) ) {
            if ( !empty($request) && $request !== home_url('/') && $request !== admin_url() ) {
                if (wp_validate_redirect($request, home_url())) {
                    return $request;
                }
            }
            if ( in_array( 'ecp_business_admin', $user->roles ) || in_array( 'administrator', $user->roles ) || in_array( 'ecp_client_manager', $user->roles ) ) {
                $dashboard_page = get_page_by_path('admin-dashboard');
                return $dashboard_page ? get_permalink($dashboard_page->ID) : home_url();
            }
            if ( in_array( 'ecp_client', $user->roles ) || in_array( 'scp_client', $user->roles ) ) {
                $portal_page = get_page_by_path('client-portal');
                return $portal_page ? get_permalink($portal_page->ID) : home_url();
            }
        }
        return $redirect_to;
    }

    public function check_if_user_is_disabled( $user, $password ) {
        if ( is_a($user, 'WP_User') && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

    public function handle_custom_logout() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'ecp_logout' ) {
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ecp_logout_nonce' ) ) {
                wp_die( 'Security check failed.' );
            }
            wp_logout();
            $login_page = get_page_by_path('login');
            $redirect_url = $login_page ? get_permalink($login_page->ID) : home_url();
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    public function force_login_redirect() {
        if ( ! is_user_logged_in() && ( ECP_Shortcode_Helper::page_has_shortcode('client_portal') || ECP_Shortcode_Helper::page_has_shortcode('elevate_admin_dashboard') ) ) {
            global $post;
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
