<?php
/**
 * Catalogue helpers shared by the admin screens and the REST API.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product categories, and the price/stock/category/tag choices the shop owner
 * makes before a product is created.
 *
 * Lives outside admin/ on purpose: REST requests are never is_admin(), so
 * anything the app needs has to load unconditionally, not just in wp-admin.
 */
class Melomaniac_Sync_Catalog {

	/**
	 * Product categories, for the selects that let the user pick before creating.
	 *
	 * @return array<int,string> Category name keyed by term ID.
	 */
	public static function categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[ (int) $term->term_id ] = $term->name;
		}

		return $categories;
	}

	/**
	 * Reads the price, stock, category and tag choices out of a request.
	 *
	 * Shared by the admin scan screen, the manual form and the REST API so all
	 * three produce the same override array for the product factory. The
	 * caller is responsible for its own auth (nonce or REST permission).
	 *
	 * @param array $source Raw values, e.g. wp_unslash( $_POST ) or a REST
	 *                      request's params.
	 * @return array
	 */
	public static function read_overrides( array $source ) {
		$overrides = array();

		if ( isset( $source['price'] ) ) {
			$price = wc_format_decimal( $source['price'] );

			// A typo that formats to nothing falls back to the default rather than
			// creating a product with no price at all.
			if ( '' !== $price ) {
				$overrides['price'] = $price;
			}
		}

		if ( isset( $source['stock'] ) && '' !== trim( (string) $source['stock'] ) ) {
			$overrides['stock'] = absint( $source['stock'] );
		}

		if ( isset( $source['category_ids'] ) ) {
			// The browser sends an array; a single value can arrive as a string.
			$overrides['category_ids'] = array_values(
				array_unique( array_filter( array_map( 'absint', (array) $source['category_ids'] ) ) )
			);
		}

		if ( isset( $source['new_category'] ) ) {
			$overrides['new_category'] = sanitize_text_field( $source['new_category'] );
		}

		if ( isset( $source['tags'] ) ) {
			$overrides['tags'] = self::parse_tag_list( $source['tags'] );
		}

		return $overrides;
	}

	/**
	 * Splits a comma separated tag field into clean names.
	 *
	 * @param string|array $raw Raw field value; an array is accepted as-is.
	 * @return string[]
	 */
	private static function parse_tag_list( $raw ) {
		$pieces = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$names  = array();

		foreach ( $pieces as $name ) {
			$name = sanitize_text_field( trim( (string) $name ) );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}
}
