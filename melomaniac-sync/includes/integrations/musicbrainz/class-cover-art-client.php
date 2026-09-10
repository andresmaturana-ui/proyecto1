<?php
/**
 * Cover Art Archive client.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves front cover URLs for a release MBID.
 *
 * Cover Art Archive exposes predictable URLs, so when the release payload
 * already tells us a front cover exists we build the URLs directly instead of
 * spending another request. The JSON endpoint is only used as a fallback.
 */
class Melomaniac_Sync_Cover_Art_Client {

	/**
	 * Archive base URL.
	 */
	const BASE = 'https://coverartarchive.org/release/';

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
	 * Builds the well-known front cover URLs for a release.
	 *
	 * @param string $mbid Release MBID.
	 * @return array {
	 *     @type string $full  Full size image URL.
	 *     @type string $thumb 500px thumbnail URL.
	 * }
	 */
	public function build_front_urls( $mbid ) {
		$base = self::BASE . rawurlencode( $mbid );

		return array(
			'full'  => $base . '/front',
			'thumb' => $base . '/front-500',
		);
	}

	/**
	 * Asks the archive which images exist for a release.
	 *
	 * @param string $mbid Release MBID.
	 * @return array|null Front cover URLs, or null when there is no artwork.
	 */
	public function fetch_front_urls( $mbid ) {
		$response = wp_remote_get(
			self::BASE . rawurlencode( $mbid ),
			array(
				'timeout'    => Melomaniac_Sync_Settings::http_timeout(),
				'user-agent' => sprintf( 'MelomaniacSync/%s ( %s )', MELOMANIAC_SYNC_VERSION, home_url( '/' ) ),
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Cover Art Archive request failed.', array( 'error' => $response->get_error_message() ) );
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) || empty( $decoded['images'] ) || ! is_array( $decoded['images'] ) ) {
			return null;
		}

		foreach ( $decoded['images'] as $image ) {
			if ( empty( $image['front'] ) || empty( $image['image'] ) ) {
				continue;
			}

			$thumb = '';

			if ( ! empty( $image['thumbnails']['500'] ) ) {
				$thumb = $image['thumbnails']['500'];
			} elseif ( ! empty( $image['thumbnails']['large'] ) ) {
				$thumb = $image['thumbnails']['large'];
			}

			return array(
				'full'  => esc_url_raw( $image['image'] ),
				'thumb' => '' !== $thumb ? esc_url_raw( $thumb ) : esc_url_raw( $image['image'] ),
			);
		}

		return null;
	}
}
