<?php
/**
 * REST API for the companion app.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the melomaniac-sync/v1 namespace the app talks to.
 *
 * Every route reuses the same services the admin scan screen uses
 * (Melomaniac_Sync_Release_Lookup_Service, Melomaniac_Sync_Product_Factory,
 * Melomaniac_Sync_Manual_Release_Builder, Melomaniac_Sync_Catalog): the app
 * is another door into the same logic, not a second implementation of it.
 */
class Melomaniac_Sync_Rest_Api {

	/**
	 * Namespace every route is registered under.
	 */
	const NAMESPACE_ = 'melomaniac-sync/v1';

	/**
	 * Release lookup service.
	 *
	 * @var Melomaniac_Sync_Release_Lookup_Service
	 */
	private $lookup_service;

	/**
	 * Product factory.
	 *
	 * @var Melomaniac_Sync_Product_Factory
	 */
	private $product_factory;

	/**
	 * Manual release builder.
	 *
	 * @var Melomaniac_Sync_Manual_Release_Builder
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @param Melomaniac_Sync_Release_Lookup_Service $lookup_service  Lookup service.
	 * @param Melomaniac_Sync_Product_Factory        $product_factory Product factory.
	 * @param Melomaniac_Sync_Manual_Release_Builder $builder         Manual release builder.
	 */
	public function __construct(
		Melomaniac_Sync_Release_Lookup_Service $lookup_service,
		Melomaniac_Sync_Product_Factory $product_factory,
		Melomaniac_Sync_Manual_Release_Builder $builder
	) {
		$this->lookup_service  = $lookup_service;
		$this->product_factory = $product_factory;
		$this->builder         = $builder;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/site-info',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'get_site_info' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/lookup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_use' ),
				'callback'            => array( $this, 'post_lookup' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/release',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'can_use' ),
				'callback'            => array( $this, 'get_release' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_use' ),
					'callback'            => array( $this, 'get_categories' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_use' ),
					'callback'            => array( $this, 'post_category' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/tags',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_use' ),
				'callback'            => array( $this, 'post_tag' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'can_use' ),
				'callback'            => array( $this, 'post_media' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( $this, 'can_use' ),
					'callback'            => array( $this, 'get_products' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'can_use' ),
					'callback'            => array( $this, 'post_product' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/products/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'permission_callback' => array( $this, 'can_edit_product' ),
					'callback'            => array( $this, 'put_product' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'permission_callback' => array( $this, 'can_edit_product' ),
					'callback'            => array( $this, 'delete_product' ),
				),
			)
		);
	}

	/**
	 * Whether the current request may use the app's endpoints at all.
	 *
	 * @return true|WP_Error
	 */
	public function can_use() {
		if ( ! get_current_user_id() ) {
			return new WP_Error(
				'melomaniac_sync_rest_unauthenticated',
				__( 'No se detectó la sesión de la app. Vuelve a conectarla desde Ajustes.', 'melomaniac-sync' ),
				array( 'status' => 401 )
			);
		}

		if ( current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			return true;
		}

		return new WP_Error(
			'melomaniac_sync_rest_forbidden',
			__( 'Tu usuario no tiene permiso para gestionar productos.', 'melomaniac-sync' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Same as can_use(), plus ownership of the specific product in the URL.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return true|WP_Error
	 */
	public function can_edit_product( WP_REST_Request $request ) {
		$allowed = $this->can_use();

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$product_id = absint( $request->get_param( 'id' ) );

		if ( 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error(
				'melomaniac_sync_rest_not_found',
				__( 'Ese producto no existe.', 'melomaniac-sync' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return new WP_Error(
				'melomaniac_sync_rest_forbidden',
				__( 'No tienes permiso para editar este producto.', 'melomaniac-sync' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Store name and logo, for the app's connection screen.
	 *
	 * Public and minimal on purpose: this is what the app shows *before* the
	 * shop owner has connected an account, so it cannot leak anything beyond
	 * what an anonymous visitor already sees on the storefront.
	 *
	 * @return array
	 */
	public function get_site_info() {
		return array(
			'name' => get_bloginfo( 'name' ),
			'logo' => self::site_logo_url(),
		);
	}

	/**
	 * The store's logo, if it set one, else its site icon, else nothing.
	 *
	 * Shared with the app shell (Melomaniac_Sync_Pwa), so both the connection
	 * screen this endpoint serves and the app's own header show the same logo.
	 *
	 * @return string Empty when the store has neither.
	 */
	public static function site_logo_url() {
		$logo_id = get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'medium' );

			if ( $src ) {
				return $src[0];
			}
		}

		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			return get_site_icon_url( 270 );
		}

		return '';
	}

	/**
	 * Looks a barcode up.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_lookup( WP_REST_Request $request ) {
		$result = $this->lookup_service->find_by_barcode( (string) $request->get_param( 'barcode' ) );

		if ( is_wp_error( $result ) ) {
			return $this->lookup_error_response( $result );
		}

		$candidates = array();

		foreach ( $result['candidates'] as $release ) {
			$candidates[] = $this->release_to_array( $release );
		}

		return new WP_REST_Response(
			array(
				'barcode'    => $result['barcode'],
				'resolved'   => (bool) $result['resolved'],
				'source'     => $result['source'],
				'candidates' => $candidates,
			)
		);
	}

	/**
	 * Fetches one full release.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_release( WP_REST_Request $request ) {
		$release = $this->lookup_service->get_release(
			(string) $request->get_param( 'source' ),
			(string) $request->get_param( 'id' )
		);

		if ( is_wp_error( $release ) ) {
			return $this->lookup_error_response( $release );
		}

		return new WP_REST_Response( $this->release_to_array( $release ) );
	}

	/**
	 * Lists product categories.
	 *
	 * @return WP_REST_Response
	 */
	public function get_categories() {
		$out = array();

		foreach ( Melomaniac_Sync_Catalog::categories() as $id => $name ) {
			$out[] = array(
				'id'   => $id,
				'name' => $name,
			);
		}

		return new WP_REST_Response( $out );
	}

	/**
	 * Resolves or creates a category by name.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_category( WP_REST_Request $request ) {
		return $this->resolve_term_route( $request, 'product_cat' );
	}

	/**
	 * Resolves or creates a tag by name.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_tag( WP_REST_Request $request ) {
		return $this->resolve_term_route( $request, 'product_tag' );
	}

	/**
	 * Shared implementation for the category and tag creation routes.
	 *
	 * @param WP_REST_Request $request  Current request.
	 * @param string           $taxonomy Taxonomy to resolve against.
	 * @return WP_REST_Response|WP_Error
	 */
	private function resolve_term_route( WP_REST_Request $request, $taxonomy ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );

		if ( '' === $name ) {
			return new WP_Error(
				'melomaniac_sync_rest_bad_request',
				__( 'Falta el nombre.', 'melomaniac-sync' ),
				array( 'status' => 400 )
			);
		}

		$term_id = Melomaniac_Sync_Terms::resolve( $name, $taxonomy );

		if ( ! $term_id ) {
			return new WP_Error(
				'melomaniac_sync_rest_term_failed',
				__( 'No se pudo crear la categoría o etiqueta.', 'melomaniac-sync' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'id'   => $term_id,
				'name' => $name,
			)
		);
	}

	/**
	 * Uploads a photo taken from the app.
	 *
	 * The image bytes are the raw request body, same as the reference plugin:
	 * a phone camera upload has no reason to go through multipart form fields.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_media( WP_REST_Request $request ) {
		$filename     = 'foto-' . time() . '.jpg';
		$disposition  = $request->get_header( 'content_disposition' );

		if ( $disposition && preg_match( '/filename="?([^"]+)"?/', $disposition, $matches ) ) {
			$filename = sanitize_file_name( $matches[1] );
		}

		$bits = wp_upload_bits( $filename, null, $request->get_body() );

		if ( ! empty( $bits['error'] ) ) {
			return new WP_Error( 'melomaniac_sync_rest_upload_failed', $bits['error'], array( 'status' => 500 ) );
		}

		$attachment = array(
			'post_mime_type' => $request->get_header( 'content_type' ) ? $request->get_header( 'content_type' ) : 'image/jpeg',
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $bits['file'] );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error(
				'melomaniac_sync_rest_attachment_failed',
				__( 'No se pudo crear el adjunto.', 'melomaniac-sync' ),
				array( 'status' => 500 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( $attachment_id, $bits['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return new WP_REST_Response(
			array(
				'id'         => $attachment_id,
				'source_url' => $bits['url'],
			)
		);
	}

	/**
	 * Lists the most recently created products, for the app's history screen.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_products() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'melomaniac_sync_rest_no_woo', __( 'WooCommerce no está activo.', 'melomaniac-sync' ), array( 'status' => 500 ) );
		}

		$ids = wc_get_products(
			array(
				'limit'   => 50,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);

		$out = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			$image_id = $product->get_image_id();

			$out[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'status'         => $product->get_status(),
				'regular_price'  => $product->get_regular_price(),
				'stock_quantity' => $product->get_stock_quantity(),
				'featured_image' => $image_id ? wp_get_attachment_url( $image_id ) : '',
				'edit_url'       => get_edit_post_link( $product->get_id(), 'raw' ),
			);
		}

		return new WP_REST_Response( $out );
	}

	/**
	 * Creates a draft product, either from a resolved release or from
	 * manually typed fields.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function post_product( WP_REST_Request $request ) {
		$params = $request->get_params();

		if ( ! empty( $params['manual'] ) ) {
			$release = $this->builder->build_from_array( $params );
		} else {
			$source     = (string) ( $params['source'] ?? '' );
			$release_id = (string) ( $params['release_id'] ?? '' );

			// Re-fetched from the service rather than trusting whatever the app
			// sent for the release fields, same as the admin AJAX flow.
			$release = $this->lookup_service->get_release( $source, $release_id );

			if ( is_wp_error( $release ) ) {
				return $this->lookup_error_response( $release );
			}
		}

		if ( ! empty( $params['barcode'] ) ) {
			$release->barcode = preg_replace( '/\D/', '', (string) $params['barcode'] );
		}

		// The manual path already reads this via the builder; a resolved
		// release is fetched fresh from the lookup service above, so a photo
		// taken in the app for it (e.g. no cover came back from MusicBrainz)
		// would otherwise never reach the product.
		if ( ! empty( $params['cover_attachment_id'] ) ) {
			$attachment_id = absint( $params['cover_attachment_id'] );

			if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
				$release->cover_attachment_id = $attachment_id;
			}
		}

		$product_id = $this->product_factory->create_draft(
			$release,
			Melomaniac_Sync_Catalog::read_overrides( $params )
		);

		if ( is_wp_error( $product_id ) ) {
			return $this->product_error_response( $product_id );
		}

		return new WP_REST_Response(
			array(
				'id'       => $product_id,
				'name'     => get_the_title( $product_id ),
				'edit_url' => get_edit_post_link( $product_id, 'raw' ),
				'quota'    => Melomaniac_Sync_Usage::summary(),
			),
			201
		);
	}

	/**
	 * Quick edits from the app: name, price, stock.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function put_product( WP_REST_Request $request ) {
		$product = wc_get_product( absint( $request->get_param( 'id' ) ) );

		if ( ! $product ) {
			return new WP_Error( 'melomaniac_sync_rest_not_found', __( 'Producto no encontrado.', 'melomaniac-sync' ), array( 'status' => 404 ) );
		}

		if ( null !== $request->get_param( 'name' ) ) {
			$product->set_name( sanitize_text_field( (string) $request->get_param( 'name' ) ) );
		}

		if ( null !== $request->get_param( 'regular_price' ) ) {
			$product->set_regular_price( (string) $request->get_param( 'regular_price' ) );
		}

		if ( null !== $request->get_param( 'stock_quantity' ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( absint( $request->get_param( 'stock_quantity' ) ) );
		}

		try {
			$product->save();
		} catch ( WC_Data_Exception $exception ) {
			return new WP_Error( 'melomaniac_sync_rest_save_failed', $exception->getMessage(), array( 'status' => 400 ) );
		}

		return new WP_REST_Response(
			array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
			)
		);
	}

	/**
	 * Trashes a product. Moves it to the trash rather than deleting outright,
	 * so a mis-tap from the app is still recoverable from wp-admin.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_product( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! wp_trash_post( $product_id ) ) {
			return new WP_Error( 'melomaniac_sync_rest_delete_failed', __( 'No se pudo eliminar el producto.', 'melomaniac-sync' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'trashed' => true ) );
	}

	/**
	 * Turns a release object into a JSON-friendly array.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release.
	 * @return array
	 */
	private function release_to_array( Melomaniac_Sync_Release_DTO $release ) {
		return array_merge(
			$release->to_array(),
			array(
				'display_name' => $release->display_name(),
				'format_label' => $release->format_label(),
			)
		);
	}

	/**
	 * Turns a WP_Error into a REST response, picking a sensible status when
	 * the error itself did not already carry one.
	 *
	 * @param WP_Error $error   Error to convert.
	 * @param int      $default Fallback HTTP status.
	 * @return WP_Error
	 */
	private function error_response( WP_Error $error, $default = 400 ) {
		$data = $error->get_error_data();

		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			$data           = is_array( $data ) ? $data : array();
			$data['status'] = $default;

			$error->add_data( $data );
		}

		return $error;
	}

	/**
	 * Maps a product creation error to the right HTTP status, keeping
	 * whatever extra data (upgrade_url, product_id) the factory attached.
	 *
	 * @param WP_Error $error Error from Melomaniac_Sync_Product_Factory::create_draft().
	 * @return WP_Error
	 */
	private function product_error_response( WP_Error $error ) {
		$statuses = array(
			'melomaniac_sync_quota_exceeded'     => 402,
			'melomaniac_sync_duplicate'          => 409,
			'melomaniac_sync_incomplete_release' => 422,
			'melomaniac_sync_save_failed'        => 500,
		);

		$status = isset( $statuses[ $error->get_error_code() ] ) ? $statuses[ $error->get_error_code() ] : 400;

		return $this->error_response( $error, $status );
	}

	/**
	 * Maps a lookup-service error to the right HTTP status.
	 *
	 * @param WP_Error $error Error from Melomaniac_Sync_Release_Lookup_Service.
	 * @return WP_Error
	 */
	private function lookup_error_response( WP_Error $error ) {
		$statuses = array(
			'melomaniac_sync_not_found'           => 404,
			'melomaniac_sync_discogs_rate_limited' => 429,
			'melomaniac_sync_invalid_barcode'     => 400,
			'melomaniac_sync_invalid_mbid'        => 400,
			'melomaniac_sync_invalid_discogs_id'  => 400,
			'melomaniac_sync_unknown_source'      => 400,
			'melomaniac_sync_stale_assets'        => 400,
		);

		$status = isset( $statuses[ $error->get_error_code() ] ) ? $statuses[ $error->get_error_code() ] : 502;

		return $this->error_response( $error, $status );
	}
}
