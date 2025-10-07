<?php
// File: elevate-client-portal/frontend/views/components/files-modal.php
/**
 * View template for the reusable "Move Files" modal component.
 *
 * @package Elevate_Client_Portal
 * @version 52.0.0
 * @comment Added a dedicated container for the selected file list (.ecp-modal-file-list) and added the correct class (.ecp-modal-cancel-btn) to the cancel button.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div id="ecp-move-files-modal" class="ecp-modal-overlay" style="display:none;">
    <div class="ecp-modal-content">
        <h4><?php _e('Move Selected Files', 'ecp'); ?></h4>
        
        <div class="ecp-modal-file-list-container">
            <p><strong><?php _e('You are moving the following files:', 'ecp'); ?></strong></p>
            <div class="ecp-modal-file-list">
                <!-- File list will be injected here by JavaScript -->
            </div>
        </div>

        <p><label for="ecp-modal-folder-select"><?php _e('Select a destination folder:', 'ecp'); ?></label></p>
        <select id="ecp-modal-folder-select" class="widefat"></select>

        <div class="ecp-modal-actions">
            <button class="button ecp-modal-cancel-btn" id="ecp-modal-cancel-btn"><?php _e('Cancel', 'ecp'); ?></button>
            <button class="button button-primary" id="ecp-modal-confirm-move-btn"><?php _e('Move Files', 'ecp'); ?></button>
        </div>
    </div>
</div>

