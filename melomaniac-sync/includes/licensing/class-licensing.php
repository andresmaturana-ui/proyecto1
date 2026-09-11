<?php
/**
 * Plan resolution.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * The only place that knows which plan the store is on.
 *
 * Three plans:
 *
 *   free    -> limited number of discs per calendar month
 *   pro     -> a higher monthly limit
 *   premium -> unlimited discs plus bulk upload
 *
 * Everything else in the plugin asks this class rather than talking to Freemius,
 * so the licensing provider can change without touching feature code, and tests
 * can force a plan.
 *
 * To pin a plan without Freemius, in wp-config.php:
 *   define( 'MELOMANIAC_SYNC_FORCE_PLAN', 'pro' );
 */
class Melomaniac_Sync_Licensing {

	/**
	 * Known plan slugs, cheapest first. These must match the plan names in the
	 * Freemius dashboard exactly.
	 */
	const PLANS = array( 'free', 'pro', 'premium' );

	/**
	 * Option holding a plan forced from the Diagnostics screen, for testing
	 * without touching wp-config.php or depending on Freemius. Empty means no
	 * override is active.
	 */
	const OPTION_TEST_PLAN = 'melomaniac_sync_test_plan';

	/**
	 * Monthly disc limits per plan. Null means unlimited.
	 */
	const DEFAULT_LIMITS = array(
		'free'    => 5,
		'pro'     => 30,
		'premium' => null,
	);

	/**
	 * Fraction of the limit at which the store starts being warned.
	 */
	const WARN_THRESHOLD = 0.8;

	/**
	 * Registers the bridge between Freemius and the plan filter.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'melomaniac_sync_plan', array( __CLASS__, 'resolve_plan_from_freemius' ) );
	}

	/**
	 * Reads the plan out of the Freemius SDK.
	 *
	 * @param string $plan Plan resolved so far.
	 * @return string
	 */
	public static function resolve_plan_from_freemius( $plan ) {
		if ( ! function_exists( 'melomaniac_sync_fs' ) ) {
			return $plan;
		}

		$fs = melomaniac_sync_fs();

		if ( $fs->is_plan( 'premium' ) ) {
			return 'premium';
		}

		if ( $fs->is_plan( 'pro' ) ) {
			return 'pro';
		}

		return 'free';
	}

	/**
	 * The store's current plan.
	 *
	 * @return string One of the slugs in self::PLANS.
	 */
	public static function get_plan() {
		if ( defined( 'MELOMANIAC_SYNC_FORCE_PLAN' ) && in_array( MELOMANIAC_SYNC_FORCE_PLAN, self::PLANS, true ) ) {
			return MELOMANIAC_SYNC_FORCE_PLAN;
		}

		$test_plan = self::test_plan();

		if ( '' !== $test_plan ) {
			return $test_plan;
		}

		/**
		 * Filters the store's plan.
		 *
		 * @param string $plan Plan slug.
		 */
		$plan = apply_filters( 'melomaniac_sync_plan', 'free' );

		return in_array( $plan, self::PLANS, true ) ? $plan : 'free';
	}

	/**
	 * The plan forced from Diagnostics, when there is one.
	 *
	 * Sits below the wp-config.php constant (a host-level override should always
	 * win) and above Freemius, so a shop owner can try each plan's screens
	 * without a real subscription.
	 *
	 * @return string One of self::PLANS, or empty when no override is set.
	 */
	public static function test_plan() {
		$value = get_option( self::OPTION_TEST_PLAN, '' );

		return in_array( $value, self::PLANS, true ) ? $value : '';
	}

	/**
	 * Sets or clears the plan forced from Diagnostics.
	 *
	 * @param string $plan One of self::PLANS, or empty to go back to the real plan.
	 * @return void
	 */
	public static function set_test_plan( $plan ) {
		if ( '' === $plan || ! in_array( $plan, self::PLANS, true ) ) {
			delete_option( self::OPTION_TEST_PLAN );
			return;
		}

		update_option( self::OPTION_TEST_PLAN, $plan, false );
	}

	/**
	 * Whether the plan in effect right now is a test override rather than the
	 * store's real plan.
	 *
	 * @return bool
	 */
	public static function is_test_plan_active() {
		if ( defined( 'MELOMANIAC_SYNC_FORCE_PLAN' ) && in_array( MELOMANIAC_SYNC_FORCE_PLAN, self::PLANS, true ) ) {
			return false;
		}

		return '' !== self::test_plan();
	}

	/**
	 * Monthly disc limit for the current plan.
	 *
	 * @return int|null Null when unlimited.
	 */
	public static function monthly_limit() {
		/**
		 * Filters the monthly disc limit of each plan.
		 *
		 * @param array<string,int|null> $limits Limits keyed by plan slug.
		 */
		$limits = apply_filters( 'melomaniac_sync_plan_limits', self::DEFAULT_LIMITS );
		$plan   = self::get_plan();

		if ( ! array_key_exists( $plan, $limits ) ) {
			return self::DEFAULT_LIMITS['free'];
		}

		return null === $limits[ $plan ] ? null : (int) $limits[ $plan ];
	}

	/**
	 * Whether the current plan has no monthly limit.
	 *
	 * @return bool
	 */
	public static function has_unlimited_discs() {
		return null === self::monthly_limit();
	}

	/**
	 * Whether the current plan includes bulk upload.
	 *
	 * @return bool
	 */
	public static function has_bulk_upload() {
		return 'premium' === self::get_plan();
	}

	/**
	 * Translated label for a plan.
	 *
	 * @param string|null $plan Plan slug, current plan when null.
	 * @return string
	 */
	public static function plan_label( $plan = null ) {
		$labels = array(
			'free'    => __( 'Gratis', 'melomaniac-sync' ),
			'pro'     => __( 'Pro', 'melomaniac-sync' ),
			'premium' => __( 'Premium', 'melomaniac-sync' ),
		);

		$plan = null === $plan ? self::get_plan() : $plan;

		return isset( $labels[ $plan ] ) ? $labels[ $plan ] : $labels['free'];
	}

	/**
	 * Describes what the next plan up adds, for upgrade prompts.
	 *
	 * @return string Empty on the top plan.
	 */
	public static function next_plan_description() {
		$plan = self::get_plan();

		if ( 'free' === $plan ) {
			return __( 'Pro (30 discos al mes) o Premium (discos ilimitados + carga masiva)', 'melomaniac-sync' );
		}

		if ( 'pro' === $plan ) {
			return __( 'Premium (discos ilimitados + carga masiva)', 'melomaniac-sync' );
		}

		return '';
	}

	/**
	 * URL of the Freemius pricing screen, for upgrade buttons.
	 *
	 * @return string Falls back to the plugin's own page when Freemius is absent.
	 */
	public static function upgrade_url() {
		if ( function_exists( 'melomaniac_sync_fs' ) ) {
			$url = melomaniac_sync_fs()->get_upgrade_url();

			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return admin_url( 'admin.php?page=melomaniac-sync-pricing' );
	}
}
