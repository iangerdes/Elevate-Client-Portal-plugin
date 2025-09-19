<?php
// File: elevate-client-portal/elevate-client-portal.php
/**
 * Plugin Name:       Elevate Client Portal
 * Description:       A private portal for clients to download files uploaded by an administrator.
 * Version:           74.0.0 (Singleton Fix)
 * Author:            Elevate Agency Ltd
 * Author URI:        https://www.elevatedigital.agency/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ecp
 * Domain Path:       /languages
 * @comment Fixed a fatal error by correctly implementing the Singleton pattern in all AJAX handler classes, ensuring stable initialization.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'ECP_VERSION', '74.0.0' );
define( 'ECP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ECP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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

    public function init_plugin() {
        if ( file_exists( ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php';
        } elseif ( file_exists( ECP_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/autoload.php';
        }

        $this->load_dependencies();
        $this->init_components();
        $this->load_textdomain();
    }
    
    private function load_dependencies() {
        $files_to_load = [
            'includes/helpers/class-ecp-security-helper.php',
            'includes/helpers/class-ecp-permissions-helper.php',
            'includes/class-ecp-shortcode-helper.php',
            'includes/class-ecp-s3.php',
            'admin/includes/class-ecp-file-helper.php',
            'includes/class-ecp-asset-manager.php',
            'includes/class-ecp-auth-handler.php',
            'includes/class-ecp-download-handler.php',
            'includes/download-handlers/class-ecp-standard-file-handler.php',
            'includes/download-handlers/class-ecp-encrypted-file-handler.php',
            'includes/download-handlers/class-ecp-zip-file-handler.php',
            'includes/class-ecp-shortcodes.php',
            'includes/class-ecp-audit-log.php',
            'admin/class-ecp-admin.php',
            'admin/class-ecp-settings.php',
            'admin/includes/class-ecp-user-manager.php',
            'admin/includes/class-ecp-file-operations.php',
            'admin/includes/class-ecp-folder-operations.php',
            'admin/includes/class-ecp-bulk-actions.php',
            'admin/includes/class-ecp-impersonation-handler.php',
            'admin/includes/class-ecp-admin-ajax-handler.php',
            'admin/includes/class-ecp-ajax-file-manager.php',
            'frontend/class-ecp-login.php',
            'frontend/class-ecp-client-portal.php',
            'frontend/class-ecp-admin-dashboard.php',
            'frontend/class-ecp-file-manager.php',
            'frontend/includes/class-ecp-client-portal-ajax-handler.php',
        ];
        foreach ($files_to_load as $file) require_once ECP_PLUGIN_PATH . $file;
    }

    private function init_components() {
        ECP_Admin::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Settings::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Auth_Handler::get_instance();
        ECP_Download_Handler::get_instance();
        ECP_Shortcodes::get_instance();
        ECP_Client_Portal::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Admin_Dashboard::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Login::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Impersonation_Handler::get_instance();
        ECP_Audit_Log::get_instance();

        // ** FIX: Use the correct get_instance() method for all AJAX handlers. **
        ECP_Admin_Ajax_Handler::get_instance();
        ECP_Ajax_File_Manager::get_instance();
        ECP_Client_Portal_Ajax_Handler::get_instance();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'ecp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function activate() {
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-audit-log.php';
        ECP_Audit_Log::create_table();
    }
}

Elevate_Client_Portal_Init::get_instance();

