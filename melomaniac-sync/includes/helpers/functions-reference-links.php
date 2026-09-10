<?php
/**
 * Manual reference links.
 *
 * ============================================================================
 * HARD RULE FOR THIS FILE AND THE WHOLE PLUGIN
 * ============================================================================
 * These functions build URL strings and nothing else. They exist so a human can
 * click through and read a page in their own browser.
 *
 * The plugin MUST NOT, in any phase and under any circumstance:
 *   - send automated HTTP requests to discogs.com,
 *   - scrape, parse or cache discogs.com markup or responses,
 *   - use the Discogs API, authenticated or not.
 *
 * There is deliberately no HTTP client for Discogs anywhere in this codebase.
 * If you are about to add one, the answer is no. The shop owner copies and
 * pastes whatever they decide to use, by hand.
 * ============================================================================
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds a Discogs search URL for a human to open in a new tab.
 *
 * @param string $barcode Barcode, digits only.
 * @param string $extra   Optional extra terms, e.g. artist and album.
 * @return string
 */
function melomaniac_sync_discogs_search_url( $barcode, $extra = '' ) {
	$query = trim( (string) $barcode . ' ' . (string) $extra );

	return 'https://www.discogs.com/search/?' . http_build_query(
		array(
			'q'    => $query,
			'type' => 'release',
		)
	);
}

/**
 * Builds a Google search URL for a human to open in a new tab.
 *
 * @param string $barcode Barcode, digits only.
 * @param string $extra   Optional extra terms.
 * @return string
 */
function melomaniac_sync_google_search_url( $barcode, $extra = '' ) {
	$query = trim( (string) $barcode . ' ' . (string) $extra );

	return 'https://www.google.com/search?' . http_build_query( array( 'q' => $query ) );
}

/**
 * Builds a MusicBrainz search URL for a human to open in a new tab.
 *
 * @param string $barcode Barcode, digits only.
 * @return string
 */
function melomaniac_sync_musicbrainz_search_url( $barcode ) {
	return 'https://musicbrainz.org/search?' . http_build_query(
		array(
			'query' => 'barcode:' . (string) $barcode,
			'type'  => 'release',
		)
	);
}

/**
 * Returns the reference links shown above the manual entry form.
 *
 * @param string $barcode Barcode, digits only.
 * @param string $extra   Optional extra terms.
 * @return array[] List of array{label:string,url:string,description:string}.
 */
function melomaniac_sync_reference_links( $barcode, $extra = '' ) {
	$links = array(
		array(
			'label'       => __( 'Buscar en Discogs', 'melomaniac-sync' ),
			'url'         => melomaniac_sync_discogs_search_url( $barcode, $extra ),
			'description' => __( 'Abre la búsqueda en discogs.com. Copiá a mano los datos que quieras usar.', 'melomaniac-sync' ),
		),
		array(
			'label'       => __( 'Buscar en Google', 'melomaniac-sync' ),
			'url'         => melomaniac_sync_google_search_url( $barcode, $extra ),
			'description' => __( 'Útil cuando el disco es una edición local o muy antigua.', 'melomaniac-sync' ),
		),
		array(
			'label'       => __( 'Buscar en MusicBrainz', 'melomaniac-sync' ),
			'url'         => melomaniac_sync_musicbrainz_search_url( $barcode ),
			'description' => __( 'Verificá si el disco existe con otro código de barra.', 'melomaniac-sync' ),
		),
	);

	/**
	 * Filters the manual reference links.
	 *
	 * Anything added here must be a plain link for a human to click. This hook
	 * is not a place to register an automated data source.
	 *
	 * @param array[] $links   Reference links.
	 * @param string  $barcode Barcode.
	 */
	return apply_filters( 'melomaniac_sync_reference_links', $links, $barcode );
}
