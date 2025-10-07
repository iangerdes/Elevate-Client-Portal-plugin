<?php
// File: elevate-client-portal/frontend/views/components/manage-folders.php
/**
 * View template for the reusable "Manage Folders" component.
 *
 * @package Elevate_Client_Portal
 * @version 12.1.0 (Final Audit & JS Fix)
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
            <p>
                <label for="ecp-new-folder-name-<?php echo esc_attr($user_id); ?>"><?php _e('New Folder Name:', 'ecp'); ?></label><br>
                <input type="text" id="ecp-new-folder-name-<?php echo esc_attr($user_id); ?>" name="folder" class="widefat" required/>
            </p>
            <p>
                <label for="ecp-new-folder-location-<?php echo esc_attr($user_id); ?>"><?php _e('Location (Optional):', 'ecp'); ?></label><br>
                <input type="text" id="ecp-new-folder-location-<?php echo esc_attr($user_id); ?>" name="location" class="widefat"/>
            </p>
            <p><button type="submit" class="button"><?php _e('Add Folder', 'ecp'); ?></button></p>
        </form>
        <?php if(!empty($folders)): ?>
            <hr>
            <ul class="ecp-folder-list">
                <?php foreach($folders as $folder) : 
                    $folder_name = is_array($folder) ? $folder['name'] : $folder;
                    $folder_location = ( is_array($folder) && ! empty($folder['location']) ) ? ' <span class="ecp-folder-location">(' . esc_html($folder['location']) . ')</span>' : '';
                ?>
                    <li><?php echo esc_html($folder_name) . $folder_location; ?> 
                        <?php // ** FIX: Changed class from ecp-delete-link to ecp-delete-folder-btn to match JS handler ** ?>
                        <button class="button-link-delete ecp-delete-folder-btn" data-folder="<?php echo esc_attr($folder_name); ?>" data-location="<?php echo esc_attr(is_array($folder) ? $folder['location'] : ''); ?>">(x)</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

