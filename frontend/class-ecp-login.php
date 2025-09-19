<?php
// File: elevate-client-portal/frontend/class-ecp-login.php
/**
 * Handles all logic for the [elevate_login] shortcode.
 * @package Elevate_Client_Portal
 * @version 80.0.0 (Final Stable Asset Loading)
 * @comment This shortcode now "requests" its scripts from the Asset Manager, which will then reliably load them in the footer. This is the definitive fix for all script loading issues.
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
    }
    
    public function render_shortcode() {
        // Request the scripts and styles required for this shortcode.
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL )->request_login_suite();
        
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $allowed_admin_roles = ['ecp_business_admin', 'administrator', 'ecp_client_manager'];
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

