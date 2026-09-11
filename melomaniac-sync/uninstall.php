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
	'melomaniac_sync_test_plan',
	'melomaniac_sync_disable_freemius',
	'melomaniac_sync_bulk_table_version',
);

foreach ( $melomaniac_options as $melomaniac_option ) {
	delete_option( $melomaniac_option );
}

// The bulk import table is this plugin's own bookkeeping (which barcodes were
// queued and what happened to each), not store data, so it goes too. The
// products it created are regular WooCommerce products by this point and are
// left alone, same as everything else this scan creates.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}melomaniac_sync_bulk_items" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'melomaniac_sync_bulk_process_item', array(), 'melomaniac-sync' );
}

// Drop any leftover transients this plugin wrote.

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_melosync_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_melosync_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
