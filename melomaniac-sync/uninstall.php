<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin's own settings and caches. Products and media created from
 * scans are the store's data and are deliberately left alone.
 *
 * @package Melomaniac_Sync
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$melomaniac_options = array(
	'melomaniac_sync_settings',
	'melomaniac_sync_description_fields',
	'melomaniac_sync_category_map',
	'melomaniac_sync_db_version',
	'melomaniac_sync_cache_index',
	'melomaniac_sync_mb_last_request',
	'melomaniac_sync_mb_last_request_discogs',
);

foreach ( $melomaniac_options as $melomaniac_option ) {
	delete_option( $melomaniac_option );
}

// Drop any leftover transients this plugin wrote.
global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_melosync_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_melosync_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
