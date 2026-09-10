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
	 * Connectivity checker.
	 *
	 * @var Melomaniac_Sync_Connectivity_Check
	 */
	private $check;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Connectivity_Check $check Connectivity checker.
	 */
	public function __construct( Melomaniac_Sync_Connectivity_Check $check ) {
		$this->check = $check;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenés permisos para ver esta pantalla.', 'melomaniac-sync' ) );
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
				'probes'      => $probes,
				'ran'         => ! empty( $probes ),
				'flushed'     => $flushed,
				'environment' => $this->check->environment(),
			)
		);
	}
}
