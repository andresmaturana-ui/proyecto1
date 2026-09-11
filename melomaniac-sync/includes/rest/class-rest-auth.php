<?php
/**
 * Authenticates the app's REST requests via a custom header.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets the companion app authenticate with a WordPress Application Password,
 * carried in a header of our own instead of the standard Authorization one.
 *
 * A custom header sidesteps a real-world host quirk: many shared hosts strip
 * or mangle the Authorization header before PHP ever sees it, which is why
 * some app-password setups need a PHP_AUTH_USER fallback trick at all. Since
 * this header is one only this plugin looks for, that problem does not come
 * up here.
 *
 * The reference plugin this was modelled on force-enabled Application
 * Passwords for every request on the site, admin screens included, at
 * PHP_INT_MAX priority. That widens what a compromised application password
 * can do beyond the app's own traffic. Here the three filters that do that
 * forcing only return true while the request itself carries our header, and
 * otherwise pass through whatever WordPress or another plugin already
 * decided.
 */
class Melomaniac_Sync_Rest_Auth {

	/**
	 * Header name the app sends its credentials in.
	 */
	const HEADER = 'X-Melomaniac-Auth';

	/**
	 * $_SERVER key the same header arrives under.
	 */
	const SERVER_KEY = 'HTTP_X_MELOMANIAC_AUTH';

	/**
	 * Registers hooks.
	 *
	 * Runs unconditionally, not only in wp-admin: REST requests are never
	 * is_admin(), so gating this behind that check would mean the app could
	 * never authenticate at all.
	 *
	 * @return void
	 */
	public function register() {
		// Negative priority: WordPress validates its own login cookie in
		// determine_current_user at priority 0 via wp_validate_logged_in_cookie(),
		// which returns its $user_id argument unchanged once it is already set.
		// Running first here means a request authenticated by our header never
		// hits that cookie/nonce check at all.
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), -10 );

		add_filter( 'wp_is_application_passwords_available', array( $this, 'force_available' ), PHP_INT_MAX );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'force_available_for_user' ), PHP_INT_MAX, 2 );
		add_filter( 'application_password_is_api_request', array( $this, 'force_is_api_request' ), PHP_INT_MAX );
		add_filter( 'rest_allowed_cors_headers', array( $this, 'allow_header' ) );
	}

	/**
	 * Authenticates from the header, when one was sent.
	 *
	 * @param int $user_id User ID resolved so far.
	 * @return int
	 */
	public function authenticate( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$raw = $this->read_header();

		if ( '' === $raw ) {
			return $user_id;
		}

		$decoded = base64_decode( trim( $raw ), true );

		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return $user_id;
		}

		list( $username, $password ) = explode( ':', $decoded, 2 );

		if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
			return $user_id;
		}

		$user = wp_authenticate_application_password( null, $username, $password );

		return $user instanceof WP_User ? $user->ID : $user_id;
	}

	/**
	 * Forces Application Passwords on, only for requests carrying our header.
	 *
	 * @param bool $available Value from an earlier filter.
	 * @return bool
	 */
	public function force_available( $available ) {
		return $this->header_present() ? true : $available;
	}

	/**
	 * Forces Application Passwords on for one user, same scoping as above.
	 *
	 * @param bool    $available Value from an earlier filter.
	 * @param WP_User $user      User being checked.
	 * @return bool
	 */
	public function force_available_for_user( $available, $user ) {
		return $this->header_present() ? true : $available;
	}

	/**
	 * Tells core this looks like an API request, same scoping as above.
	 *
	 * Core's own check only recognises REST_REQUEST or XMLRPC_REQUEST; a
	 * request carrying our header is by definition an API call from the app.
	 *
	 * @param bool $is_api_request Value from an earlier filter.
	 * @return bool
	 */
	public function force_is_api_request( $is_api_request ) {
		return $this->header_present() ? true : $is_api_request;
	}

	/**
	 * Allows the header through CORS preflight, for an app on another origin.
	 *
	 * @param string[] $headers Already allowed headers.
	 * @return string[]
	 */
	public function allow_header( $headers ) {
		$headers[] = self::HEADER;

		return $headers;
	}

	/**
	 * Whether this request carries our header at all.
	 *
	 * @return bool
	 */
	private function header_present() {
		return '' !== $this->read_header();
	}

	/**
	 * Reads the header from wherever the server made it available.
	 *
	 * @return string Empty when absent.
	 */
	private function read_header() {
		if ( ! empty( $_SERVER[ self::SERVER_KEY ] ) ) {
			return (string) $_SERVER[ self::SERVER_KEY ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Not form input; validated below by base64_decode/explode.
		}

		if ( function_exists( 'getallheaders' ) ) {
			foreach ( getallheaders() as $name => $value ) {
				if ( 0 === strcasecmp( $name, self::HEADER ) ) {
					return (string) $value;
				}
			}
		}

		return '';
	}
}
