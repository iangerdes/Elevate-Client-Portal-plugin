<?php
// File: elevate-client-portal/elevate-client-portal.php
/**
 * Plugin Name:       Elevate Client Portal
 * Description:       A private portal for clients to download files uploaded by an administrator.
 * Version:           119.0.0 (Role Registration Fix)
 * Author:            Elevate Agency Ltd
 * Author URI:        https://www.elevatedigital.agency/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ecp
 * Domain Path:       /languages
 * @comment Added a robust role registration system that runs on activation and every page load to ensure custom roles are always available, fixing the "Role not found" error.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Hide the admin bar on the front-end for non-admins.
 * This is placed here to ensure it loads early.
 */
add_action('after_setup_theme', function() {
    if ( ! is_admin() && ! current_user_can('manage_options') ) {
        show_admin_bar(false);
    }
});

define( 'ECP_VERSION', '119.0.0' );
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
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        add_action('plugins_loaded', [ $this, 'init_early_handlers' ]);
        
        add_action('init', [ $this, 'init_plugin' ]);
        add_action('init', [ $this, 'load_textdomain' ]);
    }

    /**
     * ** FIX: This function now loads both the download and background ZIP handlers early. **
     */
    public function init_early_handlers() {
        $early_files = [
            'includes/helpers/class-ecp-security-helper.php',
            'includes/class-ecp-s3.php',
            'admin/includes/class-ecp-file-helper.php',
            'admin/includes/class-ecp-file-operations.php',
            'includes/class-ecp-audit-log.php',
            'includes/download-handlers/class-ecp-standard-file-handler.php',
            'includes/download-handlers/class-ecp-encrypted-file-handler.php',
            'includes/download-handlers/class-ecp-zip-file-handler.php',
            'includes/class-ecp-download-handler.php',
            'includes/class-ecp-background-zip-handler.php', // ** FIX: Load background zip handler early **
        ];
        foreach ($early_files as $file) {
            if ( file_exists( ECP_PLUGIN_PATH . $file ) ) {
                require_once ECP_PLUGIN_PATH . $file;
            }
        }
        
        // ** FIX: Initialize both handlers **
        if ( class_exists('ECP_Download_Handler') ) {
            ECP_Download_Handler::get_instance();
        }
        if ( class_exists('ECP_Background_Zip_Handler') ) {
            ECP_Background_Zip_Handler::get_instance();
        }
    }

    public function init_plugin() {
        if ( file_exists( ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/aws-autoloader.php';
        } elseif ( file_exists( ECP_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
            require_once ECP_PLUGIN_PATH . 'vendor/autoload.php';
        }

        $this->load_dependencies();
        
        ECP_User_Manager::register_custom_roles();

        $this->init_components();
    }
    
    private function load_dependencies() {
        $files_to_load = [
            'includes/helpers/class-ecp-permissions-helper.php',
            'includes/class-ecp-shortcode-helper.php',
            'includes/class-ecp-asset-manager.php',
            'includes/asset-loaders/class-ecp-admin-dashboard-assets.php',
            'includes/asset-loaders/class-ecp-client-portal-assets.php',
            'includes/asset-loaders/class-ecp-login-assets.php',
            'includes/class-ecp-auth-handler.php',
            'includes/class-ecp-shortcodes.php',
            'includes/class-ecp-cron-handler.php', 
            'admin/class-ecp-admin.php',
            'admin/class-ecp-settings.php',
            'admin/includes/class-ecp-user-manager.php',
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
        ECP_Shortcodes::get_instance();
        ECP_Client_Portal::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Admin_Dashboard::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Login::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL );
        ECP_Impersonation_Handler::get_instance();
        ECP_Audit_Log::get_instance();
        ECP_Cron_Handler::get_instance();

        ECP_Admin_Ajax_Handler::get_instance();
        ECP_Ajax_File_Manager::get_instance();
        ECP_Client_Portal_Ajax_Handler::get_instance();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'ecp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function activate() {
        require_once ECP_PLUGIN_PATH . 'admin/includes/class-ecp-user-manager.php';
        ECP_User_Manager::register_custom_roles();

        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-audit-log.php';
        ECP_Audit_Log::create_table();
        
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-cron-handler.php';
        ECP_Cron_Handler::schedule_events();
    }
    
    public function deactivate() {
        require_once ECP_PLUGIN_PATH . 'includes/class-ecp-cron-handler.php';
        ECP_Cron_Handler::unschedule_events();
    }
}

Elevate_Client_Portal_Init::get_instance();

