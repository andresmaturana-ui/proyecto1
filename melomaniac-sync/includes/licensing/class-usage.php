<?php
/**
 * Monthly usage counter.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Counts how many discs this store added during the current calendar month.
 *
 * One option holds the count and the month it belongs to, so the counter resets
 * itself on the first read of a new month and no rows accumulate over time.
 *
 * The count is taken where the product is actually created, so every path that
 * creates a product — the app, the admin screen, the bulk importer — is covered
 * by the same counter and none of them can drift.
 */
class Melomaniac_Sync_Usage {

	/**
	 * Option holding the month and the count.
	 */
	const OPTION = 'melomaniac_sync_usage';

	/**
	 * Current month in the site's timezone.
	 *
	 * @return string
	 */
	private static function current_month() {
		return current_time( 'Y-m' );
	}

	/**
	 * Reads the counter, resetting it when the month changed.
	 *
	 * @return array{month:string,count:int}
	 */
	private static function read() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || ! isset( $stored['month'] ) || $stored['month'] !== self::current_month() ) {
			return array(
				'month' => self::current_month(),
				'count' => 0,
			);
		}

		return array(
			'month' => (string) $stored['month'],
			'count' => isset( $stored['count'] ) ? (int) $stored['count'] : 0,
		);
	}

	/**
	 * Discs added so far this month.
	 *
	 * @return int
	 */
	public static function used_this_month() {
		return self::read()['count'];
	}

	/**
	 * Discs still allowed this month.
	 *
	 * @return int|null Null when the plan is unlimited.
	 */
	public static function remaining() {
		$limit = Melomaniac_Sync_Licensing::monthly_limit();

		if ( null === $limit ) {
			return null;
		}

		return max( 0, $limit - self::used_this_month() );
	}

	/**
	 * Whether another disc can be added right now.
	 *
	 * @return bool
	 */
	public static function has_quota() {
		$limit = Melomaniac_Sync_Licensing::monthly_limit();

		return null === $limit || self::used_this_month() < $limit;
	}

	/**
	 * Whether the store is close enough to the limit to be warned.
	 *
	 * @return bool
	 */
	public static function is_near_limit() {
		$limit = Melomaniac_Sync_Licensing::monthly_limit();

		if ( null === $limit || $limit <= 0 ) {
			return false;
		}

		if ( ! self::has_quota() ) {
			// At the limit there is a block to show, not a warning.
			return false;
		}

		return ( self::used_this_month() / $limit ) >= Melomaniac_Sync_Licensing::WARN_THRESHOLD;
	}

	/**
	 * Adds one to this month's count. Does nothing on an unlimited plan.
	 *
	 * @return void
	 */
	public static function record_disc() {
		if ( Melomaniac_Sync_Licensing::has_unlimited_discs() ) {
			return;
		}

		$data = self::read();
		++$data['count'];

		update_option( self::OPTION, $data, false );
	}

	/**
	 * Everything a screen or an API response needs to describe the quota.
	 *
	 * @return array{plan:string,plan_label:string,limit:int|null,used:int,remaining:int|null,near_limit:bool,upgrade_url:string}
	 */
	public static function summary() {
		return array(
			'plan'        => Melomaniac_Sync_Licensing::get_plan(),
			'plan_label'  => Melomaniac_Sync_Licensing::plan_label(),
			'limit'       => Melomaniac_Sync_Licensing::monthly_limit(),
			'used'        => self::used_this_month(),
			'remaining'   => self::remaining(),
			'near_limit'  => self::is_near_limit(),
			'upgrade_url' => Melomaniac_Sync_Licensing::upgrade_url(),
		);
	}

	/**
	 * Message shown when the monthly limit is reached.
	 *
	 * @return string
	 */
	public static function limit_reached_message() {
		$limit = Melomaniac_Sync_Licensing::monthly_limit();
		$next  = Melomaniac_Sync_Licensing::next_plan_description();

		if ( '' === $next ) {
			return __( 'Alcanzaste el límite mensual de discos.', 'melomaniac-sync' );
		}

		return sprintf(
			/* translators: 1: monthly limit, 2: current plan name, 3: description of the next plan up. */
			__( 'Alcanzaste el límite de %1$d discos este mes en el plan %2$s. Pasa a %3$s para seguir cargando.', 'melomaniac-sync' ),
			(int) $limit,
			Melomaniac_Sync_Licensing::plan_label(),
			$next
		);
	}

	/**
	 * Message shown as the store approaches the monthly limit.
	 *
	 * @return string Empty when there is nothing to warn about.
	 */
	public static function near_limit_message() {
		if ( ! self::is_near_limit() ) {
			return '';
		}

		return sprintf(
			/* translators: 1: discs used, 2: monthly limit. */
			__( 'Vas %1$d de %2$d discos este mes.', 'melomaniac-sync' ),
			self::used_this_month(),
			(int) Melomaniac_Sync_Licensing::monthly_limit()
		);
	}
}
