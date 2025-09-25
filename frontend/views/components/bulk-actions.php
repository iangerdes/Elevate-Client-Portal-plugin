<?php
/**
 * View template for the reusable "Bulk Actions" component.
 *
 * @package Elevate_Client_Portal
 * @version 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the parent file manager view:
// @var int $user_id The user ID, or 0 for global actions.
?>
<div class="ecp-bulk-actions-container">
    <select class="ecp-bulk-action-select" id="ecp-bulk-action-select-<?php echo esc_attr($user_id); ?>" name="bulk_action">
        <option value=""><?php _e('Bulk Actions', 'ecp'); ?></option>
        <option value="delete"><?php _e('Delete Selected', 'ecp'); ?></option>
        <option value="move"><?php _e('Move Selected', 'ecp'); ?></option>
        <option value="encrypt"><?php _e('Encrypt Selected', 'ecp'); ?></option>
        <option value="decrypt"><?php _e('Decrypt Selected', 'ecp'); ?></option>
    </select>
    <button class="button ecp-bulk-action-apply"><?php _e('Apply', 'ecp'); ?></button>
</div>


