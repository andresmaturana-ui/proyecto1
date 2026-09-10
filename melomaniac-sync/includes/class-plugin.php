<?php
/**
 * Plugin container.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads dependencies, wires services together and registers hooks.
 */
final class Melomaniac_Sync_Plugin {

	/**
	 * Capability required to use any of the plugin's screens.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Release lookup service.
	 *
	 * @var Melomaniac_Sync_Release_Lookup_Service|null
	 */
	private $lookup_service = null;

	/**
	 * Product factory.
	 *
	 * @var Melomaniac_Sync_Product_Factory|null
	 */
	private $product_factory = null;

	/**
	 * Logger.
	 *
	 * @var Melomaniac_Sync_Logger|null
	 */
	private $logger = null;

	/**
	 * Whether run() already executed.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor is private; use instance().
	 */
	private function __construct() {}

	/**
	 * Returns the singleton.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function run() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Requires every class file.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		$files = array(
			'includes/support/class-logger.php',
			'includes/support/class-cache.php',
			'includes/support/class-settings.php',
			'includes/licensing/class-licensing.php',
			'includes/licensing/class-usage.php',
			'includes/helpers/functions-reference-links.php',
			'includes/integrations/musicbrainz/class-rate-limiter.php',
			'includes/integrations/musicbrainz/class-musicbrainz-client.php',
			'includes/integrations/musicbrainz/class-response-parser.php',
			'includes/integrations/musicbrainz/class-cover-art-client.php',
			'includes/integrations/discogs/class-discogs-client.php',
			'includes/integrations/discogs/class-discogs-response-parser.php',
			'includes/services/class-release-dto.php',
			'includes/services/class-release-lookup-service.php',
			'includes/services/class-product-factory.php',
		);

		if ( is_admin() ) {
			$files = array_merge(
				$files,
				array(
					'admin/class-admin.php',
					'admin/class-admin-menu.php',
					'admin/class-scan-page.php',
					'admin/class-ajax-handler.php',
					'admin/class-manual-entry-handler.php',
				)
			);
		}

		foreach ( $files as $file ) {
			require_once MELOMANIAC_SYNC_PATH . $file;
		}
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		Melomaniac_Sync_Licensing::register();

		if ( ! is_admin() ) {
			return;
		}

		$menu = new Melomaniac_Sync_Admin_Menu( new Melomaniac_Sync_Scan_Page( $this->lookup_service() ) );
		$menu->register();

		$admin = new Melomaniac_Sync_Admin();
		$admin->register();

		$ajax = new Melomaniac_Sync_Ajax_Handler( $this->lookup_service(), $this->product_factory() );
		$ajax->register();

		$manual = new Melomaniac_Sync_Manual_Entry_Handler( $this->product_factory() );
		$manual->register();
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'melomaniac-sync',
			false,
			dirname( MELOMANIAC_SYNC_BASENAME ) . '/languages'
		);
	}

	/**
	 * Logger.
	 *
	 * @return Melomaniac_Sync_Logger
	 */
	public function logger() {
		if ( null === $this->logger ) {
			$this->logger = new Melomaniac_Sync_Logger();
		}

		return $this->logger;
	}

	/**
	 * Release lookup service, built once per request.
	 *
	 * @return Melomaniac_Sync_Release_Lookup_Service
	 */
	public function lookup_service() {
		if ( null === $this->lookup_service ) {
			$logger = $this->logger();

			$this->lookup_service = new Melomaniac_Sync_Release_Lookup_Service(
				new Melomaniac_Sync_MusicBrainz_Client(
					new Melomaniac_Sync_Rate_Limiter( 'musicbrainz', 1.1 ),
					$logger
				),
				new Melomaniac_Sync_MusicBrainz_Response_Parser(),
				new Melomaniac_Sync_Cover_Art_Client( $logger ),
				new Melomaniac_Sync_Discogs_Client(
					new Melomaniac_Sync_Rate_Limiter( 'discogs', 1.1 ),
					$logger
				),
				new Melomaniac_Sync_Discogs_Response_Parser(),
				new Melomaniac_Sync_Cache()
			);
		}

		return $this->lookup_service;
	}

	/**
	 * Product factory.
	 *
	 * @return Melomaniac_Sync_Product_Factory
	 */
	public function product_factory() {
		if ( null === $this->product_factory ) {
			$this->product_factory = new Melomaniac_Sync_Product_Factory( $this->logger() );
		}

		return $this->product_factory;
	}
}
