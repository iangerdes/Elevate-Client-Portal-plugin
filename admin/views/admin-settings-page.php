<?php
// File: admin/views/admin-settings-page.php
/**
 * View for the Elevate Client Portal settings page.
 *
 * @package Elevate_Client_Portal
 * @version 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap">
    <h1><?php _e( 'Elevate Client Portal Settings', 'ecp' ); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'ecp_settings_group' );
        do_settings_sections( 'ecp-settings' );
        submit_button();
        ?>
    </form>
</div>

