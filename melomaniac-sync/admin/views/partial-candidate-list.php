<?php
/**
 * Candidate list shown when one barcode matches several releases.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type Melomaniac_Sync_Release_DTO[] $candidates Matching releases.
 *     @type string                        $barcode    Normalised barcode.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_candidates = isset( $data['candidates'] ) && is_array( $data['candidates'] ) ? $data['candidates'] : array();
$melomaniac_barcode    = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';

if ( empty( $melomaniac_candidates ) ) {
	return;
}
?>
<div class="melomaniac-card melomaniac-candidates">
	<h2>
		<?php
		printf(
			/* translators: %d: number of matching releases. */
			esc_html( _n( 'Hay %d edición con este código', 'Hay %d ediciones con este código', count( $melomaniac_candidates ), 'melomaniac-sync' ) ),
			count( $melomaniac_candidates )
		);
		?>
	</h2>
	<p class="description">
		<?php esc_html_e( 'Elegí la que tenés en la mano para ver su ficha completa antes de crear el producto.', 'melomaniac-sync' ); ?>
	</p>

	<ul class="melomaniac-candidate-list">
		<?php foreach ( $melomaniac_candidates as $melomaniac_candidate ) : ?>
			<?php if ( ! $melomaniac_candidate instanceof Melomaniac_Sync_Release_DTO ) { continue; } ?>
			<li class="melomaniac-candidate">
				<div class="melomaniac-candidate-main">
					<strong><?php echo esc_html( $melomaniac_candidate->title ); ?></strong>
					<span class="melomaniac-candidate-artist"><?php echo esc_html( $melomaniac_candidate->artist ); ?></span>
					<span class="melomaniac-candidate-meta">
						<?php
						$melomaniac_bits = array_filter(
							array(
								$melomaniac_candidate->year,
								$melomaniac_candidate->label,
								'' !== $melomaniac_candidate->format_detail ? $melomaniac_candidate->format_detail : '',
								$melomaniac_candidate->country,
							),
							static function ( $value ) {
								return '' !== trim( (string) $value );
							}
						);
						echo esc_html( implode( ' · ', $melomaniac_bits ) );
						?>
					</span>
				</div>
				<button
					type="button"
					class="button melomaniac-select-candidate"
					data-source="<?php echo esc_attr( $melomaniac_candidate->source ); ?>"
					data-release-id="<?php echo esc_attr( $melomaniac_candidate->source_id() ); ?>"
					data-barcode="<?php echo esc_attr( $melomaniac_barcode ); ?>"
				>
					<?php esc_html_e( 'Usar esta edición', 'melomaniac-sync' ); ?>
				</button>
			</li>
		<?php endforeach; ?>
	</ul>

	<p>
		<button type="button" class="button-link melomaniac-reject-match">
			<?php esc_html_e( 'Ninguna es, cargarlo a mano', 'melomaniac-sync' ); ?>
		</button>
	</p>
</div>
