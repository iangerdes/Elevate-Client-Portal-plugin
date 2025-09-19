<?php
// File: elevate-client-portal/frontend/class-ecp-client-portal.php
/**
 * Handles all logic for the [client_portal] shortcode.
 *
 * @package Elevate_Client_Portal
 * @version 83.0.0 (Error Checking & Stability)
 * @comment Added robust error checking for script loading. Now displays a user-friendly message if the main JS file fails to load, preventing user confusion.
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
        add_shortcode( 'client_portal', [ $this, 'render_shortcode' ] );
        add_shortcode( 'ecp_account', [ $this, 'render_account_shortcode' ] );
    }
    
    public function render_shortcode() {
        if ( !is_user_logged_in() ) {
            return '<div class="ecp-portal-wrapper"><p>' . __('Please log in to view your files.', 'ecp') . '</p></div>';
        }
        
        // ** FIX: Request the scripts from the Asset Manager, which will reliably load them in the footer. **
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL )->request_client_portal_suite();
        
        $user = wp_get_current_user();
        $allowed_roles = ['ecp_client', 'scp_client', 'ecp_business_admin', 'administrator', 'ecp_client_manager'];
        if ( !array_intersect($allowed_roles, $user->roles) ) { 
            return '<div class="ecp-portal-wrapper"><p>' . __('You do not have permission to view this content.', 'ecp') . '</p></div>';
        }
        
        $user_folders = get_user_meta( $user->ID, '_ecp_client_folders', true ) ?: [];
        $all_users_folders = get_option( '_ecp_all_users_folders', [] );
        $merged_folders = array_merge($user_folders, $all_users_folders);
        
        $unique_folders = [];
        $folder_names = [];
        foreach ($merged_folders as $folder) {
            $name = is_array($folder) && isset($folder['name']) ? $folder['name'] : (is_string($folder) ? $folder : null);
            if ($name === null) continue;

            $lower_name = strtolower($name);
            if (!in_array($lower_name, $folder_names)) {
                $unique_folders[] = $folder;
                $folder_names[] = $lower_name;
            }
        }

        usort($unique_folders, function($a, $b) {
            $a_name = is_array($a) && isset($a['name']) ? $a['name'] : (is_string($a) ? $a : '');
            $b_name = is_array($b) && isset($b['name']) ? $b['name'] : (is_string($b) ? $b : '');
            return strcasecmp($a_name, $b_name);
        });
        
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
                            <p><button type="submit" class="button button-primary"><?php _e('Send Message', 'ecp'); ?></button></p>
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
                <div><a href="<?php echo esc_url(home_url('/account')); ?>" class="button ecp-account-link"><?php _e('My Account', 'ecp'); ?></a></div>
            </div>
            <?php echo $manager_info_html; ?>
            <div class="ecp-controls">
                <div><label for="ecp-search-input"><?php _e('Search Files', 'ecp'); ?></label><input type="search" id="ecp-search-input" placeholder="<?php _e('Enter keyword...', 'ecp'); ?>"></div>
                <div><label for="ecp-folder-filter"><?php _e('Filter by Folder', 'ecp'); ?></label>
                    <select id="ecp-folder-filter">
                        <option value="all"><?php _e('All Folders', 'ecp'); ?></option>
                        <option value="/"><?php _e('Uncategorized', 'ecp'); ?></option>
                        <?php 
                        foreach($unique_folders as $folder) { 
                            $name = is_array($folder) ? $folder['name'] : $folder;
                            $location = is_array($folder) && !empty($folder['location']) ? ' (' . $folder['location'] . ')' : '';
                            echo '<option value="'.esc_attr($name).'">'.esc_html($name . $location).'</option>'; 
                        } 
                        ?>
                    </select>
                </div>
                <div><label for="ecp-sort-select"><?php _e('Sort By', 'ecp'); ?></label><select id="ecp-sort-select"><option value="date_desc"><?php _e('Date (Newest)', 'ecp'); ?></option><option value="date_asc"><?php _e('Date (Oldest)', 'ecp'); ?></option><option value="name_asc"><?php _e('Name (A-Z)', 'ecp'); ?></option><option value="name_desc"><?php _e('Name (Z-A)', 'ecp'); ?></option></select></div>
            </div>
            <div id="ecp-bulk-actions-wrapper" style="display:none;">
                <button id="ecp-download-zip-btn" class="button button-primary"><?php _e('Download Selected as ZIP', 'ecp'); ?></button>
                <span id="ecp-selection-info"></span>
                <div id="ecp-zip-password-wrapper" style="display:none;">
                    <strong><?php _e('ZIP Password:', 'ecp'); ?></strong> <code id="ecp-zip-password"></code> <button id="ecp-copy-zip-password" class="button button-small"><?php _e('Copy', 'ecp'); ?></button>
                    <p><?php _e('Your download will begin shortly. Use this password to open the ZIP file.', 'ecp'); ?></p>
                </div>
                 <!-- ** NEW: Error message container for script loading failures ** -->
                <div id="ecp-script-error-notice" style="display:none; color: #d63638; margin-top: 10px; font-weight: bold;">
                    <?php _e('Error: A required script did not load. Please refresh the page. If the problem persists, please contact support.', 'ecp'); ?>
                </div>
            </div>
            <div id="ecp-file-list-wrapper">
                <div id="ecp-loader"><div class="ecp-spinner"></div></div>
                <table class="ecp-file-table">
                    <thead><tr>
                        <th class="ecp-col-checkbox"><input type="checkbox" id="ecp-select-all-files" title="<?php _e('Select all non-encrypted files', 'ecp'); ?>"></th>
                        <th><?php _e('File Name', 'ecp'); ?></th><th><?php _e('Folder', 'ecp'); ?></th><th><?php _e('Date', 'ecp'); ?></th><th><?php _e('Size', 'ecp'); ?></th><th></th>
                    </tr></thead>
                    <tbody id="ecp-file-list-container"></tbody>
                </table>
                 <div id="ecp-no-files-message" style="display: none; padding: 20px; text-align: center;"></div>
            </div>
             <!-- ** NEW: Inline script to check if the main JS file loaded ** -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        if (typeof window.ECP_Client_Portal_Loaded === 'undefined') {
                            var zipButton = document.getElementById('ecp-download-zip-btn');
                            var errorNotice = document.getElementById('ecp-script-error-notice');
                            if(zipButton && errorNotice) {
                                zipButton.disabled = true;
                                errorNotice.style.display = 'block';
                            }
                        }
                    }, 500); // Wait 500ms for scripts to load
                });
            </script>
        </div>
        <?php return ob_get_clean();
    }

    public function render_account_shortcode() {
        if ( !is_user_logged_in() ) {
            return '<div class="ecp-portal-wrapper"><p>' . __('Please log in to view your account details.', 'ecp') . '</p></div>';
        }
        
        ECP_Asset_Manager::get_instance( ECP_PLUGIN_PATH, ECP_PLUGIN_URL )->request_client_portal_suite();
        
        $user = wp_get_current_user();
        ob_start();
        include $this->plugin_path . 'frontend/views/client-portal-account.php';
        return ob_get_clean();
    }
}

