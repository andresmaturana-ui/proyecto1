<?php
/**
 * Manual entry form handler.
 *
 * @package Melomaniac_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates the manual entry form and turns it into a release object.
 *
 * The form is the fallback when MusicBrainz has no match, and phase 4 reuses it
 * as the contribution form, so the parsing lives here rather than in the view.
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

		$release    = $this->build_release_from_post();
		$product_id = $this->product_factory->create_draft(
			$release,
			Melomaniac_Sync_Admin::read_product_overrides()
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
	 * Builds a release object out of the submitted fields.
	 *
	 * Public so the phase 4 contribution flow can reuse the exact same parsing.
	 *
	 * @return Melomaniac_Sync_Release_DTO
	 */
	public function build_release_from_post() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$release = new Melomaniac_Sync_Release_DTO();

		$release->barcode        = isset( $_POST['barcode'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['barcode'] ) ) : '';
		$release->artist         = $this->read_text( 'artist' );
		$release->title          = $this->read_text( 'title' );
		$release->label          = $this->read_text( 'label' );
		$release->catalog_number = $this->read_text( 'catalog_number' );
		$release->country        = $this->read_text( 'country' );
		$release->format_detail  = $this->read_text( 'format_detail' );

		$this->read_release_date( $release );
		$this->read_musicbrainz_fields( $release );

		$format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : '';
		$formats = Melomaniac_Sync_Release_DTO::formats();

		$release->format = isset( $formats[ $format ] ) ? $format : 'other';

		if ( '' === $release->format_detail ) {
			$release->format_detail = $release->format_label();
		}

		$release->tracklist = $this->parse_tracklist(
			isset( $_POST['tracklist'] ) ? wp_unslash( $_POST['tracklist'] ) : ''
		);

		$attachment_id = isset( $_POST['cover_attachment_id'] ) ? absint( wp_unslash( $_POST['cover_attachment_id'] ) ) : 0;

		if ( $attachment_id > 0 && 'attachment' === get_post_type( $attachment_id ) ) {
			$release->cover_attachment_id = $attachment_id;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $release;
	}

	/**
	 * Builds the release date out of the year, month and day fields.
	 *
	 * MusicBrainz accepts a partial date, so a year on its own is valid and a
	 * month without a day is too; the parts are only joined while they are
	 * present and in order.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release being built.
	 * @return void
	 */
	private function read_release_date( Melomaniac_Sync_Release_DTO $release ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$year  = isset( $_POST['year'] ) ? absint( wp_unslash( $_POST['year'] ) ) : 0;
		$month = isset( $_POST['month'] ) ? absint( wp_unslash( $_POST['month'] ) ) : 0;
		$day   = isset( $_POST['day'] ) ? absint( wp_unslash( $_POST['day'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Reject nonsense years instead of storing them.
		if ( $year < 1880 || $year > (int) gmdate( 'Y' ) + 1 ) {
			return;
		}

		$release->year         = (string) $year;
		$release->release_date = (string) $year;

		if ( $month < 1 || $month > 12 ) {
			return;
		}

		$release->release_date .= sprintf( '-%02d', $month );

		if ( $day < 1 || $day > 31 ) {
			return;
		}

		$release->release_date .= sprintf( '-%02d', $day );
	}

	/**
	 * Reads the fields a MusicBrainz contribution needs.
	 *
	 * Each one is checked against its own vocabulary, so only values MusicBrainz
	 * actually accepts ever get stored.
	 *
	 * @param Melomaniac_Sync_Release_DTO $release Release being built.
	 * @return void
	 */
	private function read_musicbrainz_fields( Melomaniac_Sync_Release_DTO $release ) {
		$vocabularies = array(
			'status'         => Melomaniac_Sync_Release_DTO::statuses(),
			'release_type'   => Melomaniac_Sync_Release_DTO::release_types(),
			'secondary_type' => Melomaniac_Sync_Release_DTO::secondary_types(),
			'packaging'      => Melomaniac_Sync_Release_DTO::packagings(),
			'language'       => Melomaniac_Sync_Release_DTO::languages(),
			'script'         => Melomaniac_Sync_Release_DTO::scripts(),
		);

		foreach ( $vocabularies as $field => $allowed ) {
			$value = $this->read_text( $field );

			if ( array_key_exists( $value, $allowed ) ) {
				$release->{$field} = $value;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		$count = isset( $_POST['medium_count'] ) ? absint( wp_unslash( $_POST['medium_count'] ) ) : 1;

		$release->medium_count = max( 1, min( 50, $count ) );
	}

	/**
	 * Reads and sanitises a text field.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function read_text( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by the caller.
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Parses the track list textarea into structured tracks.
	 *
	 * Accepted per line: "Title", "1. Title", "A1 Title", any of those followed
	 * by "| 3:45" for the duration.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array[]
	 */
	public function parse_tracklist( $raw ) {
		$lines     = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$tracklist = array();
		$position  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			++$position;

			$length = '';
			$parts  = explode( '|', $line );

			if ( count( $parts ) > 1 ) {
				$candidate = trim( array_pop( $parts ) );

				if ( preg_match( '/^\d{1,3}:[0-5]\d$/', $candidate ) ) {
					$length = $candidate;
					$line   = trim( implode( '|', $parts ) );
				}
			}

			$number = (string) $position;

			foreach ( $this->track_number_patterns() as $pattern ) {
				if ( preg_match( $pattern, $line, $matches ) ) {
					$number = sanitize_text_field( $matches[1] );
					$line   = trim( $matches[2] );
					break;
				}
			}

			if ( '' === $line ) {
				continue;
			}

			$tracklist[] = array(
				'medium' => 1,
				'number' => $number,
				'title'  => sanitize_text_field( $line ),
				'length' => $length,
			);
		}

		return $tracklist;
	}

	/**
	 * Patterns that recognise a leading track number.
	 *
	 * Deliberately conservative: a bare number followed by a space is NOT
	 * treated as a track number, because it would turn "12 Monkeys" into track
	 * 12 titled "Monkeys". A number needs an explicit dot or bracket, unless it
	 * is a vinyl style side prefix such as "A1", which is unambiguous.
	 *
	 * @return string[]
	 */
	private function track_number_patterns() {
		return array(
			// A1 Title, B2. Title, C10) Title.
			'/^([A-Za-z]{1,2}\d{1,3})[.)]?\s+(.+)$/',
			// 1. Title, 12) Title, A. Title, B) Title.
			'/^(\d{1,3}|[A-Za-z]{1,2})[.)]\s*(.+)$/',
		);
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
