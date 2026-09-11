<?php
/**
 * Queues and runs a bulk barcode import in the background.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Takes a pasted list of barcodes, queues one Action Scheduler action per
 * barcode, and reuses the exact same lookup service and product factory the
 * single-scan screen uses — a batch is not a second way of creating a
 * product, just many calls to the first one, spread out over time so the
 * MusicBrainz rate limit and a long-running request are never a problem.
 *
 * Ambiguous matches (more than one candidate for a barcode) take the first
 * one automatically, since nobody is there to pick interactively; anything
 * not found is left for the shop to scan by hand afterwards.
 */
class Melomaniac_Sync_Bulk_Import_Service {

	/**
	 * Action Scheduler hook one queued item runs on.
	 */
	const ACTION_HOOK = 'melomaniac_sync_bulk_process_item';

	/**
	 * Action Scheduler group, so the pending queue is easy to find.
	 */
	const ACTION_GROUP = 'melomaniac-sync';

	/**
	 * Gap between two items of the same batch, in seconds.
	 *
	 * The lookup service's own rate limiter already spaces out the actual
	 * MusicBrainz/Discogs requests; this on top of it just keeps a single
	 * Action Scheduler run from trying to process an entire batch at once.
	 */
	const SECONDS_BETWEEN_ITEMS = 3;

	/**
	 * Every status an item can be in.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'pendiente', 'creado', 'no_encontrado', 'duplicado', 'sin_cupo', 'error', 'cancelado' );
	}

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
		add_action( self::ACTION_HOOK, array( $this, 'process_item' ), 10, 2 );
	}

	/**
	 * Parses pasted text into clean, de-duplicated barcodes.
	 *
	 * @param string $raw One barcode per line; anything else on the line is
	 *                    stripped, so "EAN 0731458 1234 5" still works.
	 * @return string[]
	 */
	public function parse_barcodes( $raw ) {
		$lines    = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$barcodes = array();

		foreach ( $lines as $line ) {
			$barcode = $this->lookup_service->normalise_barcode( $line );

			if ( ! is_wp_error( $barcode ) ) {
				$barcodes[ $barcode ] = $barcode;
			}
		}

		return array_values( $barcodes );
	}

	/**
	 * Queues a new batch: one row and one staggered action per barcode.
	 *
	 * @param string $raw_barcodes Pasted barcode list, one per line.
	 * @param array  $overrides    price/stock/category_ids/new_category/tags,
	 *                             applied to every product this batch creates.
	 * @return array{batch_id:string,queued:int}|WP_Error
	 */
	public function queue_batch( $raw_barcodes, array $overrides ) {
		$barcodes = $this->parse_barcodes( $raw_barcodes );

		if ( empty( $barcodes ) ) {
			return new WP_Error(
				'melomaniac_sync_bulk_empty',
				__( 'No se encontró ningún código de barra válido (entre 6 y 14 dígitos, uno por línea).', 'melomaniac-sync' )
			);
		}

		global $wpdb;

		$table    = Melomaniac_Sync_Bulk_Table::name();
		$batch_id = wp_generate_password( 12, false, false );
		$now      = current_time( 'mysql' );

		foreach ( $barcodes as $index => $barcode ) {
			$wpdb->insert(
				$table,
				array(
					'batch_id'   => $batch_id,
					'barcode'    => $barcode,
					'status'     => 'pendiente',
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);

			$item_id = (int) $wpdb->insert_id;

			as_schedule_single_action(
				time() + ( $index * self::SECONDS_BETWEEN_ITEMS ),
				self::ACTION_HOOK,
				array( $item_id, $overrides ),
				self::ACTION_GROUP
			);
		}

		return array(
			'batch_id' => $batch_id,
			'queued'   => count( $barcodes ),
		);
	}

	/**
	 * Action Scheduler callback: resolves and creates one item.
	 *
	 * @param int   $item_id   Row id in the bulk items table.
	 * @param array $overrides Same overrides queue_batch() was given.
	 * @return void
	 */
	public function process_item( $item_id, $overrides = array() ) {
		$row = $this->get_item( (int) $item_id );

		if ( ! $row ) {
			return;
		}

		// A batch can be cancelled while items are still waiting their turn;
		// this is checked here rather than by unscheduling each action.
		if ( 'pendiente' !== $row->status ) {
			return;
		}

		$result = $this->lookup_service->find_by_barcode( $row->barcode );

		if ( is_wp_error( $result ) ) {
			$this->update_item( $item_id, 'error', 0, $result->get_error_message() );
			return;
		}

		if ( empty( $result['resolved'] ) || empty( $result['candidates'] ) ) {
			$this->update_item( $item_id, 'no_encontrado', 0, '' );
			return;
		}

		$release          = $result['candidates'][0];
		$release->barcode = $row->barcode;

		$product_id = $this->product_factory->create_draft( $release, (array) $overrides );

		if ( is_wp_error( $product_id ) ) {
			$this->update_item_from_error( $item_id, $product_id );
			return;
		}

		$this->update_item( $item_id, 'creado', $product_id, '' );
	}

	/**
	 * Maps a product factory error to the item's final status.
	 *
	 * @param int      $item_id Row id.
	 * @param WP_Error $error   Error from create_draft().
	 * @return void
	 */
	private function update_item_from_error( $item_id, WP_Error $error ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();

		if ( 'melomaniac_sync_duplicate' === $error->get_error_code() ) {
			$this->update_item( $item_id, 'duplicado', isset( $data['product_id'] ) ? (int) $data['product_id'] : 0, $error->get_error_message() );
			return;
		}

		if ( 'melomaniac_sync_quota_exceeded' === $error->get_error_code() ) {
			$this->update_item( $item_id, 'sin_cupo', 0, $error->get_error_message() );
			return;
		}

		$this->update_item( $item_id, 'error', 0, $error->get_error_message() );
	}

	/**
	 * Cancels every item of a batch still waiting its turn.
	 *
	 * @param string $batch_id Batch id.
	 * @return int Rows cancelled.
	 */
	public function cancel_pending( $batch_id ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		return (int) $wpdb->update(
			$table,
			array(
				'status'     => 'cancelado',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'batch_id' => $batch_id,
				'status'   => 'pendiente',
			),
			array( '%s', '%s' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * One row by id.
	 *
	 * @param int $item_id Row id.
	 * @return object|null
	 */
	public function get_item( $item_id ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $item_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, not user input.
	}

	/**
	 * Every item of a batch, oldest first.
	 *
	 * @param string $batch_id Batch id.
	 * @return object[]
	 */
	public function items( $batch_id ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_id = %s ORDER BY id ASC", $batch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, not user input.
	}

	/**
	 * Counts of a batch's items by status, plus a 'total' key.
	 *
	 * @param string $batch_id Batch id.
	 * @return array<string,int>
	 */
	public function summary( $batch_id ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		$summary = array_fill_keys( self::statuses(), 0 );

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE batch_id = %s GROUP BY status", $batch_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, not user input.
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			if ( isset( $summary[ $row['status'] ] ) ) {
				$summary[ $row['status'] ] = (int) $row['total'];
			}
		}

		$summary['total'] = array_sum( $summary );

		return $summary;
	}

	/**
	 * Recent batches, most recent first, for the importer's landing screen.
	 *
	 * @param int $limit Maximum batches to return.
	 * @return object[]
	 */
	public function recent_batches( $limit = 10 ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT batch_id, MIN(created_at) AS created_at, COUNT(*) AS total, " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name, not user input.
				. "SUM(status = 'pendiente') AS pendientes "
				. "FROM {$table} GROUP BY batch_id ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Updates one item's outcome.
	 *
	 * @param int    $item_id    Row id.
	 * @param string $status     New status.
	 * @param int    $product_id Product created, when there is one.
	 * @param string $message    Detail to show in the results table.
	 * @return void
	 */
	private function update_item( $item_id, $status, $product_id, $message ) {
		global $wpdb;

		$table = Melomaniac_Sync_Bulk_Table::name();

		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'product_id' => (int) $product_id,
				'message'    => $message,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}
}
