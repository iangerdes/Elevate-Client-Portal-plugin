<?php
// File: includes/class-ecp-asset-manager.php
/**
 * Handles loading of all CSS and JavaScript assets for the plugin.
 *
 * @package Elevate_Client_Portal
 * @version 9.0.1 (Stable Refactor)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Asset_Manager
 * Enqueues scripts and styles and localizes data for frontend components.
 */
class ECP_Asset_Manager {

    private static $instance;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance( $path, $url ) {
        if ( null === self::$instance ) self::$instance = new self( $path, $url );
        return self::$instance;
    }

    private function __construct( $path, $url ) {
        $this->plugin_path = $path;
        $this->plugin_url = $url;
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_head', [ $this, 'output_custom_styles' ] );
    }

    /**
     * Enqueues scripts and styles for the frontend.
     */
    public function enqueue_scripts() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || empty($post->post_content) ) return;

        $has_client_shortcode = has_shortcode( $post->post_content, 'client_portal' );
        $has_admin_shortcode = has_shortcode( $post->post_content, 'elevate_admin_dashboard' );
        
        if ( $has_client_shortcode || has_shortcode( $post->post_content, 'ecp_account' ) ) {
            wp_enqueue_style( 'ecp-styles', $this->plugin_url . 'assets/css/ecp-styles.css', [], ECP_VERSION );
            wp_enqueue_script( 'ecp-client-portal-js', $this->plugin_url . 'assets/js/ecp-client-portal.js', ['jquery'], ECP_VERSION, true );
            wp_localize_script( 'ecp-client-portal-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ecp_client_ajax_nonce' ),
            ]);
        }
        
        if ( $has_admin_shortcode ) {
            wp_enqueue_style( 'ecp-styles', $this->plugin_url . 'assets/css/ecp-styles.css', [], ECP_VERSION );
            wp_enqueue_script( 'ecp-admin-dashboard-js', $this->plugin_url . 'assets/js/ecp-admin-dashboard.js', ['jquery'], ECP_VERSION, true );
            wp_enqueue_script( 'ecp-file-manager-js', $this->plugin_url . 'assets/js/file-manager.js', ['ecp-admin-dashboard-js'], ECP_VERSION, true );
            wp_localize_script( 'ecp-admin-dashboard-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ecp_admin_ajax_nonce' ),
            ]);
        }
    }

    /**
     * Enqueues scripts and styles for the admin area.
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'ecp_client_page_ecp-settings' || $hook === 'client-portal_page_ecp-audit-log') {
             wp_enqueue_style( 'ecp-admin-styles', $this->plugin_url . 'assets/css/ecp-admin-styles.css', [], ECP_VERSION );
        }

        if ( 'ecp_client_page_ecp-settings' === $hook ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'ecp-admin-settings-js', $this->plugin_url . 'assets/js/ecp-admin-settings.js', [ 'jquery', 'wp-color-picker' ], ECP_VERSION, true );
            wp_localize_script( 'ecp-admin-settings-js', 'ecp_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ecp_admin_ajax_nonce' ),
            ]);
        }
    }

    /**
     * Outputs the custom CSS variables to the head of the site.
     */
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

