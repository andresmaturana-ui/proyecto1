<?php
/**
 * HTTP client for the Discogs API.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queries Discogs as a fallback when MusicBrainz has no match.
 *
 * Two rules this class exists to enforce:
 *
 * 1. The token belongs to the store, comes from the settings screen, and is
 *    never bundled with the plugin. A credential shipped inside a plugin is
 *    public the moment the plugin is distributed, and its rate limit would be
 *    shared by every installation.
 * 2. Discogs is only ever called from PHP. The token must never reach the
 *    browser, so the app asks our own REST endpoint and the server calls
 *    Discogs on its behalf.
 */
class Melomaniac_Sync_Discogs_Client {

	/**
	 * API base URL.
	 */
	const API_BASE = 'https://api.discogs.com/';

	/**
	 * Rate limiter shared across processes.
	 *
	 * @var Melomaniac_Sync_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Logger.
	 *
	 * @var Melomaniac_Sync_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Rate_Limiter $rate_limiter Rate limiter.
	 * @param Melomaniac_Sync_Logger       $logger       Logger.
	 */
	public function __construct( Melomaniac_Sync_Rate_Limiter $rate_limiter, Melomaniac_Sync_Logger $logger ) {
		$this->rate_limiter = $rate_limiter;
		$this->logger       = $logger;
	}

	/**
	 * Whether this client is usable right now.
	 *
	 * @return bool
	 */
	public function is_available() {
		return Melomaniac_Sync_Settings::discogs_enabled();
	}

	/**
	 * Searches releases by barcode.
	 *
	 * @param string $barcode Digits only.
	 * @return array|WP_Error List of search results, or error.
	 */
	public function search_by_barcode( $barcode ) {
		$payload = $this->get(
			'database/search',
			array(
				'barcode' => $barcode,
				'type'    => 'release',
			)
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return isset( $payload['results'] ) && is_array( $payload['results'] ) ? $payload['results'] : array();
	}

	/**
	 * Fetches the full detail of one release.
	 *
	 * @param int $release_id Discogs release identifier.
	 * @return array|WP_Error
	 */
	public function get_release( $release_id ) {
		$release_id = absint( $release_id );

		if ( ! $release_id ) {
			return new WP_Error(
				'melomaniac_sync_invalid_discogs_id',
				__( 'El identificador de Discogs no es válido.', 'melomaniac-sync' )
			);
		}

		return $this->get( 'releases/' . $release_id );
	}

	/**
	 * Performs a GET request against the API.
	 *
	 * @param string $endpoint Path relative to the API base.
	 * @param array  $args     Query arguments.
	 * @return array|WP_Error
	 */
	private function get( $endpoint, array $args = array() ) {
		$token = Melomaniac_Sync_Settings::discogs_token();

		if ( '' === $token ) {
			return new WP_Error(
				'melomaniac_sync_discogs_no_token',
				__( 'Falta el token de Discogs de la tienda. Agrégalo en Melomaniac Sync → Ajustes.', 'melomaniac-sync' )
			);
		}

		$url = self::API_BASE . ltrim( $endpoint, '/' );

		if ( ! empty( $args ) ) {
			$url .= '?' . http_build_query( $args );
		}

		$this->rate_limiter->wait_for_slot();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => Melomaniac_Sync_Settings::http_timeout(),
				'user-agent' => $this->user_agent(),
				'headers'    => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Discogs token=' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Discogs request failed.', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );

			return new WP_Error(
				'melomaniac_sync_discogs_rate_limited',
				__( 'Se alcanzó el límite de peticiones de Discogs.', 'melomaniac-sync' ),
				array( 'retry_after' => $retry_after > 0 ? $retry_after : 60 )
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error(
				'melomaniac_sync_discogs_unauthorized',
				__( 'Discogs rechazó el token de la tienda. Revísalo en Melomaniac Sync → Ajustes.', 'melomaniac-sync' )
			);
		}

		if ( 404 === $code ) {
			return new WP_Error(
				'melomaniac_sync_not_found',
				__( 'Discogs no tiene ese lanzamiento.', 'melomaniac-sync' )
			);
		}

		if ( $code < 200 || $code > 299 ) {
			$message = is_array( $body ) && ! empty( $body['message'] )
				? $body['message']
				: sprintf(
					/* translators: %d: HTTP status code returned by Discogs. */
					__( 'Discogs respondió con el código %d.', 'melomaniac-sync' ),
					$code
				);

			$this->logger->error( 'Unexpected Discogs status.', array( 'status' => $code ) );

			return new WP_Error( 'melomaniac_sync_discogs_error', $message );
		}

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'melomaniac_sync_invalid_json',
				__( 'La respuesta de Discogs no se pudo interpretar.', 'melomaniac-sync' )
			);
		}

		return $body;
	}

	/**
	 * Builds the User-Agent string Discogs requires.
	 *
	 * @return string
	 */
	private function user_agent() {
		return sprintf( 'MelomaniacSync/%s +%s', MELOMANIAC_SYNC_VERSION, home_url( '/' ) );
	}
}
