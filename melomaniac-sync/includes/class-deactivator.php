<?php
/**
 * Plugin deactivation routines.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cleans up transient state on deactivation. User data is never touched here.
 */
class Melomaniac_Sync_Deactivator {

	/**
	 * Runs on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// The container may never have booted (e.g. WooCommerce missing), so load what we need.
		require_once MELOMANIAC_SYNC_PATH . 'includes/support/class-cache.php';
		require_once MELOMANIAC_SYNC_PATH . 'includes/integrations/musicbrainz/class-rate-limiter.php';

		Melomaniac_Sync_Cache::flush();
		delete_option( Melomaniac_Sync_Rate_Limiter::OPTION_LAST_REQUEST );

		// Removes the /melomaniac-app/ rule along with everything else, since
		// it is not re-registered while the plugin is off.
		flush_rewrite_rules();
	}
}
