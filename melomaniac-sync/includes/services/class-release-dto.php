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

		return $dto;
	}
}
