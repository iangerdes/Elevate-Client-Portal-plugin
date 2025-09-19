<?php
// File: admin/includes/class-ecp-file-helper.php
/**
 * A helper class for retrieving and formatting file data.
 *
 * @package Elevate_Client_Portal
 * @version 6.6.1 (Stable Find Logic)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class ECP_File_Helper
 *
 * Provides static methods for fetching and processing file lists from the database and S3.
 */
class ECP_File_Helper {

    /**
     * Finds a specific file by its unique hash/key for a given user context.
     *
     * @param string $hash             The file's unique identifier (S3 key or md5 hash).
     * @param int    $user_id          The primary user context to search within. 0 for "All Users".
     * @param bool   $search_all_users If true, also searches the "All Users" files if not found in the primary context.
     * @return array|null An array containing file details and ownership, or null if not found.
     */
    public static function find_file_by_hash( $hash, $user_id, $search_all_users = false ) {
        // Search in the primary user context first.
        if ( $user_id > 0 ) {
            $user_files = get_user_meta( $user_id, '_ecp_client_file', false );
            if ( ! empty( $user_files ) ) {
                foreach ( $user_files as $file_data ) {
                    $current_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : '');
                    if ( $current_key === $hash ) {
                        return [ 'user_id' => $user_id, 'original_data' => $file_data ];
                    }
                }
            }
        } else { // Primary context is "All Users"
            $all_users_files = get_option( '_ecp_all_users_files', [] );
            if ( ! empty( $all_users_files ) ) {
                foreach ( $all_users_files as $file_data ) {
                    $current_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : '');
                    if ( $current_key === $hash ) {
                        return [ 'user_id' => 0, 'original_data' => $file_data ];
                    }
                }
            }
        }
        
        // If specified, perform a fallback search in the "All Users" scope.
        if ( $search_all_users && $user_id > 0 ) {
            $all_users_files = get_option( '_ecp_all_users_files', [] );
             if ( ! empty( $all_users_files ) ) {
                foreach ( $all_users_files as $file_data ) {
                    $current_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : '');
                    if ( $current_key === $hash ) {
                        return [ 'user_id' => 0, 'original_data' => $file_data ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Gets a complete list of files for a specific user, combining their personal files
     * and any non-excluded "All Users" files, then hydrates them with metadata.
     *
     * @param int $user_id The ID of the user.
     * @return array An array of hydrated file data.
     */
    public static function get_hydrated_files_for_user( $user_id ) {
        $user_files      = get_user_meta( $user_id, '_ecp_client_file', false );
        $all_users_files = get_option( '_ecp_all_users_files', [] );

        $available_all_users_files = array_filter( $all_users_files, function( $file ) use ( $user_id ) {
            return ! in_array( $user_id, $file['excluded_users'] ?? [] );
        });

        $merged_files = array_merge( is_array( $user_files ) ? $user_files : [], is_array( $available_all_users_files ) ? $available_all_users_files : [] );

        return self::hydrate_files( $merged_files );
    }

    /**
     * Gets the list of "All Users" files and hydrates them with metadata.
     *
     * @return array An array of hydrated file data for "All Users".
     */
    public static function get_hydrated_all_users_files() {
        $all_users_files = get_option( '_ecp_all_users_files', [] );
        return self::hydrate_files( is_array( $all_users_files ) ? $all_users_files : [] );
    }

    /**
     * Hydrates a list of file arrays with live metadata (size, timestamp) from
     * either the local filesystem or Amazon S3.
     *
     * @param array $files An array of file data from the database.
     * @return array The same array of files, but with 'size' and 'timestamp' keys added/updated.
     */
    private static function hydrate_files( $files ) {
        $s3_handler = ECP_S3::get_instance();
        $hydrated_files = [];

        foreach ( $files as $file_data ) {
            if ( ! is_array( $file_data ) ) continue;

            $file_data['size'] = 0;
            $file_data['timestamp'] = time();

            if ( ! empty( $file_data['s3_key'] ) && $s3_handler->is_s3_enabled() ) {
                $metadata = $s3_handler->get_file_metadata( $file_data['s3_key'] );
                if ( ! is_wp_error( $metadata ) ) {
                    $file_data['size'] = $metadata['size'];
                    $file_data['timestamp'] = $metadata['timestamp'];
                }
            } elseif ( ! empty( $file_data['path'] ) && file_exists( $file_data['path'] ) ) {
                $file_data['size'] = filesize( $file_data['path'] );
                $file_data['timestamp'] = filemtime( $file_data['path'] );
            }
            $hydrated_files[] = $file_data;
        }

        return $hydrated_files;
    }

    /**
     * Formats a file size in bytes into a human-readable string (e.g., KB, MB).
     *
     * @param int $bytes The file size in bytes.
     * @return string The formatted file size.
     */
    public static function format_file_size( $bytes ) {
        if ( is_wp_error($bytes) || !is_numeric($bytes) ) {
            return 'N/A';
        }
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }
}

