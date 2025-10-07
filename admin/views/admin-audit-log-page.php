<?php
// File: admin/views/admin-audit-log-page.php
/**
 * View template for the Audit Log page.
 *
 * @package Elevate_Client_Portal
 * @version 8.1.0 (New Feature)
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap">
    <h1><?php _e( 'File Download Audit Log', 'ecp' ); ?></h1>
    <p><?php _e( 'This log shows a record of every successful file download from the client portal.', 'ecp' ); ?></p>
    
    <?php
    $per_page = 30;
    $current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $log_data = ECP_Audit_Log::get_logs( $per_page, $current_page );
    $total_items = $log_data['total_items'];
    $total_pages = ceil( $total_items / $per_page );
    ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'User', 'ecp' ); ?></th>
                <th><?php _e( 'File Name', 'ecp' ); ?></th>
                <th><?php _e( 'IP Address', 'ecp' ); ?></th>
                <th><?php _e( 'Date & Time (UTC)', 'ecp' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $log_data['logs'] ) ) : ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px;"><?php _e( 'No download events have been recorded yet.', 'ecp' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $log_data['logs'] as $log_entry ) : 
                    $user = get_userdata( $log_entry['user_id'] );
                ?>
                    <tr>
                        <td>
                            <?php if ($user) : ?>
                                <a href="<?php echo get_edit_user_link( $user->ID ); ?>">
                                    <?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)
                                </a>
                            <?php else : ?>
                                <?php _e( 'User Not Found', 'ecp' ); ?> (ID: <?php echo esc_html( $log_entry['user_id'] ); ?>)
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $log_entry['file_name'] ); ?></td>
                        <td><?php echo esc_html( $log_entry['ip_address'] ); ?></td>
                        <td><?php echo esc_html( $log_entry['download_time'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html( $total_items ); ?> items</span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg( 'paged', '%#%' ),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
