<?php
/**
 * Normalised representation of a music release.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Internal contract for release data.
 *
 * Every producer of release data (the MusicBrainz parser, the manual entry
 * form, and later the bulk importer) builds one of these, and every consumer
 * reads one. Adding a new legitimate data source means adding a producer, not
 * touching consumers.
 */
class Melomaniac_Sync_Release_DTO {

	/**
	 * Where this data came from: musicbrainz, discogs or manual.
	 *
	 * @var string
	 */
	public $source = 'manual';

	/**
	 * MusicBrainz release identifier, empty when the source is not MusicBrainz.
	 *
	 * @var string
	 */
	public $mbid = '';

	/**
	 * Discogs release identifier, empty when the source is not Discogs.
	 *
	 * @var string
	 */
	public $discogs_id = '';

	/**
	 * Free-form release notes, only Discogs provides these.
	 *
	 * @var string
	 */
	public $notes = '';

	/**
	 * Release status, in MusicBrainz's own vocabulary.
	 *
	 * Stored with MusicBrainz's exact values so a contribution can be seeded
	 * without translating anything.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Primary release group type: Album, Single, EP, Broadcast, Other.
	 *
	 * @var string
	 */
	public $release_type = '';

	/**
	 * Secondary release group type: Compilation, Live, Soundtrack, Remix.
	 *
	 * @var string
	 */
	public $secondary_type = '';

	/**
	 * Physical packaging: Digipak, Gatefold Cover, Jewel Case and so on.
	 *
	 * @var string
	 */
	public $packaging = '';

	/**
	 * Release language, ISO 639-3.
	 *
	 * @var string
	 */
	public $language = '';

	/**
	 * Writing script, ISO 15924.
	 *
	 * @var string
	 */
	public $script = '';

	/**
	 * How many discs, tapes or records the release has.
	 *
	 * @var int
	 */
	public $medium_count = 1;

	/**
	 * Barcode as printed on the sleeve.
	 *
	 * @var string
	 */
	public $barcode = '';

	/**
	 * Artist credit, already joined into a display string.
	 *
	 * @var string
	 */
	public $artist = '';

	/**
	 * Album title.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Record label.
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Catalogue number.
	 *
	 * @var string
	 */
	public $catalog_number = '';

	/**
	 * Release year, four digits.
	 *
	 * @var string
	 */
	public $year = '';

	/**
	 * Full release date when known.
	 *
	 * @var string
	 */
	public $release_date = '';

	/**
	 * Normalised format key: vinyl, cd, cassette or other.
	 *
	 * @var string
	 */
	public $format = '';

	/**
	 * Verbatim format description, e.g. '12" Vinyl'.
	 *
	 * @var string
	 */
	public $format_detail = '';

	/**
	 * Pressing country code.
	 *
	 * @var string
	 */
	public $country = '';

	/**
	 * Genres reported by MusicBrainz.
	 *
	 * @var string[]
	 */
	public $genres = array();

	/**
	 * Track list.
	 *
	 * Each entry: array{ medium:int, number:string, title:string, length:string }.
	 *
	 * @var array[]
	 */
	public $tracklist = array();

	/**
	 * Front cover URL, full size.
	 *
	 * @var string
	 */
	public $cover_url = '';

	/**
	 * Front cover URL, thumbnail.
	 *
	 * @var string
	 */
	public $cover_thumb_url = '';

	/**
	 * Attachment ID when the cover was uploaded manually.
	 *
	 * @var int
	 */
	public $cover_attachment_id = 0;

	/**
	 * Release statuses MusicBrainz accepts, keyed by its own value.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'Official'       => __( 'Oficial', 'melomaniac-sync' ),
			'Promotion'      => __( 'Promocional', 'melomaniac-sync' ),
			'Bootleg'        => __( 'Bootleg', 'melomaniac-sync' ),
			'Pseudo-Release' => __( 'Pseudo-lanzamiento (traducción o transliteración)', 'melomaniac-sync' ),
		);
	}

	/**
	 * Primary release group types.
	 *
	 * @return array<string,string>
	 */
	public static function release_types() {
		return array(
			'Album'     => __( 'Álbum', 'melomaniac-sync' ),
			'Single'    => __( 'Single', 'melomaniac-sync' ),
			'EP'        => __( 'EP', 'melomaniac-sync' ),
			'Broadcast' => __( 'Transmisión', 'melomaniac-sync' ),
			'Other'     => __( 'Otro', 'melomaniac-sync' ),
		);
	}

	/**
	 * Secondary release group types, which stack on top of the primary one.
	 *
	 * @return array<string,string>
	 */
	public static function secondary_types() {
		return array(
			''             => __( 'Ninguno', 'melomaniac-sync' ),
			'Compilation'  => __( 'Compilado', 'melomaniac-sync' ),
			'Live'         => __( 'En vivo', 'melomaniac-sync' ),
			'Soundtrack'   => __( 'Banda sonora', 'melomaniac-sync' ),
			'Remix'        => __( 'Remixes', 'melomaniac-sync' ),
			'DJ-mix'       => __( 'DJ mix', 'melomaniac-sync' ),
			'Demo'         => __( 'Demo', 'melomaniac-sync' ),
		);
	}

	/**
	 * Packaging options MusicBrainz accepts.
	 *
	 * @return array<string,string>
	 */
	public static function packagings() {
		return array(
			''                      => __( 'Sin especificar', 'melomaniac-sync' ),
			'Jewel Case'            => __( 'Caja de CD (jewel case)', 'melomaniac-sync' ),
			'Slim Jewel Case'       => __( 'Caja de CD delgada', 'melomaniac-sync' ),
			'Digipak'               => __( 'Digipak', 'melomaniac-sync' ),
			'Cardboard/Paper Sleeve' => __( 'Funda de cartón o papel', 'melomaniac-sync' ),
			'Gatefold Cover'        => __( 'Carátula doble (gatefold)', 'melomaniac-sync' ),
			'Box'                   => __( 'Caja (box set)', 'melomaniac-sync' ),
			'Keep Case'             => __( 'Caja de DVD', 'melomaniac-sync' ),
			'None'                  => __( 'Sin empaque', 'melomaniac-sync' ),
			'Other'                 => __( 'Otro', 'melomaniac-sync' ),
		);
	}

	/**
	 * Languages, ISO 639-3, limited to what a record shop actually stocks.
	 *
	 * @return array<string,string>
	 */
	public static function languages() {
		return array(
			''    => __( 'Sin especificar', 'melomaniac-sync' ),
			'spa' => __( 'Español', 'melomaniac-sync' ),
			'eng' => __( 'Inglés', 'melomaniac-sync' ),
			'por' => __( 'Portugués', 'melomaniac-sync' ),
			'fra' => __( 'Francés', 'melomaniac-sync' ),
			'ita' => __( 'Italiano', 'melomaniac-sync' ),
			'deu' => __( 'Alemán', 'melomaniac-sync' ),
			'jpn' => __( 'Japonés', 'melomaniac-sync' ),
			'zxx' => __( 'Sin letra (instrumental)', 'melomaniac-sync' ),
			'mul' => __( 'Varios idiomas', 'melomaniac-sync' ),
		);
	}

	/**
	 * Writing scripts, ISO 15924.
	 *
	 * @return array<string,string>
	 */
	public static function scripts() {
		return array(
			''     => __( 'Sin especificar', 'melomaniac-sync' ),
			'Latn' => __( 'Latino', 'melomaniac-sync' ),
			'Cyrl' => __( 'Cirílico', 'melomaniac-sync' ),
			'Jpan' => __( 'Japonés', 'melomaniac-sync' ),
			'Hang' => __( 'Coreano', 'melomaniac-sync' ),
			'Hans' => __( 'Chino simplificado', 'melomaniac-sync' ),
		);
	}

	/**
	 * Known format keys mapped to their translated labels.
	 *
	 * @return array<string,string>
	 */
	public static function formats() {
		return array(
			'vinyl'    => __( 'Vinilo', 'melomaniac-sync' ),
			'cd'       => __( 'CD', 'melomaniac-sync' ),
			'cassette' => __( 'Cassette', 'melomaniac-sync' ),
			'other'    => __( 'Otro', 'melomaniac-sync' ),
		);
	}

	/**
	 * Human readable label for this release's format.
	 *
	 * @return string
	 */
	public function format_label() {
		$formats = self::formats();

		if ( isset( $formats[ $this->format ] ) ) {
			return $formats[ $this->format ];
		}

		return $formats['other'];
	}

	/**
	 * Artist and title joined for use as a product name.
	 *
	 * @return string
	 */
	public function display_name() {
		if ( '' === $this->artist ) {
			return $this->title;
		}

		if ( '' === $this->title ) {
			return $this->artist;
		}

		return sprintf( '%1$s - %2$s', $this->artist, $this->title );
	}

	/**
	 * Renders the track list in the plain text shape the manual form accepts.
	 *
	 * Inverse of Melomaniac_Sync_Manual_Entry_Handler::parse_tracklist(), so a
	 * release can be loaded into the form, edited and submitted back.
	 *
	 * @return string
	 */
	public function tracklist_as_text() {
		$lines = array();

		foreach ( $this->tracklist as $track ) {
			$title = isset( $track['title'] ) ? (string) $track['title'] : '';

			if ( '' === $title ) {
				continue;
			}

			$line = '';

			if ( ! empty( $track['number'] ) ) {
				$line .= $track['number'] . '. ';
			}

			$line .= $title;

			if ( ! empty( $track['length'] ) ) {
				$line .= ' | ' . $track['length'];
			}

			$lines[] = $line;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Identifier of this release within its own source.
	 *
	 * @return string Empty for manual entries.
	 */
	public function source_id() {
		if ( 'musicbrainz' === $this->source ) {
			return $this->mbid;
		}

		if ( 'discogs' === $this->source ) {
			return $this->discogs_id;
		}

		return '';
	}

	/**
	 * Whether there is enough data to create a product.
	 *
	 * @return bool
	 */
	public function is_usable() {
		return '' !== trim( $this->title ) || '' !== trim( $this->artist );
	}

	/**
	 * Exports the object as a plain array, for caching and for JSON responses.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'source'              => $this->source,
			'status'              => $this->status,
			'release_type'        => $this->release_type,
			'secondary_type'      => $this->secondary_type,
			'packaging'           => $this->packaging,
			'language'            => $this->language,
			'script'              => $this->script,
			'medium_count'        => $this->medium_count,
			'mbid'                => $this->mbid,
			'discogs_id'          => $this->discogs_id,
			'notes'               => $this->notes,
			'barcode'             => $this->barcode,
			'artist'              => $this->artist,
			'title'               => $this->title,
			'label'               => $this->label,
			'catalog_number'      => $this->catalog_number,
			'year'                => $this->year,
			'release_date'        => $this->release_date,
			'format'              => $this->format,
			'format_detail'       => $this->format_detail,
			'country'             => $this->country,
			'genres'              => $this->genres,
			'tracklist'           => $this->tracklist,
			'cover_url'           => $this->cover_url,
			'cover_thumb_url'     => $this->cover_thumb_url,
			'cover_attachment_id' => $this->cover_attachment_id,
		);
	}

	/**
	 * Rebuilds an instance from a plain array.
	 *
	 * @param array $data Previously exported data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		$dto = new self();

		$strings = array(
			'source',
			'status',
			'release_type',
			'secondary_type',
			'packaging',
			'language',
			'script',
			'mbid',
			'discogs_id',
			'notes',
			'barcode',
			'artist',
			'title',
			'label',
			'catalog_number',
			'year',
			'release_date',
			'format',
			'format_detail',
			'country',
			'cover_url',
			'cover_thumb_url',
		);

		foreach ( $strings as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$dto->{$key} = (string) $data[ $key ];
			}
		}

		if ( isset( $data['genres'] ) && is_array( $data['genres'] ) ) {
			$dto->genres = array_values( array_filter( array_map( 'strval', $data['genres'] ) ) );
		}

		if ( isset( $data['tracklist'] ) && is_array( $data['tracklist'] ) ) {
			$dto->tracklist = $data['tracklist'];
		}

		if ( isset( $data['cover_attachment_id'] ) ) {
			$dto->cover_attachment_id = (int) $data['cover_attachment_id'];
		}

		if ( isset( $data['medium_count'] ) ) {
			$dto->medium_count = max( 1, (int) $data['medium_count'] );
		}

		return $dto;
	}
}
