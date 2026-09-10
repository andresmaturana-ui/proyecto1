<?php
/**
 * HTTP client for the MusicBrainz web service.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Performs read-only, unauthenticated calls against musicbrainz.org/ws/2.
 *
 * This class knows about HTTP, headers and error codes. It does not know what a
 * release looks like: turning payloads into domain objects is the parser's job.
 */
class Melomaniac_Sync_MusicBrainz_Client {

	/**
	 * Web service base URL.
	 */
	const API_BASE = 'https://musicbrainz.org/ws/2/';

	/**
	 * Request timeout in seconds.
	 */
	const TIMEOUT = 15;

	/**
	 * Rate limiter shared across processes.
	 *
	 * @var Melomaniac_Sync_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Logger.
	 *
	 * @var Melomaniac_Sync_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Rate_Limiter $rate_limiter Rate limiter.
	 * @param Melomaniac_Sync_Logger       $logger       Logger.
	 */
	public function __construct( Melomaniac_Sync_Rate_Limiter $rate_limiter, Melomaniac_Sync_Logger $logger ) {
		$this->rate_limiter = $rate_limiter;
		$this->logger       = $logger;
	}

	/**
	 * Searches releases by barcode.
	 *
	 * @param string $barcode Digits only.
	 * @param int    $limit   Maximum results.
	 * @return array|WP_Error Decoded payload or error.
	 */
	public function search_releases_by_barcode( $barcode, $limit = 25 ) {
		return $this->get(
			'release',
			array(
				'query' => 'barcode:' . $barcode,
				'limit' => $limit,
			)
		);
	}

	/**
	 * Searches releases by artist and title.
	 *
	 * Used by the contribution flow in phase 4 to find an existing release
	 * before proposing a new one.
	 *
	 * @param string $artist Artist name.
	 * @param string $title  Release title.
	 * @param int    $limit  Maximum results.
	 * @return array|WP_Error Decoded payload or error.
	 */
	public function search_releases_by_text( $artist, $title, $limit = 10 ) {
		$clauses = array();

		if ( '' !== trim( (string) $artist ) ) {
			$clauses[] = 'artist:' . $this->escape_lucene( $artist );
		}

		if ( '' !== trim( (string) $title ) ) {
			$clauses[] = 'release:' . $this->escape_lucene( $title );
		}

		if ( empty( $clauses ) ) {
			return new WP_Error(
				'melomaniac_sync_empty_query',
				__( 'Se necesita al menos artista o título para buscar.', 'melomaniac-sync' )
			);
		}

		return $this->get(
			'release',
			array(
				'query' => implode( ' AND ', $clauses ),
				'limit' => $limit,
			)
		);
	}

	/**
	 * Fetches the full detail of one release.
	 *
	 * @param string $mbid MusicBrainz release identifier.
	 * @return array|WP_Error Decoded payload or error.
	 */
	public function get_release( $mbid ) {
		return $this->get(
			'release/' . $mbid,
			array(
				'inc' => 'artist-credits+labels+recordings+release-groups+genres',
			)
		);
	}

	/**
	 * Performs a GET request against the web service.
	 *
	 * @param string $endpoint Path relative to the API base.
	 * @param array  $args     Query arguments.
	 * @return array|WP_Error Decoded payload or error.
	 */
	public function get( $endpoint, array $args = array() ) {
		$args['fmt'] = 'json';

		$url = self::API_BASE . ltrim( $endpoint, '/' ) . '?' . http_build_query( $args );

		$this->rate_limiter->wait_for_slot();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => $this->user_agent(),
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'MusicBrainz request failed.', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// 503 is how MusicBrainz signals throttling. Back off once and retry.
		if ( 503 === $code ) {
			$this->logger->log( 'MusicBrainz returned 503, retrying once.', 'warning' );
			$this->rate_limiter->reserve_slot();
			$this->rate_limiter->wait_for_slot();

			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => self::TIMEOUT,
					'user-agent' => $this->user_agent(),
					'headers'    => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
		}

		if ( 404 === $code ) {
			return new WP_Error(
				'melomaniac_sync_not_found',
				__( 'MusicBrainz no tiene ese lanzamiento.', 'melomaniac-sync' )
			);
		}

		if ( $code < 200 || $code > 299 ) {
			$this->logger->error( 'Unexpected MusicBrainz status.', array( 'status' => $code ) );

			return new WP_Error(
				'melomaniac_sync_http_error',
				sprintf(
					/* translators: %d: HTTP status code returned by MusicBrainz. */
					__( 'MusicBrainz respondió con el código %d.', 'melomaniac-sync' ),
					$code
				)
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'melomaniac_sync_invalid_json',
				__( 'La respuesta de MusicBrainz no se pudo interpretar.', 'melomaniac-sync' )
			);
		}

		return $decoded;
	}

	/**
	 * Builds the User-Agent string MusicBrainz requires from every client.
	 *
	 * @return string
	 */
	private function user_agent() {
		return sprintf(
			'MelomaniacSync/%s ( %s )',
			MELOMANIAC_SYNC_VERSION,
			Melomaniac_Sync_Settings::api_contact()
		);
	}

	/**
	 * Escapes characters that carry meaning in the Lucene query syntax.
	 *
	 * @param string $value Raw user value.
	 * @return string Quoted, safe term.
	 */
	private function escape_lucene( $value ) {
		$value = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value );

		return '"' . $value . '"';
	}
}
