<?php
/**
 * Manual reference links.
 *
 * ============================================================================
 * WHAT THIS FILE IS, AND WHAT IT IS NOT
 * ============================================================================
 * These functions build URL strings and nothing else, so a human can click
 * through and read a page in their own browser. They never fetch anything.
 *
 * Automated access to Discogs does exist in this plugin, but it lives only in
 * includes/integrations/discogs/ and it is bound by two rules:
 *
 *   - It runs server-side with the store's own personal access token, taken
 *     from the settings screen. No credential is ever shipped inside the
 *     plugin, and the token never reaches the browser.
 *   - It is a fallback, consulted only when MusicBrainz has no match. Discogs
 *     images in particular carry usage restrictions that Cover Art Archive
 *     images do not, which is why MusicBrainz is always asked first.
 *
 * Scraping discogs.com markup is still never acceptable: use the API or a
 * manual link, nothing in between.
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
			'description' => __( 'Abre la búsqueda en discogs.com. Copia a mano los datos que quieras usar.', 'melomaniac-sync' ),
		),
		array(
			'label'       => __( 'Buscar en Google', 'melomaniac-sync' ),
			'url'         => melomaniac_sync_google_search_url( $barcode, $extra ),
			'description' => __( 'Útil cuando el disco es una edición local o muy antigua.', 'melomaniac-sync' ),
		),
		array(
			'label'       => __( 'Buscar en MusicBrainz', 'melomaniac-sync' ),
			'url'         => melomaniac_sync_musicbrainz_search_url( $barcode ),
			'description' => __( 'Verifica si el disco existe con otro código de barra.', 'melomaniac-sync' ),
		),
	);

	/**
	 * Filters the manual reference links.
	 *
	 * Anything added here must be a plain link for a human to click. Automated
	 * data sources belong in includes/integrations/, not on this hook.
	 *
	 * @param array[] $links   Reference links.
	 * @param string  $barcode Barcode.
	 */
	return apply_filters( 'melomaniac_sync_reference_links', $links, $barcode );
}
