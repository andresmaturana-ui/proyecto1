<?php
/**
 * Plugin Name:          Melomaniac Sync
 * Plugin URI:           https://melomaniac.cl/melomaniac-sync
 * Description:          Escanea el código de barra de un vinilo, CD o cassette y completa la ficha de producto de WooCommerce con sus datos musicales desde MusicBrainz.
 * Version:              2.1.1
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Author:               Melomaniac
 * Author URI:           https://melomaniac.cl
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          melomaniac-sync
 * Domain Path:          /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.4
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

define( 'MELOMANIAC_SYNC_VERSION', '2.1.1' );
define( 'MELOMANIAC_SYNC_FILE', __FILE__ );
define( 'MELOMANIAC_SYNC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MELOMANIAC_SYNC_URL', plugin_dir_url( __FILE__ ) );
define( 'MELOMANIAC_SYNC_BASENAME', plugin_basename( __FILE__ ) );

// A shop can turn Freemius off entirely from Diagnostics (no wp-config.php
// access needed), so the option is checked here, before the SDK file loads.
// A constant set in wp-config.php still wins over the option either way.
if ( ! defined( 'MELOMANIAC_SYNC_SKIP_FREEMIUS' ) && get_option( 'melomaniac_sync_disable_freemius' ) ) {
	define( 'MELOMANIAC_SYNC_SKIP_FREEMIUS', true );
}

// Freemius requires loading unconditionally and as early as possible, before
// this plugin registers any hook of its own.
require_once MELOMANIAC_SYNC_PATH . 'includes/licensing/class-freemius.php';

require_once MELOMANIAC_SYNC_PATH . 'includes/class-activator.php';
require_once MELOMANIAC_SYNC_PATH . 'includes/class-deactivator.php';
require_once MELOMANIAC_SYNC_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Melomaniac_Sync_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Melomaniac_Sync_Deactivator', 'deactivate' ) );

/**
 * Declares compatibility with WooCommerce High Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				MELOMANIAC_SYNC_FILE,
				true
			);
		}
	}
);

/**
 * Boots the plugin once all other plugins are loaded.
 *
 * WooCommerce is a hard requirement: without it there is no product to fill in.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! Melomaniac_Sync_Activator::is_woocommerce_active() ) {
			add_action( 'admin_notices', array( 'Melomaniac_Sync_Activator', 'render_missing_woocommerce_notice' ) );
			return;
		}

		Melomaniac_Sync_Plugin::instance()->run();
	}
);

/**
 * Returns the plugin container.
 *
 * @return Melomaniac_Sync_Plugin
 */
function melomaniac_sync() {
	return Melomaniac_Sync_Plugin::instance();
}
