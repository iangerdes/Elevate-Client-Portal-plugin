<?php
// File: elevate-client-portal/frontend/includes/class-ecp-client-portal-ajax-handler.php
/**
 * Handles all server-side AJAX logic for the front-end Client Portal.
 *
 * @package Elevate_Client_Portal
 * @version 127.0.0 (AJAX Final Password Reset)
 * @comment Added a new AJAX handler for the final password reset submission.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Client_Portal_Ajax_Handler {

    private static $instance;
    const ZIP_SIZE_LIMIT_BYTES = 250 * 1024 * 1024; // 250 MB

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_ecp_filter_files', [ $this, 'ajax_filter_files_handler' ] );
        add_action( 'wp_ajax_ecp_prepare_zip', [ $this, 'ajax_prepare_zip_handler' ] );
        add_action( 'wp_ajax_ecp_get_ready_downloads', [ $this, 'ajax_get_ready_zips_handler' ] );
        add_action( 'wp_ajax_ecp_delete_ready_zip', [ $this, 'ajax_delete_zip_handler' ] );
        add_action( 'wp_ajax_ecp_send_manager_email', [ $this, 'ajax_send_manager_email' ] );
        add_action( 'wp_ajax_ecp_update_account', [ $this, 'ajax_update_account_handler' ] );
        add_action( 'wp_ajax_ecp_logout_user', [ $this, 'ajax_logout_handler' ] );
        add_action( 'wp_ajax_nopriv_ecp_ajax_login', [ $this, 'ajax_login_handler' ] );
        add_action( 'wp_ajax_nopriv_ecp_lost_password', [ $this, 'ajax_lost_password_handler' ] );
        add_action( 'wp_ajax_nopriv_ecp_reset_password', [ $this, 'ajax_reset_password_handler' ] );
    }

    public function ajax_reset_password_handler() {
        ECP_Security_Helper::verify_nonce_or_die('reset_password');

        $rp_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $rp_login = isset($_POST['login']) ? sanitize_text_field($_POST['login']) : '';
        $pass1 = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? $_POST['pass2'] : '';

        $user = check_password_reset_key($rp_key, $rp_login);

        if ( is_wp_error($user) ) {
            wp_send_json_error(['message' => __('Your password reset link appears to be invalid or has expired.', 'ecp')]);
        }
        if ( $pass1 != $pass2 ) {
            wp_send_json_error(['message' => __('The passwords do not match.', 'ecp')]);
        }
        if (empty($pass1)) {
            wp_send_json_error(['message' => __('Password field cannot be empty.', 'ecp')]);
        }

        reset_password($user, $pass1);

        wp_send_json_success(['message' => __('Your password has been reset successfully.', 'ecp')]);
    }

    public function ajax_lost_password_handler() {
        ECP_Security_Helper::verify_nonce_or_die('lost_password');

        $user_login = isset($_POST['user_login']) ? sanitize_text_field(wp_unslash($_POST['user_login'])) : '';
        if (empty($user_login)) {
            wp_send_json_error(['message' => __('Please enter a username or email address.', 'ecp')]);
        }

        $user_data = strpos( $user_login, '@' ) ? get_user_by( 'email', trim( $user_login ) ) : get_user_by( 'login', trim($user_login) );
        if ( empty( $user_data ) ) {
            wp_send_json_error(['message' => __('There is no account registered with that username or email address.', 'ecp')]);
        }

        $key = get_password_reset_key( $user_data );
        if ( is_wp_error( $key ) ) {
            wp_send_json_error(['message' => __('Could not generate a password reset key.', 'ecp')]);
        }
        
        $login_page = get_page_by_path('login');
        if (!$login_page) {
            wp_send_json_error(['message' => __('Login page not found.', 'ecp')]);
        }
        $login_page_url = get_permalink($login_page->ID);

        $message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
        $message .= sprintf( __( 'Site Name: %s' ), get_bloginfo( 'name' ) ) . "\r\n\r\n";
        $message .= sprintf( __( 'Username: %s' ), $user_data->user_login ) . "\r\n\r\n";
        $message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
        $message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
        $reset_url = add_query_arg( ['action' => 'rp', 'key' => $key, 'login' => rawurlencode( $user_data->user_login )], $login_page_url );
        $message .= '<' . $reset_url . ">\r\n";
        $title = sprintf( __( '[%s] Password Reset' ), get_bloginfo( 'name' ) );
        
        if ( wp_mail( $user_data->user_email, wp_strip_all_tags( $title ), $message ) ) {
            wp_send_json_success(['message' => __('Check your email for the confirmation link.', 'ecp')]);
        } else {
            wp_send_json_error(['message' => __('The email could not be sent. It is possible your server is not configured to send emails. Please contact a site administrator.', 'ecp')]);
        }
    }
 
    public function ajax_login_handler() {
        ECP_Security_Helper::verify_nonce_or_die('ajax_login');

        $username = isset($_POST['log']) ? sanitize_user(wp_unslash($_POST['log'])) : '';
        if ( !empty($username) ) {
            $user_obj = get_user_by('login', $username) ?: get_user_by('email', $username);
            if ($user_obj && get_user_meta($user_obj->ID, 'ecp_user_disabled', true)) {
                wp_send_json_error(['message' => __('Your account has been disabled.', 'ecp')]);
            }
        }
        
        $info = [
            'user_login'    => $username,
            'user_password' => isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '',
            'remember'      => isset($_POST['rememberme']) ? true : false,
        ];

        $user_signon = wp_signon($info, is_ssl());

        if ( is_wp_error($user_signon) ) {
            $error_message = __('Invalid username or password. Please try again.', 'ecp');
            wp_send_json_error(['message' => $error_message]);
        } else {
            $redirect_url = home_url();
            if ( ! empty($_REQUEST['redirect_to']) ) {
                $redirect_url = esc_url_raw($_REQUEST['redirect_to']);
            } else if ( user_can( $user_signon, 'edit_users' ) ) {
                $dashboard_page = get_page_by_path('admin-dashboard');
                if ($dashboard_page) $redirect_url = get_permalink($dashboard_page->ID);
            } else {
                $portal_page = get_page_by_path('client-portal');
                if ($portal_page) $redirect_url = get_permalink($portal_page->ID);
            }
            wp_send_json_success(['redirect_url' => $redirect_url]);
        }
    }
    
    public function ajax_filter_files_handler() {
        ECP_Security_Helper::verify_nonce_or_die('client_portal');
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
                    $current_folder_name = is_array($file['folder']) ? ($file['folder']['name'] ?? '/') : ($file['folder'] ?? '/');
                    $in_folder = $folder === 'all' || $current_folder_name === $folder;

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
                $download_link = wp_nonce_url( add_query_arg( [ 'ecp_action' => 'download_file', 'file_key' => urlencode($file_key) ], home_url() ), 'ecp_download_file_nonce' );
                $folder_name = ($file_data['folder'] ?? '/') === '/' ? 'Uncategorized' : ($file_data['folder']['name'] ?? $file_data['folder']);

                $filtered_files_data[] = [
                    'key'          => $file_key,
                    'name'         => esc_html($file_data['name']),
                    'folder'       => esc_html($folder_name),
                    'date'         => esc_html(date_i18n(get_option('date_format'), $file_data['timestamp'] ?? time())),
                    'download_url' => $download_link,
                    'size'         => ECP_File_Helper::format_file_size($file_data['size']),
                    'size_bytes'   => $file_data['size'] ?? 0,
                    'is_encrypted' => !empty($file_data['is_encrypted']),
                    'type'         => $file_data['type'] ?? 'application/octet-stream'
                ];
            }
        }
        
        wp_send_json_success($filtered_files_data);
    }

    public function ajax_prepare_zip_handler() { 
        ECP_Security_Helper::verify_nonce_or_die('zip_prepare');
        $user_id = get_current_user_id();
        $file_keys_str = isset($_POST['file_keys']) ? sanitize_text_field($_POST['file_keys']) : '';

        if (!$user_id || empty($file_keys_str)) {
            wp_send_json_error(['message' => __('Invalid request or no files selected.', 'ecp')]);
        }
        
        $file_keys = explode(',', $file_keys_str);
        $total_size = 0;
        foreach ($file_keys as $file_key) {
            $file_info = ECP_File_Helper::find_and_authorize_file($file_key, $user_id);
            if ($file_info && !is_wp_error($file_info) && isset($file_info['size'])) {
                $total_size += $file_info['size'];
            }
        }

        if ($total_size > self::ZIP_SIZE_LIMIT_BYTES) {
            $message = sprintf(
                __('The total size of the selected files (%s) exceeds the maximum limit of %s for a single ZIP file. Please select fewer files.', 'ecp'),
                ECP_File_Helper::format_file_size($total_size),
                ECP_File_Helper::format_file_size(self::ZIP_SIZE_LIMIT_BYTES)
            );
            wp_send_json_error(['message' => $message]);
        }

        wp_schedule_single_event(time(), 'ecp_background_create_zip_action', [
            'user_id' => $user_id,
            'file_keys' => $file_keys,
        ]);
        
        spawn_cron();
    
        wp_send_json_success([ 'message' => __('Your ZIP file is being prepared in the background. It will appear in your Ready Downloads list shortly.', 'ecp') ]);
    }
    
    public function ajax_get_ready_zips_handler() {
        ECP_Security_Helper::verify_nonce_or_die('zip_get_list');
        $user_id = get_current_user_id();
        if ( !$user_id ) { wp_send_json_error([]); }

        clean_user_cache($user_id);

        $user_zips = get_user_meta($user_id, '_ecp_ready_zips', true) ?: [];
        $response_data = [];

        if (!empty($user_zips)) {
            krsort($user_zips); 
            foreach ($user_zips as $filename => $data) {
                 $download_url = add_query_arg([
                    'ecp_action' => 'download_zip',
                    'zip_file'   => urlencode($filename),
                    '_wpnonce'   => ECP_Security_Helper::create_nonce('zip_download')
                ], home_url('/'));
                $response_data[] = [
                    'filename' => esc_html($filename),
                    'date' => esc_html(date_i18n(get_option('date_format'), $data['timestamp'])),
                    'password' => esc_html($data['password']),
                    'download_url' => esc_url($download_url)
                ];
            }
        }
        wp_send_json_success($response_data);
    }

    public function ajax_delete_zip_handler() {
        ECP_Security_Helper::verify_nonce_or_die('zip_delete');
        $user_id = get_current_user_id();
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';

        if (!$user_id || empty($filename)) {
            wp_send_json_error(['message' => 'Invalid request.']);
        }
        
        $user_zips = get_user_meta($user_id, '_ecp_ready_zips', true) ?: [];
        if (isset($user_zips[$filename])) {
            $upload_dir = wp_upload_dir();
            $tmp_dir = $upload_dir['basedir'] . '/ecp_client_files/temp_zips/';
            $filepath = $tmp_dir . $filename;
            
            if (file_exists($filepath)) {
                wp_delete_file($filepath);
            }

            unset($user_zips[$filename]);
            update_user_meta($user_id, '_ecp_ready_zips', $user_zips);
            wp_send_json_success(['message' => 'ZIP file deleted.']);
        } else {
            wp_send_json_error(['message' => 'File not found in your list.']);
        }
    }
    
    public function ajax_logout_handler() {
        ECP_Security_Helper::verify_nonce_or_die('logout');
        wp_logout();
        $login_page = get_page_by_path('login');
        $redirect_url = $login_page ? get_permalink($login_page->ID) : home_url('/');
        wp_send_json_success(['redirect_url' => $redirect_url]);
    }

    public function ajax_send_manager_email() {
        ECP_Security_Helper::verify_nonce_or_die('contact_manager');
        $client_user = wp_get_current_user();
        if ( !$client_user->ID ) { wp_send_json_error(['message' => __('You must be logged in to send a message.', 'ecp')]); }
        $manager_id = get_user_meta($client_user->ID, '_ecp_managed_by', true) ?: get_user_meta($client_user->ID, '_ecp_created_by', true);
        if ( !$manager_id ) { wp_send_json_error(['message' => __('Account manager not found.', 'ecp')]); }
        $manager = get_userdata($manager_id);
        if ( !$manager ) { wp_send_json_error(['message' => __('Manager not found.', 'ecp')]); }
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        if ( empty($subject) || empty($message) ) { wp_send_json_error(['message' => __('Please fill out all fields.', 'ecp')]); }

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
    
    public function ajax_update_account_handler() {
        ECP_Security_Helper::verify_nonce_or_die('update_account');
        $user_id = get_current_user_id();
        if ( !$user_id ) { wp_send_json_error(['message' => __('You must be logged in.', 'ecp')]); }

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
            if ( !is_email($new_email) ) { wp_send_json_error(['message' => __('The new email address is not valid.', 'ecp')]); }
            if ( email_exists($new_email) && email_exists($new_email) != $user_id ) { wp_send_json_error(['message' => __('That email address is already in use.', 'ecp')]); }
            $user_data['user_email'] = $new_email;
        }

        if ( !empty($data['ecp_user_password']) ) {
            if ( $data['ecp_user_password'] !== $data['ecp_user_password_confirm'] ) { wp_send_json_error(['message' => __('The new passwords do not match.', 'ecp')]); }
            wp_set_password($data['ecp_user_password'], $user_id);
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
        }

        $result = wp_update_user($user_data);

        if ( is_wp_error($result) ) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Your account has been updated successfully.', 'ecp')]);
        }
    }
}

