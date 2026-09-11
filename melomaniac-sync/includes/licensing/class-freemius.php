<?php
/**
 * Freemius SDK bootstrap.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets a test harness load the plugin without the real SDK, which needs parts of
 * WordPress a lightweight mock does not provide. Never defined in production.
 */
if ( defined( 'MELOMANIAC_SYNC_SKIP_FREEMIUS' ) && MELOMANIAC_SYNC_SKIP_FREEMIUS ) {
	return;
}

if ( ! function_exists( 'melomaniac_sync_fs' ) ) {

	/**
	 * Returns the Freemius instance for this plugin.
	 *
	 * Freemius requires this to run before any other hook is registered, which
	 * is why the main plugin file loads it first and unconditionally.
	 *
	 * @return Freemius
	 */
	function melomaniac_sync_fs() {
		global $melomaniac_sync_fs;

		if ( ! isset( $melomaniac_sync_fs ) ) {
			require_once MELOMANIAC_SYNC_PATH . 'vendor-libs/freemius/start.php';

			// The public key is publishable by design; the secret key is not part
			// of the SDK and never ships with a plugin.
			$melomaniac_sync_fs = fs_dynamic_init(
				array(
					'id'                  => '39229',
					'slug'                => 'melomaniac-sync',
					'type'                => 'plugin',
					'public_key'          => 'pk_bea7bceea6fe0117deac18c30a6d5',
					'is_premium'          => true,
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => false,
					'menu'                => array(
						'slug'    => 'melomaniac-sync',
						'support' => false,
					),
				)
			);
		}

		return $melomaniac_sync_fs;
	}

	melomaniac_sync_fs();

	do_action( 'melomaniac_sync_fs_loaded' );
}
