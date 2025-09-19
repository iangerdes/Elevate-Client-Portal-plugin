<?php
// File: elevate-client-portal/includes/class-ecp-asset-manager.php
/**
 * Handles loading of all CSS and JavaScript assets for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 83.0.0 (Final Stable Asset Loading)
 * @comment Re-engineered to use a flag and hook system for frontend scripts and a direct enqueue for admin scripts. This provides maximum stability and compatibility.
 */

if ( ! defined( 'WPINC') ) {
    die;
}

class ECP_Asset_Manager {

    private static $instance;
    private $plugin_path;
    private $plugin_url;

    // Flags to track which script suites are needed for the current page.
    private static $needs_admin_dashboard_suite = false;
    private static $needs_client_portal_suite = false;
    private static $needs_login_suite = false;

    public static function get_instance( $path, $url ) {
        if ( null === self::$instance ) self::$instance = new self( $path, $url );
        return self::$instance;
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        
        add_action( 'wp_footer', [ $this, 'enqueue_needed_frontend_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        add_action( 'wp_head', [ $this, 'output_custom_styles' ] );
        add_action( 'admin_head', [ $this, 'output_custom_styles' ] );
    }

    // --- Request Methods (called by shortcodes) ---

    public function request_admin_dashboard_suite() {
        self::$needs_admin_dashboard_suite = true;
    }

    public function request_client_portal_suite() {
        self::$needs_client_portal_suite = true;
    }
 
    public function request_login_suite() {
        self::$needs_login_suite = true;
    }

    /**
     * This function runs in the footer and enqueues scripts/styles that were requested by shortcodes.
     */
    public function enqueue_needed_frontend_scripts() {
        // We check the flags here. If a shortcode has set a flag, we load the corresponding suite.
        if ( self::$needs_admin_dashboard_suite ) {
            $this->enqueue_admin_dashboard_suite();
        }
        if ( self::$needs_client_portal_suite ) {
            $this->enqueue_client_portal_suite();
        }
        if ( self::$needs_login_suite ) {
            $this->enqueue_login_suite();
        }
    }

    public function enqueue_admin_scripts( $hook_suffix ) {
        $ecp_admin_pages = [
            'ecp_client_page_ecp-settings',
            'ecp_client_page_ecp-s3-browser',
            'ecp_client_page_ecp-audit-log',
        ];

        if ( in_array( $hook_suffix, $ecp_admin_pages ) ) {
            wp_enqueue_style( 'wp-color-picker' );
            $this->enqueue_style('ecp-admin-styles', 'assets/css/ecp-styles.css');
            
            $this->enqueue_script('ecp-admin-settings-js', 'assets/js/ecp-admin-settings.js', ['jquery', 'wp-color-picker'], true);
            wp_localize_script( 'ecp-admin-settings-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => ECP_Security_Helper::create_nonce('admin_settings'),
            ]);
        }
    }

    // --- Enqueue Suites ---

    private function enqueue_admin_dashboard_suite() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
        
        $this->enqueue_script('ecp-admin-dashboard-js', 'assets/js/ecp-admin-dashboard.js', ['jquery']);
        wp_localize_script( 'ecp-admin-dashboard-js', 'ecp_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'home_url' => home_url('/'),
            'nonces'   => ECP_Security_Helper::get_script_nonces(['dashboard', 'view', 'file_manager', 'decrypt_file']),
            'strings'  => [
                'confirm_action'        => __('Are you sure you want to %s this user?', 'ecp'),
                'confirm_delete_user'   => __('Are you sure you want to permanently remove this user and all their files?', 'ecp'),
                'confirm_delete_file'   => __('Are you sure you want to delete the selected files? This cannot be undone.', 'ecp'),
                'confirm_delete_folder' => __('Are you sure you want to delete this folder? All files inside will be moved to Uncategorized.', 'ecp'),
                'encrypt_prompt'        => __('Please enter a password to encrypt this file:', 'ecp'),
                'decrypt_prompt'        => __('Please enter the password to decrypt this file:', 'ecp'),
            ]
        ]);
    }

    private function enqueue_client_portal_suite() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
        $this->enqueue_script('ecp-client-portal-js', 'assets/js/ecp-client-portal.js', ['jquery']);
        wp_localize_script( 'ecp-client-portal-js', 'ecp_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'home_url' => home_url('/'),
            'nonces'   => ECP_Security_Helper::get_script_nonces(['client_portal', 'decrypt_file', 'contact_manager', 'update_account', 'zip']),
            'strings'  => [
                'error_zip'      => __('An unknown error occurred while creating the ZIP file.', 'ecp'),
                'copied'         => __('Copied!', 'ecp'),
                'decrypt_prompt' => __('This file is encrypted. Please enter the password to download:', 'ecp'),
            ]
        ]);
    }

    private function enqueue_login_suite() {
        $this->enqueue_style('ecp-styles', 'assets/css/ecp-styles.css');
    }
    
    // --- Helper Functions ---

    private function enqueue_script($handle, $path, $deps = [], $in_footer = true) {
        $file_path = $this->plugin_path . $path;
        $version = file_exists($file_path) ? filemtime($file_path) : ECP_VERSION;
        wp_enqueue_script($handle, $this->plugin_url . $path, $deps, $version, $in_footer);
    }

    private function enqueue_style($handle, $path, $deps = []) {
        $file_path = $this->plugin_path . $path;
        $version = file_exists($file_path) ? filemtime($file_path) : ECP_VERSION;
        wp_enqueue_style($handle, $this->plugin_url . $path, $deps, $version);
    }

    public function output_custom_styles() {
        $options = get_option('ecp_style_options');
        $primary_color = !empty($options['primary_color']) ? sanitize_hex_color($options['primary_color']) : '#007cba';
        $secondary_color = !empty($options['secondary_color']) ? sanitize_hex_color($options['secondary_color']) : '#f0f6fc';
        ?>
        <style type="text/css">
            :root {
                --ecp-primary-color: <?php echo esc_html($primary_color); ?>;
                --ecp-secondary-color: <?php echo esc_html($secondary_color); ?>;
            }
        </style>
        <?php
    }
}

