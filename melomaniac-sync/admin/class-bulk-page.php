<?php
/**
 * Bulk import screen controller.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the bulk import screen: starting a batch, cancelling one, the
 * status poll, and rendering.
 */
class Melomaniac_Sync_Bulk_Page {

	/**
	 * Nonce action shared by the start and cancel forms.
	 */
	const NONCE_ACTION = 'melomaniac_sync_bulk';

	/**
	 * admin-post action that starts a batch.
	 */
	const ACTION_START = 'melomaniac_sync_bulk_start';

	/**
	 * admin-post action that cancels a batch's pending items.
	 */
	const ACTION_CANCEL = 'melomaniac_sync_bulk_cancel';

	/**
	 * wp_ajax action the status table polls.
	 */
	const ACTION_STATUS = 'melomaniac_sync_bulk_status';

	/**
	 * Bulk import service.
	 *
	 * @var Melomaniac_Sync_Bulk_Import_Service
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Bulk_Import_Service $service Bulk import service.
	 */
	public function __construct( Melomaniac_Sync_Bulk_Import_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION_START, array( $this, 'handle_start' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL, array( $this, 'handle_cancel' ) );
		add_action( 'wp_ajax_' . self::ACTION_STATUS, array( $this, 'handle_status' ) );
	}

	/**
	 * Queues a new batch from the pasted barcode list.
	 *
	 * @return void
	 */
	public function handle_start() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$posted   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$barcodes = isset( $posted['barcodes'] ) ? (string) $posted['barcodes'] : '';

		$result = $this->service->queue_batch( $barcodes, Melomaniac_Sync_Catalog::read_overrides( $posted ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				Melomaniac_Sync_Admin_Menu::bulk_url( array( 'melomaniac_message' => $result->get_error_message() ) )
			);
			exit;
		}

		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::bulk_url( array( 'batch' => $result['batch_id'] ) ) );
		exit;
	}

	/**
	 * Cancels a batch's items still waiting their turn.
	 *
	 * @return void
	 */
	public function handle_cancel() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( '' !== $batch_id ) {
			$this->service->cancel_pending( $batch_id );
		}

		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::bulk_url( array( 'batch' => $batch_id ) ) );
		exit;
	}

	/**
	 * Returns the current summary and item list for a batch, for the status
	 * table to poll without reloading the page.
	 *
	 * @return void
	 */
	public function handle_status() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión expiró. Recarga la página.', 'melomaniac-sync' ) ), 403 );
		}

		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';

		if ( '' === $batch_id ) {
			wp_send_json_error( array( 'message' => __( 'Falta el identificador del lote.', 'melomaniac-sync' ) ) );
		}

		wp_send_json_success(
			array(
				'summary' => $this->service->summary( $batch_id ),
				'items'   => array_map( array( $this, 'item_to_array' ), $this->service->items( $batch_id ) ),
			)
		);
	}

	/**
	 * Turns one row into the shape the status table's JS expects.
	 *
	 * @param object $item Row from the bulk items table.
	 * @return array
	 */
	private function item_to_array( $item ) {
		$product_id = (int) $item->product_id;

		return array(
			'barcode'  => $item->barcode,
			'status'   => $item->status,
			'message'  => $item->message,
			'editUrl'  => $product_id > 0 ? get_edit_post_link( $product_id, 'raw' ) : '',
			'name'     => $product_id > 0 ? get_the_title( $product_id ) : '',
		);
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, nothing is changed here.
		$batch_id = isset( $_GET['batch'] ) ? sanitize_text_field( wp_unslash( $_GET['batch'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation, nothing is changed here.
		$message = isset( $_GET['melomaniac_message'] ) ? sanitize_text_field( wp_unslash( $_GET['melomaniac_message'] ) ) : '';

		Melomaniac_Sync_Admin::render_view(
			'page-bulk',
			array(
				'batch_id'       => $batch_id,
				'summary'        => '' !== $batch_id ? $this->service->summary( $batch_id ) : array(),
				'items'          => '' !== $batch_id ? array_map( array( $this, 'item_to_array' ), $this->service->items( $batch_id ) ) : array(),
				'recent_batches' => $this->service->recent_batches(),
				'categories'     => Melomaniac_Sync_Catalog::categories(),
				'message'        => $message,
			)
		);
	}
}
