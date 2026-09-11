<?php
/**
 * Reorders the single product page.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * By default WooCommerce shows the short description (artist · year · label
 * · country · format · catalog, for a record created by this plugin) right
 * under the price, and the attributes table further down in the "Additional
 * information" tab. The shop wants those two swapped: the attributes table
 * under the price, and the short description where the table used to be.
 */
class Melomaniac_Sync_Product_Display {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp', array( $this, 'reorder_summary' ) );
		add_filter( 'woocommerce_product_tabs', array( $this, 'swap_additional_information_tab' ), 98 );
	}

	/**
	 * Swaps the short description for the attributes table in the summary,
	 * on single product pages only.
	 *
	 * @return void
	 */
	public function reorder_summary() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_attributes_table' ), 15 );
	}

	/**
	 * Prints the attributes table (artist, label, year, format, country,
	 * catalog number) right under the price.
	 *
	 * @return void
	 */
	public function render_attributes_table() {
		global $product;

		if ( ! $product instanceof WC_Product || ! function_exists( 'wc_display_product_attributes' ) ) {
			return;
		}

		wc_display_product_attributes( $product );
	}

	/**
	 * Replaces the "Additional information" tab's table with the short
	 * description, so the same information isn't shown twice on the page.
	 *
	 * @param array $tabs Registered product tabs.
	 * @return array
	 */
	public function swap_additional_information_tab( $tabs ) {
		if ( ! isset( $tabs['additional_information'] ) ) {
			return $tabs;
		}

		$tabs['additional_information']['callback'] = array( $this, 'render_short_description_tab' );

		return $tabs;
	}

	/**
	 * Prints the short description inside what used to be the attributes tab.
	 *
	 * @return void
	 */
	public function render_short_description_tab() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$short_description = $product->get_short_description();

		if ( '' === $short_description ) {
			return;
		}

		echo wp_kses_post( wpautop( $short_description ) );
	}
}
