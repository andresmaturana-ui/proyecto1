<?php
/**
 * Release lookup service.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * The single entry point for finding release data.
 *
 * The scan screen, the bulk importer (phase 3) and the contribution flow
 * (phase 4) all call this class. The HTTP client, the rate limiter and the
 * parser sit behind it and are not used directly anywhere else.
 */
class Melomaniac_Sync_Release_Lookup_Service {

	/**
	 * MusicBrainz HTTP client.
	 *
	 * @var Melomaniac_Sync_MusicBrainz_Client
	 */
	private $client;

	/**
	 * Payload parser.
	 *
	 * @var Melomaniac_Sync_MusicBrainz_Response_Parser
	 */
	private $parser;

	/**
	 * Cover art client.
	 *
	 * @var Melomaniac_Sync_Cover_Art_Client
	 */
	private $cover_art;

	/**
	 * Response cache.
	 *
	 * @var Melomaniac_Sync_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_MusicBrainz_Client          $client    HTTP client.
	 * @param Melomaniac_Sync_MusicBrainz_Response_Parser $parser    Parser.
	 * @param Melomaniac_Sync_Cover_Art_Client            $cover_art Cover art client.
	 * @param Melomaniac_Sync_Cache                       $cache     Cache.
	 */
	public function __construct(
		Melomaniac_Sync_MusicBrainz_Client $client,
		Melomaniac_Sync_MusicBrainz_Response_Parser $parser,
		Melomaniac_Sync_Cover_Art_Client $cover_art,
		Melomaniac_Sync_Cache $cache
	) {
		$this->client    = $client;
		$this->parser    = $parser;
		$this->cover_art = $cover_art;
		$this->cache     = $cache;
	}

	/**
	 * Normalises a barcode to digits and validates its length.
	 *
	 * @param string $barcode Raw user input.
	 * @return string|WP_Error Digits only, or an error.
	 */
	public function normalise_barcode( $barcode ) {
		$digits = preg_replace( '/\D/', '', (string) $barcode );

		// Covers EAN-8, UPC-A, EAN-13 and ITF-14, plus short legacy codes.
		if ( strlen( $digits ) < 6 || strlen( $digits ) > 14 ) {
			return new WP_Error(
				'melomaniac_sync_invalid_barcode',
				__( 'El código de barra debe tener entre 6 y 14 dígitos.', 'melomaniac-sync' )
			);
		}

		return $digits;
	}

	/**
	 * Finds every release matching a barcode.
	 *
	 * Results are lightweight candidates. When exactly one release matches, it
	 * is upgraded to a full record including track list and cover art so the
	 * caller can create a product straight away.
	 *
	 * @param string $barcode Raw or normalised barcode.
	 * @return array|WP_Error {
	 *     @type string                          $barcode    Normalised barcode.
	 *     @type Melomaniac_Sync_Release_DTO[]    $candidates Matching releases.
	 *     @type bool                            $resolved   True when a single full record is available.
	 * }
	 */
	public function find_by_barcode( $barcode ) {
		$barcode = $this->normalise_barcode( $barcode );

		if ( is_wp_error( $barcode ) ) {
			return $barcode;
		}

		$cache_key = 'barcode_' . $barcode;
		$cached    = $this->cache->get( $cache_key );

		if ( is_array( $cached ) ) {
			return $this->hydrate_barcode_result( $barcode, $cached );
		}

		$payload = $this->client->search_releases_by_barcode( $barcode );

		if ( is_wp_error( $payload ) ) {
			// A 404 on a search means no match rather than a failure.
			if ( 'melomaniac_sync_not_found' === $payload->get_error_code() ) {
				$payload = array( 'releases' => array() );
			} else {
				return $payload;
			}
		}

		$candidates = $this->parser->parse_search_results( $payload );

		// The search index can lag; keep only releases that really carry this barcode.
		$candidates = array_values(
			array_filter(
				$candidates,
				static function ( $candidate ) use ( $barcode ) {
					return '' === $candidate->barcode || $candidate->barcode === $barcode;
				}
			)
		);

		$serialised = array();

		foreach ( $candidates as $candidate ) {
			$candidate->barcode = $barcode;
			$serialised[]       = $candidate->to_array();
		}

		$this->cache->set( $cache_key, $serialised );

		return $this->hydrate_barcode_result( $barcode, $serialised );
	}

	/**
	 * Turns cached candidate arrays back into the public result shape.
	 *
	 * @param string $barcode    Normalised barcode.
	 * @param array  $serialised Cached candidate arrays.
	 * @return array|WP_Error
	 */
	private function hydrate_barcode_result( $barcode, array $serialised ) {
		$candidates = array();

		foreach ( $serialised as $data ) {
			if ( is_array( $data ) ) {
				$candidates[] = Melomaniac_Sync_Release_DTO::from_array( $data );
			}
		}

		$resolved = false;

		if ( 1 === count( $candidates ) && '' !== $candidates[0]->mbid ) {
			$full = $this->get_release( $candidates[0]->mbid );

			if ( is_wp_error( $full ) ) {
				return $full;
			}

			$full->barcode = $barcode;
			$candidates    = array( $full );
			$resolved      = true;
		}

		return array(
			'barcode'    => $barcode,
			'candidates' => $candidates,
			'resolved'   => $resolved,
		);
	}

	/**
	 * Fetches the complete record for one release, cover art included.
	 *
	 * @param string $mbid MusicBrainz release identifier.
	 * @return Melomaniac_Sync_Release_DTO|WP_Error
	 */
	public function get_release( $mbid ) {
		$mbid = sanitize_text_field( (string) $mbid );

		if ( ! $this->is_valid_mbid( $mbid ) ) {
			return new WP_Error(
				'melomaniac_sync_invalid_mbid',
				__( 'El identificador de MusicBrainz no es válido.', 'melomaniac-sync' )
			);
		}

		$cache_key = 'release_' . $mbid;
		$cached    = $this->cache->get( $cache_key );

		if ( is_array( $cached ) ) {
			return Melomaniac_Sync_Release_DTO::from_array( $cached );
		}

		$payload = $this->client->get_release( $mbid );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$release = $this->parser->parse_release( $payload );

		if ( $this->parser->has_front_cover( $payload ) ) {
			$urls                     = $this->cover_art->build_front_urls( $mbid );
			$release->cover_url       = $urls['full'];
			$release->cover_thumb_url = $urls['thumb'];
		}

		$this->cache->set( $cache_key, $release->to_array() );

		return $release;
	}

	/**
	 * Searches releases by artist and title.
	 *
	 * Reserved for the phase 4 contribution flow, which needs to check whether
	 * a release already exists before proposing a new one.
	 *
	 * @param string $artist Artist name.
	 * @param string $title  Release title.
	 * @param int    $limit  Maximum results.
	 * @return Melomaniac_Sync_Release_DTO[]|WP_Error
	 */
	public function search_by_text( $artist, $title, $limit = 10 ) {
		$payload = $this->client->search_releases_by_text( $artist, $title, $limit );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return $this->parser->parse_search_results( $payload );
	}

	/**
	 * Validates the UUID shape of a MusicBrainz identifier.
	 *
	 * @param string $mbid Candidate identifier.
	 * @return bool
	 */
	public function is_valid_mbid( $mbid ) {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $mbid );
	}
}
