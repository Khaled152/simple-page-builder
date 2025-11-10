<?php
/**
 * Uninstall cleanup for Simple Page Builder.
 *
 * @package Simple_Page_Builder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load WordPress environment helpers if needed.
// The Settings option may not be autoloaded here, so read directly.
$settings = get_option( 'spb_settings', array() );
$delete   = ! empty( $settings['delete_data_on_uninstall'] );

if ( $delete ) {
	global $wpdb;
	$tables = array(
		$wpdb->prefix . 'spb_api_keys',
		$wpdb->prefix . 'spb_api_logs',
		$wpdb->prefix . 'spb_created_pages',
		$wpdb->prefix . 'spb_webhook_logs',
	);
	foreach ( $tables as $t ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$t}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	delete_option( 'spb_settings' );
	delete_option( 'spb_db_version' );
}


