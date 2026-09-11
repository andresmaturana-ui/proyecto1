<?php
/**
 * Scan screen controller.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepares the data the scan screen view needs and renders it.
 */
class Melomaniac_Sync_Scan_Page {

	/**
	 * Release lookup service.
	 *
	 * @var Melomaniac_Sync_Release_Lookup_Service
	 */
	private $lookup_service;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Release_Lookup_Service $lookup_service Lookup service.
	 */
	public function __construct( Melomaniac_Sync_Release_Lookup_Service $lookup_service ) {
		$this->lookup_service = $lookup_service;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para usar esta pantalla.', 'melomaniac-sync' ) );
		}

		Melomaniac_Sync_Admin::render_view(
			'page-scan',
			array(
				'notice'  => $this->read_notice(),
				'barcode' => $this->read_prefilled_barcode(),
				'formats' => Melomaniac_Sync_Release_DTO::formats(),
				'quota'   => Melomaniac_Sync_Usage::summary(),
			)
		);
	}

	/**
	 * Reads a one-off notice passed back through the redirect after a form post.
	 *
	 * @return array{type:string,message:string,link:string,link_label:string}|null
	 */
	private function read_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$status = isset( $_GET['melomaniac_status'] ) ? sanitize_key( wp_unslash( $_GET['melomaniac_status'] ) ) : '';

		if ( '' === $status ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$product_id = isset( $_GET['melomaniac_product'] ) ? absint( wp_unslash( $_GET['melomaniac_product'] ) ) : 0;

		if ( 'created' === $status && $product_id > 0 ) {
			return array(
				'type'       => 'success',
				'message'    => sprintf(
					/* translators: %s: product title. */
					__( 'Se creó el borrador "%s".', 'melomaniac-sync' ),
					get_the_title( $product_id )
				),
				'link'       => get_edit_post_link( $product_id, 'url' ),
				'link_label' => __( 'Editar el producto', 'melomaniac-sync' ),
			);
		}

		if ( 'duplicate' === $status && $product_id > 0 ) {
			return array(
				'type'       => 'warning',
				'message'    => sprintf(
					/* translators: %s: product title. */
					__( 'Ya existía un producto para este disco: "%s".', 'melomaniac-sync' ),
					get_the_title( $product_id )
				),
				'link'       => get_edit_post_link( $product_id, 'url' ),
				'link_label' => __( 'Ver el producto', 'melomaniac-sync' ),
			);
		}

		if ( 'error' === $status ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
			$message = isset( $_GET['melomaniac_message'] ) ? sanitize_text_field( wp_unslash( $_GET['melomaniac_message'] ) ) : '';

			return array(
				'type'       => 'error',
				'message'    => '' !== $message ? $message : __( 'No se pudo crear el producto.', 'melomaniac-sync' ),
				'link'       => '',
				'link_label' => '',
			);
		}

		return null;
	}

	/**
	 * Reads a barcode passed in the URL, so other screens can deep link here.
	 *
	 * @return string
	 */
	private function read_prefilled_barcode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill of a search field.
		if ( ! isset( $_GET['barcode'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill of a search field.
		$barcode = $this->lookup_service->normalise_barcode( wp_unslash( $_GET['barcode'] ) );

		return is_wp_error( $barcode ) ? '' : $barcode;
	}
}
