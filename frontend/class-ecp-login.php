<?php
// File: elevate-client-portal/frontend/class-ecp-login.php
/**
 * Handles all logic for the [elevate_login] shortcode.
 * @package Elevate_Client_Portal
 * @version 122.0.0 (JS Loading Fix)
 * @comment Removed direct script enqueuing. Asset loading is now handled globally by the ECP_Asset_Manager.
 */
class ECP_Login {

    private static $instance;
    private $plugin_path;
    private $plugin_url;
    
    public static function get_instance( $path, $url ) { 
        if ( null === self::$instance ) { 
            self::$instance = new self( $path, $url ); 
        } 
        return self::$instance; 
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        add_shortcode( 'elevate_login', [ $this, 'render_shortcode' ] );

        // ** NEW: Moved login-related hooks here for consolidation **
        add_filter( 'authenticate', [ $this, 'check_if_user_is_disabled' ], 30, 3 );
        add_action( 'template_redirect', [ $this, 'force_login_redirect' ] );
    }
    
    /**
     * ** NEW: Moved from ECP_Auth_Handler **
     * Checks if a user is disabled during the authentication process.
     */
    public function check_if_user_is_disabled( $user, $username, $password ) {
        if ( is_a( $user, 'WP_User' ) && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

    /**
     * ** NEW: Moved from ECP_Auth_Handler **
     * Forces unauthenticated users to the login page when they try to access protected content.
     */
    public function force_login_redirect() {
        if ( ! is_user_logged_in() && ( ECP_Shortcode_Helper::page_has_shortcode('client-portal') || ECP_Shortcode_Helper::page_has_shortcode('elevate_admin_dashboard') ) ) {
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

    public function render_shortcode() {
        $error_message = '';

        // ** FIX: Process login form submission directly to avoid wp-login.php **
        if ( ! empty( $_POST['ecp-login-nonce'] ) && wp_verify_nonce( $_POST['ecp-login-nonce'], 'ecp-login-action' ) ) {
            $creds = [
                'user_login'    => sanitize_user( wp_unslash( $_POST['log'] ) ),
                'user_password' => wp_unslash( $_POST['pwd'] ),
                'remember'      => isset( $_POST['rememberme'] ),
            ];

            $user = wp_signon( $creds, is_ssl() );

            if ( is_wp_error( $user ) ) {
                if ( $user->get_error_code() === 'user_disabled' ) {
                    $error_message = __('Your account has been disabled.', 'ecp');
                } else {
                    $error_message = __('Invalid username or password. Please try again.', 'ecp');
                }
            } else {
                // Successful login, perform redirect.
                $redirect_to = home_url();
                if ( user_can( $user, 'edit_users' ) ) {
                    $dashboard_page = get_page_by_path('admin-dashboard');
                    if ($dashboard_page) $redirect_to = get_permalink($dashboard_page->ID);
                } else {
                    $portal_page = get_page_by_path('client-portal');
                    if ($portal_page) $redirect_to = get_permalink($portal_page->ID);
                }
                wp_safe_redirect($redirect_to);
                exit;
            }
        }

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $allowed_admin_roles = ['ecp_business_admin', 'administrator', 'ecp_client_manager'];
            $logout_button = '<a href="' . wp_logout_url( get_permalink() ) . '" class="button">' . __('Logout', 'ecp') . '</a>';

            if( count(array_intersect($allowed_admin_roles, $user->roles)) > 0 ) {
                 $admin_url = home_url('/admin-dashboard');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($admin_url) . '">' . __('Go to Admin Dashboard', 'ecp') . '</a>', $logout_button) . '</p></div>';
            } else {
                 $portal_url = home_url('/client-portal');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are already logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($portal_url) . '">' . __('View your files', 'ecp') . '</a>', $logout_button) . '</p></div>';
            }
        }
        
        ob_start();
        ?>
        <div class="ecp-login-form-wrapper">
            <h3><?php _e('Client Login', 'ecp'); ?></h3>
            
            <?php if ( ! empty( $error_message ) ) : ?>
                <div class="ecp-login-error"><strong><?php _e('Error:', 'ecp'); ?></strong> <?php echo esc_html($error_message); ?></div>
            <?php endif; ?>

            <form name="loginform" id="loginform" action="" method="post">
                <p>
                    <label for="user_login"><?php _e( 'Email Address', 'ecp' ); ?></label>
                    <input type="text" name="log" id="user_login" class="input" value="<?php echo isset($_POST['log']) ? esc_attr(wp_unslash($_POST['log'])) : ''; ?>" size="20" required="required" />
                </p>
                <p>
                    <label for="user_pass"><?php _e( 'Password', 'ecp' ); ?></label>
                    <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" required="required" />
                </p>
                <p class="login-remember">
                    <label><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <?php _e( 'Remember Me', 'ecp' ); ?></label>
                </p>
                <p class="login-submit">
                    <button type="submit" name="wp-submit" id="wp-submit" class="button button-primary"><?php _e( 'Log In', 'ecp' ); ?></button>
                    <?php wp_nonce_field( 'ecp-login-action', 'ecp-login-nonce' ); ?>
                </p>
            </form>
            <p class="login-lost-password">
                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php _e('Lost your password?', 'ecp'); ?></a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}

