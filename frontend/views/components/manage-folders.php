<?php
/**
 * View template for the reusable "Manage Folders" component.
 *
 * @package Elevate_Client_Portal
 * @version 1.0.1 (Patched)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// These variables are passed from the parent file manager view:
// @var int   $user_id   The user ID, or 0 for global folders.
// @var array $folders   Array of folders.
?>
<div class="postbox">
    <h2 class="hndle"><span><?php _e('Manage Folders', 'ecp'); ?></span></h2>
    <div class="inside">
        <form class="ecp-ajax-form" id="ecp-add-folder-form" method="post">
             <input type="hidden" name="action" value="ecp_file_manager_actions">
             <input type="hidden" name="sub_action" value="add_folder">
             <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
             <?php wp_nonce_field('ecp_ajax_nonce', 'nonce'); ?>
            <p>
                <label for="ecp-new-folder-name"><?php _e('New Folder Name:', 'ecp'); ?></label><br>
                <input type="text" id="ecp-new-folder-name" name="folder" class="widefat" required/>
            </p>
            <p><button type="submit" class="button"><?php _e('Add Folder', 'ecp'); ?></button></p>
        </form>
        <?php if(!empty($folders)): ?>
            <ul class="ecp-folder-list">
                <?php foreach($folders as $folder) { echo '<li>'.esc_html($folder).' <button class="button-link-delete ecp-delete-link" data-action="delete_folder" data-folder="'.esc_attr($folder).'">(x)</button></li>'; } ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

 

