<?php
/**
 * Plugin activation routines.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles everything that must happen when the plugin is switched on.
 */
class Melomaniac_Sync_Activator {

	/**
	 * Option holding the schema/settings version, so future releases can migrate.
	 */
	const OPTION_DB_VERSION = 'melomaniac_sync_db_version';

	/**
	 * Runs on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( MELOMANIAC_SYNC_BASENAME );
			wp_die(
				esc_html__( 'Melomaniac Sync requiere PHP 7.4 o superior.', 'melomaniac-sync' ),
				esc_html__( 'Requisitos no cumplidos', 'melomaniac-sync' ),
				array( 'back_link' => true )
			);
		}

		require_once MELOMANIAC_SYNC_PATH . 'includes/services/class-release-dto.php';
		require_once MELOMANIAC_SYNC_PATH . 'includes/support/class-settings.php';
		require_once MELOMANIAC_SYNC_PATH . 'includes/frontend/class-pwa.php';
		require_once MELOMANIAC_SYNC_PATH . 'includes/support/class-bulk-table.php';

		self::seed_settings();

		update_option( self::OPTION_DB_VERSION, MELOMANIAC_SYNC_VERSION, false );

		// The rule needs to exist before flushing, since flushing only writes
		// out whatever is currently registered; 'init' would register it too,
		// but not necessarily before WordPress processes this activation request.
		Melomaniac_Sync_Pwa::add_rewrite_rules();
		flush_rewrite_rules();

		Melomaniac_Sync_Bulk_Table::maybe_upgrade();
	}

	/**
	 * Writes the store's contact on first activation.
	 *
	 * Every other default is applied on read by Melomaniac_Sync_Settings, so
	 * there is nothing else to seed. Only the contact is stored, because it is
	 * the one default derived from this particular site.
	 *
	 * @return void
	 */
	private static function seed_settings() {
		if ( '' === trim( (string) Melomaniac_Sync_Settings::get( 'api_contact', '' ) ) ) {
			Melomaniac_Sync_Settings::update( array( 'api_contact' => get_option( 'admin_email' ) ) );
		}
	}

	/**
	 * Checks whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 *
	 * @return void
	 */
	public static function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Melomaniac Sync necesita WooCommerce activo para funcionar.', 'melomaniac-sync' )
		);
	}
}
