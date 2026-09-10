<?php
/**
 * Publishing drafts straight from the products list.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Publish row action and a Publish bulk action to the products list.
 *
 * Products are created as drafts so they can be priced and checked, which means
 * publishing them one by one through the editor is the slow part of the job.
 * This turns it into one click from the list.
 */
class Melomaniac_Sync_Quick_Publish {

	/**
	 * Bulk action name.
	 *
	 * Deliberately different from the row action: edit.php loads admin.php, which
	 * fires admin_action_{$_REQUEST['action']} for any request carrying that
	 * parameter, bulk submissions included. Sharing one name would make a bulk
	 * publish also trigger the single-row handler, which needs a product_id that
	 * a bulk request does not have.
	 */
	const BULK_ACTION = 'melomaniac_sync_publish';

	/**
	 * Row action name.
	 */
	const ROW_ACTION = 'melomaniac_sync_publish_row';

	/**
	 * Query argument carrying the result back to the list.
	 */
	const RESULT_ARG = 'melomaniac_sync_published';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'admin_action_' . self::ROW_ACTION, array( $this, 'handle_row_publish' ) );

		add_filter( 'bulk_actions-edit-product', array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_publish' ), 10, 3 );

		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * Adds the row action to draft products.
	 *
	 * @param string[] $actions Existing row actions.
	 * @param WP_Post  $post    Current post.
	 * @return string[]
	 */
	public function add_row_action( $actions, $post ) {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type || 'draft' !== $post->post_status ) {
			return $actions;
		}

		if ( ! current_user_can( 'publish_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=' . self::ROW_ACTION . '&product_id=' . $post->ID ),
			self::ROW_ACTION . '_' . $post->ID
		);

		$actions[ self::ROW_ACTION ] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Publicar', 'melomaniac-sync' )
		);

		return $actions;
	}

	/**
	 * Publishes one product.
	 *
	 * @return void
	 */
	public function handle_row_publish() {
		$product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( $_GET['product_id'] ) ) : 0;

		if ( ! $product_id ) {
			wp_die( esc_html__( 'Falta el producto a publicar.', 'melomaniac-sync' ) );
		}

		check_admin_referer( self::ROW_ACTION . '_' . $product_id );

		if ( 'product' !== get_post_type( $product_id ) ) {
			wp_die( esc_html__( 'Ese contenido no es un producto.', 'melomaniac-sync' ) );
		}

		if ( ! current_user_can( 'publish_post', $product_id ) ) {
			wp_die( esc_html__( 'No tenés permisos para publicar este producto.', 'melomaniac-sync' ) );
		}

		wp_update_post(
			array(
				'ID'          => $product_id,
				'post_status' => 'publish',
			)
		);

		$back = wp_get_referer();

		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=product' );
		}

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, 1, $back ) );
		exit;
	}

	/**
	 * Adds the bulk action.
	 *
	 * @param array<string,string> $actions Existing bulk actions.
	 * @return array<string,string>
	 */
	public function add_bulk_action( $actions ) {
		$actions[ self::BULK_ACTION ] = __( 'Publicar', 'melomaniac-sync' );

		return $actions;
	}

	/**
	 * Publishes every selected product.
	 *
	 * @param string $redirect_to Redirect target.
	 * @param string $action      Chosen bulk action.
	 * @param int[]  $post_ids    Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_publish( $redirect_to, $action, $post_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $redirect_to;
		}

		$count = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( ! current_user_can( 'publish_post', $post_id ) ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);

			++$count;
		}

		return add_query_arg( self::RESULT_ARG, $count, $redirect_to );
	}

	/**
	 * Reports how many products were published.
	 *
	 * @return void
	 */
	public function maybe_render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		if ( ! isset( $_GET[ self::RESULT_ARG ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$count = absint( wp_unslash( $_GET[ self::RESULT_ARG ] ) );

		if ( $count < 1 ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of products published. */
					_n( '%d producto publicado.', '%d productos publicados.', $count, 'melomaniac-sync' ),
					$count
				)
			)
		);
	}
}
