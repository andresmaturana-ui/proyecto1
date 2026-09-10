<?php
/**
 * Translates MusicBrainz payloads into release objects.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps the MusicBrainz JSON shape onto Melomaniac_Sync_Release_DTO.
 */
class Melomaniac_Sync_MusicBrainz_Response_Parser {

	/**
	 * Builds release objects from a barcode or text search payload.
	 *
	 * Search results carry enough data for a candidate list but no track list,
	 * so these objects are deliberately partial.
	 *
	 * @param array $payload Decoded search response.
	 * @return Melomaniac_Sync_Release_DTO[]
	 */
	public function parse_search_results( array $payload ) {
		if ( empty( $payload['releases'] ) || ! is_array( $payload['releases'] ) ) {
			return array();
		}

		$releases = array();

		foreach ( $payload['releases'] as $release ) {
			if ( ! is_array( $release ) ) {
				continue;
			}

			$releases[] = $this->parse_release( $release );
		}

		return $releases;
	}

	/**
	 * Builds a release object from a single release payload.
	 *
	 * Works for both search entries and full lookups; the richer the payload,
	 * the more fields get populated.
	 *
	 * @param array $release Decoded release data.
	 * @return Melomaniac_Sync_Release_DTO
	 */
	public function parse_release( array $release ) {
		$dto = new Melomaniac_Sync_Release_DTO();

		$dto->mbid    = isset( $release['id'] ) ? sanitize_text_field( $release['id'] ) : '';
		$dto->title   = isset( $release['title'] ) ? sanitize_text_field( $release['title'] ) : '';
		$dto->barcode = isset( $release['barcode'] ) ? preg_replace( '/\D/', '', (string) $release['barcode'] ) : '';
		$dto->country = isset( $release['country'] ) ? sanitize_text_field( $release['country'] ) : '';
		$dto->artist  = $this->parse_artist_credit( $release );

		if ( ! empty( $release['date'] ) ) {
			$dto->release_date = sanitize_text_field( $release['date'] );
			$dto->year         = substr( $dto->release_date, 0, 4 );
		}

		$label_info            = $this->parse_label_info( $release );
		$dto->label            = $label_info['label'];
		$dto->catalog_number   = $label_info['catalog_number'];

		$format                = $this->parse_format( $release );
		$dto->format           = $format['key'];
		$dto->format_detail    = $format['detail'];

		$dto->genres    = $this->parse_genres( $release );
		$dto->tracklist = $this->parse_tracklist( $release );

		return $dto;
	}

	/**
	 * Whether a full release payload reports an available front cover.
	 *
	 * @param array $release Decoded release data.
	 * @return bool
	 */
	public function has_front_cover( array $release ) {
		return ! empty( $release['cover-art-archive']['front'] );
	}

	/**
	 * Joins the artist credit array into a display string.
	 *
	 * MusicBrainz models collaborations as a list of credits separated by join
	 * phrases, e.g. [Bowie][' & '][Queen], which must be concatenated in order.
	 *
	 * @param array $release Decoded release data.
	 * @return string
	 */
	private function parse_artist_credit( array $release ) {
		if ( empty( $release['artist-credit'] ) || ! is_array( $release['artist-credit'] ) ) {
			return '';
		}

		$parts = '';

		foreach ( $release['artist-credit'] as $credit ) {
			if ( ! is_array( $credit ) ) {
				continue;
			}

			if ( ! empty( $credit['name'] ) ) {
				$parts .= $credit['name'];
			} elseif ( ! empty( $credit['artist']['name'] ) ) {
				$parts .= $credit['artist']['name'];
			}

			if ( ! empty( $credit['joinphrase'] ) ) {
				$parts .= $credit['joinphrase'];
			}
		}

		return sanitize_text_field( $parts );
	}

	/**
	 * Extracts label name and catalogue number.
	 *
	 * @param array $release Decoded release data.
	 * @return array{label:string,catalog_number:string}
	 */
	private function parse_label_info( array $release ) {
		$result = array(
			'label'          => '',
			'catalog_number' => '',
		);

		if ( empty( $release['label-info'] ) || ! is_array( $release['label-info'] ) ) {
			return $result;
		}

		foreach ( $release['label-info'] as $info ) {
			if ( ! is_array( $info ) ) {
				continue;
			}

			if ( '' === $result['label'] && ! empty( $info['label']['name'] ) ) {
				$result['label'] = sanitize_text_field( $info['label']['name'] );
			}

			if ( '' === $result['catalog_number'] && ! empty( $info['catalog-number'] ) ) {
				$result['catalog_number'] = sanitize_text_field( $info['catalog-number'] );
			}

			if ( '' !== $result['label'] && '' !== $result['catalog_number'] ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Reads the physical format from the media list and normalises it.
	 *
	 * @param array $release Decoded release data.
	 * @return array{key:string,detail:string}
	 */
	private function parse_format( array $release ) {
		$detail = '';

		if ( ! empty( $release['media'] ) && is_array( $release['media'] ) ) {
			$descriptions = array();

			foreach ( $release['media'] as $medium ) {
				if ( is_array( $medium ) && ! empty( $medium['format'] ) ) {
					$descriptions[] = sanitize_text_field( $medium['format'] );
				}
			}

			if ( ! empty( $descriptions ) ) {
				$counts = array_count_values( $descriptions );
				$parts  = array();

				foreach ( $counts as $label => $count ) {
					$parts[] = $count > 1 ? sprintf( '%1$dx %2$s', $count, $label ) : $label;
				}

				$detail = implode( ' + ', $parts );
			}
		}

		return array(
			'key'    => $this->normalise_format( $detail ),
			'detail' => $detail,
		);
	}

	/**
	 * Maps a MusicBrainz format string onto one of our four format keys.
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
	 * Extracts genre names.
	 *
	 * @param array $release Decoded release data.
	 * @return string[]
	 */
	private function parse_genres( array $release ) {
		$genres = array();

		$sources = array();

		if ( ! empty( $release['genres'] ) && is_array( $release['genres'] ) ) {
			$sources[] = $release['genres'];
		}

		if ( ! empty( $release['release-group']['genres'] ) && is_array( $release['release-group']['genres'] ) ) {
			$sources[] = $release['release-group']['genres'];
		}

		foreach ( $sources as $list ) {
			foreach ( $list as $genre ) {
				if ( is_array( $genre ) && ! empty( $genre['name'] ) ) {
					$genres[] = sanitize_text_field( $genre['name'] );
				}
			}
		}

		return array_values( array_unique( $genres ) );
	}

	/**
	 * Flattens the media/tracks structure into a single track list.
	 *
	 * @param array $release Decoded release data.
	 * @return array[]
	 */
	private function parse_tracklist( array $release ) {
		if ( empty( $release['media'] ) || ! is_array( $release['media'] ) ) {
			return array();
		}

		$tracklist     = array();
		$medium_number = 0;

		foreach ( $release['media'] as $medium ) {
			++$medium_number;

			if ( ! is_array( $medium ) || empty( $medium['tracks'] ) || ! is_array( $medium['tracks'] ) ) {
				continue;
			}

			foreach ( $medium['tracks'] as $track ) {
				if ( ! is_array( $track ) ) {
					continue;
				}

				$title = '';

				if ( ! empty( $track['title'] ) ) {
					$title = $track['title'];
				} elseif ( ! empty( $track['recording']['title'] ) ) {
					$title = $track['recording']['title'];
				}

				if ( '' === $title ) {
					continue;
				}

				$length = 0;

				if ( ! empty( $track['length'] ) ) {
					$length = (int) $track['length'];
				} elseif ( ! empty( $track['recording']['length'] ) ) {
					$length = (int) $track['recording']['length'];
				}

				$tracklist[] = array(
					'medium' => isset( $medium['position'] ) ? (int) $medium['position'] : $medium_number,
					'number' => isset( $track['number'] ) ? sanitize_text_field( (string) $track['number'] ) : '',
					'title'  => sanitize_text_field( $title ),
					'length' => $this->format_duration( $length ),
				);
			}
		}

		return $tracklist;
	}

	/**
	 * Converts a duration in milliseconds to m:ss.
	 *
	 * @param int $milliseconds Duration.
	 * @return string Empty string when unknown.
	 */
	private function format_duration( $milliseconds ) {
		if ( $milliseconds <= 0 ) {
			return '';
		}

		$seconds = (int) round( $milliseconds / 1000 );

		return sprintf( '%d:%02d', (int) floor( $seconds / 60 ), $seconds % 60 );
	}
}
