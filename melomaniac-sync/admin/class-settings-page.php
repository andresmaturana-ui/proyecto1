<?php
/**
 * Settings screen controller.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the settings screen: validation, saving and rendering.
 */
class Melomaniac_Sync_Settings_Page {

	/**
	 * Nonce action for the settings form.
	 */
	const NONCE_ACTION = 'melomaniac_sync_save_settings';

	/**
	 * Transient prefix for the one-off confirmation message.
	 */
	const FLASH_KEY = 'melomaniac_sync_settings_flash_';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	/**
	 * Saves the form when it was submitted.
	 *
	 * @return void
	 */
	public function maybe_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked right below.
		if ( ! isset( $_POST['melomaniac_sync_settings_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenés permisos para cambiar estos ajustes.', 'melomaniac-sync' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$this->save_general();
		$this->save_description_fields();
		$this->save_category_map();

		$this->set_flash( __( 'Ajustes guardados.', 'melomaniac-sync' ) );

		wp_safe_redirect( Melomaniac_Sync_Admin_Menu::settings_url() );
		exit;
	}

	/**
	 * Saves the general settings block.
	 *
	 * @return void
	 */
	private function save_general() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$values = array();

		if ( isset( $_POST['api_contact'] ) ) {
			$contact = sanitize_text_field( wp_unslash( $_POST['api_contact'] ) );

			// MusicBrainz wants a real email or URL; anything else is useless to them.
			if ( '' === $contact || is_email( $contact ) || wp_http_validate_url( $contact ) ) {
				$values['api_contact'] = $contact;
			}
		}

		if ( isset( $_POST['discogs_token'] ) ) {
			$values['discogs_token'] = sanitize_text_field( wp_unslash( $_POST['discogs_token'] ) );
		}

		if ( isset( $_POST['product_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['product_status'] ) );

			if ( in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ) {
				$values['product_status'] = $status;
			}
		}

		if ( isset( $_POST['default_price'] ) ) {
			$price = wc_format_decimal( wp_unslash( $_POST['default_price'] ) );

			$values['default_price'] = '' === $price ? '0' : $price;
		}

		if ( isset( $_POST['default_stock'] ) ) {
			$values['default_stock'] = absint( wp_unslash( $_POST['default_stock'] ) );
		}

		if ( isset( $_POST['http_timeout'] ) ) {
			$timeout = absint( wp_unslash( $_POST['http_timeout'] ) );

			if ( $timeout >= 5 && $timeout <= 60 ) {
				$values['http_timeout'] = $timeout;
			}
		}

		// Checkboxes are absent when unticked, so they are read unconditionally.
		$values['discogs_enabled'] = ! empty( $_POST['discogs_enabled'] );
		$values['import_cover']    = ! empty( $_POST['import_cover'] );
		$values['genre_tag']       = ! empty( $_POST['genre_tag'] );
		$values['logging_enabled'] = ! empty( $_POST['logging_enabled'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		Melomaniac_Sync_Settings::update( $values );
	}

	/**
	 * Saves the description field toggles.
	 *
	 * @return void
	 */
	private function save_description_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$raw = isset( $_POST['description_fields'] ) ? wp_unslash( $_POST['description_fields'] ) : array();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$fields = array();

		foreach ( array_keys( Melomaniac_Sync_Settings::description_fields() ) as $key ) {
			$fields[ $key ] = ! empty( $raw[ $key ] );
		}

		Melomaniac_Sync_Settings::update_description_fields( $fields );
	}

	/**
	 * Saves the format to category mapping.
	 *
	 * @return void
	 */
	private function save_category_map() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$raw = isset( $_POST['category_map'] ) ? wp_unslash( $_POST['category_map'] ) : array();

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$map = array();

		foreach ( array_keys( Melomaniac_Sync_Release_DTO::formats() ) as $format ) {
			$map[ $format ] = isset( $raw[ $format ] ) ? absint( $raw[ $format ] ) : 0;
		}

		Melomaniac_Sync_Settings::update_category_map( $map );
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Melomaniac_Sync_Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenés permisos para ver esta pantalla.', 'melomaniac-sync' ) );
		}

		Melomaniac_Sync_Admin::render_view(
			'page-settings',
			array(
				'settings'   => Melomaniac_Sync_Settings::all(),
				'fields'     => Melomaniac_Sync_Settings::description_fields(),
				'enabled'    => Melomaniac_Sync_Settings::enabled_description_fields(),
				'formats'    => Melomaniac_Sync_Release_DTO::formats(),
				'map'        => Melomaniac_Sync_Settings::category_map(),
				'categories' => $this->product_categories(),
				'flash'      => $this->take_flash(),
			)
		);
	}

	/**
	 * Product categories for the mapping selects.
	 *
	 * @return array<int,string>
	 */
	private function product_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[ (int) $term->term_id ] = $term->name;
		}

		return $categories;
	}

	/**
	 * Stores a one-off message for the current user.
	 *
	 * @param string $message Message text.
	 * @return void
	 */
	private function set_flash( $message ) {
		set_transient( self::FLASH_KEY . get_current_user_id(), $message, 60 );
	}

	/**
	 * Reads and clears the one-off message.
	 *
	 * @return string
	 */
	private function take_flash() {
		$key     = self::FLASH_KEY . get_current_user_id();
		$message = get_transient( $key );

		if ( false === $message ) {
			return '';
		}

		delete_transient( $key );

		return (string) $message;
	}
}
