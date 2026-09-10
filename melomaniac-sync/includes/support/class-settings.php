<?php
/**
 * Plugin settings storage and accessors.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single read/write point for every configurable value.
 *
 * Services ask this class rather than calling get_option() themselves, so the
 * option shape stays in one place and defaults are applied consistently.
 */
class Melomaniac_Sync_Settings {

	/**
	 * Option holding general settings.
	 */
	const OPTION = 'melomaniac_sync_settings';

	/**
	 * Option holding which fields go into the product description.
	 */
	const OPTION_FIELDS = 'melomaniac_sync_description_fields';

	/**
	 * Option mapping a physical format to a chosen product category.
	 */
	const OPTION_CATEGORY_MAP = 'melomaniac_sync_category_map';

	/**
	 * Fields that can be included in the generated description.
	 *
	 * @return array<string,string>
	 */
	public static function description_fields() {
		return array(
			'artist'    => __( 'Artista', 'melomaniac-sync' ),
			'year'      => __( 'Año', 'melomaniac-sync' ),
			'label'     => __( 'Sello', 'melomaniac-sync' ),
			'country'   => __( 'País de prensaje', 'melomaniac-sync' ),
			'genre'     => __( 'Género', 'melomaniac-sync' ),
			'format'    => __( 'Formato', 'melomaniac-sync' ),
			'tracklist' => __( 'Lista de canciones', 'melomaniac-sync' ),
			'catalog'   => __( 'Número de catálogo', 'melomaniac-sync' ),
		);
	}

	/**
	 * General defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'api_contact'     => get_option( 'admin_email' ),
			'product_status'  => 'draft',
			'import_cover'    => true,
			'logging_enabled' => false,
			'discogs_token'   => '',
			'discogs_enabled' => true,
			'default_price'   => '0',
			'default_stock'   => 1,
			'genre_tag'       => true,
			'http_timeout'    => 15,
		);
	}

	/**
	 * Reads the whole settings array with defaults applied.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Reads one setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Writes a partial update, leaving untouched keys as they are.
	 *
	 * @param array $values Keys to change.
	 * @return void
	 */
	public static function update( array $values ) {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		update_option( self::OPTION, array_merge( $stored, $values ), false );
	}

	/**
	 * Post status new products get.
	 *
	 * @return string
	 */
	public static function product_status() {
		$status = (string) self::get( 'product_status', 'draft' );

		return in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ? $status : 'draft';
	}

	/**
	 * Whether cover art should be downloaded into the media library.
	 *
	 * @return bool
	 */
	public static function import_cover() {
		return (bool) self::get( 'import_cover', true );
	}

	/**
	 * Contact detail sent to MusicBrainz in the User-Agent header.
	 *
	 * @return string
	 */
	public static function api_contact() {
		$contact = trim( (string) self::get( 'api_contact', '' ) );

		if ( '' === $contact ) {
			$contact = home_url( '/' );
		}

		/**
		 * Filters the contact detail sent to MusicBrainz.
		 *
		 * Must be a real email address or URL belonging to the store.
		 *
		 * @param string $contact Contact detail.
		 */
		return (string) apply_filters( 'melomaniac_sync_api_contact', $contact );
	}

	/**
	 * The store's own Discogs personal access token.
	 *
	 * Each store supplies its own, so the rate limit is not shared between
	 * installations and no credential ever ships inside the plugin.
	 *
	 * @return string
	 */
	public static function discogs_token() {
		return trim( (string) self::get( 'discogs_token', '' ) );
	}

	/**
	 * Whether the Discogs fallback can run.
	 *
	 * Requires both the toggle and a token: without a token there is nothing to
	 * authenticate with, and unauthenticated Discogs access is not used.
	 *
	 * @return bool
	 */
	public static function discogs_enabled() {
		return (bool) self::get( 'discogs_enabled', true ) && '' !== self::discogs_token();
	}

	/**
	 * Default regular price for created products.
	 *
	 * @return string
	 */
	public static function default_price() {
		return (string) self::get( 'default_price', '0' );
	}

	/**
	 * Default stock quantity for created products.
	 *
	 * @return int
	 */
	public static function default_stock() {
		return (int) self::get( 'default_stock', 1 );
	}

	/**
	 * Whether a genre tag should be added to created products.
	 *
	 * @return bool
	 */
	public static function genre_tag_enabled() {
		return (bool) self::get( 'genre_tag', true );
	}

	/**
	 * Which description fields are switched on. Unknown keys default to on.
	 *
	 * @return array<string,bool>
	 */
	public static function enabled_description_fields() {
		$stored = get_option( self::OPTION_FIELDS, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$enabled = array();

		foreach ( array_keys( self::description_fields() ) as $key ) {
			$enabled[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : true;
		}

		return $enabled;
	}

	/**
	 * Stores the description field toggles.
	 *
	 * @param array<string,bool> $fields Toggles keyed by field name.
	 * @return void
	 */
	public static function update_description_fields( array $fields ) {
		$clean = array();

		foreach ( array_keys( self::description_fields() ) as $key ) {
			$clean[ $key ] = ! empty( $fields[ $key ] );
		}

		update_option( self::OPTION_FIELDS, $clean, false );
	}

	/**
	 * Manually chosen product category per format. Zero means auto-detect.
	 *
	 * @return array<string,int>
	 */
	public static function category_map() {
		$stored = get_option( self::OPTION_CATEGORY_MAP, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$map = array();

		foreach ( array_keys( Melomaniac_Sync_Release_DTO::formats() ) as $format ) {
			$map[ $format ] = isset( $stored[ $format ] ) ? (int) $stored[ $format ] : 0;
		}

		return $map;
	}

	/**
	 * Stores the format to category mapping.
	 *
	 * @param array<string,int> $map Category IDs keyed by format.
	 * @return void
	 */
	public static function update_category_map( array $map ) {
		$clean = array();

		foreach ( array_keys( Melomaniac_Sync_Release_DTO::formats() ) as $format ) {
			$clean[ $format ] = isset( $map[ $format ] ) ? absint( $map[ $format ] ) : 0;
		}

		update_option( self::OPTION_CATEGORY_MAP, $clean, false );
	}

	/**
	 * Seconds to wait for an external service before giving up.
	 *
	 * Raised from the old hard-coded 15 on request: slow shared hosts and
	 * congested links can need more, and a timeout costs the whole lookup.
	 *
	 * @return int
	 */
	public static function http_timeout() {
		$timeout = (int) self::get( 'http_timeout', 15 );

		// Below 5 nothing ever completes; above 60 PHP itself usually dies first.
		if ( $timeout < 5 || $timeout > 60 ) {
			$timeout = 15;
		}

		/**
		 * Filters the outbound request timeout, in seconds.
		 *
		 * @param int $timeout Seconds.
		 */
		return (int) apply_filters( 'melomaniac_sync_http_timeout', $timeout );
	}

	/**
	 * Whether diagnostic logging is switched on.
	 *
	 * @return bool
	 */
	public static function logging_enabled() {
		return (bool) self::get( 'logging_enabled', false );
	}
}
