<?php
/**
 * Manual entry form handler.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin-post submission of the manual entry form.
 *
 * Parsing the fields into a release object lives in
 * Melomaniac_Sync_Manual_Release_Builder, shared with the REST API; this class
 * only deals with admin-post transport: nonce, capability, redirect.
 */
class Melomaniac_Sync_Manual_Entry_Handler {

	/**
	 * admin-post action name.
	 */
	const ACTION = 'melomaniac_sync_manual_entry';

	/**
	 * Product factory.
	 *
	 * @var Melomaniac_Sync_Product_Factory
	 */
	private $product_factory;

	/**
	 * Release builder.
	 *
	 * @var Melomaniac_Sync_Manual_Release_Builder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Product_Factory        $product_factory Product factory.
	 * @param Melomaniac_Sync_Manual_Release_Builder $builder         Release builder.
	 */
	public function __construct(
		Melomaniac_Sync_Product_Factory $product_factory,
		Melomaniac_Sync_Manual_Release_Builder $builder
	) {
		$this->product_factory = $product_factory;
		$this->builder         = $builder;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
	}

	/**
	 * Handles the form post.
	 *
	 * @return void
	 */
	public function handle_submit() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) );
		}

		check_admin_referer( Melomaniac_Sync_Admin::NONCE_ACTION );

		$posted     = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$release    = $this->builder->build_from_array( $posted );
		$product_id = $this->product_factory->create_draft(
			$release,
			Melomaniac_Sync_Catalog::read_overrides( $posted )
		);

		if ( is_wp_error( $product_id ) ) {
			$this->redirect_with_error( $product_id );
		}

		wp_safe_redirect(
			Melomaniac_Sync_Admin_Menu::scan_url(
				array(
					'melomaniac_status'  => 'created',
					'melomaniac_product' => $product_id,
				)
			)
		);
		exit;
	}

	/**
	 * Sends the user back to the scan screen with an error notice.
	 *
	 * @param WP_Error $error Error to report.
	 * @return void
	 */
	private function redirect_with_error( WP_Error $error ) {
		$args = array();
		$data = $error->get_error_data();

		if ( 'melomaniac_sync_duplicate' === $error->get_error_code() && is_array( $data ) && ! empty( $data['product_id'] ) ) {
			$args['melomaniac_status']  = 'duplicate';
			$args['melomaniac_product'] = (int) $data['product_id'];
		} else {
			$args['melomaniac_status']  = 'error';
			$args['melomaniac_message'] = $error->get_error_message();
		}

		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::scan_url( $args ) );
		exit;
	}
}
