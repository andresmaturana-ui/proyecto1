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
	 * Option holding plugin settings.
	 */
	const OPTION_SETTINGS = 'melomaniac_sync_settings';

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

		self::seed_settings();

		update_option( self::OPTION_DB_VERSION, MELOMANIAC_SYNC_VERSION, false );
	}

	/**
	 * Writes default settings without overwriting an existing configuration.
	 *
	 * @return void
	 */
	private static function seed_settings() {
		$defaults = array(
			'api_contact'      => get_option( 'admin_email' ),
			'product_status'   => 'draft',
			'import_cover'     => true,
			'logging_enabled'  => false,
		);

		$stored = get_option( self::OPTION_SETTINGS );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		update_option( self::OPTION_SETTINGS, array_merge( $defaults, $stored ), false );
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
