<?php
/**
 * MusicBrainz contribution queue screen controller.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists discs loaded by hand that are not yet contributed to MusicBrainz, and
 * lets the shop mark one as done after opening its seeded Add Release page.
 *
 * MusicBrainz has no bulk or CSV import of its own: this only speeds up
 * opening one seed form after another, it does not submit anything on its
 * own — each one still needs the shop to review and send it themselves.
 */
class Melomaniac_Sync_Mb_Queue_Page {

	/**
	 * Nonce action for the mark-as-contributed AJAX call.
	 */
	const NONCE_ACTION = 'melomaniac_sync_mb_queue';

	/**
	 * wp_ajax action that marks a product as contributed.
	 */
	const ACTION_MARK = 'melomaniac_sync_mb_mark_contributed';

	/**
	 * Maximum discs shown at once.
	 */
	const LIMIT = 50;

	/**
	 * Product factory.
	 *
	 * @var Melomaniac_Sync_Product_Factory
	 */
	private $product_factory;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Product_Factory $product_factory Product factory.
	 */
	public function __construct( Melomaniac_Sync_Product_Factory $product_factory ) {
		$this->product_factory = $product_factory;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION_MARK, array( $this, 'handle_mark' ) );
	}

	/**
	 * Marks one product as contributed, so it drops out of the queue.
	 *
	 * @return void
	 */
	public function handle_mark() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión expiró. Recarga la página.', 'melomaniac-sync' ) ), 403 );
		}

		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para hacer esto.', 'melomaniac-sync' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( 0 === $product_id || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ese producto ya no existe.', 'melomaniac-sync' ) ) );
		}

		$this->product_factory->mark_contributed( $product_id );

		wp_send_json_success();
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver esta pantalla.', 'melomaniac-sync' ) );
		}

		$items = array();

		foreach ( $this->product_factory->find_uncontributed_manual( self::LIMIT ) as $product_id ) {
			$items[] = array(
				'product_id' => $product_id,
				'title'      => get_the_title( $product_id ),
				'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
				'release'    => $this->product_factory->release_from_meta( $product_id ),
			);
		}

		Melomaniac_Sync_Admin::render_view( 'page-mb-queue', array( 'items' => $items ) );
	}
}
