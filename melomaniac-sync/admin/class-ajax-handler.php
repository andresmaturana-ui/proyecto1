<?php
/**
 * AJAX endpoints for the scan screen.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves the scan screen's asynchronous requests.
 *
 * Every endpoint verifies the nonce and the capability before doing anything.
 * Product creation deliberately re-reads the release from the lookup service
 * instead of trusting fields posted by the browser.
 */
class Melomaniac_Sync_Ajax_Handler {

	/**
	 * Barcode lookup action.
	 */
	const ACTION_LOOKUP = 'melomaniac_sync_lookup';

	/**
	 * Single release detail action.
	 */
	const ACTION_GET_RELEASE = 'melomaniac_sync_get_release';

	/**
	 * Product creation action.
	 */
	const ACTION_CREATE_PRODUCT = 'melomaniac_sync_create_product';

	/**
	 * Manual entry form action, for when the user skips the search.
	 */
	const ACTION_MANUAL_FORM = 'melomaniac_sync_manual_form';

	/**
	 * Release lookup service.
	 *
	 * @var Melomaniac_Sync_Release_Lookup_Service
	 */
	private $lookup_service;

	/**
	 * Product factory.
	 *
	 * @var Melomaniac_Sync_Product_Factory
	 */
	private $product_factory;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Release_Lookup_Service $lookup_service  Lookup service.
	 * @param Melomaniac_Sync_Product_Factory        $product_factory Product factory.
	 */
	public function __construct(
		Melomaniac_Sync_Release_Lookup_Service $lookup_service,
		Melomaniac_Sync_Product_Factory $product_factory
	) {
		$this->lookup_service  = $lookup_service;
		$this->product_factory = $product_factory;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION_LOOKUP, array( $this, 'handle_lookup' ) );
		add_action( 'wp_ajax_' . self::ACTION_GET_RELEASE, array( $this, 'handle_get_release' ) );
		add_action( 'wp_ajax_' . self::ACTION_CREATE_PRODUCT, array( $this, 'handle_create_product' ) );
		add_action( 'wp_ajax_' . self::ACTION_MANUAL_FORM, array( $this, 'handle_manual_form' ) );
	}

	/**
	 * Returns the manual entry form, with or without a barcode.
	 *
	 * @return void
	 */
	public function handle_manual_form() {
		$this->guard();

		$barcode = $this->read_barcode();

		// An empty or malformed barcode is fine here: the form just renders blank.
		if ( is_wp_error( $barcode ) ) {
			$barcode = '';
		}

		wp_send_json_success(
			array(
				'state' => 'manual',
				'html'  => Melomaniac_Sync_Admin::capture_view(
					'partial-manual-form',
					array(
						'barcode' => $barcode,
						'release' => null,
						'formats' => Melomaniac_Sync_Release_DTO::formats(),
					)
				),
			)
		);
	}

	/**
	 * Looks a barcode up and returns markup for the result area.
	 *
	 * @return void
	 */
	public function handle_lookup() {
		$this->guard();

		$barcode = $this->read_barcode();

		if ( is_wp_error( $barcode ) ) {
			wp_send_json_error( array( 'message' => $barcode->get_error_message() ) );
		}

		$result = $this->lookup_service->find_by_barcode( $barcode );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( empty( $result['candidates'] ) ) {
			wp_send_json_success(
				array(
					'state' => 'not_found',
					'html'  => Melomaniac_Sync_Admin::capture_view(
						'partial-manual-form',
						array(
							'barcode' => $result['barcode'],
							'release' => null,
							'formats' => Melomaniac_Sync_Release_DTO::formats(),
						)
					),
				)
			);
		}

		if ( ! empty( $result['resolved'] ) ) {
			wp_send_json_success(
				array(
					'state' => 'resolved',
					'html'  => Melomaniac_Sync_Admin::capture_view(
						'partial-result-preview',
						array(
							'release' => $result['candidates'][0],
							'barcode' => $result['barcode'],
						)
					),
				)
			);
		}

		wp_send_json_success(
			array(
				'state' => 'candidates',
				'html'  => Melomaniac_Sync_Admin::capture_view(
					'partial-candidate-list',
					array(
						'candidates' => $result['candidates'],
						'barcode'    => $result['barcode'],
					)
				),
			)
		);
	}

	/**
	 * Returns the full preview for one release chosen from a candidate list.
	 *
	 * @return void
	 */
	public function handle_get_release() {
		$this->guard();

		$release_ref = $this->read_release_ref();
		$barcode     = $this->read_barcode();

		if ( is_wp_error( $barcode ) ) {
			wp_send_json_error( array( 'message' => $barcode->get_error_message() ) );
		}

		$release = $this->lookup_service->get_release( $release_ref['source'], $release_ref['id'] );

		if ( is_wp_error( $release ) ) {
			wp_send_json_error( array( 'message' => $release->get_error_message() ) );
		}

		$release->barcode = $barcode;

		wp_send_json_success(
			array(
				'state' => 'resolved',
				'html'  => Melomaniac_Sync_Admin::capture_view(
					'partial-result-preview',
					array(
						'release' => $release,
						'barcode' => $barcode,
					)
				),
			)
		);
	}

	/**
	 * Creates the draft product for a release.
	 *
	 * @return void
	 */
	public function handle_create_product() {
		$this->guard();

		$release_ref = $this->read_release_ref();
		$barcode     = $this->read_barcode();

		if ( is_wp_error( $barcode ) ) {
			wp_send_json_error( array( 'message' => $barcode->get_error_message() ) );
		}

		// Re-read from the service rather than trusting posted fields.
		$release = $this->lookup_service->get_release( $release_ref['source'], $release_ref['id'] );

		if ( is_wp_error( $release ) ) {
			wp_send_json_error( array( 'message' => $release->get_error_message() ) );
		}

		$release->barcode = $barcode;

		$product_id = $this->product_factory->create_draft( $release );

		if ( is_wp_error( $product_id ) ) {
			$data     = $product_id->get_error_data();
			$existing = is_array( $data ) && isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;

			wp_send_json_error(
				array(
					'message'   => $product_id->get_error_message(),
					'code'      => $product_id->get_error_code(),
					'productId' => $existing,
					'editUrl'   => $existing > 0 ? get_edit_post_link( $existing, 'url' ) : '',
				)
			);
		}

		wp_send_json_success(
			array(
				'productId' => $product_id,
				'editUrl'   => get_edit_post_link( $product_id, 'url' ),
				'message'   => sprintf(
					/* translators: %s: product title. */
					__( 'Se creó el borrador "%s".', 'melomaniac-sync' ),
					get_the_title( $product_id )
				),
			)
		);
	}

	/**
	 * Verifies nonce and capability, ending the request when either fails.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! check_ajax_referer( Melomaniac_Sync_Admin::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La sesión expiró. Recarga la página.', 'melomaniac-sync' ) ),
				403
			);
		}

		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) ),
				403
			);
		}
	}

	/**
	 * Reads the posted reference to a release: which service, and which id there.
	 *
	 * @return array{source:string,id:string}
	 */
	private function read_release_ref() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in guard().
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$id     = isset( $_POST['release_id'] ) ? sanitize_text_field( wp_unslash( $_POST['release_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return array(
			'source' => $source,
			'id'     => $id,
		);
	}

	/**
	 * Reads and validates the posted barcode.
	 *
	 * @return string|WP_Error
	 */
	private function read_barcode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in guard().
		$raw = isset( $_POST['barcode'] ) ? wp_unslash( $_POST['barcode'] ) : '';

		return $this->lookup_service->normalise_barcode( $raw );
	}
}
