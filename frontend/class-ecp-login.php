<?php
/**
 * Handles all logic for the [elevate_login] shortcode.
 * Version: 5.0.2
 */
class ECP_Login {

    private static $instance;
    public static function get_instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }

    private function __construct() {
        add_shortcode( 'elevate_login', [ $this, 'render_shortcode' ] );
        add_action( 'init', [ $this, 'handle_custom_login_submission' ] );
    }

    /**
     * Handles the submission of the custom login form.
     */
    public function handle_custom_login_submission() {
        if ( isset( $_POST['elevate_login_form'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            if ( ! isset( $_POST['ecp_login_nonce'] ) || ! wp_verify_nonce( $_POST['ecp_login_nonce'], 'ecp-login-nonce-action' ) ) {
                wp_die('Security check failed.');
            }

            $referrer = isset($_POST['_wp_http_referer']) ? esc_url_raw(wp_unslash($_POST['_wp_http_referer'])) : home_url('/login/');
            
            if ( empty($_POST['log']) || empty($_POST['pwd']) ) {
                $referrer = remove_query_arg('login', $referrer);
                wp_safe_redirect(add_query_arg('login', 'empty', $referrer));
                exit;
            }

            $creds = [
                'user_login'    => sanitize_user( $_POST['log'] ),
                'user_password' => $_POST['pwd'],
                'remember'      => isset( $_POST['rememberme'] ),
            ];

            $user = wp_signon( $creds, is_ssl() );

            if ( is_wp_error( $user ) ) {
                $error_code = 'failed';
                $user_check = get_user_by('email', $creds['user_login']) ?: get_user_by('login', $creds['user_login']);
                if ( $user_check && get_user_meta( $user_check->ID, 'ecp_user_disabled', true ) ) {
                    $error_code = 'disabled';
                }
                
                $referrer = remove_query_arg('login', $referrer);
                wp_safe_redirect(add_query_arg('login', $error_code, $referrer));
                exit;
            } else {
                $redirect_to = isset($_REQUEST['redirect_to']) && !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : home_url();
                $redirect_url = apply_filters( 'login_redirect', $redirect_to, (isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : ''), $user );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }
    
    /**
     * Renders the login form shortcode and displays any errors.
     * FIX: Replaced wp_logout_url with a custom URL to trigger our handler.
     */
    public function render_shortcode() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $allowed_admin_roles = ['ecp_business_admin', 'administrator', 'ecp_client_manager'];
            // Use our custom logout URL generator
            $logout_url = wp_nonce_url( home_url( '/?action=ecp_logout' ), 'ecp_logout_nonce' );

            if( count(array_intersect($allowed_admin_roles, $user->roles)) > 0 ) {
                 $admin_url = home_url('/admin-dashboard');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($admin_url) . '">' . __('Go to Admin Dashboard', 'ecp') . '</a>', '<a href="' . esc_url($logout_url) . '">' . __('Logout', 'ecp') . '</a>') . '</p></div>';
            } else {
                 $portal_url = home_url('/client-portal');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are already logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($portal_url) . '">' . __('View your files', 'ecp') . '</a>', '<a href="' . esc_url($logout_url) . '">' . __('Logout', 'ecp') . '</a>') . '</p></div>';
            }
        }
        
        ob_start();
        ?>
        <div class="ecp-login-form-wrapper">
             <h3><?php _e('Client Login', 'ecp'); ?></h3>
             
             <?php 
             if ( !empty($_REQUEST['login']) ) {
                 $error_code = sanitize_key($_REQUEST['login']);
                 $error_message = '';
                 if ( $error_code === 'failed' ) {
                     $error_message = __('Invalid username or password. Please try again.', 'ecp');
                 } elseif ( $error_code === 'empty' ) {
                     $error_message = __('Username and password fields cannot be empty.', 'ecp');
                 } elseif ( $error_code === 'disabled' ) {
                     $error_message = __('Your account has been disabled.', 'ecp');
                 }
                 if($error_message) {
                    echo '<p class="ecp-login-error"><strong>' . __('Error:', 'ecp') . '</strong> ' . esc_html($error_message) . '</p>';
                 }
             }
             
             $redirect_url = home_url();
             if ( isset( $_REQUEST['redirect_to'] ) ) {
                 $redirect_url = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
             }
             ?>

            <form name="loginform" id="loginform" action="<?php echo esc_url( get_permalink() ); ?>" method="post">
                <p class="login-username">
                    <label for="user_login"><?php _e('Email Address', 'ecp'); ?></label>
                    <input type="text" name="log" id="user_login" autocomplete="username" class="input" value="" size="20" />
                </p>
                <p class="login-password">
                    <label for="user_pass"><?php _e('Password', 'ecp'); ?></label>
                    <input type="password" name="pwd" id="user_pass" autocomplete="current-password" spellcheck="false" class="input" value="" size="20" />
                </p>
                <p class="login-remember">
                    <label><input name="rememberme" type="checkbox" id="rememberme" value="forever" /> <?php _e('Remember Me', 'ecp'); ?></label>
                </p>
                <p class="login-submit">
                    <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e('Log In', 'ecp'); ?>" />
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_url); ?>" />
                    <input type="hidden" name="elevate_login_form" value="1" />
                    <?php wp_nonce_field( 'ecp-login-nonce-action', 'ecp_login_nonce' ); ?>
                </p>
            </form>

        </div>
        <?php
        return ob_get_clean();
    }
}
