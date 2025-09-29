<?php
// File: elevate-client-portal/frontend/class-ecp-login.php
/**
 * Handles all logic for the [elevate_login] shortcode.
 * @package Elevate_Client_Portal
 * @version 125.0.0 (AJAX Login)
 * @comment Switched to a fully AJAX-powered login form to prevent page reload issues and redirect loops.
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

        add_action( 'init', [ $this, 'handle_password_reset_forms' ] );
        add_shortcode( 'elevate_login', [ $this, 'render_shortcode' ] );
        add_filter( 'authenticate', [ $this, 'check_if_user_is_disabled' ], 30, 3 );
        add_action( 'template_redirect', [ $this, 'force_login_redirect' ] );
        add_filter( 'lostpassword_url', [ $this, 'custom_lostpassword_url' ], 10, 0 );
    }

    public function custom_lostpassword_url( $lostpassword_url = '' ) {
        $login_page = get_page_by_path('login');
        if ($login_page) {
            return add_query_arg('action', 'lostpassword', get_permalink($login_page->ID));
        }
        return home_url( 'wp-login.php?action=lostpassword' );
    }
    
    public function handle_password_reset_forms() {
        if ( ! isset($_REQUEST['action']) ) {
            return;
        }

        $action = $_REQUEST['action'];
        $login_page_url = remove_query_arg('action', $this->custom_lostpassword_url());

        if ('lostpassword' === $action && 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['user_login'])) {
            if ( ! isset( $_POST['ecp-lost-password-nonce'] ) || ! wp_verify_nonce( $_POST['ecp-lost-password-nonce'], 'ecp-lost-password-action' ) ) {
                return;
            }
            $errors = retrieve_password();
            $redirect_url = add_query_arg('action', 'lostpassword', $login_page_url);
            if ( is_wp_error($errors) ) {
                $redirect_url = add_query_arg('errors', $errors->get_error_code(), $redirect_url);
            } else {
                 $redirect_url = add_query_arg('checkemail', 'confirm', $redirect_url);
            }
            wp_safe_redirect($redirect_url);
            exit;
        }

        if ('rp' === $action && 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['pass1'])) {
            if ( ! isset( $_POST['ecp-reset-password-nonce'] ) || ! wp_verify_nonce( $_POST['ecp-reset-password-nonce'], 'ecp-reset-password-action' ) ) {
                return;
            }

            $rp_key = $_REQUEST['key'] ?? '';
            $rp_login = $_REQUEST['login'] ?? '';
            $user = check_password_reset_key($rp_key, $rp_login);
            $redirect_url = add_query_arg( ['action' => 'rp', 'key' => $rp_key, 'login' => $rp_login], $login_page_url );

            if ( is_wp_error($user) ) {
                $redirect_url = add_query_arg('errors', 'invalidkey', $redirect_url);
                wp_safe_redirect($redirect_url);
                exit;
            }
            if ( isset($_POST['pass1']) && $_POST['pass1'] != $_POST['pass2'] ) {
                $redirect_url = add_query_arg('errors', 'password_reset_mismatch', $redirect_url);
                wp_safe_redirect($redirect_url);
                exit;
            }
            if (empty($_POST['pass1'])) {
                $redirect_url = add_query_arg('errors', 'password_reset_empty', $redirect_url);
                wp_safe_redirect($redirect_url);
                exit;
            }

            reset_password($user, $_POST['pass1']);
            $redirect_url = add_query_arg( ['action' => 'login', 'reset' => 'true'], $login_page_url );
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
    
    public function check_if_user_is_disabled( $user, $username, $password ) {
        if ( is_a( $user, 'WP_User' ) && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
            return new WP_Error( 'user_disabled', __( '<strong>ERROR</strong>: Your account has been disabled.', 'ecp' ) );
        }
        return $user;
    }

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
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $allowed_admin_roles = ['ecp_business_admin', 'administrator', 'ecp_client_manager'];
            $logout_button = '<a href="#" class="button ecp-ajax-logout-btn">' . __('Logout', 'ecp') . '</a>';

            if( count(array_intersect($allowed_admin_roles, $user->roles)) > 0 ) {
                 $admin_url = home_url('/admin-dashboard');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($admin_url) . '">' . __('Go to Admin Dashboard', 'ecp') . '</a>', $logout_button) . '</p></div>';
            } else {
                 $portal_url = home_url('/client-portal');
                 return '<div class="ecp-login-form-wrapper"><p>' . sprintf( __('You are already logged in. %s or %s.', 'ecp'), '<a href="' . esc_url($portal_url) . '">' . __('View your files', 'ecp') . '</a>', $logout_button) . '</p></div>';
            }
        }
        
        ob_start();
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
        $error_message = '';
        
        if (isset($_GET['errors'])) {
            switch ($_GET['errors']) {
                case 'invalidkey':
                case 'expiredkey':
                    $error_message = __('Your password reset link appears to be invalid or has expired. Please request a new one.', 'ecp');
                    break;
                case 'password_reset_mismatch':
                    $error_message = __('The passwords do not match.', 'ecp');
                    break;
                case 'password_reset_empty':
                    $error_message = __('Password field cannot be empty.', 'ecp');
                    break;
                default:
                     $error_message = __('An unknown error occurred.', 'ecp');
                    break;
            }
        }
        ?>
        <div class="ecp-login-form-wrapper">
        <?php
        if ( !empty($error_message) ) {
            echo '<div class="ecp-login-error"><strong>' . __('Error:', 'ecp') . '</strong> ' . esc_html($error_message) . '</div>';
        }
        if (isset($_GET['checkemail']) && $_GET['checkemail'] == 'confirm') {
            echo '<p class="ecp-login-message">' . __('Check your email for the confirmation link.', 'ecp') . '</p>';
        }
        if (isset($_GET['reset']) && $_GET['reset'] == 'true') {
            echo '<p class="ecp-login-message">' . __('Your password has been reset.', 'ecp') . ' <a href="' . esc_url( remove_query_arg(['action', 'reset']) ) . '">' . __('Log in', 'ecp') . '</a></p>';
        }

        switch ($action) {
            case 'lostpassword':
                ?>
                <h3><?php _e('Lost Password', 'ecp'); ?></h3>
                <p><?php _e('Please enter your username or email address. You will receive a link to create a new password via email.', 'ecp'); ?></p>
                <form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( add_query_arg('action', 'lostpassword', '') ); ?>" method="post">
                    <p>
                        <label for="user_login"><?php _e('Username or Email Address', 'ecp'); ?></label>
                        <input type="text" name="user_login" id="user_login" class="input" value="" size="20" required="required" />
                    </p>
                    <?php wp_nonce_field( 'ecp-lost-password-action', 'ecp-lost-password-nonce' ); ?>
                    <p class="login-submit">
                        <button type="submit" name="wp-submit" id="wp-submit" class="button button-primary"><?php _e( 'Get New Password', 'ecp' ); ?></button>
                    </p>
                </form>
                <p><a href="<?php echo esc_url( remove_query_arg('action') ); ?>"><?php _e('Log in', 'ecp'); ?></a></p>
                <?php
                break;
            
            case 'rp':
                $rp_key = $_REQUEST['key'] ?? '';
                $rp_login = $_REQUEST['login'] ?? '';
                $user = check_password_reset_key($rp_key, $rp_login);

                if ( is_wp_error($user) ) {
                    ?>
                    <p><a href="<?php echo esc_url( $this->custom_lostpassword_url() ); ?>"><?php _e('Request a new password reset link', 'ecp'); ?></a></p>
                    <?php
                } else {
                    ?>
                    <h3><?php _e('Reset Password', 'ecp'); ?></h3>
                    <form name="resetpassform" id="resetpassform" action="<?php echo esc_url( add_query_arg('action', 'rp', '') ); ?>" method="post" autocomplete="off">
                         <input type="hidden" name="key" value="<?php echo esc_attr( $rp_key ); ?>" />
                         <input type="hidden" name="login" value="<?php echo esc_attr( $rp_login ); ?>" />
                        <p>
                            <label for="pass1"><?php _e('New password', 'ecp'); ?></label>
                            <input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="new-password" required />
                        </p>
                        <p>
                            <label for="pass2"><?php _e('Confirm new password', 'ecp'); ?></label>
                            <input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="new-password" required />
                        </p>
                        <?php wp_nonce_field( 'ecp-reset-password-action', 'ecp-reset-password-nonce' ); ?>
                        <p class="login-submit">
                            <button type="submit" name="wp-submit" class="button button-primary"><?php _e('Reset Password', 'ecp'); ?></button>
                        </p>
                    </form>
                    <?php
                }
                break;

            case 'login':
            default:
                ?>
                <h3><?php _e('Client Login', 'ecp'); ?></h3>
                <div id="ecp-login-error-container" class="ecp-login-error" style="display:none;"></div>
                <form name="loginform" id="ecp-ajax-login-form" method="post">
                    <p>
                        <label for="user_login"><?php _e( 'Email Address', 'ecp' ); ?></label>
                        <input type="text" name="log" id="user_login" class="input" value="" size="20" required="required" />
                    </p>
                    <p>
                        <label for="user_pass"><?php _e( 'Password', 'ecp' ); ?></label>
                        <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" required="required" />
                    </p>
                    <p class="login-remember">
                        <label><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <?php _e( 'Remember Me', 'ecp' ); ?></label>
                    </p>
                    <p class="login-submit">
                        <button type="submit" name="wp-submit" id="ecp-login-submit-btn" class="button button-primary"><?php _e( 'Log In', 'ecp' ); ?></button>
                         <?php if ( !empty($_REQUEST['redirect_to']) ): ?>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($_REQUEST['redirect_to']); ?>" />
                        <?php endif; ?>
                    </p>
                </form>
                <p class="login-lost-password">
                    <a href="<?php echo esc_url( $this->custom_lostpassword_url() ); ?>"><?php _e('Lost your password?', 'ecp'); ?></a>
                </p>
                <?php
                break;
        }
        ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

