<?php
/**
 * Translates Discogs payloads into release objects.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps the Discogs JSON shape onto Melomaniac_Sync_Release_DTO.
 *
 * Producing the same object as the MusicBrainz parser is the whole point: the
 * product factory, the bulk importer and the app all stay unaware of which
 * service answered.
 */
class Melomaniac_Sync_Discogs_Response_Parser {

	/**
	 * Builds release objects from a search payload.
	 *
	 * Search results carry no track list, so these objects are partial.
	 *
	 * @param array $results Decoded search results.
	 * @return Melomaniac_Sync_Release_DTO[]
	 */
	public function parse_search_results( array $results ) {
		$releases = array();

		foreach ( $results as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$releases[] = $this->parse_search_result( $result );
		}

		return $releases;
	}

	/**
	 * Builds a partial release object from one search result.
	 *
	 * @param array $result Decoded search result.
	 * @return Melomaniac_Sync_Release_DTO
	 */
	public function parse_search_result( array $result ) {
		$dto = new Melomaniac_Sync_Release_DTO();

		$dto->source     = 'discogs';
		$dto->discogs_id = isset( $result['id'] ) ? (string) absint( $result['id'] ) : '';
		$dto->country    = isset( $result['country'] ) ? sanitize_text_field( $result['country'] ) : '';
		$dto->year       = isset( $result['year'] ) ? preg_replace( '/\D/', '', (string) $result['year'] ) : '';

		// Discogs search returns "Artist - Title" as a single string.
		$title  = isset( $result['title'] ) ? sanitize_text_field( $result['title'] ) : '';
		$split  = $this->split_artist_title( $title );
		$dto->artist = $split['artist'];
		$dto->title  = $split['title'];

		if ( ! empty( $result['label'] ) && is_array( $result['label'] ) ) {
			$dto->label = sanitize_text_field( (string) reset( $result['label'] ) );
		}

		if ( ! empty( $result['catno'] ) ) {
			$dto->catalog_number = sanitize_text_field( $result['catno'] );
		}

		if ( ! empty( $result['format'] ) && is_array( $result['format'] ) ) {
			$dto->format_detail = sanitize_text_field( implode( ', ', array_map( 'strval', $result['format'] ) ) );
			$dto->format        = $this->normalise_format( $dto->format_detail );
		}

		$thumb = '';

		if ( ! empty( $result['cover_image'] ) ) {
			$thumb = esc_url_raw( $result['cover_image'] );
		} elseif ( ! empty( $result['thumb'] ) ) {
			$thumb = esc_url_raw( $result['thumb'] );
		}

		$dto->cover_thumb_url = $thumb;
		$dto->cover_url       = $thumb;

		return $dto;
	}

	/**
	 * Builds a full release object from a release payload.
	 *
	 * @param array $release Decoded release detail.
	 * @return Melomaniac_Sync_Release_DTO
	 */
	public function parse_release( array $release ) {
		$dto = new Melomaniac_Sync_Release_DTO();

		$dto->source     = 'discogs';
		$dto->discogs_id = isset( $release['id'] ) ? (string) absint( $release['id'] ) : '';
		$dto->title      = isset( $release['title'] ) ? sanitize_text_field( $release['title'] ) : '';
		$dto->country    = isset( $release['country'] ) ? sanitize_text_field( $release['country'] ) : '';
		$dto->artist     = $this->parse_artists( $release );

		if ( ! empty( $release['year'] ) ) {
			$dto->year = preg_replace( '/\D/', '', (string) $release['year'] );
		}

		if ( ! empty( $release['released'] ) ) {
			$dto->release_date = sanitize_text_field( (string) $release['released'] );
		}

		if ( ! empty( $release['labels'] ) && is_array( $release['labels'] ) ) {
			$names = array();

			foreach ( $release['labels'] as $label ) {
				if ( is_array( $label ) && ! empty( $label['name'] ) ) {
					$names[] = sanitize_text_field( $label['name'] );
				}

				if ( '' === $dto->catalog_number && is_array( $label ) && ! empty( $label['catno'] ) ) {
					$dto->catalog_number = sanitize_text_field( $label['catno'] );
				}
			}

			$dto->label = implode( ', ', array_unique( $names ) );
		}

		if ( ! empty( $release['formats'] ) && is_array( $release['formats'] ) ) {
			$dto->format_detail = $this->parse_formats( $release['formats'] );
			$dto->format        = $this->normalise_format( $dto->format_detail );
		}

		$dto->genres    = $this->parse_genres( $release );
		$dto->tracklist = $this->parse_tracklist( $release );
		$dto->barcode   = $this->parse_barcode( $release );
		$dto->notes     = ! empty( $release['notes'] ) ? sanitize_textarea_field( $release['notes'] ) : '';

		$cover = $this->parse_cover( $release );

		$dto->cover_url       = $cover['full'];
		$dto->cover_thumb_url = $cover['thumb'];

		return $dto;
	}

	/**
	 * Splits the "Artist - Title" string Discogs search returns.
	 *
	 * Only the first separator counts, so a title that itself contains a dash
	 * survives intact.
	 *
	 * @param string $combined Combined string.
	 * @return array{artist:string,title:string}
	 */
	private function split_artist_title( $combined ) {
		$parts = explode( ' - ', $combined, 2 );

		if ( count( $parts ) < 2 ) {
			return array(
				'artist' => '',
				'title'  => $combined,
			);
		}

		return array(
			'artist' => $this->strip_disambiguation( trim( $parts[0] ) ),
			'title'  => trim( $parts[1] ),
		);
	}

	/**
	 * Joins the artist list from a release detail payload.
	 *
	 * @param array $release Decoded release detail.
	 * @return string
	 */
	private function parse_artists( array $release ) {
		if ( empty( $release['artists'] ) || ! is_array( $release['artists'] ) ) {
			return '';
		}

		$names = array();

		foreach ( $release['artists'] as $artist ) {
			if ( ! is_array( $artist ) || empty( $artist['name'] ) ) {
				continue;
			}

			$name = $this->strip_disambiguation( $artist['name'] );

			if ( '' === $name ) {
				continue;
			}

			$join = isset( $artist['join'] ) ? trim( (string) $artist['join'] ) : '';

			$names[] = array(
				'name' => $name,
				'join' => $join,
			);
		}

		if ( empty( $names ) ) {
			return '';
		}

		$out = '';

		foreach ( $names as $index => $entry ) {
			$out .= $entry['name'];

			if ( $index < count( $names ) - 1 ) {
				$out .= '' !== $entry['join'] ? ' ' . $entry['join'] . ' ' : ', ';
			}
		}

		return sanitize_text_field( $out );
	}

	/**
	 * Removes the numeric suffix Discogs appends to disambiguate artists.
	 *
	 * Discogs names a second artist called Nirvana as "Nirvana (2)"; that suffix
	 * is an internal detail and should not end up in a product title.
	 *
	 * @param string $name Artist name.
	 * @return string
	 */
	private function strip_disambiguation( $name ) {
		return trim( preg_replace( '/\s*\(\d+\)$/', '', (string) $name ) );
	}

	/**
	 * Builds a human readable format description.
	 *
	 * @param array $formats Decoded formats list.
	 * @return string
	 */
	private function parse_formats( array $formats ) {
		$parts = array();

		foreach ( $formats as $format ) {
			if ( ! is_array( $format ) || empty( $format['name'] ) ) {
				continue;
			}

			$piece = sanitize_text_field( $format['name'] );

			if ( ! empty( $format['descriptions'] ) && is_array( $format['descriptions'] ) ) {
				$piece .= ' (' . sanitize_text_field( implode( ', ', array_map( 'strval', $format['descriptions'] ) ) ) . ')';
			}

			$qty = isset( $format['qty'] ) ? (int) $format['qty'] : 1;

			if ( $qty > 1 ) {
				$piece = $qty . 'x ' . $piece;
			}

			$parts[] = $piece;
		}

		return implode( ' + ', $parts );
	}

	/**
	 * Maps a Discogs format string onto one of our four format keys.
	 *
	 * @param string $detail Verbatim format description.
	 * @return string
	 */
	private function normalise_format( $detail ) {
		if ( '' === $detail ) {
			return 'other';
		}

		$haystack = strtolower( $detail );

		if ( false !== strpos( $haystack, 'vinyl' ) || preg_match( '/\b(lp|ep)\b/', $haystack ) ) {
			return 'vinyl';
		}

		if ( false !== strpos( $haystack, 'cassette' ) ) {
			return 'cassette';
		}

		if ( false !== strpos( $haystack, 'cd' ) ) {
			return 'cd';
		}

		return 'other';
	}

	/**
	 * Merges genres and styles into one list.
	 *
	 * @param array $release Decoded release detail.
	 * @return string[]
	 */
	private function parse_genres( array $release ) {
		$genres = array();

		foreach ( array( 'genres', 'styles' ) as $key ) {
			if ( empty( $release[ $key ] ) || ! is_array( $release[ $key ] ) ) {
				continue;
			}

			foreach ( $release[ $key ] as $value ) {
				$clean = sanitize_text_field( (string) $value );

				if ( '' !== $clean ) {
					$genres[] = $clean;
				}
			}
		}

		return array_values( array_unique( $genres ) );
	}

	/**
	 * Converts the Discogs tracklist into our shape.
	 *
	 * @param array $release Decoded release detail.
	 * @return array[]
	 */
	private function parse_tracklist( array $release ) {
		if ( empty( $release['tracklist'] ) || ! is_array( $release['tracklist'] ) ) {
			return array();
		}

		$tracklist = array();

		foreach ( $release['tracklist'] as $track ) {
			if ( ! is_array( $track ) || empty( $track['title'] ) ) {
				continue;
			}

			// Headings and index tracks carry no position and are not songs.
			if ( isset( $track['type_'] ) && 'track' !== $track['type_'] ) {
				continue;
			}

			$tracklist[] = array(
				'medium' => 1,
				'number' => isset( $track['position'] ) ? sanitize_text_field( (string) $track['position'] ) : '',
				'title'  => sanitize_text_field( $track['title'] ),
				'length' => isset( $track['duration'] ) ? sanitize_text_field( (string) $track['duration'] ) : '',
			);
		}

		return $tracklist;
	}

	/**
	 * Reads the barcode out of the identifiers list.
	 *
	 * @param array $release Decoded release detail.
	 * @return string Digits only.
	 */
	private function parse_barcode( array $release ) {
		if ( empty( $release['identifiers'] ) || ! is_array( $release['identifiers'] ) ) {
			return '';
		}

		foreach ( $release['identifiers'] as $identifier ) {
			if ( ! is_array( $identifier ) || empty( $identifier['type'] ) || empty( $identifier['value'] ) ) {
				continue;
			}

			if ( 0 !== strcasecmp( (string) $identifier['type'], 'Barcode' ) ) {
				continue;
			}

			$digits = preg_replace( '/\D/', '', (string) $identifier['value'] );

			if ( strlen( $digits ) >= 6 ) {
				return $digits;
			}
		}

		return '';
	}

	/**
	 * Picks the primary cover image.
	 *
	 * @param array $release Decoded release detail.
	 * @return array{full:string,thumb:string}
	 */
	private function parse_cover( array $release ) {
		$empty = array(
			'full'  => '',
			'thumb' => '',
		);

		if ( empty( $release['images'] ) || ! is_array( $release['images'] ) ) {
			return $empty;
		}

		$primary = null;

		foreach ( $release['images'] as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			if ( isset( $image['type'] ) && 'primary' === $image['type'] ) {
				$primary = $image;
				break;
			}

			if ( null === $primary ) {
				$primary = $image;
			}
		}

		if ( ! is_array( $primary ) ) {
			return $empty;
		}

		$full = '';

		if ( ! empty( $primary['uri'] ) ) {
			$full = esc_url_raw( $primary['uri'] );
		} elseif ( ! empty( $primary['resource_url'] ) ) {
			$full = esc_url_raw( $primary['resource_url'] );
		}

		$thumb = ! empty( $primary['uri150'] ) ? esc_url_raw( $primary['uri150'] ) : $full;

		return array(
			'full'  => $full,
			'thumb' => $thumb,
		);
	}
}
