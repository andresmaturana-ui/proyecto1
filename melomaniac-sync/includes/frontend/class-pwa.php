<?php
/**
 * Serves the installable web app.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * A small SPA served straight from the plugin at /melomaniac-app/, so a shop
 * can scan and create products from a phone without opening wp-admin. It
 * talks to melomaniac-sync/v1 (Melomaniac_Sync_Rest_Api) the same way any
 * other client of that API would, using an Application Password the person
 * using the app creates for themselves.
 *
 * The service worker has to be served through this same route (rather than
 * as a static file under assets/) because a service worker can only control
 * pages at or below the URL it was served from: one at
 * wp-content/plugins/.../assets/app/sw.js could never control /melomaniac-app/.
 */
class Melomaniac_Sync_Pwa {

	/**
	 * Query var carrying which part of the app is being requested.
	 */
	const QUERY_VAR = 'melomaniac_sync_app';

	/**
	 * URL slug the app is served under.
	 */
	const SLUG = 'melomaniac-app';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve' ) );
	}

	/**
	 * Registers the rewrite rules for the app shell, manifest and service
	 * worker. Static and called directly (not only via the 'init' hook) so
	 * the activator can register the same rules right before flushing.
	 *
	 * @return void
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^' . self::SLUG . '/manifest\.webmanifest$', 'index.php?' . self::QUERY_VAR . '=manifest', 'top' );
		add_rewrite_rule( '^' . self::SLUG . '/sw\.js$', 'index.php?' . self::QUERY_VAR . '=sw', 'top' );
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=shell', 'top' );
	}

	/**
	 * Makes the query var recognised by WP_Query.
	 *
	 * @param string[] $vars Already public query vars.
	 * @return string[]
	 */
	public function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * The app's own URL, for the settings screen and for building start_url.
	 *
	 * @return string
	 */
	public static function url() {
		return home_url( '/' . self::SLUG . '/' );
	}

	/**
	 * Intercepts the request and serves the app, when the URL matches.
	 *
	 * Works whether or not pretty permalinks are on: WordPress recognises a
	 * registered query var either way, so ?melomaniac_sync_app=shell on the
	 * home page reaches here too, as a fallback for a site that has not
	 * re-saved its permalinks since installing the plugin.
	 *
	 * @return void
	 */
	public function maybe_serve() {
		$which = $this->requested_view();

		if ( '' === $which ) {
			return;
		}

		$this->render( $which );
		exit;
	}

	/**
	 * Which view the current request is asking for, empty when none.
	 *
	 * @return string
	 */
	public function requested_view() {
		$which = get_query_var( self::QUERY_VAR );

		return is_string( $which ) ? $which : '';
	}

	/**
	 * Sends headers and prints the body for one view. Split out from
	 * maybe_serve() (which exits right after calling this) so the actual
	 * output can be exercised directly.
	 *
	 * @param string $which 'manifest', 'sw', or anything else for the shell.
	 * @return void
	 */
	public function render( $which ) {
		if ( ! Melomaniac_Sync_Settings::app_enabled() ) {
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			return;
		}

		switch ( $which ) {
			case 'manifest':
				$this->serve_manifest();
				break;

			case 'sw':
				$this->serve_service_worker();
				break;

			default:
				$this->serve_shell();
				break;
		}
	}

	/**
	 * The web app manifest.
	 *
	 * @return void
	 */
	private function serve_manifest() {
		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );

		echo wp_json_encode( $this->manifest_data() );
	}

	/**
	 * Builds the manifest data.
	 *
	 * @return array
	 */
	public function manifest_data() {
		return array(
			'name'             => sprintf(
				/* translators: %s: store name. */
				__( '%s - Melomaniac Sync', 'melomaniac-sync' ),
				get_bloginfo( 'name' )
			),
			'short_name'       => __( 'Melomaniac', 'melomaniac-sync' ),
			'description'      => __( 'Escanea un disco y crea el producto en la tienda.', 'melomaniac-sync' ),
			'start_url'        => self::url(),
			'scope'            => self::url(),
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#111318',
			'theme_color'      => '#111318',
			'lang'             => 'es-CL',
			'icons'            => array(
				array(
					'src'   => MELOMANIAC_SYNC_URL . 'assets/app/icon-192.png',
					'sizes' => '192x192',
					'type'  => 'image/png',
				),
				array(
					'src'   => MELOMANIAC_SYNC_URL . 'assets/app/icon-512.png',
					'sizes' => '512x512',
					'type'  => 'image/png',
				),
			),
		);
	}

	/**
	 * The service worker script.
	 *
	 * Served as a template rather than a static file: the shell, app.js,
	 * app.css and the shared camera scanner script all live at plugin URLs
	 * that have nothing to do with the /melomaniac-app/ path the service
	 * worker itself is served from, so their real URLs are substituted in
	 * here instead of being guessed from a relative path.
	 *
	 * @return void
	 */
	private function serve_service_worker() {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . wp_parse_url( self::url(), PHP_URL_PATH ) );

		$template = (string) file_get_contents( MELOMANIAC_SYNC_PATH . 'assets/app/sw.js' );

		$replacements = array(
			'__CACHE_VERSION__' => MELOMANIAC_SYNC_VERSION,
			'__SHELL_URL__'     => self::url(),
			'__APP_JS_URL__'    => MELOMANIAC_SYNC_URL . 'assets/app/app.js',
			'__APP_CSS_URL__'   => MELOMANIAC_SYNC_URL . 'assets/app/app.css',
			'__CAMERA_JS_URL__' => MELOMANIAC_SYNC_URL . 'assets/js/camera-scanner.js',
			'__ZXING_JS_URL__'  => MELOMANIAC_SYNC_URL . 'vendor-libs/zxing/zxing.min.js',
		);

		echo str_replace( array_keys( $replacements ), array_values( $replacements ), $template ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static JS template with URL substitutions, not user input.
	}

	/**
	 * The app shell.
	 *
	 * @return void
	 */
	private function serve_shell() {
		header( 'Content-Type: text/html; charset=utf-8' );

		$data = array(
			'rest_url'      => esc_url_raw( rest_url( Melomaniac_Sync_Rest_Api::NAMESPACE_ ) ),
			'manifest_url'  => esc_url_raw( self::url() . 'manifest.webmanifest' ),
			'sw_url'        => esc_url_raw( self::url() . 'sw.js' ),
			'app_js_url'    => esc_url_raw( MELOMANIAC_SYNC_URL . 'assets/app/app.js' ),
			'app_css_url'   => esc_url_raw( MELOMANIAC_SYNC_URL . 'assets/app/app.css' ),
			'camera_js_url' => esc_url_raw( MELOMANIAC_SYNC_URL . 'assets/js/camera-scanner.js' ),
			'zxing_js_url'  => esc_url_raw( MELOMANIAC_SYNC_URL . 'vendor-libs/zxing/zxing.min.js' ),
			'icon_url'      => esc_url_raw( MELOMANIAC_SYNC_URL . 'assets/app/icon-192.png' ),
			'site_name'     => get_bloginfo( 'name' ),
			'profile_url'   => esc_url_raw( admin_url( 'profile.php#application-passwords-section' ) ),
			'settings_url'  => esc_url_raw( Melomaniac_Sync_Admin_Menu::settings_url() ),
			'version'       => MELOMANIAC_SYNC_VERSION,
		);

		include MELOMANIAC_SYNC_PATH . 'includes/frontend/views/view-app-shell.php';
	}
}
