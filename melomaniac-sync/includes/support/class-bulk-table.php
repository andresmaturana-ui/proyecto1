<?php
/**
 * Database table backing the bulk importer.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per barcode queued through Melomaniac_Sync_Bulk_Import_Service.
 *
 * A dedicated table rather than posts or an option: a batch can be a few
 * hundred barcodes, each one updated independently as its own Action
 * Scheduler action runs, which is exactly what a table with an indexed
 * batch_id is for.
 */
class Melomaniac_Sync_Bulk_Table {

	/**
	 * Table name, without the site's prefix.
	 */
	const NAME = 'melomaniac_sync_bulk_items';

	/**
	 * Option holding the schema version this site's table was created with.
	 */
	const OPTION_VERSION = 'melomaniac_sync_bulk_table_version';

	/**
	 * Current schema version. Bump this and extend create_or_upgrade()'s SQL
	 * when the table shape changes; dbDelta() handles the migration itself.
	 */
	const VERSION = '1.0';

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function name() {
		global $wpdb;

		return $wpdb->prefix . self::NAME;
	}

	/**
	 * Creates the table, or brings an older copy up to date.
	 *
	 * Safe to call on every admin_init: dbDelta() is a no-op once the stored
	 * version already matches, so a site that never deactivates the plugin
	 * (a plain file replace on update) still gets the table without needing
	 * a deactivate/reactivate round trip.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::OPTION_VERSION ) === self::VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id VARCHAR(32) NOT NULL,
			barcode VARCHAR(32) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::OPTION_VERSION, self::VERSION, false );
	}
}
