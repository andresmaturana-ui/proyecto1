<?php
/**
 * Builds a release object out of manually entered fields.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns a plain array of fields into a Melomaniac_Sync_Release_DTO.
 *
 * Used by the manual entry form (from $_POST) and by the REST API (from a
 * request body), so a disc typed in from wp-admin and one typed in from the
 * app go through the exact same validation.
 */
class Melomaniac_Sync_Manual_Release_Builder {

	/**
	 * Builds a release object out of submitted fields.
	 *
	 * Every value is read from the given array rather than a superglobal, so
	 * the caller decides where the data came from and how it was unslashed.
	 *
	 * @param array $data Field values, e.g. wp_unslash( $_POST ) or a REST
	 *                    request's params.
	 * @return Melomaniac_Sync_Release_DTO
	 */
	public function build_from_array( array $data ) {
		$release = new Melomaniac_Sync_Release_DTO();

		$release->barcode        = isset( $data['barcode'] ) ? preg_replace( '/\D/', '', (string) $data['barcode'] ) : '';
		$release->artist         = $this->read_text( $data, 'artist' );
		$release->title          = $this->read_text( $data, 'title' );
		$release->label          = $this->read_text( $data, 'label' );
		$release->catalog_number = $this->read_text( $data, 'catalog_number' );
		$release->country        = $this->read_text( $data, 'country' );
		$release->format_detail  = $this->read_text( $data, 'format_detail' );

		$this->read_release_date( $data, $release );
		$this->read_musicbrainz_fields( $data, $release );

		$format  = isset( $data['format'] ) ? sanitize_key( (string) $data['format'] ) : '';
		$formats = Melomaniac_Sync_Release_DTO::formats();

		$release->format = isset( $formats[ $format ] ) ? $format : 'other';

		if ( '' === $release->format_detail ) {
			$release->format_detail = $release->format_label();
		}

		$release->tracklist = $this->parse_tracklist( isset( $data['tracklist'] ) ? (string) $data['tracklist'] : '' );

		$attachment_id = isset( $data['cover_attachment_id'] ) ? absint( $data['cover_attachment_id'] ) : 0;

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			$release->cover_attachment_id = $attachment_id;
		}

		return $release;
	}

	/**
	 * Builds the release date out of the year, month and day fields.
	 *
	 * MusicBrainz accepts a partial date, so a year on its own is valid and a
	 * month without a day is too; the parts are only joined while they are
	 * present and in order.
	 *
	 * @param array                        $data    Field values.
	 * @param Melomaniac_Sync_Release_DTO $release Release being built.
	 * @return void
	 */
	private function read_release_date( array $data, Melomaniac_Sync_Release_DTO $release ) {
		$year  = isset( $data['year'] ) ? absint( $data['year'] ) : 0;
		$month = isset( $data['month'] ) ? absint( $data['month'] ) : 0;
		$day   = isset( $data['day'] ) ? absint( $data['day'] ) : 0;

		// Reject nonsense years instead of storing them.
		if ( $year < 1880 || $year > (int) gmdate( 'Y' ) + 1 ) {
			return;
		}

		$release->year         = (string) $year;
		$release->release_date = (string) $year;

		if ( $month < 1 || $month > 12 ) {
			return;
		}

		$release->release_date .= sprintf( '-%02d', $month );

		if ( $day < 1 || $day > 31 ) {
			return;
		}

		$release->release_date .= sprintf( '-%02d', $day );
	}

	/**
	 * Reads the fields a MusicBrainz contribution needs.
	 *
	 * Each one is checked against its own vocabulary, so only values MusicBrainz
	 * actually accepts ever get stored.
	 *
	 * @param array                        $data    Field values.
	 * @param Melomaniac_Sync_Release_DTO $release Release being built.
	 * @return void
	 */
	private function read_musicbrainz_fields( array $data, Melomaniac_Sync_Release_DTO $release ) {
		$vocabularies = array(
			'status'         => Melomaniac_Sync_Release_DTO::statuses(),
			'release_type'   => Melomaniac_Sync_Release_DTO::release_types(),
			'secondary_type' => Melomaniac_Sync_Release_DTO::secondary_types(),
			'packaging'      => Melomaniac_Sync_Release_DTO::packagings(),
			'language'       => Melomaniac_Sync_Release_DTO::languages(),
			'script'         => Melomaniac_Sync_Release_DTO::scripts(),
		);

		foreach ( $vocabularies as $field => $allowed ) {
			$value = $this->read_text( $data, $field );

			if ( array_key_exists( $value, $allowed ) ) {
				$release->{$field} = $value;
			}
		}

		$count = isset( $data['medium_count'] ) ? absint( $data['medium_count'] ) : 1;

		$release->medium_count = max( 1, min( 50, $count ) );
	}

	/**
	 * Reads and sanitises a text field.
	 *
	 * @param array  $data Field values.
	 * @param string $key  Field name.
	 * @return string
	 */
	private function read_text( array $data, $key ) {
		if ( ! isset( $data[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $data[ $key ] );
	}

	/**
	 * Parses the track list textarea into structured tracks.
	 *
	 * Accepted per line: "Title", "1. Title", "A1 Title", any of those followed
	 * by "| 3:45" for the duration.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array[]
	 */
	public function parse_tracklist( $raw ) {
		$lines     = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$tracklist = array();
		$position  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			++$position;

			$length = '';
			$parts  = explode( '|', $line );

			if ( count( $parts ) > 1 ) {
				$candidate = trim( array_pop( $parts ) );

				if ( preg_match( '/^\d{1,3}:[0-5]\d$/', $candidate ) ) {
					$length = $candidate;
					$line   = trim( implode( '|', $parts ) );
				}
			}

			$number = (string) $position;

			foreach ( $this->track_number_patterns() as $pattern ) {
				if ( preg_match( $pattern, $line, $matches ) ) {
					$number = sanitize_text_field( $matches[1] );
					$line   = trim( $matches[2] );
					break;
				}
			}

			if ( '' === $line ) {
				continue;
			}

			$tracklist[] = array(
				'medium' => 1,
				'number' => $number,
				'title'  => sanitize_text_field( $line ),
				'length' => $length,
			);
		}

		return $tracklist;
	}

	/**
	 * Patterns that recognise a leading track number.
	 *
	 * Deliberately conservative: a bare number followed by a space is NOT
	 * treated as a track number, because it would turn "12 Monkeys" into track
	 * 12 titled "Monkeys". A number needs an explicit dot or bracket, unless it
	 * is a vinyl style side prefix such as "A1", which is unambiguous.
	 *
	 * @return string[]
	 */
	private function track_number_patterns() {
		return array(
			// A1 Title, B2. Title, C10) Title.
			'/^([A-Za-z]{1,2}\d{1,3})[.)]?\s+(.+)$/',
			// 1. Title, 12) Title, A. Title, B) Title.
			'/^(\d{1,3}|[A-Za-z]{1,2})[.)]\s*(.+)$/',
		);
	}
}
