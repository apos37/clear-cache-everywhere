<?php
/**
 * Uninstall script for Clear Cache Everywhere
 *
 * Runs only when the plugin is deleted from the Plugins screen. Removes all plugin data
 * only when "Delete All Data on Uninstall" is checked.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}


/**
 * Delete the plugin's table, options, user meta and scheduled event on the current site
 *
 * @return void
 */
function cceverywhere_uninstall_site() {
    global $wpdb;

    if ( ! get_option( 'clear_cache_everywhere_delete_on_uninstall' ) ) {
        return;
    }

    $table = $wpdb->prefix . 'cceverywhere_logs';
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- removing this plugin's table on uninstall.

    $options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'clear_cache_everywhere_' ) . '%', $wpdb->esc_like( 'cceverywhere_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall.

    foreach ( (array) $options as $option ) {
        delete_option( $option );
    }

    delete_metadata( 'user', 0, 'cceverywhere_logs_per_page', '', true );
    wp_clear_scheduled_hook( 'cceverywhere_purge_logs' );
} // End cceverywhere_uninstall_site()


if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $cceverywhere_blog_id ) {
        switch_to_blog( (int) $cceverywhere_blog_id );
        cceverywhere_uninstall_site();
        restore_current_blog();
    }
} else {
    cceverywhere_uninstall_site();
}