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
 * MusicBrainz is asked first: it needs no credential, its data is open, and its
 * cover art can be reused freely. Discogs is only consulted when MusicBrainz has
 * nothing, because it needs the store's own token and its images carry usage
 * restrictions that MusicBrainz's do not.
 *
 * The scan app, the bulk importer and the contribution flow all call this class.
 * The HTTP clients, the rate limiters and the parsers sit behind it.
 */
class Melomaniac_Sync_Release_Lookup_Service {

	/**
	 * MusicBrainz HTTP client.
	 *
	 * @var Melomaniac_Sync_MusicBrainz_Client
	 */
	private $musicbrainz;

	/**
	 * MusicBrainz payload parser.
	 *
	 * @var Melomaniac_Sync_MusicBrainz_Response_Parser
	 */
	private $musicbrainz_parser;

	/**
	 * Cover art client.
	 *
	 * @var Melomaniac_Sync_Cover_Art_Client
	 */
	private $cover_art;

	/**
	 * Discogs HTTP client.
	 *
	 * @var Melomaniac_Sync_Discogs_Client
	 */
	private $discogs;

	/**
	 * Discogs payload parser.
	 *
	 * @var Melomaniac_Sync_Discogs_Response_Parser
	 */
	private $discogs_parser;

	/**
	 * Response cache.
	 *
	 * @var Melomaniac_Sync_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_MusicBrainz_Client          $musicbrainz        MusicBrainz client.
	 * @param Melomaniac_Sync_MusicBrainz_Response_Parser $musicbrainz_parser MusicBrainz parser.
	 * @param Melomaniac_Sync_Cover_Art_Client            $cover_art          Cover art client.
	 * @param Melomaniac_Sync_Discogs_Client              $discogs            Discogs client.
	 * @param Melomaniac_Sync_Discogs_Response_Parser     $discogs_parser     Discogs parser.
	 * @param Melomaniac_Sync_Cache                       $cache              Cache.
	 */
	public function __construct(
		Melomaniac_Sync_MusicBrainz_Client $musicbrainz,
		Melomaniac_Sync_MusicBrainz_Response_Parser $musicbrainz_parser,
		Melomaniac_Sync_Cover_Art_Client $cover_art,
		Melomaniac_Sync_Discogs_Client $discogs,
		Melomaniac_Sync_Discogs_Response_Parser $discogs_parser,
		Melomaniac_Sync_Cache $cache
	) {
		$this->musicbrainz        = $musicbrainz;
		$this->musicbrainz_parser = $musicbrainz_parser;
		$this->cover_art          = $cover_art;
		$this->discogs            = $discogs;
		$this->discogs_parser     = $discogs_parser;
		$this->cache              = $cache;
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
	 * Finds every release matching a barcode, MusicBrainz first.
	 *
	 * When exactly one release matches, it is upgraded to a full record
	 * including track list and cover art so the caller can create a product
	 * straight away.
	 *
	 * @param string $barcode Raw or normalised barcode.
	 * @return array|WP_Error {
	 *     @type string                        $barcode    Normalised barcode.
	 *     @type Melomaniac_Sync_Release_DTO[] $candidates Matching releases.
	 *     @type bool                          $resolved   True when a single full record is available.
	 *     @type string                        $source     Which service answered, empty when none did.
	 * }
	 */
	public function find_by_barcode( $barcode ) {
		$barcode = $this->normalise_barcode( $barcode );

		if ( is_wp_error( $barcode ) ) {
			return $barcode;
		}

		$cache_key = 'barcode_' . $barcode;
		$cached    = $this->cache->get( $cache_key );

		if ( is_array( $cached ) && isset( $cached['candidates'] ) ) {
			return $this->hydrate_barcode_result( $barcode, $cached['candidates'], (string) $cached['source'] );
		}

		$candidates = array();
		$source     = '';

		$from_musicbrainz = $this->search_musicbrainz_by_barcode( $barcode );

		if ( is_wp_error( $from_musicbrainz ) ) {
			return $from_musicbrainz;
		}

		if ( ! empty( $from_musicbrainz ) ) {
			$candidates = $from_musicbrainz;
			$source     = 'musicbrainz';
		} elseif ( $this->discogs->is_available() ) {
			$from_discogs = $this->search_discogs_by_barcode( $barcode );

			// A Discogs failure must not hide a clean "MusicBrainz had nothing":
			// the caller can still fall back to the manual form.
			if ( ! is_wp_error( $from_discogs ) && ! empty( $from_discogs ) ) {
				$candidates = $from_discogs;
				$source     = 'discogs';
			}
		}

		$serialised = array();

		foreach ( $candidates as $candidate ) {
			$candidate->barcode = $barcode;
			$serialised[]       = $candidate->to_array();
		}

		$this->cache->set(
			$cache_key,
			array(
				'candidates' => $serialised,
				'source'     => $source,
			)
		);

		return $this->hydrate_barcode_result( $barcode, $serialised, $source );
	}

	/**
	 * Asks each source about a barcode separately, for diagnostics.
	 *
	 * Unlike find_by_barcode() this does not cascade and does not read or write
	 * the cache: the point is to see what every source really answers right now,
	 * including the one the cascade would never have reached.
	 *
	 * @param string $barcode Raw or normalised barcode.
	 * @return array|WP_Error {
	 *     @type string $barcode     Normalised barcode.
	 *     @type array  $musicbrainz Result for MusicBrainz.
	 *     @type array  $discogs     Result for Discogs.
	 * }
	 */
	public function probe_barcode( $barcode ) {
		$barcode = $this->normalise_barcode( $barcode );

		if ( is_wp_error( $barcode ) ) {
			return $barcode;
		}

		return array(
			'barcode'     => $barcode,
			'musicbrainz' => $this->describe_probe(
				__( 'MusicBrainz', 'melomaniac-sync' ),
				true,
				'',
				$this->search_musicbrainz_by_barcode( $barcode )
			),
			'discogs'     => $this->describe_probe(
				__( 'Discogs', 'melomaniac-sync' ),
				$this->discogs->is_available(),
				__( 'Falta el token de Discogs, o el respaldo está desactivado en Ajustes.', 'melomaniac-sync' ),
				$this->discogs->is_available() ? $this->search_discogs_by_barcode( $barcode ) : array()
			),
		);
	}

	/**
	 * Normalises one source's answer into something a screen can render.
	 *
	 * @param string                                $label     Service name.
	 * @param bool                                  $available Whether the source could be asked at all.
	 * @param string                                $skipped   Why it was skipped, when it was.
	 * @param Melomaniac_Sync_Release_DTO[]|WP_Error $results  What the source answered.
	 * @return array
	 */
	private function describe_probe( $label, $available, $skipped, $results ) {
		$out = array(
			'label'     => $label,
			'available' => (bool) $available,
			'error'     => '',
			'count'     => 0,
			'matches'   => array(),
		);

		if ( ! $available ) {
			$out['error'] = $skipped;
			return $out;
		}

		if ( is_wp_error( $results ) ) {
			$out['error'] = $results->get_error_message();
			return $out;
		}

		$out['count'] = count( $results );

		// Three is enough to tell whether the answer is the right record.
		foreach ( array_slice( $results, 0, 3 ) as $release ) {
			$out['matches'][] = array(
				'artist' => $release->artist,
				'title'  => $release->title,
				'year'   => $release->year,
				'label'  => $release->label,
				'format' => '' !== $release->format_detail ? $release->format_detail : $release->format_label(),
			);
		}

		return $out;
	}

	/**
	 * Searches MusicBrainz by barcode.
	 *
	 * @param string $barcode Normalised barcode.
	 * @return Melomaniac_Sync_Release_DTO[]|WP_Error
	 */
	private function search_musicbrainz_by_barcode( $barcode ) {
		$payload = $this->musicbrainz->search_releases_by_barcode( $barcode );

		if ( is_wp_error( $payload ) ) {
			// A 404 on a search means no match rather than a failure.
			if ( 'melomaniac_sync_not_found' !== $payload->get_error_code() ) {
				return $payload;
			}

			$payload = array( 'releases' => array() );
		}

		$candidates = $this->musicbrainz_parser->parse_search_results( $payload );

		// The search index can lag; keep only releases that really carry this barcode.
		return array_values(
			array_filter(
				$candidates,
				static function ( $candidate ) use ( $barcode ) {
					return '' === $candidate->barcode || $candidate->barcode === $barcode;
				}
			)
		);
	}

	/**
	 * Searches Discogs by barcode.
	 *
	 * @param string $barcode Normalised barcode.
	 * @return Melomaniac_Sync_Release_DTO[]|WP_Error
	 */
	private function search_discogs_by_barcode( $barcode ) {
		$results = $this->discogs->search_by_barcode( $barcode );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		// More than a handful of editions is a data problem, not a choice for
		// the shop owner to make; keep the list reviewable.
		return $this->discogs_parser->parse_search_results( array_slice( $results, 0, 10 ) );
	}

	/**
	 * Turns cached candidate arrays back into the public result shape.
	 *
	 * @param string $barcode    Normalised barcode.
	 * @param array  $serialised Cached candidate arrays.
	 * @param string $source     Which service answered.
	 * @return array|WP_Error
	 */
	private function hydrate_barcode_result( $barcode, array $serialised, $source ) {
		$candidates = array();

		foreach ( $serialised as $data ) {
			if ( is_array( $data ) ) {
				$candidates[] = Melomaniac_Sync_Release_DTO::from_array( $data );
			}
		}

		$resolved = false;

		if ( 1 === count( $candidates ) && '' !== $candidates[0]->source_id() ) {
			$full = $this->get_release( $candidates[0]->source, $candidates[0]->source_id() );

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
			'source'     => $source,
		);
	}

	/**
	 * Fetches the complete record for one release, cover art included.
	 *
	 * @param string $source One of musicbrainz or discogs.
	 * @param string $id     Identifier within that source.
	 * @return Melomaniac_Sync_Release_DTO|WP_Error
	 */
	public function get_release( $source, $id ) {
		$source = sanitize_key( (string) $source );
		$id     = sanitize_text_field( (string) $id );

		$cache_key = 'release_' . $source . '_' . $id;
		$cached    = $this->cache->get( $cache_key );

		if ( is_array( $cached ) ) {
			return Melomaniac_Sync_Release_DTO::from_array( $cached );
		}

		if ( 'musicbrainz' === $source ) {
			$release = $this->fetch_musicbrainz_release( $id );
		} elseif ( 'discogs' === $source ) {
			$release = $this->fetch_discogs_release( $id );
		} elseif ( '' === $source ) {
			// The browser sent no source at all, which is what an outdated
			// cached script does: older versions posted different field names.
			return new WP_Error(
				'melomaniac_sync_stale_assets',
				__( 'La página quedó con una versión vieja del plugin en caché. Recarga con Ctrl+F5 (Cmd+Shift+R en Mac) y vuelve a intentar.', 'melomaniac-sync' )
			);
		} else {
			return new WP_Error(
				'melomaniac_sync_unknown_source',
				sprintf(
					/* translators: %s: the data source that was requested. */
					__( 'Fuente de datos desconocida: %s', 'melomaniac-sync' ),
					$source
				)
			);
		}

		if ( is_wp_error( $release ) ) {
			return $release;
		}

		$this->cache->set( $cache_key, $release->to_array() );

		return $release;
	}

	/**
	 * Fetches and parses one MusicBrainz release.
	 *
	 * @param string $mbid Release MBID.
	 * @return Melomaniac_Sync_Release_DTO|WP_Error
	 */
	private function fetch_musicbrainz_release( $mbid ) {
		if ( ! $this->is_valid_mbid( $mbid ) ) {
			return new WP_Error(
				'melomaniac_sync_invalid_mbid',
				__( 'El identificador de MusicBrainz no es válido.', 'melomaniac-sync' )
			);
		}

		$payload = $this->musicbrainz->get_release( $mbid );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$release         = $this->musicbrainz_parser->parse_release( $payload );
		$release->source = 'musicbrainz';

		if ( $this->musicbrainz_parser->has_front_cover( $payload ) ) {
			$urls                     = $this->cover_art->build_front_urls( $mbid );
			$release->cover_url       = $urls['full'];
			$release->cover_thumb_url = $urls['thumb'];
		}

		return $release;
	}

	/**
	 * Fetches and parses one Discogs release.
	 *
	 * @param string $release_id Discogs release identifier.
	 * @return Melomaniac_Sync_Release_DTO|WP_Error
	 */
	private function fetch_discogs_release( $release_id ) {
		if ( ! $this->discogs->is_available() ) {
			return new WP_Error(
				'melomaniac_sync_discogs_unavailable',
				__( 'La búsqueda en Discogs está desactivada o sin token.', 'melomaniac-sync' )
			);
		}

		$payload = $this->discogs->get_release( $release_id );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return $this->discogs_parser->parse_release( $payload );
	}

	/**
	 * Searches releases by artist and title on MusicBrainz.
	 *
	 * Used by the contribution flow, which needs to check whether a release
	 * already exists before proposing a new one. Deliberately MusicBrainz only:
	 * contributions go to MusicBrainz.
	 *
	 * @param string $artist Artist name.
	 * @param string $title  Release title.
	 * @param int    $limit  Maximum results.
	 * @return Melomaniac_Sync_Release_DTO[]|WP_Error
	 */
	public function search_by_text( $artist, $title, $limit = 10 ) {
		$payload = $this->musicbrainz->search_releases_by_text( $artist, $title, $limit );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return $this->musicbrainz_parser->parse_search_results( $payload );
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
