<?php
// File: elevate-client-portal/includes/class-ecp-s3.php
/**
 * A helper class for all Amazon S3 functionality.
 *
 * @package Elevate_Client_Portal
 * @version 121.0.0 (Custom S3 Subfolder)
 * @comment Added logic to use a customizable subfolder for all S3 uploads, which defaults to the site's domain if not specified.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_S3 {

    private static $instance;
    private $s3_client;
    private $options;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option( 'ecp_s3_options', [] );
        $this->initialize_s3_client();
    }

    private function initialize_s3_client() {
        if ( ! $this->is_s3_enabled() || ! class_exists( 'Aws\\S3\\S3Client' ) ) {
            $this->s3_client = null;
            return;
        }

        try {
            $this->s3_client = new Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => $this->options['s3_region'],
                'credentials' => [
                    'key'    => $this->options['s3_access_key'],
                    'secret' => $this->options['s3_secret_key'],
                ],
            ]);
        } catch (\Exception $e) {
            $this->s3_client = null;
            // Optionally log this error
        }
    }

    public function is_s3_enabled() {
        return ! empty( $this->options['s3_bucket'] ) && ! empty( $this->options['s3_region'] ) && ! empty( $this->options['s3_access_key'] ) && ! empty( $this->options['s3_secret_key'] );
    }

    /**
     * Gets the base path for S3 uploads, including the custom subfolder.
     *
     * @return string The base path for uploads (e.g., 'client-files/your-domain-name/').
     */
    public function get_s3_base_path() {
        $base_path = 'client-files/';
        
        // Get the custom subfolder from settings.
        $subfolder = !empty($this->options['s3_subfolder']) ? sanitize_key($this->options['s3_subfolder']) : '';

        // If the subfolder is empty, use the site domain as a default.
        if (empty($subfolder)) {
            $site_url = home_url();
            $domain = wp_parse_url($site_url, PHP_URL_HOST);
            // Remove 'www.' if it exists
            if (substr($domain, 0, 4) == 'www.') {
                $domain = substr($domain, 4);
            }
            $subfolder = sanitize_key($domain);
        }
        
        // Ensure the path ends with a slash.
        return trailingslashit($base_path . $subfolder);
    }

    public function test_connection() {
        if ( ! $this->s3_client ) return new WP_Error( 's3_sdk_missing', 'The AWS SDK is not loaded or S3 is not configured correctly.' );
        try {
            $this->s3_client->headBucket([ 'Bucket' => $this->options['s3_bucket'] ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_connection_failed', 'Could not connect. Details: ' . $e->getMessage() );
        }
    }

    public function upload_file( $file_path, $file_name ) {
        if ( ! $this->s3_client ) return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        
        $base_path = $this->get_s3_base_path();
        $s3_key = $base_path . wp_generate_uuid4() . '/' . sanitize_file_name($file_name);
        
        try {
            $this->s3_client->putObject([
                'Bucket'     => $this->options['s3_bucket'],
                'Key'        => $s3_key,
                'SourceFile' => $file_path,
            ]);
            $meta = $this->get_file_metadata( $s3_key );
            return [ 'key' => $s3_key, 'size' => $meta['size'] ?? 0 ];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_upload_failed', $e->getMessage() );
        }
    }

    public function delete_file( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        try {
            $this->s3_client->deleteObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_delete_failed', $e->getMessage() );
        }
    }
    
    public function update_file_contents( $s3_key, $contents ) {
        if ( ! $this->s3_client ) {
            return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        }
        try {
            $this->s3_client->putObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
                'Body'   => $contents,
            ]);
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_update_failed', $e->getMessage() );
        }
    }

    public function get_file_metadata( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        try {
            $result = $this->s3_client->headObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return [
                'size'      => $result['ContentLength'] ?? 0,
                'timestamp' => isset($result['LastModified']) ? $result['LastModified']->getTimestamp() : time(),
            ];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_metadata_failed', $e->getMessage() );
        }
    }
    
    public function get_presigned_url( $s3_key, $file_name ) {
        if ( ! $this->s3_client ) {
            return new WP_Error( 's3_not_configured', 'S3 is not configured.' );
        }
        try {
            $cmd = $this->s3_client->getCommand('GetObject', [
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
                'ResponseContentDisposition' => 'attachment; filename="' . $file_name . '"',
            ]);
            $request = $this->s3_client->createPresignedRequest($cmd, '+15 minutes');
            return (string) $request->getUri();
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_presign_failed', $e->getMessage() );
        }
    }

    public function get_file_contents( $s3_key ) {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        try {
            $result = $this->s3_client->getObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
            ]);
            return (string) $result['Body'];
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_get_contents_failed', $e->getMessage() );
        }
    }

    public function stream_s3_file_to_zip($zip, $s3_key, $filename_in_zip) {
        if (!$this->s3_client) {
            return false;
        }
        try {
            $temp_stream = fopen('php://temp', 'r+');
            $this->s3_client->getObject([
                'Bucket' => $this->options['s3_bucket'],
                'Key'    => $s3_key,
                'SaveAs' => $temp_stream,
            ]);
            rewind($temp_stream);
            $zip->addFileFromStream($filename_in_zip, $temp_stream);
            if (is_resource($temp_stream)) {
                fclose($temp_stream);
            }
            return true;
        } catch (\Exception $e) {
            // Log error: $e->getMessage()
            if (is_resource($temp_stream)) {
                fclose($temp_stream);
            }
            return false;
        }
    }

    public function get_all_s3_files() {
        if ( ! $this->s3_client ) return new WP_Error('s3_not_configured', 'S3 not configured.');
        
        $base_path = $this->get_s3_base_path();
        
        try {
            $result = $this->s3_client->listObjectsV2([
                'Bucket' => $this->options['s3_bucket'],
                'Prefix' => $base_path,
            ]);
            
            $files = [];
            foreach ($result['Contents'] ?? [] as $object) {
                if (substr($object['Key'], -1) === '/') continue;
                $files[] = [
                    'key'           => $object['Key'],
                    'size'          => $object['Size'],
                    'last_modified' => $object['LastModified']
                ];
            }
            return $files;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_list_failed', $e->getMessage() );
        }
    }
}

