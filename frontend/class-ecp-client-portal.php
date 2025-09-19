<?php
// File: frontend/class-ecp-client-portal.php
/**
 * Handles all logic for the [client_portal] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 6.6.0 (Standardized Nonce)
 */
class ECP_Client_Portal {

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

        require_once $this->plugin_path . 'admin/includes/class-ecp-file-helper.php';

        add_shortcode( 'client_portal', [ $this, 'render_shortcode' ] );
        add_shortcode( 'ecp_account', [ $this, 'render_account_shortcode' ] );
        add_action( 'wp_ajax_ecp_filter_files', [ $this, 'ajax_filter_files_handler' ] );
        add_action( 'wp_ajax_ecp_create_zip', [ $this, 'ajax_create_zip_handler' ] );
        add_action( 'wp_ajax_ecp_send_manager_email', [ $this, 'ajax_send_manager_email' ] );
        add_action( 'wp_ajax_ecp_update_account', [ $this, 'ajax_update_account_handler' ] );
        add_action( 'wp_ajax_ecp_get_account_page', [ $this, 'ajax_get_account_page_handler' ] );
    }
    
    public function ajax_filter_files_handler() {
        // ** FIX: Use the standardized nonce for all AJAX requests. **
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );
        $user_id = get_current_user_id(); 
        if(!$user_id) { wp_send_json_error(['message' => 'Not logged in.']); }

        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sort_order = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc';
        $folder = isset($_POST['folder']) ? sanitize_text_field($_POST['folder']) : 'all';
        
        $all_files = ECP_File_Helper::get_hydrated_files_for_user($user_id);
        
        $filtered_files_data = [];

        if (is_array($all_files)) {
            $filtered_files = [];
            foreach($all_files as $key => $file) {
                if (is_array($file) && isset($file['name'])) {
                    $in_search = empty($search_term) || stripos($file['name'], $search_term) !== false;
                    $current_folder = isset($file['folder']) ? $file['folder'] : '/';
                    $in_folder = $folder === 'all' || $current_folder === $folder;
                    if($in_search && $in_folder) { 
                        $file['original_key'] = $key;
                        $filtered_files[] = $file;
                    }
                }
            }

            uasort($filtered_files, function($a, $b) use ($sort_order) {
                switch ($sort_order) {
                    case 'name_asc': return strcasecmp($a['name'], $b['name']);
                    case 'name_desc': return strcasecmp($b['name'], $a['name']);
                    case 'date_asc': return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
                    default: return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
                }
            });
            
            foreach ($filtered_files as $file_data) {
                $file_key = $file_data['s3_key'] ?? (isset($file_data['path']) ? md5($file_data['path']) : '');
                $download_link = wp_nonce_url( add_query_arg( [ 'ecp_action' => 'download_file', 'file_key' => $file_key ], home_url() ), 'ecp_download_file_nonce' );
                
                $filtered_files_data[] = [
                    'key'          => $file_key,
                    'name'         => esc_html($file_data['name']),
                    'folder'       => esc_html(($file_data['folder'] ?? '/') === '/' ? 'Uncategorized' : $file_data['folder']),
                    'date'         => esc_html(date_i18n(get_option('date_format'), $file_data['timestamp'] ?? time())),
                    'download_url' => $download_link,
                    'size'         => ECP_File_Helper::format_file_size($file_data['size']),
                    'size_bytes'   => $file_data['size'],
                    'is_encrypted' => !empty($file_data['is_encrypted']),
                    'type'         => $file_data['type'] ?? 'application/octet-stream'
                ];
            }
        }
        
        wp_send_json_success($filtered_files_data);
    }

    public function ajax_create_zip_handler() { 
        check_ajax_referer('ecp_admin_ajax_nonce', 'nonce');
        $user_id = get_current_user_id();
        if (!$user_id || !isset($_POST['file_keys']) || !is_array($_POST['file_keys'])) {
            wp_send_json_error(['message' => __('Invalid request.', 'ecp')]);
        }
        if (!class_exists('ZipArchive')) {
            wp_send_json_error(['message' => __('Error: ZipArchive PHP extension is not installed on your server.', 'ecp')]);
        }
        if (version_compare(PHP_VERSION, '7.2.0', '<') && !defined('ZipArchive::EM_AES_256')) {
            wp_send_json_error(['message' => __('Error: Your server does not support AES-256 ZIP encryption.', 'ecp')]);
        }

        $file_keys = array_map('sanitize_text_field', $_POST['file_keys']);
        $all_possible_files = ECP_File_Helper::get_hydrated_files_for_user($user_id);
        $s3_handler = ECP_S3::get_instance();
        $files_to_zip = [];
    
        foreach ($all_possible_files as $file) {
            $current_key = $file['s3_key'] ?? (isset($file['path']) ? md5($file['path']) : '');
            if (in_array($current_key, $file_keys)) {
                $files_to_zip[] = $file;
            }
        }
    
        if (empty($files_to_zip)) {
            wp_send_json_error(['message' => __('No valid files selected or files could not be found.', 'ecp')]);
        }
    
        $upload_dir = wp_upload_dir();
        $tmp_dir = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';
        if (!file_exists($tmp_dir)) { wp_mkdir_p($tmp_dir); }
    
        $zip_filename = 'elevate-portal-files-' . $user_id . '-' . time() . '.zip';
        $zip_filepath = $tmp_dir . $zip_filename;
        $zip = new ZipArchive();
        if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            wp_send_json_error(['message' => __('Could not create ZIP file.', 'ecp')]);
        }
    
        $password = wp_generate_password(16, true, true);
        if (method_exists($zip, 'setPassword')) { $zip->setPassword($password); }
    
        foreach ($files_to_zip as $file_data) {
            if (!empty($file_data['s3_key']) && $s3_handler->is_s3_enabled()) {
                $file_contents = $s3_handler->get_file_contents($file_data['s3_key']);
                if (!is_wp_error($file_contents)) {
                    $zip->addFromString($file_data['name'], $file_contents);
                }
            } elseif (!empty($file_data['path']) && file_exists($file_data['path'])) {
                $zip->addFile($file_data['path'], $file_data['name']);
            }
            if (method_exists($zip, 'setEncryptionName')) {
                $zip->setEncryptionName($file_data['name'], ZipArchive::EM_AES_256);
            }
        }
    
        $zip->close();
    
        $download_url = add_query_arg([
            'ecp_action' => 'download_zip', 'zip_file' => urlencode($zip_filename),
            '_wpnonce' => wp_create_nonce('ecp_zip_download_nonce')
        ], home_url('/'));
    
        wp_send_json_success([ 'password' => $password, 'download_url' => $download_url ]);
    }

    public function render_shortcode() {
        if ( !is_user_logged_in() ) {
            return '<div class="ecp-portal-wrapper"><p>' . __('Please log in to view your files.', 'ecp') . '</p></div>';
        }
        $user = wp_get_current_user();
        $allowed_roles = ['ecp_client', 'scp_client', 'ecp_business_admin', 'administrator'];
        if ( !array_intersect($allowed_roles, $user->roles) ) { 
            return '<div class="ecp-portal-wrapper"><p>' . __('You do not have permission to view this content.', 'ecp') . '</p></div>';
        }
        
        $user_folders = get_user_meta( $user->ID, '_ecp_client_folders', true ) ?: [];
        $all_users_folders = get_option( '_ecp_all_users_folders', [] );
        $folders = array_unique(array_merge($user_folders, $all_users_folders));
        sort($folders);

        $manager_info_html = '';
        $manager_id = get_user_meta( $user->ID, '_ecp_managed_by', true ) ?: get_user_meta( $user->ID, '_ecp_created_by', true );
        if ( $manager_id ) {
            $manager = get_userdata( $manager_id );
            if ( $manager ) {
                ob_start();
                ?>
                <div class="ecp-manager-contact-section">
                    <h4><?php _e('Your Account Manager', 'ecp'); ?></h4>
                    <p><?php printf( __('Your account is managed by %s.', 'ecp'), '<strong>' . esc_html($manager->display_name) . '</strong>'); ?></p>
                    <button id="ecp-contact-manager-toggle" class="button"><?php _e('Contact Manager', 'ecp'); ?></button>
                    <div id="ecp-contact-manager-form-wrapper" style="display:none; margin-top: 15px;">
                        <form id="ecp-contact-manager-form">
                            <div id="ecp-contact-form-messages"></div>
                            <p><label for="ecp-contact-subject"><?php _e('Subject', 'ecp'); ?></label><input type="text" id="ecp-contact-subject" name="subject" required class="widefat"></p>
                            <p><label for="ecp-contact-message"><?php _e('Message', 'ecp'); ?></label><textarea id="ecp-contact-message" name="message" rows="5" required class="widefat"></textarea></p>
                            <p><input type="hidden" name="action" value="ecp_send_manager_email"><input type="hidden" name="manager_id" value="<?php echo esc_attr($manager_id); ?>"><?php wp_nonce_field( 'ecp_contact_manager_nonce', 'nonce' ); ?><button type="submit" class="button button-primary"><?php _e('Send Message', 'ecp'); ?></button></p>
                        </form>
                    </div>
                </div>
                <?php
                $manager_info_html = ob_get_clean();
            }
        }

        ob_start(); ?>
        <div class="ecp-portal-wrapper">
            <div class="ecp-header">
                <h3><?php _e( 'Your Private Files', 'ecp' ); ?></h3>
                <div><a href="<?php echo esc_url(home_url('/account')); ?>" class="button"><?php _e('My Account', 'ecp'); ?></a></div>
            </div>
            <?php echo $manager_info_html; ?>
            <div class="ecp-controls">
                <div><label for="ecp-search-input"><?php _e('Search Files', 'ecp'); ?></label><input type="search" id="ecp-search-input" placeholder="<?php _e('Enter keyword...', 'ecp'); ?>"></div>
                <div><label for="ecp-folder-filter"><?php _e('Filter by Folder', 'ecp'); ?></label><select id="ecp-folder-filter"><option value="all"><?php _e('All Folders', 'ecp'); ?></option><option value="/"><?php _e('Uncategorized', 'ecp'); ?></option><?php foreach($folders as $folder) { echo '<option value="'.esc_attr($folder).'">'.esc_html($folder).'</option>'; } ?></select></div>
                <div><label for="ecp-sort-select"><?php _e('Sort By', 'ecp'); ?></label><select id="ecp-sort-select"><option value="date_desc"><?php _e('Date (Newest)', 'ecp'); ?></option><option value="date_asc"><?php _e('Date (Oldest)', 'ecp'); ?></option><option value="name_asc"><?php _e('Name (A-Z)', 'ecp'); ?></option><option value="name_desc"><?php _e('Name (Z-A)', 'ecp'); ?></option></select></div>
            </div>
            <div id="ecp-bulk-actions-wrapper" style="display:none;">
                <button id="ecp-download-zip-btn" class="button button-primary"><?php _e('Download Selected as ZIP', 'ecp'); ?></button>
                <span id="ecp-selection-info"></span>
                <div id="ecp-zip-password-wrapper" style="display:none;">
                    <strong><?php _e('ZIP Password:', 'ecp'); ?></strong> <code id="ecp-zip-password"></code> <button id="ecp-copy-zip-password" class="button button-small"><?php _e('Copy', 'ecp'); ?></button>
                    <p><?php _e('Your download will begin shortly. Use this password to open the ZIP file.', 'ecp'); ?></p>
                </div>
            </div>
            <div id="ecp-file-list-wrapper">
                <div id="ecp-loader"><div class="ecp-spinner"></div></div>
                <table class="ecp-file-table">
                    <thead><tr>
                        <th class="ecp-col-checkbox"><input type="checkbox" id="ecp-select-all-files" title="<?php _e('Select all files', 'ecp'); ?>"></th>
                        <th><?php _e('File Name', 'ecp'); ?></th><th><?php _e('Folder', 'ecp'); ?></th><th><?php _e('Date', 'ecp'); ?></th><th><?php _e('Size', 'ecp'); ?></th><th></th>
                    </tr></thead>
                    <tbody id="ecp-file-list-container"></tbody>
                </table>
                 <div id="ecp-no-files-message" style="display: none; padding: 20px; text-align: center;"></div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function ajax_send_manager_email() {
        check_ajax_referer( 'ecp_contact_manager_nonce', 'nonce' );
        
        $client_user = wp_get_current_user();
        if ( !$client_user->ID ) {
            wp_send_json_error(['message' => __('You must be logged in to send a message.', 'ecp')]);
        }
        $manager_id = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
        if ( !$manager_id ) {
            wp_send_json_error(['message' => __('Manager not found.', 'ecp')]);
        }
        $manager = get_userdata($manager_id);
        if ( !$manager ) {
            wp_send_json_error(['message' => __('Manager not found.', 'ecp')]);
        }
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        if ( empty($subject) || empty($message) ) {
            wp_send_json_error(['message' => __('Please fill out all fields.', 'ecp')]);
        }

        $to = $manager->user_email;
        $email_subject = sprintf(__('[Client Portal] Message from %s: %s', 'ecp'), $client_user->display_name, $subject);
        $body = "You have received a new message from a client via the Elevate Client Portal.\n\n" .
                "Client Name: " . $client_user->display_name . "\n" .
                "Client Email: " . $client_user->user_email . "\n\n" .
                "Message:\n\n" . $message;
        $headers = [ 'Reply-To: ' . $client_user->display_name . ' <' . $client_user->user_email . '>' ];

        if ( wp_mail( $to, $email_subject, $body, $headers ) ) {
            wp_send_json_success(['message' => __('Your message has been sent successfully.', 'ecp')]);
        } else {
            wp_send_json_error(['message' => __('There was an error sending your message.', 'ecp')]);
        }
    }

    public function render_account_shortcode() {
        if ( !is_user_logged_in() ) {
            return '<div class="ecp-portal-wrapper"><p>' . __('Please log in to view your account details.', 'ecp') . '</p></div>';
        }
        $user = wp_get_current_user();
        ob_start();
        include $this->plugin_path . 'frontend/views/client-portal-account.php';
        return ob_get_clean();
    }
    
    public function ajax_update_account_handler() {
        check_ajax_referer( 'ecp_update_account_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( !$user_id ) {
            wp_send_json_error(['message' => __('You must be logged in.', 'ecp')]);
        }

        $data = $_POST;
        $user_data = [ 'ID' => $user_id ];

        if (isset($data['ecp_user_firstname']) && isset($data['ecp_user_surname'])) {
            $user_data['first_name'] = sanitize_text_field($data['ecp_user_firstname']);
            $user_data['last_name'] = sanitize_text_field($data['ecp_user_surname']);
            $user_data['display_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
        }
        if (isset($data['ecp_user_title'])) {
            update_user_meta($user_id, 'ecp_user_title', sanitize_text_field($data['ecp_user_title']));
        }
        if (isset($data['ecp_user_mobile'])) {
            update_user_meta($user_id, 'ecp_user_mobile', sanitize_text_field($data['ecp_user_mobile']));
        }
        if ( !empty($data['ecp_user_email']) ) {
            $new_email = sanitize_email($data['ecp_user_email']);
            if ( !is_email($new_email) ) {
                wp_send_json_error(['message' => __('The new email address is not valid.', 'ecp')]);
            }
            if ( email_exists($new_email) && email_exists($new_email) != $user_id ) {
                wp_send_json_error(['message' => __('That email address is already in use.', 'ecp')]);
            }
            $user_data['user_email'] = $new_email;
        }

        if ( !empty($data['ecp_user_password']) ) {
            if ( $data['ecp_user_password'] !== $data['ecp_user_password_confirm'] ) {
                wp_send_json_error(['message' => __('The new passwords do not match.', 'ecp')]);
            }
            wp_set_password($data['ecp_user_password'], $user_id);
        }

        $result = wp_update_user($user_data);

        if ( is_wp_error($result) ) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Your account has been updated successfully.', 'ecp')]);
        }
    }

    public function ajax_get_account_page_handler() {
        check_ajax_referer( 'ecp_admin_ajax_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'You must be logged in to view your account.' ] );
        }

        $account_html = $this->render_account_shortcode();
        
        wp_send_json_success( [ 'html' => $account_html ] );
    }
}

