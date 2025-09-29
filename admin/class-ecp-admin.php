<?php
// File: admin/class-ecp-admin.php
/**
 * Main controller for the WordPress admin area.
 *
 * @package Elevate_Client_Portal
 * @version 128.0.0 (Admin Menu Fix)
 * @comment Refactored the admin menu creation to use the standard `add_menu_page` function instead of a dummy post type. This is a more robust method that prevents conflicts and ensures the menu always appears.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_Admin
 *
 * Initializes the admin-side functionality of the plugin, including
 * creating the main menu and loading backend dependencies.
 */
class ECP_Admin {

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
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
    }

    /**
     * Creates the top-level "Client Portal" menu in the WordPress admin area.
     */
    public function create_admin_menu() {
        add_menu_page(
            __( 'Client Portal', 'ecp' ), // Page Title
            __( 'Client Portal', 'ecp' ),           // Menu Title
            'manage_options',                       // Capability
            'ecp-settings',                         // Menu Slug
            [ ECP_Settings::get_instance( $this->plugin_path, $this->plugin_url ), 'render_settings_page' ], // Function
            'dashicons-groups',                     // Icon
            25                                      // Position
        );
    }
}
