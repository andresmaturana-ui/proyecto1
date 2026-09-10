<?php
/**
 * Outbound connectivity probes.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tests whether this server can actually reach the services the plugin needs.
 *
 * A timeout with zero bytes received is almost never a problem with the remote
 * service: it is the store's own host dropping outbound packets, a broken IPv6
 * route, or the site's IP being blocked. These probes separate those cases so
 * the shop owner knows who to ask.
 */
class Melomaniac_Sync_Connectivity_Check {

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
	 * Runs every probe.
	 *
	 * @return array[] One entry per service.
	 */
	public function run_all() {
		$results = array(
			$this->probe_musicbrainz(),
			$this->probe_cover_art(),
		);

		$results[] = $this->probe_discogs();

		return $results;
	}

	/**
	 * Probes the MusicBrainz web service.
	 *
	 * @return array
	 */
	private function probe_musicbrainz() {
		return $this->probe(
			__( 'MusicBrainz', 'melomaniac-sync' ),
			'musicbrainz.org',
			'https://musicbrainz.org/ws/2/release?query=barcode:0000000000000&fmt=json&limit=1',
			array(
				'Accept'     => 'application/json',
				'User-Agent' => sprintf( 'MelomaniacSync/%s ( %s )', MELOMANIAC_SYNC_VERSION, Melomaniac_Sync_Settings::api_contact() ),
			),
			__( 'Datos de los discos. Sin esto el plugin no puede buscar nada.', 'melomaniac-sync' )
		);
	}

	/**
	 * Probes the Cover Art Archive.
	 *
	 * @return array
	 */
	private function probe_cover_art() {
		return $this->probe(
			__( 'Cover Art Archive', 'melomaniac-sync' ),
			'coverartarchive.org',
			'https://coverartarchive.org/release/f5093c06-23e3-404f-aeaa-40f72885ee3a',
			array( 'Accept' => 'application/json' ),
			__( 'Portadas. Si falla, los productos se crean sin imagen.', 'melomaniac-sync' )
		);
	}

	/**
	 * Probes the Discogs API, when a token is configured.
	 *
	 * @return array
	 */
	private function probe_discogs() {
		$token = Melomaniac_Sync_Settings::discogs_token();

		if ( '' === $token ) {
			return array(
				'label'       => __( 'Discogs', 'melomaniac-sync' ),
				'host'        => 'api.discogs.com',
				'purpose'     => __( 'Respaldo cuando MusicBrainz no tiene el disco.', 'melomaniac-sync' ),
				'state'       => 'skipped',
				'message'     => __( 'Sin token configurado, no se prueba.', 'melomaniac-sync' ),
				'status_code' => 0,
				'elapsed_ms'  => 0,
				'ipv4'        => '',
				'ipv6'        => '',
			);
		}

		return $this->probe(
			__( 'Discogs', 'melomaniac-sync' ),
			'api.discogs.com',
			'https://api.discogs.com/database/search?barcode=0000000000000&type=release',
			array(
				'Accept'        => 'application/json',
				'Authorization' => 'Discogs token=' . $token,
			),
			__( 'Respaldo cuando MusicBrainz no tiene el disco.', 'melomaniac-sync' )
		);
	}

	/**
	 * Resolves a host and performs one timed request against it.
	 *
	 * @param string $label   Human readable service name.
	 * @param string $host    Hostname, for the DNS part.
	 * @param string $url     URL to request.
	 * @param array  $headers Request headers.
	 * @param string $purpose What the plugin uses this service for.
	 * @return array
	 */
	private function probe( $label, $host, $url, array $headers, $purpose ) {
		$dns = $this->resolve( $host );

		$started  = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => Melomaniac_Sync_Settings::http_timeout(),
				'headers'     => $headers,
				'redirection' => 3,
			)
		);
		$elapsed  = (int) round( ( microtime( true ) - $started ) * 1000 );

		$result = array(
			'label'       => $label,
			'host'        => $host,
			'purpose'     => $purpose,
			'elapsed_ms'  => $elapsed,
			'ipv4'        => $dns['ipv4'],
			'ipv6'        => $dns['ipv6'],
			'status_code' => 0,
			'state'       => 'fail',
			'message'     => '',
		);

		if ( is_wp_error( $response ) ) {
			$result['message'] = $this->explain_error( $response, $dns, $elapsed );
			$this->logger->error(
				'Connectivity probe failed.',
				array(
					'host'  => $host,
					'error' => $response->get_error_message(),
				)
			);

			return $result;
		}

		$result['status_code'] = (int) wp_remote_retrieve_response_code( $response );

		// Any HTTP answer proves the network path works, even a 401 or a 404.
		if ( $result['status_code'] > 0 ) {
			$result['state'] = $result['status_code'] >= 200 && $result['status_code'] <= 299 ? 'ok' : 'warn';
		}

		if ( 401 === $result['status_code'] || 403 === $result['status_code'] ) {
			$result['message'] = __( 'El servidor llega, pero rechazó la credencial. Revisá el token.', 'melomaniac-sync' );
		} elseif ( 503 === $result['status_code'] ) {
			$result['message'] = __( 'El servidor llega, pero está limitando las peticiones en este momento.', 'melomaniac-sync' );
		} elseif ( 'ok' === $result['state'] ) {
			$result['message'] = __( 'Responde bien.', 'melomaniac-sync' );
		}

		return $result;
	}

	/**
	 * Turns a WP_Error into something a shop owner can act on.
	 *
	 * @param WP_Error $error   The failure.
	 * @param array    $dns     Resolution result.
	 * @param int      $elapsed Milliseconds spent before failing.
	 * @return string
	 */
	private function explain_error( WP_Error $error, array $dns, $elapsed ) {
		$raw     = $error->get_error_message();
		$timeout = Melomaniac_Sync_Settings::http_timeout();

		if ( false !== stripos( $raw, 'timed out' ) || false !== stripos( $raw, 'timeout' ) ) {
			// Nothing came back at all. The remote service being down looks
			// different from this: it answers with an error code.
			if ( '' === $dns['ipv4'] && '' !== $dns['ipv6'] ) {
				return sprintf(
					/* translators: %d: timeout in seconds. */
					__( 'Se agotaron los %d segundos sin recibir un solo byte, y el dominio solo resolvió a IPv6. Si el servidor no tiene salida IPv6 funcionando, las conexiones quedan colgadas hasta el timeout. Pedile a tu hosting que habilite IPv6 o que fuerce IPv4.', 'melomaniac-sync' ),
					$timeout
				);
			}

			return sprintf(
				/* translators: %d: timeout in seconds. */
				__( 'Se agotaron los %d segundos sin recibir un solo byte. El hosting está descartando la conexión de salida, o la IP del sitio está bloqueada. Pedile a tu hosting que permita conexiones HTTPS salientes a este dominio, o subí el tiempo de espera más abajo.', 'melomaniac-sync' ),
				$timeout
			);
		}

		if ( false !== stripos( $raw, 'could not resolve' ) || false !== stripos( $raw, 'resolve host' ) ) {
			return __( 'El servidor no pudo resolver el dominio. Es un problema de DNS del hosting.', 'melomaniac-sync' );
		}

		if ( false !== stripos( $raw, 'blocked' ) ) {
			return __( 'WordPress tiene las peticiones externas bloqueadas por configuración (WP_HTTP_BLOCK_EXTERNAL en wp-config.php).', 'melomaniac-sync' );
		}

		if ( false !== stripos( $raw, 'certificate' ) || false !== stripos( $raw, 'SSL' ) ) {
			return __( 'Falló la verificación del certificado. El hosting tiene los certificados raíz desactualizados.', 'melomaniac-sync' );
		}

		/* translators: 1: raw transport error, 2: milliseconds elapsed. */
		return sprintf( __( '%1$s (a los %2$d ms)', 'melomaniac-sync' ), $raw, $elapsed );
	}

	/**
	 * Resolves a hostname to IPv4 and IPv6, as far as PHP allows.
	 *
	 * @param string $host Hostname.
	 * @return array{ipv4:string,ipv6:string}
	 */
	private function resolve( $host ) {
		$ipv4 = '';
		$ipv6 = '';

		if ( function_exists( 'gethostbyname' ) ) {
			$resolved = gethostbyname( $host );

			// gethostbyname() returns the hostname unchanged on failure.
			if ( $resolved !== $host ) {
				$ipv4 = $resolved;
			}
		}

		if ( function_exists( 'dns_get_record' ) ) {
			// Some hosts disable this; a failure here is not itself a problem.
			$records = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Optional diagnostic.

			if ( is_array( $records ) && ! empty( $records[0]['ipv6'] ) ) {
				$ipv6 = $records[0]['ipv6'];
			}
		}

		return array(
			'ipv4' => $ipv4,
			'ipv6' => $ipv6,
		);
	}

	/**
	 * Environment facts worth reporting next to the probes.
	 *
	 * @return array<string,string>
	 */
	public function environment() {
		global $wp_version;

		$blocked = defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL;

		$rows = array(
			__( 'Versión de WordPress', 'melomaniac-sync' ) => isset( $wp_version ) ? $wp_version : __( 'desconocida', 'melomaniac-sync' ),
			__( 'Versión de PHP', 'melomaniac-sync' )       => PHP_VERSION,
			__( 'Versión de WooCommerce', 'melomaniac-sync' ) => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'no detectada', 'melomaniac-sync' ),
			__( 'Versión del plugin', 'melomaniac-sync' )   => MELOMANIAC_SYNC_VERSION,
			__( 'Tiempo de espera configurado', 'melomaniac-sync' ) => sprintf(
				/* translators: %d: seconds. */
				__( '%d segundos', 'melomaniac-sync' ),
				Melomaniac_Sync_Settings::http_timeout()
			),
			__( 'Plan', 'melomaniac-sync' )                 => Melomaniac_Sync_Licensing::plan_label(),
			__( 'Peticiones externas bloqueadas en wp-config', 'melomaniac-sync' ) => $blocked
				? __( 'Sí, y eso rompe el plugin', 'melomaniac-sync' )
				: __( 'No', 'melomaniac-sync' ),
			__( 'Action Scheduler disponible', 'melomaniac-sync' ) => function_exists( 'as_enqueue_async_action' )
				? __( 'Sí', 'melomaniac-sync' )
				: __( 'No', 'melomaniac-sync' ),
		);

		if ( $blocked && defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
			$rows[ __( 'Dominios permitidos', 'melomaniac-sync' ) ] = (string) WP_ACCESSIBLE_HOSTS;
		}

		return $rows;
	}
}
