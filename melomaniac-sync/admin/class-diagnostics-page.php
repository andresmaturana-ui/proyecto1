<?php
/**
 * Diagnostics screen controller.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs the connectivity probes on demand and renders the result.
 *
 * The probes are never run on page load: each one makes real outbound requests
 * and a blocked host makes them slow, which would make the screen itself hang.
 */
class Melomaniac_Sync_Diagnostics_Page {

	/**
	 * Nonce action for the run button.
	 */
	const NONCE_ACTION = 'melomaniac_sync_run_diagnostics';

	/**
	 * Nonce action for the cache reset button.
	 */
	const NONCE_FLUSH = 'melomaniac_sync_flush_cache';

	/**
	 * Nonce action for the barcode probe.
	 */
	const NONCE_BARCODE = 'melomaniac_sync_probe_barcode';

	/**
	 * Connectivity checker.
	 *
	 * @var Melomaniac_Sync_Connectivity_Check
	 */
	private $check;

	/**
	 * Release lookup service.
	 *
	 * @var Melomaniac_Sync_Release_Lookup_Service
	 */
	private $lookup_service;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Connectivity_Check     $check          Connectivity checker.
	 * @param Melomaniac_Sync_Release_Lookup_Service $lookup_service Lookup service.
	 */
	public function __construct(
		Melomaniac_Sync_Connectivity_Check $check,
		Melomaniac_Sync_Release_Lookup_Service $lookup_service
	) {
		$this->check          = $check;
		$this->lookup_service = $lookup_service;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pantalla.', 'melomaniac-sync' ) );
		}

		$probes  = array();
		$flushed = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked right below.
		if ( isset( $_POST['melomaniac_sync_run_probes'] ) ) {
			check_admin_referer( self::NONCE_ACTION );
			$probes = $this->check->run_all();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked right below.
		if ( isset( $_POST['melomaniac_sync_flush_cache'] ) ) {
			check_admin_referer( self::NONCE_FLUSH );
			Melomaniac_Sync_Cache::flush();
			$flushed = true;
		}

		Melomaniac_Sync_Admin::render_view(
			'page-diagnostics',
			array(
				'probes'         => $probes,
				'ran'            => ! empty( $probes ),
				'flushed'        => $flushed,
				'barcode'        => $this->read_probe_barcode(),
				'barcode_result' => $this->maybe_probe_barcode(),
				'environment'    => $this->check->environment(),
			)
		);
	}

	/**
	 * The barcode the user asked about, so the field keeps its value.
	 *
	 * @return string
	 */
	private function read_probe_barcode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only refill of a form field.
		if ( ! isset( $_POST['probe_barcode'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only refill of a form field.
		return preg_replace( '/\D/', '', wp_unslash( $_POST['probe_barcode'] ) );
	}

	/**
	 * Asks every source about one barcode, when the form was submitted.
	 *
	 * @return array|WP_Error|null Null when the form was not submitted.
	 */
	private function maybe_probe_barcode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked right below.
		if ( ! isset( $_POST['melomaniac_sync_probe_barcode'] ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_BARCODE );

		return $this->lookup_service->probe_barcode( $this->read_probe_barcode() );
	}
}
