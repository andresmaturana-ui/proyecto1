<?php
/**
 * Builds WooCommerce products from release data.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates draft WooCommerce products out of a release object.
 *
 * Consumers pass a Melomaniac_Sync_Release_DTO and never a raw API payload, so
 * the same factory serves the scan screen, the bulk importer and the manual
 * entry form.
 */
class Melomaniac_Sync_Product_Factory {

	/**
	 * Meta key prefix for every field this plugin writes.
	 */
	const META_PREFIX = '_melomaniac_sync_';

	/**
	 * Logger.
	 *
	 * @var Melomaniac_Sync_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Logger $logger Logger.
	 */
	public function __construct( Melomaniac_Sync_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Creates a draft product.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release   Release data.
	 * @param array                       $overrides What the shop owner chose before
	 *                                               creating: price, stock, category_ids,
	 *                                               new_category, tags. Anything absent
	 *                                               falls back to the settings screen.
	 * @return int|WP_Error Product ID or error.
	 */
	public function create_draft( Melomaniac_Sync_Release_DTO $release, array $overrides = array() ) {
		if ( ! $release->is_usable() ) {
			return new WP_Error(
				'melomaniac_sync_incomplete_release',
				__( 'Falta el artista o el título del álbum.', 'melomaniac-sync' )
			);
		}

		if ( ! Melomaniac_Sync_Usage::has_quota() ) {
			return new WP_Error(
				'melomaniac_sync_quota_exceeded',
				Melomaniac_Sync_Usage::limit_reached_message(),
				array( 'upgrade_url' => Melomaniac_Sync_Licensing::upgrade_url() )
			);
		}

		$existing = $this->find_duplicate( $release );

		if ( $existing > 0 ) {
			return new WP_Error(
				'melomaniac_sync_duplicate',
				sprintf(
					/* translators: %s: existing product title. */
					__( 'Ya existe un producto para este disco: %s', 'melomaniac-sync' ),
					get_the_title( $existing )
				),
				array( 'product_id' => $existing )
			);
		}

		try {
			$product = new WC_Product_Simple();

			$product->set_name( $release->display_name() );
			$product->set_status( Melomaniac_Sync_Settings::product_status() );
			$product->set_catalog_visibility( 'visible' );
			$product->set_description( $this->build_description( $release ) );
			$product->set_short_description( $this->build_short_description( $release ) );

			if ( '' !== $release->barcode ) {
				// Throws when the SKU is taken, including by a trashed product.
				$product->set_sku( $release->barcode );
			}

			$product->set_regular_price(
				isset( $overrides['price'] ) ? (string) $overrides['price'] : Melomaniac_Sync_Settings::default_price()
			);
			$product->set_manage_stock( true );
			$product->set_stock_quantity(
				isset( $overrides['stock'] ) ? (int) $overrides['stock'] : Melomaniac_Sync_Settings::default_stock()
			);
			$product->set_attributes( $this->build_attributes( $release ) );

			$product_id = $product->save();
		} catch ( WC_Data_Exception $exception ) {
			$this->logger->error(
				'WooCommerce rejected the product data.',
				array( 'error' => $exception->getMessage() )
			);

			return new WP_Error( 'melomaniac_sync_save_failed', $exception->getMessage() );
		}

		if ( ! $product_id ) {
			return new WP_Error(
				'melomaniac_sync_save_failed',
				__( 'No se pudo guardar el producto.', 'melomaniac-sync' )
			);
		}

		Melomaniac_Sync_Usage::record_disc();

		$this->save_meta( $product_id, $release );
		$this->assign_categories( $product_id, $release, $overrides );
		$this->assign_tags( $product_id, $release, $overrides );
		$this->attach_cover( $product_id, $release );

		/**
		 * Fires after a product was created from release data.
		 *
		 * @param int                         $product_id Product ID.
		 * @param Melomaniac_Sync_Release_DTO $release    Source data.
		 */
		do_action( 'melomaniac_sync_product_created', $product_id, $release );

		return $product_id;
	}

	/**
	 * Looks for an existing product representing the same release.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release data.
	 * @return int Product ID, zero when there is no duplicate.
	 */
	public function find_duplicate( Melomaniac_Sync_Release_DTO $release ) {
		if ( '' !== $release->barcode ) {
			$by_sku = wc_get_product_id_by_sku( $release->barcode );

			if ( $by_sku ) {
				return (int) $by_sku;
			}

			$by_meta = $this->find_by_meta( self::META_PREFIX . 'barcode', $release->barcode );

			if ( $by_meta > 0 ) {
				return $by_meta;
			}
		}

		if ( '' !== $release->mbid ) {
			$by_mbid = $this->find_by_meta( self::META_PREFIX . 'mbid', $release->mbid );

			if ( $by_mbid > 0 ) {
				return $by_mbid;
			}
		}

		if ( '' !== $release->discogs_id ) {
			$by_discogs = $this->find_by_meta( self::META_PREFIX . 'discogs_id', $release->discogs_id );

			if ( $by_discogs > 0 ) {
				return $by_discogs;
			}
		}

		return 0;
	}

	/**
	 * Finds a product by one of our meta fields.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @return int Product ID, zero when not found.
	 */
	private function find_by_meta( $meta_key, $meta_value ) {
		$products = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed lookup on an exact value, run once per scan.
				'meta_query'       => array(
					array(
						'key'     => $meta_key,
						'value'   => $meta_value,
						'compare' => '=',
					),
				),
			)
		);

		return empty( $products ) ? 0 : (int) $products[0];
	}

	/**
	 * Writes the musical data as product meta.
	 *
	 * @param int                         $product_id Product ID.
	 * @param Melomaniac_Sync_Release_DTO $release    Release data.
	 * @return void
	 */
	private function save_meta( $product_id, Melomaniac_Sync_Release_DTO $release ) {
		$fields = array(
			'source'         => $release->source,
			'mbid'           => $release->mbid,
			'discogs_id'     => $release->discogs_id,
			'barcode'        => $release->barcode,
			'artist'         => $release->artist,
			'album'          => $release->title,
			'label'          => $release->label,
			'catalog_number' => $release->catalog_number,
			'year'           => $release->year,
			'release_date'   => $release->release_date,
			'format'         => $release->format,
			'format_detail'  => $release->format_detail,
			'country'        => $release->country,
			// Kept for the MusicBrainz contribution flow: these describe the
			// physical object and cannot be recovered once the disc is sold.
			'status'         => $release->status,
			'release_type'   => $release->release_type,
			'secondary_type' => $release->secondary_type,
			'packaging'      => $release->packaging,
			'language'       => $release->language,
			'script'         => $release->script,
			'medium_count'   => (string) $release->medium_count,
		);

		foreach ( $fields as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}

			update_post_meta( $product_id, self::META_PREFIX . $key, $value );
		}

		if ( ! empty( $release->tracklist ) ) {
			update_post_meta( $product_id, self::META_PREFIX . 'tracklist', wp_json_encode( $release->tracklist ) );
		}

		if ( ! empty( $release->genres ) ) {
			update_post_meta( $product_id, self::META_PREFIX . 'genres', implode( ', ', $release->genres ) );
		}

		update_post_meta( $product_id, self::META_PREFIX . 'imported_at', current_time( 'mysql' ) );
	}

	/**
	 * Builds the visible product attributes.
	 *
	 * Custom (non-taxonomy) attributes keep the store's taxonomies untouched
	 * while still showing the data in the Additional Information tab.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release data.
	 * @return WC_Product_Attribute[]
	 */
	private function build_attributes( Melomaniac_Sync_Release_DTO $release ) {
		$candidates = array(
			__( 'Artista', 'melomaniac-sync' )            => $release->artist,
			__( 'Sello', 'melomaniac-sync' )              => $release->label,
			__( 'Año', 'melomaniac-sync' )                => $release->year,
			__( 'Formato', 'melomaniac-sync' )            => '' !== $release->format_detail ? $release->format_detail : $release->format_label(),
			__( 'País de prensaje', 'melomaniac-sync' )   => $release->country,
			__( 'Número de catálogo', 'melomaniac-sync' ) => $release->catalog_number,
		);

		$attributes = array();
		$position   = 0;

		foreach ( $candidates as $name => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			$attribute = new WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_name( $name );
			$attribute->set_options( array( $value ) );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( false );

			$attributes[] = $attribute;
			++$position;
		}

		return $attributes;
	}

	/**
	 * Assigns the product to a category matching its physical format.
	 *
	 * @param int                         $product_id Product ID.
	 * @param Melomaniac_Sync_Release_DTO $release    Release data.
	 * @return void
	 */
	private function assign_categories( $product_id, Melomaniac_Sync_Release_DTO $release, array $overrides ) {
		$chosen_ids = isset( $overrides['category_ids'] ) ? array_map( 'absint', (array) $overrides['category_ids'] ) : array();

		if ( ! empty( $overrides['new_category'] ) ) {
			$created = Melomaniac_Sync_Terms::resolve( $overrides['new_category'], 'product_cat' );

			if ( $created > 0 ) {
				$chosen_ids[] = $created;
			}
		}

		$chosen_ids = array_values( array_unique( array_filter( $chosen_ids ) ) );

		// An explicit choice replaces the format guess: if the shop owner picked
		// categories, adding a guessed one on top would be second-guessing them.
		if ( ! empty( $chosen_ids ) ) {
			wp_set_object_terms( $product_id, $chosen_ids, 'product_cat', false );
			return;
		}

		if ( '' === $release->format || 'other' === $release->format ) {
			return;
		}

		$mapped = Melomaniac_Sync_Settings::category_map();

		if ( ! empty( $mapped[ $release->format ] ) ) {
			wp_set_object_terms( $product_id, array( (int) $mapped[ $release->format ] ), 'product_cat', true );
			return;
		}

		$term_id = Melomaniac_Sync_Terms::resolve( $release->format_label(), 'product_cat' );

		if ( $term_id > 0 ) {
			wp_set_object_terms( $product_id, array( $term_id ), 'product_cat', true );
		}
	}

	/**
	 * Downloads the cover art and sets it as the product image.
	 *
	 * @param int                         $product_id Product ID.
	 * @param Melomaniac_Sync_Release_DTO $release    Release data.
	 * @return void
	 */
	private function attach_cover( $product_id, Melomaniac_Sync_Release_DTO $release ) {
		// A manually uploaded image is already in the media library.
		if ( $release->cover_attachment_id > 0 ) {
			set_post_thumbnail( $product_id, $release->cover_attachment_id );
			return;
		}

		if ( '' === $release->cover_url || ! Melomaniac_Sync_Settings::import_cover() ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Not media_sideload_image(): it requires the URL itself to end in an
		// image extension, and Cover Art Archive URLs end in "/front".
		$temp_file = download_url( $release->cover_url, 30 );

		if ( is_wp_error( $temp_file ) ) {
			$this->logger->error(
				'Cover download failed.',
				array(
					'product' => $product_id,
					'error'   => $temp_file->get_error_message(),
				)
			);
			return;
		}

		$extension = $this->detect_image_extension( $temp_file );

		if ( '' === $extension ) {
			wp_delete_file( $temp_file );
			$this->logger->error( 'Cover was not a usable image.', array( 'product' => $product_id ) );
			return;
		}

		$basename = '' !== $release->barcode ? $release->barcode : $release->mbid;

		$file = array(
			'name'     => sanitize_file_name( $basename . '-portada.' . $extension ),
			'tmp_name' => $temp_file,
		);

		$attachment_id = media_handle_sideload( $file, $product_id, $release->display_name() );

		if ( is_wp_error( $attachment_id ) ) {
			// media_handle_sideload() only moves the file on success.
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}

			$this->logger->error(
				'Cover import failed.',
				array(
					'product' => $product_id,
					'error'   => $attachment_id->get_error_message(),
				)
			);
			return;
		}

		set_post_thumbnail( $product_id, (int) $attachment_id );
	}

	/**
	 * Confirms a downloaded file really is an image and returns its extension.
	 *
	 * The file came from a remote service, so its type is derived from the
	 * bytes on disk rather than from anything the response claimed.
	 *
	 * @param string $path Local file path.
	 * @return string Extension without a dot, empty when the file is unusable.
	 */
	private function detect_image_extension( $path ) {
		$allowed = array(
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG  => 'png',
			IMAGETYPE_GIF  => 'gif',
			IMAGETYPE_WEBP => 'webp',
		);

		$info = wp_getimagesize( $path );

		if ( ! is_array( $info ) || empty( $info[2] ) || ! isset( $allowed[ $info[2] ] ) ) {
			return '';
		}

		return $allowed[ $info[2] ];
	}

	/**
	 * Builds the long description, track list included.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release data.
	 * @return string
	 */
	private function build_description( Melomaniac_Sync_Release_DTO $release ) {
		$enabled = Melomaniac_Sync_Settings::enabled_description_fields();

		if ( empty( $release->tracklist ) || empty( $enabled['tracklist'] ) ) {
			return '';
		}

		$html = '<h3>' . esc_html__( 'Lista de canciones', 'melomaniac-sync' ) . '</h3>';

		$current_medium = null;
		$open_list      = false;
		$multi_medium   = $this->count_media( $release->tracklist ) > 1;

		foreach ( $release->tracklist as $track ) {
			$medium = isset( $track['medium'] ) ? (int) $track['medium'] : 1;

			if ( $multi_medium && $medium !== $current_medium ) {
				if ( $open_list ) {
					$html .= '</ol>';
				}

				$html .= '<h4>' . esc_html(
					sprintf(
						/* translators: %d: disc or side number. */
						__( 'Disco %d', 'melomaniac-sync' ),
						$medium
					)
				) . '</h4><ol>';

				$current_medium = $medium;
				$open_list      = true;
			} elseif ( ! $open_list ) {
				$html     .= '<ol>';
				$open_list = true;
			}

			$title  = isset( $track['title'] ) ? (string) $track['title'] : '';
			$length = isset( $track['length'] ) ? (string) $track['length'] : '';

			$html .= '<li>' . esc_html( $title );

			if ( '' !== $length ) {
				$html .= ' <span class="melomaniac-track-length">(' . esc_html( $length ) . ')</span>';
			}

			$html .= '</li>';
		}

		if ( $open_list ) {
			$html .= '</ol>';
		}

		return $html;
	}

	/**
	 * Counts distinct media in a track list.
	 *
	 * @param array[] $tracklist Track list.
	 * @return int
	 */
	private function count_media( array $tracklist ) {
		$media = array();

		foreach ( $tracklist as $track ) {
			if ( isset( $track['medium'] ) ) {
				$media[ (int) $track['medium'] ] = true;
			}
		}

		return max( 1, count( $media ) );
	}

	/**
	 * Builds a one-line summary for the short description.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release data.
	 * @return string
	 */
	private function build_short_description( Melomaniac_Sync_Release_DTO $release ) {
		$enabled = Melomaniac_Sync_Settings::enabled_description_fields();

		$candidates = array(
			'artist'  => $release->artist,
			'year'    => $release->year,
			'label'   => $release->label,
			'country' => $release->country,
			'genre'   => implode( ', ', $release->genres ),
			'format'  => '' !== $release->format_detail ? $release->format_detail : $release->format_label(),
			'catalog' => $release->catalog_number,
		);

		$parts = array();

		foreach ( $candidates as $key => $value ) {
			if ( empty( $enabled[ $key ] ) || '' === trim( (string) $value ) ) {
				continue;
			}

			$parts[] = $value;
		}

		return empty( $parts ) ? '' : esc_html( implode( ' · ', $parts ) );
	}

	/**
	 * Tags the product with its main genre, when that is switched on.
	 *
	 * @param int                         $product_id Product ID.
	 * @param Melomaniac_Sync_Release_DTO $release    Release data.
	 * @return void
	 */
	private function assign_tags( $product_id, Melomaniac_Sync_Release_DTO $release, array $overrides ) {
		$names = isset( $overrides['tags'] ) ? (array) $overrides['tags'] : array();

		// Only fall back to the genre setting when nothing was typed: the field is
		// prefilled with the genre, so an empty field means "no tags, on purpose".
		if ( ! isset( $overrides['tags'] ) && Melomaniac_Sync_Settings::genre_tag_enabled() && ! empty( $release->genres ) ) {
			$names = array( (string) reset( $release->genres ) );
		}

		$term_ids = array();

		foreach ( $names as $name ) {
			$term_id = Melomaniac_Sync_Terms::resolve( $name, 'product_tag' );

			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $product_id, array_values( array_unique( $term_ids ) ), 'product_tag', true );
		}
	}
}
