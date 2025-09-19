<?php
// File: admin/class-ecp-admin.php
/**
 * Main controller for the WordPress admin area.
 *
 * @package Elevate_Client_Portal
 * @version 11.0.0 (Full Audit & Refactor)
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

        add_action( 'init', [ $this, 'register_dummy_post_type' ] );
    }

    /**
     * Registers a non-public post type to create the top-level admin menu.
     * This is a standard WordPress method for adding a top-level menu item
     * without creating a functional post type.
     */
    public function register_dummy_post_type() {
        $labels = [
            'name'          => _x( 'Client Portal', 'post type general name', 'ecp' ),
            'singular_name' => _x( 'Client', 'post type singular name', 'ecp' ),
            'menu_name'     => _x( 'Client Portal', 'admin menu', 'ecp' ),
        ];
        $args = [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'menu_position' => 25,
            'menu_icon'     => 'dashicons-groups',
            'supports'      => false,
            'has_archive'   => false,
            'rewrite'       => false,
        ];
        register_post_type( 'ecp_client', $args );
    }
}
