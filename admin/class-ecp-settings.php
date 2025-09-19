<?php
// File: admin/class-ecp-settings.php
/**
 * Handles the admin settings page for the Elevate Client Portal.
 *
 * @package Elevate_Client_Portal
 * @version 9.0.1 (Stable Refactor)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Settings
 * Manages the creation and handling of the plugin's settings page.
 */
class ECP_Settings {

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
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_ecp_test_s3_connection', [ $this, 'ajax_test_s3_connection' ] );
    }

    /**
     * Adds all of the plugin's pages to the WordPress admin menu.
     */
    public function add_settings_page() {
        add_submenu_page('edit.php?post_type=ecp_client', __('Settings', 'ecp'), __('Settings', 'ecp'), 'manage_options', 'ecp-settings', [ $this, 'render_settings_page' ]);
        add_submenu_page('edit.php?post_type=ecp_client', __('S3 File Browser', 'ecp'), __('S3 File Browser', 'ecp'), 'manage_options', 'ecp-s3-browser', [ $this, 'render_s3_browser_page' ]);
        add_submenu_page('edit.php?post_type=ecp_client', __('Audit Log', 'ecp'), __('Audit Log', 'ecp'), 'manage_options', 'ecp-audit-log', [ $this, 'render_audit_log_page' ]);
    }
    
    public function render_settings_page() { require_once $this->plugin_path . 'admin/views/admin-settings-page.php'; }
    public function render_s3_browser_page() { require_once $this->plugin_path . 'admin/views/admin-s3-browser-page.php'; }
    public function render_audit_log_page() { require_once $this->plugin_path . 'admin/views/admin-audit-log-page.php'; }

    /**
     * Registers all settings, sections, and fields for the settings page.
     */
    public function register_settings() {
        register_setting( 'ecp_settings_group', 'ecp_style_options' );
        register_setting( 'ecp_settings_group', 'ecp_s3_options' );

        // Styling Section
        add_settings_section('ecp_styling_section', __('Styling Options', 'ecp'), '__return_false', 'ecp-settings');
        add_settings_field('ecp_primary_color', __('Primary Color', 'ecp'), [ $this, 'render_color_picker' ], 'ecp-settings', 'ecp_styling_section', ['id' => 'primary_color', 'option_name' => 'ecp_style_options', 'default' => '#007cba']);
        add_settings_field('ecp_secondary_color', __('Secondary Color', 'ecp'), [ $this, 'render_color_picker' ], 'ecp-settings', 'ecp_styling_section', ['id' => 'secondary_color', 'option_name' => 'ecp_style_options', 'default' => '#f0f6fc']);

        // S3 Storage Section
        add_settings_section( 'ecp_s3_section', __( 'S3 Storage', 'ecp' ), [ $this, 'render_s3_section_text' ], 'ecp-settings' );
        add_settings_field( 'ecp_s3_bucket', __( 'Bucket Name', 'ecp' ), [ $this, 'render_text_input' ], 'ecp-settings', 'ecp_s3_section', [ 'id' => 's3_bucket', 'option_name' => 'ecp_s3_options' ] );
        add_settings_field( 'ecp_s3_region', __( 'Region', 'ecp' ), [ $this, 'render_text_input' ], 'ecp-settings', 'ecp_s3_section', [ 'id' => 's3_region', 'option_name' => 'ecp_s3_options', 'placeholder' => 'e.g., us-east-1' ] );
        add_settings_field( 'ecp_s3_access_key', __( 'Access Key ID', 'ecp' ), [ $this, 'render_text_input' ], 'ecp-settings', 'ecp_s3_section', [ 'id' => 's3_access_key', 'option_name' => 'ecp_s3_options' ] );
        add_settings_field( 'ecp_s3_secret_key', __( 'Secret Access Key', 'ecp' ), [ $this, 'render_password_input' ], 'ecp-settings', 'ecp_s3_section', [ 'id' => 's3_secret_key', 'option_name' => 'ecp_s3_options' ] );
        add_settings_field( 'ecp_s3_test_connection', '', [ $this, 'render_test_s3_button' ], 'ecp-settings', 'ecp_s3_section' );
    }

    public function render_s3_section_text() {
        echo '<p>' . __( 'Configure your Amazon S3 bucket details to offload file storage.', 'ecp' ) . '</p>';
        if ( ! class_exists( 'Aws\\S3\\S3Client' ) ) {
            echo '<div class="notice notice-warning"><p><strong>' . __( 'Warning:', 'ecp' ) . '</strong> ' . __( 'The AWS SDK for PHP is not detected. Please install the SDK to use the S3 functionality.', 'ecp' ) . '</p></div>';
        }
    }

    public function render_text_input( $args ) {
        $options = get_option( $args['option_name'] );
        $value = $options[ $args['id'] ] ?? '';
        $placeholder = isset($args['placeholder']) ? 'placeholder="' . esc_attr($args['placeholder']) . '"' : '';
        echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_name'] ) . '[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" ' . $placeholder . ' />';
    }
    
    public function render_password_input( $args ) {
        $options = get_option( $args['option_name'] );
        $value = $options[ $args['id'] ] ?? '';
        echo '<input type="password" id="' . esc_attr( $args['id'] ) . '" name="' . esc_attr( $args['option_name'] ) . '[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }

    public function render_color_picker( $args ) {
        $options = get_option( $args['option_name'] );
        $value = $options[ $args['id'] ] ?? $args['default'];
        echo '<input type="text" name="' . esc_attr( $args['option_name'] ) . '[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="ecp-color-picker" />';
    }

    public function render_test_s3_button() {
        echo '<button type="button" id="ecp-test-s3-btn" class="button">' . __( 'Test S3 Connection', 'ecp' ) . '</button>';
        echo '<span id="ecp-s3-test-results" style="margin-left: 10px;"></span>';
    }

    /**
     * AJAX handler for testing the S3 connection.
     */
    public function ajax_test_s3_connection() {
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $s3_handler = ECP_S3::get_instance();
        $result = $s3_handler->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        wp_send_json_success( [ 'message' => 'Connection successful!' ] );
    }
}

