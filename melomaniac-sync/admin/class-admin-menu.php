<?php
/**
 * Admin menu registration.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top level Melomaniac Sync menu and its subpages.
 */
class Melomaniac_Sync_Admin_Menu {

	/**
	 * Slug of the scan screen, which is also the menu landing page.
	 */
	const PAGE_SCAN = 'melomaniac-sync';

	/**
	 * Hook suffixes of the screens this plugin owns.
	 *
	 * @var string[]
	 */
	private static $screen_hooks = array();

	/**
	 * Scan screen controller.
	 *
	 * @var Melomaniac_Sync_Scan_Page
	 */
	private $scan_page;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Scan_Page $scan_page Scan screen controller.
	 */
	public function __construct( Melomaniac_Sync_Scan_Page $scan_page ) {
		$this->scan_page = $scan_page;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	/**
	 * Adds the menu entries.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		$hook = add_menu_page(
			__( 'Melomaniac Sync', 'melomaniac-sync' ),
			__( 'Melomaniac Sync', 'melomaniac-sync' ),
			Melomaniac_Sync_Plugin::CAPABILITY,
			self::PAGE_SCAN,
			array( $this->scan_page, 'render' ),
			'dashicons-album',
			56
		);

		if ( $hook ) {
			self::$screen_hooks[] = $hook;
		}

		$scan_hook = add_submenu_page(
			self::PAGE_SCAN,
			__( 'Escanear disco', 'melomaniac-sync' ),
			__( 'Escanear disco', 'melomaniac-sync' ),
			Melomaniac_Sync_Plugin::CAPABILITY,
			self::PAGE_SCAN,
			array( $this->scan_page, 'render' )
		);

		if ( $scan_hook ) {
			self::$screen_hooks[] = $scan_hook;
		}
	}

	/**
	 * Whether the given hook suffix belongs to one of our screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	public static function is_plugin_screen( $hook_suffix ) {
		return in_array( $hook_suffix, self::$screen_hooks, true );
	}

	/**
	 * URL of the scan screen.
	 *
	 * @param array $args Extra query arguments.
	 * @return string
	 */
	public static function scan_url( array $args = array() ) {
		$args = array_merge( array( 'page' => self::PAGE_SCAN ), $args );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
