<?php
// File: elevate-client-portal.php
/**
 * Plugin Name:       Elevate Client Portal
 * Description:       A private portal for clients to download files uploaded by an administrator.
 * Version:           9.0.1 (Autoloader Fix)
 * Author:            Elevate Agency Ltd
 * Author URI:        https://www.elevatedigital.agency/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ecp
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants.
define( 'ECP_VERSION', '9.0.1' );
define( 'ECP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ECP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main initialization class for the Elevate Client Portal.
 */
final class Elevate_Client_Portal_Init {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action('plugins_loaded', [ $this, 'init_plugin' ]);
    }

    /**
     * Initializes the plugin by loading all necessary files and instantiating classes.
     */
    public function init_plugin() {
        // ** FIX: Add support for both Composer and manual AWS SDK installations. **
        if ( file_exists( ECP_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/autoload.php';
        } elseif ( file_exists( ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php';
        }

        $this->load_classes();
        $this->instantiate_classes();
        $this->load_textdomain();
    }
    
    /**
     * Loads all the required class files for the plugin.
     */
    private function load_classes() {
        $files_to_load = [
            'includes/class-ecp-s3.php',
            'includes/class-ecp-asset-manager.php',
            'includes/class-ecp-auth-handler.php',
            'includes/class-ecp-download-handler.php',
            'includes/class-ecp-shortcodes.php',
            'includes/class-ecp-audit-log.php',
            'admin/class-ecp-admin.php',
            'admin/class-ecp-settings.php',
            'admin/includes/class-ecp-file-helper.php',
            'admin/includes/class-ecp-user-manager.php',
            'admin/includes/class-ecp-file-operations.php',
            'admin/includes/class-ecp-folder-operations.php',
            'admin/includes/class-ecp-bulk-actions.php',
            'admin/includes/class-ecp-impersonation-handler.php',
            'frontend/class-ecp-login.php',
            'frontend/class-ecp-client-portal.php',
            'frontend/class-ecp-admin-dashboard.php',
            'frontend/class-ecp-file-manager.php',
        ];
        foreach ($files_to_load as $file) require_once ECP_PLUGIN_PATH . $file;
    }

    /**
     * Instantiates all the necessary classes for the plugin to run.
     */
    private function instantiate_classes() {
        ECP_Admin::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Settings::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Auth_Handler::get_instance();
        ECP_Download_Handler::get_instance();
        ECP_Shortcodes::get_instance();
        ECP_Client_Portal::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Admin_Dashboard::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Login::get_instance();
        ECP_Impersonation_Handler::get_instance();
        ECP_Audit_Log::get_instance();
    }

    /**
     * Loads the plugin's text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'ecp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Runs on plugin activation to set up roles and the audit log table.
     */
    public function activate() {
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-audit-log.php';
        ECP_Audit_Log::create_table();
        // Add roles and other activation tasks if needed
    }
}

Elevate_Client_Portal_Init::get_instance();

