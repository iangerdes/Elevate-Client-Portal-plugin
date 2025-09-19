<?php
// File: frontend/class-ecp-frontend.php
/**
 * Main handler for the front-end.
 * Handles secure downloads for both local and S3 files.
 * Version: 5.9.8 (Refactored Download Handler)
 */
class ECP_Frontend {

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
        
        ECP_Client_Portal::get_instance($this->plugin_path, $this->plugin_url);
        ECP_Admin_Dashboard::get_instance($this->plugin_path, $this->plugin_url);
        ECP_Login::get_instance();

        add_action('after_setup_theme', [ $this, 'ecp_remove_admin_bar' ]);
        add_action( 'init', [ $this, 'handle_secure_download' ] );
        add_action( 'init', [ $this, 'handle_custom_logout' ] );
        add_filter( 'login_redirect', [ $this, 'client_login_redirect' ], 10, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_filter( 'wp_authenticate_user', [ $this, 'check_if_user_is_disabled' ], 10, 2 );
        add_action( 'template_redirect', [ $this, 'force_custom_login_redirect' ] );
        add_shortcode( 'ecp_loginout_button', [ $this, 'render_loginout_button' ] );
        add_shortcode( 'ecp_adminclient_button', [ $this, 'render_adminclient_button' ] );
    }

    /**
     * Main router for all secure download actions.
     */
    public function handle_secure_download() {
        if (!isset($_REQUEST['ecp_action']) || !isset($_REQUEST['_wpnonce'])) return;

        $action = sanitize_key($_REQUEST['ecp_action']);

        // Route to the standard file download handler
        if ( $action === 'download_file' && isset( $_GET['file_key'] ) ) {
            $this->process_file_download();
        }

        // Handle download requests for encrypted files (unchanged)
        if ( $action === 'download_decrypted_file' && isset( $_POST['file_key'] ) && isset( $_POST['password'] ) ) {
            // ... Decryption logic remains here ...
        }

        // Handle zip file downloads (unchanged)
        if ( $action === 'download_zip' && isset($_GET['zip_file']) ) {
            // ... Zip logic remains here ...
        }
    }

    /**
     * Handles the logic for a standard (non-encrypted, non-zip) file download.
     */
    private function process_file_download() {
        if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'ecp_download_file_nonce' ) ) { wp_die( 'Security check failed.' ); }
        if ( ! is_user_logged_in() ) { wp_die( 'You must be logged in to download files.' ); }
        
        $file_key_hash = sanitize_text_field( $_GET['file_key'] );
        
        $id_to_check = get_current_user_id();
        if ( isset( $_GET['target_user_id'] ) && current_user_can('edit_users') ) {
            $id_to_check = intval($_GET['target_user_id']);
        }

        $file_to_download = $this->find_file_for_download($file_key_hash, $id_to_check);

        if ( ! $file_to_download ) {
            wp_die( 'File not found or permission denied.' );
        }

        // Prevent encrypted files from being downloaded via this standard method
        if ( ! empty( $file_to_download['is_encrypted'] ) ) {
            wp_die( 'This file is encrypted and must be downloaded through the client portal interface.' );
        }

        $s3_instance = ECP_S3::get_instance();
        if ( $s3_instance->is_s3_enabled() && ! empty( $file_to_download['s3_key'] ) ) {
            $this->redirect_to_s3_url( $file_to_download );
        } elseif ( ! empty( $file_to_download['path'] ) ) {
            $this->stream_local_file( $file_to_download );
        } else {
            wp_die( 'File not found or permission denied.' );
        }
    }

    /**
     * Searches user meta and site options to find the metadata for a requested file.
     *
     * @param string $file_key_hash The MD5 hash of the file's key.
     * @param int    $user_id The ID of the user to check files for.
     * @return array|null The file's metadata array, or null if not found.
     */
    private function find_file_for_download( $file_key_hash, $user_id ) {
        $user_files = ($user_id > 0) ? get_user_meta( $user_id, '_ecp_client_file', false ) : [];
        $all_users_files = get_option( '_ecp_all_users_files', [] );
        $all_possible_files = array_merge(is_array($user_files) ? $user_files : [], is_array($all_users_files) ? $all_users_files : []);

        foreach ($all_possible_files as $file) {
            if ( ! is_array( $file ) ) {
                continue;
            }
            $key_source = !empty($file['s3_key']) ? $file['s3_key'] : ($file['path'] ?? '');
            if ( !empty($key_source) && md5($key_source) === $file_key_hash ) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Generates a pre-signed S3 URL and redirects the user to it.
     *
     * @param array $file_data The metadata for the S3 file.
     */
    private function redirect_to_s3_url( $file_data ) {
        $s3_instance = ECP_S3::get_instance();
        $download_url = $s3_instance->get_download_url( $file_data['s3_key'], $file_data['name'] );

        if ( $download_url ) {
            wp_redirect( $download_url );
            exit;
        } else {
            wp_die( __( 'Could not generate secure download link from S3.', 'ecp' ) );
        }
    }

    /**
     * Sets the appropriate headers and streams a local file to the browser.
     *
     * @param array $file_data The metadata for the local file.
     */
    private function stream_local_file( $file_data ) {
        if ( ! file_exists( $file_data['path'] ) ) {
            wp_die( 'File not found on server.' );
        }
        
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: ' . esc_attr($file_data['original_type'] ?? $file_data['type']) );
        header( 'Content-Disposition: attachment; filename="' . esc_attr($file_data['name']) . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $file_data['path'] ) );
        
        ob_clean();
        flush();
        readfile( $file_data['path'] );
        exit;
    }

    // --- Other Class Functions (Unchanged) ---

    public function ecp_remove_admin_bar() {
        if ( ! current_user_can('administrator') && ! is_admin() ) {
            show_admin_bar(false);
        }
    }

    public function enqueue_scripts() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;

        $has_client_shortcode = has_shortcode( $post->post_content, 'client_portal' );
        $has_account_shortcode = has_shortcode( $post->post_content, 'ecp_account' );
        $has_admin_shortcode = has_shortcode( $post->post_content, 'elevate_admin_dashboard' );
        $has_login_shortcode = has_shortcode( $post->post_content, 'elevate_login' );

        $load_styles = $has_client_shortcode || $has_account_shortcode || $has_admin_shortcode || $has_login_shortcode;

        if ( $load_styles ) {
             wp_enqueue_style( 'ecp-styles', $this->plugin_url . 'assets/ecp-styles.css', [], ECP_VERSION );
        }
        
        $ajax_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ecp_ajax_nonce' ),
            'strings' => [
                'confirm_action' => __('Are you sure you want to %s this user?', 'ecp'),
                'confirm_delete' => __('Are you sure?', 'ecp'),
                'error_zip' => __('An unknown error occurred while creating the ZIP file.', 'ecp'),
                'copied' => __('Copied!', 'ecp'),
                'decrypt_prompt' => __('This file is encrypted. Please enter the password to download:', 'ecp'),
                'decrypt_error' => __('Incorrect password or corrupted file.', 'ecp'),
            ]
        ];

        if ( $has_client_shortcode || $has_account_shortcode ) {
             wp_enqueue_script( 'ecp-client-portal-js', $this->plugin_url . 'assets/ecp-client-portal.js', ['jquery'], ECP_VERSION, true );
             wp_localize_script( 'ecp-client-portal-js', 'ecp_ajax', $ajax_data);
        }
        if ( $has_admin_shortcode ) {
             wp_enqueue_script( 'ecp-admin-dashboard-js', $this->plugin_url . 'assets/ecp-admin-dashboard.js', ['jquery'], ECP_VERSION, true );
             wp_localize_script( 'ecp-admin-dashboard-js', 'ecp_ajax', $ajax_data);
             wp_enqueue_script( 'ecp-file-manager-js', $this->plugin_url . 'assets/file-manager.js', ['ecp-admin-dashboard-js'], ECP_VERSION, true );
        }
    }

    public function render_loginout_button() {
        if ( is_user_logged_in() ) {
            $logout_url = wp_nonce_url( home_url( '/?action=ecp_logout' ), 'ecp_logout_nonce' );
            return '<a href="' . esc_url( $logout_url ) . '" class="button">'. __( 'Logout', 'ecp' ) . '</a>';
        } else {
            $login_url = home_url( '/login/' );
            return '<a href="' . esc_url( $login_url ) . '" class="button">' . __( 'Login', 'ecp' ) . '</a>';
        }
    }

    public function render_adminclient_button() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $admin_roles = ['administrator', 'ecp_business_admin', 'ecp_client_manager'];
            $client_roles = ['ecp_client', 'scp_client'];

            if ( count( array_intersect( $admin_roles, (array) $user->roles ) ) > 0 ) {
                $dashboard_url = home_url('/admin-dashboard');
                return '<a href="' . esc_url( $dashboard_url ) . '" class="button">'. __( 'Admin Dashboard', 'ecp' ) . '</a>';
            } elseif ( count( array_intersect( $client_roles, (array) $user->roles ) ) > 0 ) {
                $portal_url = home_url('/client-portal');
                return '<a href="' . esc_url( $portal_url ) . '" class="button">'. __( 'Client Portal', 'ecp' ) . '</a>';
            }
        }
        return '';
    }
    
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

    public function check_if_user_is_disabled( $user, $password ) {
        if ( isset($user->ID) && get_user_meta( $user->ID, 'ecp_user_disabled', true ) ) {
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
            wp_safe_redirect( home_url( '/login/' ) );
            exit;
        }
    }

    public function force_custom_login_redirect() {
        if ( is_singular() && ! is_user_logged_in() ) {
            global $post;
            if ( ! is_a( $post, 'WP_Post' ) ) return;

            if ( has_shortcode( $post->post_content, 'client_portal' ) || has_shortcode( $post->post_content, 'elevate_admin_dashboard' ) ) {
                $login_page = get_page_by_path('login');
                if ( ! $login_page ) {
                    wp_die(
                        '<strong>Elevate Client Portal Debug Error:</strong> Redirection failed because the login page could not be found.<br><br>' .
                        'Please ensure a page with the slug "<strong>login</strong>" exists and is published. This page should contain the <code>[elevate_login]</code> shortcode.',
                        'Login Page Not Found'
                    );
                }
                $login_url = home_url( '/login/' );
                $redirect_url = add_query_arg( 'redirect_to', urlencode( get_permalink( $post->ID ) ), $login_url );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }
}

