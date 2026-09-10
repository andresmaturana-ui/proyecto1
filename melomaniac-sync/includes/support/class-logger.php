<?php
/**
 * Optional debug logging.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes diagnostic messages to the WordPress debug log when logging is enabled.
 */
class Melomaniac_Sync_Logger {

	/**
	 * Logs a message.
	 *
	 * @param string $message Message to record.
	 * @param string $level   One of info, warning, error.
	 * @param array  $context Extra data appended as JSON.
	 * @return void
	 */
	public function log( $message, $level = 'info', array $context = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$line = sprintf( '[Melomaniac Sync] [%s] %s', strtoupper( $level ), $message );

		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by is_enabled().
		error_log( $line );
	}

	/**
	 * Shortcut for error level messages.
	 *
	 * @param string $message Message to record.
	 * @param array  $context Extra data.
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		$this->log( $message, 'error', $context );
	}

	/**
	 * Whether logging should happen at all.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		if ( Melomaniac_Sync_Settings::logging_enabled() ) {
			return true;
		}

		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
