<?php
/**
 * Queues and runs a bulk barcode import in the background.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Takes a pasted list of barcodes (or an uploaded CSV), queues one Action
 * Scheduler action per barcode, and reuses the exact same lookup service and
 * product factory the single-scan screen uses — a batch is not a second way
 * of creating a product, just many calls to the first one, spread out over
 * time so the MusicBrainz rate limit and a long-running request are never a
 * problem.
 *
 * An unambiguous match creates the draft straight away, same as scanning it
 * by hand would once MusicBrainz returns exactly one release. A barcode with
 * more than one candidate release stops instead of guessing: the item waits
 * in an 'eleccion' state, carrying the candidates it found, until
 * resolve_item() is called with the one the shop picked — the same choice
 * the single-scan screen's candidate list already asks for. Anything not
 * found at all is left for the shop to scan by hand afterwards.
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
		return array( 'pendiente', 'eleccion', 'creado', 'no_encontrado', 'duplicado', 'sin_cupo', 'error', 'cancelado' );
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
		add_action( self::ACTION_HOOK, array( $this, 'process_item' ) );
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
	 * Queues a new batch from a pasted barcode list: the same overrides apply
	 * to every product this batch creates.
	 *
	 * @param string $raw_barcodes Pasted barcode list, one per line.
	 * @param array  $overrides    price/stock/category_ids/new_category/tags.
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

		$rows = array();

		foreach ( $barcodes as $barcode ) {
			$rows[] = array(
				'barcode'   => $barcode,
				'overrides' => $overrides,
			);
		}

		return $this->queue_rows( $rows );
	}

	/**
	 * Queues a new batch from an uploaded CSV: código is required per row,
	 * precio and cantidad are optional and override the shared ones for that
	 * row only, e.g. a shop pricing most of the batch the same but a handful
	 * of items differently.
	 *
	 * @param string $file_path         Path to the uploaded file (its temp
	 *                                  name is fine; nothing needs to persist).
	 * @param array  $shared_overrides  category_ids/new_category/tags (and a
	 *                                  fallback price/stock), applied to every
	 *                                  row unless that row's own column says
	 *                                  otherwise.
	 * @return array{batch_id:string,queued:int}|WP_Error
	 */
	public function queue_batch_from_csv( $file_path, array $shared_overrides ) {
		$rows = $this->parse_csv( $file_path, $shared_overrides );

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		if ( empty( $rows ) ) {
			return new WP_Error(
				'melomaniac_sync_bulk_empty',
				__( 'El archivo no tiene ningún código de barra válido en la columna "código".', 'melomaniac-sync' )
			);
		}

		return $this->queue_rows( $rows );
	}

	/**
	 * Parses an uploaded CSV into rows ready for queue_rows().
	 *
	 * Accepts both comma and semicolon separated files (Excel in Spanish
	 * saves semicolon-separated by default, since it treats the comma as the
	 * decimal separator), detected from the header line. Column names are
	 * matched case- and accent-insensitively, so "Código", "codigo" and
	 * "CÓDIGO" are all the same column.
	 *
	 * @param string $file_path        Path to the CSV file.
	 * @param array  $shared_overrides Base overrides, see queue_batch_from_csv().
	 * @return array[]|WP_Error List of {barcode, overrides}.
	 */
	private function parse_csv( $file_path, array $shared_overrides ) {
		$handle = @fopen( $file_path, 'r' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reports as melomaniac_sync_bulk_csv_unreadable below.

		if ( ! $handle ) {
			return new WP_Error( 'melomaniac_sync_bulk_csv_unreadable', __( 'No se pudo leer el archivo.', 'melomaniac-sync' ) );
		}

		$first_line = fgets( $handle );
		$delimiter  = ( false !== strpos( (string) $first_line, ';' ) ) ? ';' : ',';
		rewind( $handle );

		$header = fgetcsv( $handle, 0, $delimiter );

		if ( ! is_array( $header ) ) {
			fclose( $handle );

			return new WP_Error( 'melomaniac_sync_bulk_empty', __( 'El archivo está vacío.', 'melomaniac-sync' ) );
		}

		$columns = $this->map_csv_columns( $header );

		if ( ! isset( $columns['codigo'] ) ) {
			fclose( $handle );

			return new WP_Error(
				'melomaniac_sync_bulk_csv_invalid',
				__( 'El archivo debe tener una columna "código". Descarga la plantilla si no estás seguro del formato.', 'melomaniac-sync' )
			);
		}

		$rows = array();

		while ( false !== ( $line = fgetcsv( $handle, 0, $delimiter ) ) ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInWhileCondition -- Standard fgetcsv() loop.
			$barcode = $this->lookup_service->normalise_barcode( $line[ $columns['codigo'] ] ?? '' );

			if ( is_wp_error( $barcode ) ) {
				continue;
			}

			$overrides = $shared_overrides;

			if ( isset( $columns['precio'] ) ) {
				$price = trim( (string) ( $line[ $columns['precio'] ] ?? '' ) );

				if ( '' !== $price ) {
					$overrides['price'] = wc_format_decimal( $price );
				}
			}

			if ( isset( $columns['cantidad'] ) ) {
				$stock = trim( (string) ( $line[ $columns['cantidad'] ] ?? '' ) );

				if ( '' !== $stock ) {
					$overrides['stock'] = absint( $stock );
				}
			}

			// Last row for a repeated barcode wins, same as the pasted-list path.
			$rows[ $barcode ] = array(
				'barcode'   => $barcode,
				'overrides' => $overrides,
			);
		}

		fclose( $handle );

		return array_values( $rows );
	}

	/**
	 * Matches a CSV header row to the columns this importer understands.
	 *
	 * @param string[] $header Header row.
	 * @return array<string,int> Column index keyed by 'codigo', 'precio' or 'cantidad'.
	 */
	private function map_csv_columns( array $header ) {
		$synonyms = array(
			'codigo'   => 'codigo',
			'código'   => 'codigo',
			'barcode'  => 'codigo',
			'ean'      => 'codigo',
			'precio'   => 'precio',
			'price'    => 'precio',
			'cantidad' => 'cantidad',
			'stock'    => 'cantidad',
			'qty'      => 'cantidad',
		);

		$columns = array();

		foreach ( $header as $index => $name ) {
			// Excel saves UTF-8 CSVs with a leading BOM (including the
			// plugin's own downloadable template), which would otherwise
			// stay glued to the first column's name and never match.
			$key = strtolower( trim( str_replace( "\xEF\xBB\xBF", '', (string) $name ) ) );

			if ( isset( $synonyms[ $key ] ) && ! isset( $columns[ $synonyms[ $key ] ] ) ) {
				$columns[ $synonyms[ $key ] ] = $index;
			}
		}

		return $columns;
	}

	/**
	 * Inserts one row and schedules one staggered action per item, shared by
	 * both queueing paths.
	 *
	 * @param array[] $rows Each: {barcode:string, overrides:array}.
	 * @return array{batch_id:string,queued:int}
	 */
	private function queue_rows( array $rows ) {
		global $wpdb;

		$table    = Melomaniac_Sync_Bulk_Table::name();
		$batch_id = wp_generate_password( 12, false, false );
		$now      = current_time( 'mysql' );

		foreach ( $rows as $index => $row ) {
			$wpdb->insert(
				$table,
				array(
					'batch_id'   => $batch_id,
					'barcode'    => $row['barcode'],
					'status'     => 'pendiente',
					'overrides'  => wp_json_encode( $row['overrides'] ),
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			$item_id = (int) $wpdb->insert_id;

			as_schedule_single_action(
				time() + ( $index * self::SECONDS_BETWEEN_ITEMS ),
				self::ACTION_HOOK,
				array( $item_id ),
				self::ACTION_GROUP
			);
		}

		return array(
			'batch_id' => $batch_id,
			'queued'   => count( $rows ),
		);
	}

	/**
	 * Action Scheduler callback: resolves and creates one item, or parks it
	 * in 'eleccion' when the barcode matched more than one release.
	 *
	 * @param int $item_id Row id in the bulk items table.
	 * @return void
	 */
	public function process_item( $item_id ) {
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

		if ( count( $result['candidates'] ) > 1 ) {
			$this->park_for_choice( $item_id, $result['candidates'] );
			return;
		}

		$this->create_from_release( $item_id, $result['candidates'][0], $row );
	}

	/**
	 * Creates the product for whichever release the shop picked from an
	 * 'eleccion' item's candidate list — the bulk equivalent of tapping a
	 * release in the single-scan screen's candidate list.
	 *
	 * @param int    $item_id    Row id.
	 * @param string $source     'musicbrainz' or 'discogs'.
	 * @param string $release_id Id within that source.
	 * @return void
	 */
	public function resolve_item( $item_id, $source, $release_id ) {
		$row = $this->get_item( (int) $item_id );

		if ( ! $row || 'eleccion' !== $row->status ) {
			return;
		}

		$release = $this->lookup_service->get_release( $source, $release_id );

		if ( is_wp_error( $release ) ) {
			$this->update_item( $item_id, 'error', 0, $release->get_error_message() );
			return;
		}

		$this->create_from_release( $item_id, $release, $row );
	}

	/**
	 * Shared final step for both an unambiguous automatic match and a
	 * manually resolved 'eleccion' item.
	 *
	 * @param int                          $item_id Row id.
	 * @param Melomaniac_Sync_Release_DTO $release Release to create a product from.
	 * @param object                       $row     The item's row, for its barcode and overrides.
	 * @return void
	 */
	private function create_from_release( $item_id, Melomaniac_Sync_Release_DTO $release, $row ) {
		$release->barcode = $row->barcode;

		$product_id = $this->product_factory->create_draft( $release, $this->decode_overrides( $row ) );

		if ( is_wp_error( $product_id ) ) {
			$this->update_item_from_error( $item_id, $product_id );
			return;
		}

		$this->update_item( $item_id, 'creado', $product_id, '' );
	}

	/**
	 * Parks an item waiting for the shop to pick one of several candidates.
	 *
	 * @param int                            $item_id    Row id.
	 * @param Melomaniac_Sync_Release_DTO[] $candidates Candidates found for this barcode.
	 * @return void
	 */
	private function park_for_choice( $item_id, array $candidates ) {
		global $wpdb;

		$summaries = array();

		foreach ( $candidates as $candidate ) {
			$summaries[] = array(
				'source'       => $candidate->source,
				'release_id'   => $candidate->source_id(),
				'display_name' => $candidate->display_name(),
				'format_label' => $candidate->format_label(),
				'label'        => $candidate->label,
				'catalog'      => $candidate->catalog_number,
				'year'         => $candidate->year,
				'country'      => $candidate->country,
			);
		}

		$wpdb->update(
			Melomaniac_Sync_Bulk_Table::name(),
			array(
				'status'     => 'eleccion',
				'candidates' => wp_json_encode( $summaries ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * The overrides a row was queued with.
	 *
	 * @param object $row Row from the bulk items table.
	 * @return array
	 */
	private function decode_overrides( $row ) {
		$overrides = json_decode( (string) $row->overrides, true );

		return is_array( $overrides ) ? $overrides : array();
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
