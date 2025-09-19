<?php
/**
 * View template for the reusable "Move Files" modal component.
 *
 * @package Elevate_Client_Portal
 * @version 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div id="ecp-move-files-modal" class="ecp-modal-overlay" style="display:none;">
    <div class="ecp-modal-content">
        <h4><?php _e('Move Selected Files', 'ecp'); ?></h4>
        <p><?php _e('Select a destination folder:', 'ecp'); ?></p>
        <select id="ecp-modal-folder-select" class="widefat"></select>
        <div class="ecp-modal-actions">
            <button class="button" id="ecp-modal-cancel-btn"><?php _e('Cancel', 'ecp'); ?></button>
            <button class="button button-primary" id="ecp-modal-confirm-move-btn"><?php _e('Move Files', 'ecp'); ?></button>
        </div>
    </div>
</div>
