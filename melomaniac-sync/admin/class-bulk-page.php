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
	 * wp_ajax action that resolves an 'eleccion' item with the release the
	 * shop picked, the bulk equivalent of tapping a candidate in the
	 * single-scan screen.
	 */
	const ACTION_RESOLVE = 'melomaniac_sync_bulk_resolve';

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
		add_action( 'wp_ajax_' . self::ACTION_RESOLVE, array( $this, 'handle_resolve' ) );
	}

	/**
	 * Queues a new batch, either from an uploaded CSV or from the pasted
	 * barcode list — whichever one actually arrived.
	 *
	 * @return void
	 */
	public function handle_start() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$posted    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$overrides = Melomaniac_Sync_Catalog::read_overrides( $posted );
		$upload    = $this->read_csv_upload();

		if ( is_wp_error( $upload ) ) {
			$this->redirect_with_error( $upload->get_error_message() );
		}

		if ( '' !== $upload ) {
			$result = $this->service->queue_batch_from_csv( $upload, $overrides );
		} else {
			$barcodes = isset( $posted['barcodes'] ) ? (string) $posted['barcodes'] : '';
			$result   = $this->service->queue_batch( $barcodes, $overrides );
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message() );
		}

		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::bulk_url( array( 'batch' => $result['batch_id'] ) ) );
		exit;
	}

	/**
	 * Validates the uploaded CSV, when one was actually chosen.
	 *
	 * @return string|WP_Error Temp file path, empty string when no file was
	 *                          chosen at all, or an error for a bad upload.
	 */
	private function read_csv_upload() {
		if ( empty( $_FILES['csv_file']['name'] ) ) {
			return '';
		}

		$error = isset( $_FILES['csv_file']['error'] ) ? (int) $_FILES['csv_file']['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return '';
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'melomaniac_sync_bulk_csv_upload_failed', __( 'No se pudo subir el archivo. Inténtalo de nuevo.', 'melomaniac-sync' ) );
		}

		$name = sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) );

		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'melomaniac_sync_bulk_csv_invalid', __( 'El archivo debe ser un .csv.', 'melomaniac-sync' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- A PHP-managed temp path, not user input.
		return (string) $_FILES['csv_file']['tmp_name'];
	}

	/**
	 * Sends the user back to the landing screen with an error notice.
	 *
	 * @param string $message Error to show.
	 * @return void
	 */
	private function redirect_with_error( $message ) {
		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::bulk_url( array( 'melomaniac_message' => $message ) ) );
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
	 * Creates the product for the release the shop picked out of an
	 * 'eleccion' item's candidate list.
	 *
	 * @return void
	 */
	public function handle_resolve() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión expiró. Recarga la página.', 'melomaniac-sync' ) ), 403 );
		}

		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$item_id    = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$source     = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$release_id = isset( $_POST['release_id'] ) ? sanitize_text_field( wp_unslash( $_POST['release_id'] ) ) : '';

		if ( 0 === $item_id || '' === $source || '' === $release_id ) {
			wp_send_json_error( array( 'message' => __( 'Falta indicar cuál disco elegiste.', 'melomaniac-sync' ) ) );
		}

		$this->service->resolve_item( $item_id, $source, $release_id );

		$item = $this->service->get_item( $item_id );

		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'Ese ítem ya no existe.', 'melomaniac-sync' ) ) );
		}

		wp_send_json_success( array( 'item' => $this->item_to_array( $item ) ) );
	}

	/**
	 * Turns one row into the shape the status table's JS expects.
	 *
	 * @param object $item Row from the bulk items table.
	 * @return array
	 */
	private function item_to_array( $item ) {
		$product_id = (int) $item->product_id;
		$candidates = array();

		if ( 'eleccion' === $item->status && ! empty( $item->candidates ) ) {
			$decoded    = json_decode( $item->candidates, true );
			$candidates = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'id'         => (int) $item->id,
			'barcode'    => $item->barcode,
			'status'     => $item->status,
			'message'    => $item->message,
			'candidates' => $candidates,
			'editUrl'    => $product_id > 0 ? get_edit_post_link( $product_id, 'raw' ) : '',
			'name'       => $product_id > 0 ? get_the_title( $product_id ) : '',
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
