<?php
/**
 * Transient wrapper for external API responses.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin caching layer so lookups are not repeated across screens and background jobs.
 */
class Melomaniac_Sync_Cache {

	/**
	 * Prefix for every transient written by this plugin.
	 */
	const PREFIX = 'melosync_';

	/**
	 * Option that tracks our transient keys so they can be flushed.
	 */
	const OPTION_INDEX = 'melomaniac_sync_cache_index';

	/**
	 * Default lifetime in seconds.
	 */
	const DEFAULT_TTL = DAY_IN_SECONDS;

	/**
	 * Reads a cached value.
	 *
	 * @param string $key Logical cache key.
	 * @return mixed|null Null when there is no cached value.
	 */
	public function get( $key ) {
		$value = get_transient( self::build_key( $key ) );

		return false === $value ? null : $value;
	}

	/**
	 * Stores a value.
	 *
	 * @param string   $key   Logical cache key.
	 * @param mixed    $value Value to store.
	 * @param int|null $ttl   Lifetime in seconds.
	 * @return void
	 */
	public function set( $key, $value, $ttl = null ) {
		if ( null === $ttl ) {
			/**
			 * Filters how long MusicBrainz responses stay cached.
			 *
			 * @param int    $ttl Lifetime in seconds.
			 * @param string $key Logical cache key.
			 */
			$ttl = (int) apply_filters( 'melomaniac_sync_cache_ttl', self::DEFAULT_TTL, $key );
		}

		$transient_key = self::build_key( $key );

		set_transient( $transient_key, $value, $ttl );
		self::remember_key( $transient_key );
	}

	/**
	 * Removes a cached value.
	 *
	 * @param string $key Logical cache key.
	 * @return void
	 */
	public function delete( $key ) {
		delete_transient( self::build_key( $key ) );
	}

	/**
	 * Hashes the logical key so it always fits the transient name limit.
	 *
	 * @param string $key Logical cache key.
	 * @return string
	 */
	private static function build_key( $key ) {
		return self::PREFIX . md5( (string) $key );
	}

	/**
	 * Adds a transient name to the index used by the flush routine.
	 *
	 * @param string $transient_key Full transient name.
	 * @return void
	 */
	private static function remember_key( $transient_key ) {
		$index = get_option( self::OPTION_INDEX, array() );

		if ( ! is_array( $index ) ) {
			$index = array();
		}

		if ( in_array( $transient_key, $index, true ) ) {
			return;
		}

		$index[] = $transient_key;

		// Keep the index bounded; the oldest entries simply expire on their own.
		if ( count( $index ) > 2000 ) {
			$index = array_slice( $index, -2000 );
		}

		update_option( self::OPTION_INDEX, $index, false );
	}

	/**
	 * Deletes every transient this plugin created.
	 *
	 * @return void
	 */
	public static function flush() {
		$index = get_option( self::OPTION_INDEX, array() );

		if ( is_array( $index ) ) {
			foreach ( $index as $transient_key ) {
				delete_transient( $transient_key );
			}
		}

		delete_option( self::OPTION_INDEX );
	}
}
