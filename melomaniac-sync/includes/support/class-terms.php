<?php
/**
 * Taxonomy term resolution.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds a term by name, creating it when it does not exist yet.
 *
 * Shared by the product factory and the REST API, so a category or tag typed
 * from the admin screen and one typed from the app resolve the same way.
 */
class Melomaniac_Sync_Terms {

	/**
	 * Finds a term by name, creating it when it does not exist yet.
	 *
	 * @param string $name     Term name.
	 * @param string $taxonomy Taxonomy.
	 * @return int Term ID, zero when it could not be resolved.
	 */
	public static function resolve( $name, $taxonomy ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return 0;
		}

		$term = get_term_by( 'name', $name, $taxonomy );

		if ( $term ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $created ) ) {
			// A term created by a concurrent request is not a failure.
			$existing = $created->get_error_data( 'term_exists' );

			return $existing ? (int) $existing : 0;
		}

		return (int) $created['term_id'];
	}
}
