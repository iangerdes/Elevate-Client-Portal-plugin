<?php
// File: elevate-client-portal/includes/class-ecp-asset-manager.php
/**
 * Handles loading of all CSS and JavaScript assets for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 98.0.0 (Direct Asset Loading)
 * @comment Re-engineered to use a direct-call system from shortcodes for improved reliability. Removed the fragile hook-based loading system.
 */

if ( ! defined( 'WPINC') ) {
    die;
}

require_once ECP_PLUGIN_PATH . 'includes/asset-loaders/class-ecp-admin-dashboard-assets.php';
require_once ECP_PLUGIN_PATH . 'includes/asset-loaders/class-ecp-client-portal-assets.php';

class ECP_Asset_Manager {

    private static $instance;
    private $plugin_path;
    private $plugin_url;

    private static $admin_dashboard_assets;
    private static $client_portal_assets;

    public static function get_instance( $path, $url ) {
        if ( null === self::$instance ) self::$instance = new self( $path, $url );
        return self::$instance;
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_backend_scripts' ] );

        add_action( 'wp_head', [ $this, 'output_custom_styles' ] );
        add_action( 'admin_head', [ $this, 'output_custom_styles' ] );
    }

    /**
     * Public accessor for the Client Portal asset loader instance.
     * @return ECP_Client_Portal_Assets
     */
    public static function get_client_portal_assets_loader() {
        if ( null === self::$client_portal_assets ) {
            self::$client_portal_assets = new ECP_Client_Portal_Assets( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        }
        return self::$client_portal_assets;
    }
    
    /**
     * Public accessor for the Admin Dashboard asset loader instance.
     * @return ECP_Admin_Dashboard_Assets
     */
    public static function get_admin_dashboard_assets_loader() {
        if ( null === self::$admin_dashboard_assets ) {
            self::$admin_dashboard_assets = new ECP_Admin_Dashboard_Assets( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        }
        return self::$admin_dashboard_assets;
    }

    /**
     * Enqueues scripts for the traditional WordPress admin area (Settings, etc.).
     */
    public function enqueue_admin_backend_scripts( $hook_suffix ) {
        $ecp_admin_pages = [
            'ecp_client_page_ecp-settings',
            'ecp_client_page_ecp-s3-browser',
            'ecp_client_page_ecp-audit-log',
        ];

        if ( in_array( $hook_suffix, $ecp_admin_pages ) ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_style('ecp-admin-styles', $this->plugin_url . 'assets/css/ecp-styles.css', [], ECP_VERSION);
            
            wp_enqueue_script('ecp-admin-settings-js', $this->plugin_url . 'assets/js/ecp-admin-settings.js', ['jquery', 'wp-color-picker'], ECP_VERSION, true);
            wp_localize_script( 'ecp-admin-settings-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonces'    => ECP_Security_Helper::get_script_nonces(['admin_settings']),
            ]);
        }
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

