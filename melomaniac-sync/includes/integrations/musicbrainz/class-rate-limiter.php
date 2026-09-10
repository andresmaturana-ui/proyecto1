<?php
/**
 * Cross-process rate limiter for the MusicBrainz API.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the MusicBrainz courtesy limit of roughly one request per second.
 *
 * The timestamp lives in an option rather than in object state because bulk
 * imports (phase 3) run in separate PHP processes through Action Scheduler,
 * and all of them must share the same budget.
 */
class Melomaniac_Sync_Rate_Limiter {

	/**
	 * Option holding the microtime of the last outbound request.
	 */
	const OPTION_LAST_REQUEST = 'melomaniac_sync_mb_last_request';

	/**
	 * Minimum seconds between two requests.
	 *
	 * @var float
	 */
	private $interval;

	/**
	 * Upper bound for a single wait, as a guard against clock skew.
	 *
	 * @var float
	 */
	private $max_wait = 2.0;

	/**
	 * Constructor.
	 *
	 * @param float $interval Minimum seconds between requests.
	 */
	public function __construct( $interval = 1.1 ) {
		/**
		 * Filters the minimum delay between MusicBrainz requests.
		 *
		 * Lowering this below 1.0 will get the store's IP blocked. Do not.
		 *
		 * @param float $interval Seconds.
		 */
		$this->interval = (float) apply_filters( 'melomaniac_sync_rate_limit_interval', $interval );
	}

	/**
	 * Blocks until the next request is allowed, then reserves this slot.
	 *
	 * @return void
	 */
	public function wait_for_slot() {
		$wait = $this->seconds_until_slot();

		if ( $wait > 0 ) {
			usleep( (int) round( $wait * 1000000 ) );
		}

		$this->reserve_slot();
	}

	/**
	 * How long the caller would have to wait right now.
	 *
	 * Useful for background workers that prefer rescheduling over sleeping.
	 *
	 * @return float Seconds, zero when a slot is free.
	 */
	public function seconds_until_slot() {
		$last = (float) get_option( self::OPTION_LAST_REQUEST, 0 );

		if ( $last <= 0 ) {
			return 0.0;
		}

		$elapsed = microtime( true ) - $last;

		if ( $elapsed < 0 ) {
			// Clock moved backwards; treat the slot as free.
			return 0.0;
		}

		if ( $elapsed >= $this->interval ) {
			return 0.0;
		}

		return min( $this->interval - $elapsed, $this->max_wait );
	}

	/**
	 * Marks the current moment as the last request.
	 *
	 * @return void
	 */
	public function reserve_slot() {
		update_option( self::OPTION_LAST_REQUEST, (string) microtime( true ), false );
	}
}
