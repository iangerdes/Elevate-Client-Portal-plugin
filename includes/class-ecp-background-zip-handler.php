<?php
// File: elevate-client-portal/includes/class-ecp-background-zip-handler.php
/**
 * Handles the background processing of ZIP file creation.
 *
 * @package Elevate_Client_Portal
 * @version 105.0.0 (Add Native ZIP Encryption)
 * @comment Implemented AES-256 password protection for generated ZIP archives and added server compatibility checks.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Background_Zip_Handler {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'ecp_background_create_zip_action', [ $this, 'process_zip_creation' ], 10, 2 );
    }

    public function process_zip_creation( $user_id, $file_keys ) {
        error_log('[ECP ZIP] Starting background ZIP creation for user ID: ' . $user_id);

        if ( ! $user_id || empty( $file_keys ) ) {
            error_log('[ECP ZIP] Error: No user ID or file keys provided. Aborting.');
            return;
        }

        if ( ! class_exists('ZipArchive') ) {
            error_log('[ECP ZIP] Error: ZipArchive class does not exist on this server. Aborting.');
            return;
        }

        if (!defined('ZipArchive::EM_AES_256')) {
            error_log('[ECP ZIP] FATAL ERROR: This server\'s PHP version does not support AES-256 ZIP encryption. Cannot create encrypted archive.');
            return;
        }
        
        set_time_limit(600);

        $all_possible_files = ECP_File_Helper::get_hydrated_files_for_user($user_id);
        $files_to_zip = [];
    
        foreach ($all_possible_files as $file) {
            $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : '');
            if (in_array($current_key, $file_keys) && empty($file['is_encrypted'])) {
                $files_to_zip[] = $file;
            }
        }
    
        if (empty($files_to_zip)) {
            error_log('[ECP ZIP] Error: No valid, non-encrypted files found for the provided keys. Aborting.');
            return;
        }
    
        $upload_dir = wp_upload_dir();
        $tmp_dir = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';
        if (!file_exists($tmp_dir)) { 
            wp_mkdir_p($tmp_dir); 
            error_log('[ECP ZIP] Created temp_zips directory at: ' . $tmp_dir);
        }

        if (!is_writable($tmp_dir)) {
             error_log('[ECP ZIP] FATAL ERROR: The temporary ZIP folder is not writable: ' . $tmp_dir);
             return;
        }
    
        $zip_filename = 'elevate-portal-files-' . $user_id . '-' . time() . '.zip';
        $zip_filepath = $tmp_dir . $zip_filename;
        $zip = new ZipArchive();
        $s3_handler = ECP_S3::get_instance();
        $password = wp_generate_password(12, true, true);

        if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            error_log('[ECP ZIP] Error: Could not open ZIP file for writing at: ' . $zip_filepath);
            return;
        }
        
        if (!$zip->setPassword($password)) {
            error_log('[ECP ZIP] Error: Could not set password on the ZIP archive.');
            $zip->close();
            wp_delete_file($zip_filepath);
            return;
        }
    
        $temp_files_to_delete = [];
        error_log('[ECP ZIP] Processing ' . count($files_to_zip) . ' files for the archive.');

        foreach ($files_to_zip as $file_data) {
            $file_contents = ECP_File_Operations::get_file_contents($file_data);

            if ($file_contents !== null && !is_wp_error($file_contents)) {
                if ($zip->addFromString($file_data['name'], $file_contents)) {
                    $index = $zip->locateName($file_data['name'], ZipArchive::FL_NODIR);
                    if ($index !== false) {
                        if (!$zip->setEncryptionIndex($index, ZipArchive::EM_AES_256)) {
                             error_log('[ECP ZIP] Warning: Could not set AES-256 encryption for file: ' . esc_html($file_data['name']));
                        }
                    }
                } else {
                     error_log('[ECP ZIP] Warning: Could not add file to archive: ' . esc_html($file_data['name']));
                }
            }
        }
    
        $zip->close();
        error_log('[ECP ZIP] Encrypted ZIP archive closed. Filepath: ' . $zip_filepath);

        if (!file_exists($zip_filepath) || filesize($zip_filepath) === 0) {
            error_log('[ECP ZIP] Error: ZIP file is empty or does not exist after creation process. Deleting empty file.');
            if (file_exists($zip_filepath)) { wp_delete_file($zip_filepath); }
            return;
        }

        $user_zips = get_user_meta($user_id, '_ecp_ready_zips', true) ?: [];
        $user_zips[$zip_filename] = [
            'password' => $password,
            'timestamp' => time(),
        ];
        update_user_meta($user_id, '_ecp_ready_zips', $user_zips);
        error_log('[ECP ZIP] SUCCESS: Created encrypted ZIP and updated user meta for user ID: ' . $user_id);
    }
}

