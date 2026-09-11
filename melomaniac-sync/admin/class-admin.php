<?php
/**
 * Admin assets and shared view rendering.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin-side asset loading and view includes.
 */
class Melomaniac_Sync_Admin {

	/**
	 * Nonce action shared by the scan screen's AJAX calls and forms.
	 */
	const NONCE_ACTION = 'melomaniac_sync_scan';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . MELOMANIAC_SYNC_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Cache-busting version for one asset.
	 *
	 * Uses the file's own modification time rather than the plugin version:
	 * during development the version rarely changes, and a browser holding a
	 * stale script while the server runs new PHP fails in ways that look like
	 * a bug in the feature rather than a caching problem.
	 *
	 * @param string $relative_path Path relative to the plugin root.
	 * @return string
	 */
	private static function asset_version( $relative_path ) {
		$path = MELOMANIAC_SYNC_PATH . $relative_path;

		$mtime = file_exists( $path ) ? filemtime( $path ) : 0;

		return $mtime ? MELOMANIAC_SYNC_VERSION . '.' . $mtime : MELOMANIAC_SYNC_VERSION;
	}

	/**
	 * Loads CSS and JS on the plugin's own screens only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! Melomaniac_Sync_Admin_Menu::is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'melomaniac-sync-admin',
			MELOMANIAC_SYNC_URL . 'assets/css/admin.css',
			array(),
			self::asset_version( 'assets/css/admin.css' )
		);

		wp_enqueue_media();

		// Fallback barcode decoder for browsers without BarcodeDetector
		// (Safari, and desktop browsers in general); camera-scanner.js only
		// reaches for it when the native API is missing.
		wp_enqueue_script(
			'melomaniac-sync-zxing',
			MELOMANIAC_SYNC_URL . 'vendor-libs/zxing/zxing.min.js',
			array(),
			self::asset_version( 'vendor-libs/zxing/zxing.min.js' ),
			true
		);

		wp_enqueue_script(
			'melomaniac-sync-camera',
			MELOMANIAC_SYNC_URL . 'assets/js/camera-scanner.js',
			array( 'melomaniac-sync-zxing' ),
			self::asset_version( 'assets/js/camera-scanner.js' ),
			true
		);

		wp_enqueue_script(
			'melomaniac-sync-scan',
			MELOMANIAC_SYNC_URL . 'assets/js/scan.js',
			array( 'melomaniac-sync-camera' ),
			self::asset_version( 'assets/js/scan.js' ),
			true
		);

		wp_enqueue_script(
			'melomaniac-sync-manual-form',
			MELOMANIAC_SYNC_URL . 'assets/js/manual-form.js',
			array( 'jquery' ),
			self::asset_version( 'assets/js/manual-form.js' ),
			true
		);

		wp_localize_script(
			'melomaniac-sync-scan',
			'melomaniacSync',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'actions' => array(
					'lookup'  => Melomaniac_Sync_Ajax_Handler::ACTION_LOOKUP,
					'release' => Melomaniac_Sync_Ajax_Handler::ACTION_GET_RELEASE,
					'create'  => Melomaniac_Sync_Ajax_Handler::ACTION_CREATE_PRODUCT,
					'manual'  => Melomaniac_Sync_Ajax_Handler::ACTION_MANUAL_FORM,
				),
				'i18n'    => array(
					'searching'       => __( 'Buscando en MusicBrainz…', 'melomaniac-sync' ),
					'creating'        => __( 'Creando el producto…', 'melomaniac-sync' ),
					'loadingRelease'  => __( 'Cargando los datos del disco…', 'melomaniac-sync' ),
					'genericError'    => __( 'Algo falló. Intenta de nuevo.', 'melomaniac-sync' ),
					'emptyBarcode'    => __( 'Ingresa o escanea un código de barra.', 'melomaniac-sync' ),
					'editProduct'     => __( 'Editar el producto', 'melomaniac-sync' ),
					'cameraUnsupported' => __( 'Este navegador no puede escanear con la cámara. Escribe el código a mano o usa un lector USB.', 'melomaniac-sync' ),
					'cameraDenied'    => __( 'No se pudo acceder a la cámara. Revisa los permisos del navegador.', 'melomaniac-sync' ),
					'cameraInsecure'  => __( 'La cámara solo funciona sobre HTTPS.', 'melomaniac-sync' ),
					'cameraStart'     => __( 'Escanear con la cámara', 'melomaniac-sync' ),
					'cameraStop'      => __( 'Detener la cámara', 'melomaniac-sync' ),
					'selectCover'     => __( 'Elegir la portada', 'melomaniac-sync' ),
					'useCover'        => __( 'Usar esta imagen', 'melomaniac-sync' ),
				),
			)
		);

		wp_localize_script(
			'melomaniac-sync-manual-form',
			'melomaniacSyncForm',
			array(
				'i18n' => array(
					'selectCover' => __( 'Elegir la portada', 'melomaniac-sync' ),
					'useCover'    => __( 'Usar esta imagen', 'melomaniac-sync' ),
					'removeCover' => __( 'Quitar la portada', 'melomaniac-sync' ),
				),
			)
		);
	}

	/**
	 * Adds a shortcut on the plugins list.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function add_action_links( $links ) {
		$scan = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . Melomaniac_Sync_Admin_Menu::PAGE_SCAN ) ),
			esc_html__( 'Escanear disco', 'melomaniac-sync' )
		);

		array_unshift( $links, $scan );

		return $links;
	}

	/**
	 * Includes a view file with the given data in scope.
	 *
	 * Views only present data; they never talk to services.
	 *
	 * @param string $name View file name without extension.
	 * @param array  $data Variables made available to the view as $data.
	 * @return void
	 */
	public static function render_view( $name, array $data = array() ) {
		$path = MELOMANIAC_SYNC_PATH . 'admin/views/' . sanitize_file_name( $name ) . '.php';

		if ( ! file_exists( $path ) ) {
			return;
		}

		include $path;
	}

	/**
	 * Renders a view and returns it as a string.
	 *
	 * @param string $name View file name without extension.
	 * @param array  $data Variables made available to the view as $data.
	 * @return string
	 */
	public static function capture_view( $name, array $data = array() ) {
		ob_start();
		self::render_view( $name, $data );

		return (string) ob_get_clean();
	}
}
