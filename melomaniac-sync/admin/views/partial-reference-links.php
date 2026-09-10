<?php
/**
 * Manual reference links shown above the manual entry form.
 *
 * These are plain links for the shop owner to open in a new tab. The plugin
 * never requests any of these sites on its own. See
 * includes/helpers/functions-reference-links.php.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string $barcode Normalised barcode.
 *     @type string $extra   Optional extra search terms.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_barcode = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';
$melomaniac_extra   = isset( $data['extra'] ) ? (string) $data['extra'] : '';
$melomaniac_links   = melomaniac_sync_reference_links( $melomaniac_barcode, $melomaniac_extra );

if ( empty( $melomaniac_links ) ) {
	return;
}
?>
<div class="melomaniac-reference">
	<p class="melomaniac-reference-title">
		<?php esc_html_e( '¿Necesitas datos para completar este disco?', 'melomaniac-sync' ); ?>
	</p>
	<ul class="melomaniac-reference-links">
		<?php foreach ( $melomaniac_links as $melomaniac_link ) : ?>
			<?php if ( empty( $melomaniac_link['url'] ) || empty( $melomaniac_link['label'] ) ) { continue; } ?>
			<li>
				<a href="<?php echo esc_url( $melomaniac_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $melomaniac_link['label'] ); ?>
				</a>
				<?php if ( ! empty( $melomaniac_link['description'] ) ) : ?>
					<span class="melomaniac-reference-description"><?php echo esc_html( $melomaniac_link['description'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="melomaniac-reference-note description">
		<?php esc_html_e( 'Se abren en una pestaña nueva. Melomaniac Sync no copia nada de esos sitios: lo que quieras usar lo pegás vos en el formulario.', 'melomaniac-sync' ); ?>
	</p>
</div>
