<?php
/**
 * MusicBrainz contribution queue: discs loaded by hand, still pending.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type array[] $items {
 *         @type int                           $product_id Product ID.
 *         @type string                        $title      Product title.
 *         @type string                        $edit_url   Edit link.
 *         @type Melomaniac_Sync_Release_DTO   $release    Its saved data.
 *     }
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_items = isset( $data['items'] ) ? $data['items'] : array();
?>
<div class="wrap melomaniac-sync">
	<h1><?php esc_html_e( 'Aportar a MusicBrainz', 'melomaniac-sync' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Discos que cargaste a mano porque MusicBrainz no los tenía, y todavía no marcaste como aportados. MusicBrainz no ofrece una carga masiva propia: cada uno se abre y se envía por separado, con tu cuenta. Esta pantalla solo te ahorra ir a buscar cada producto: al tocar "Aportar" se abre el formulario de MusicBrainz ya lleno, en una pestaña nueva, y el disco se quita de esta lista.', 'melomaniac-sync' ); ?>
		<?php
		printf(
			/* translators: %s: enlace para crear una cuenta en MusicBrainz. */
			esc_html__( '¿No tienes cuenta en MusicBrainz? %s.', 'melomaniac-sync' ),
			'<a href="https://musicbrainz.org/register" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Créala aquí', 'melomaniac-sync' ) . '</a>'
		);
		?>
	</p>

	<?php if ( empty( $melomaniac_items ) ) : ?>
		<p><?php esc_html_e( 'No hay discos pendientes de aportar.', 'melomaniac-sync' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Disco', 'melomaniac-sync' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody data-melomaniac-mb-queue-list>
				<?php foreach ( $melomaniac_items as $melomaniac_item ) : ?>
					<?php
					$melomaniac_release = $melomaniac_item['release'];
					$melomaniac_date    = explode( '-', $melomaniac_release->release_date );
					$melomaniac_month   = isset( $melomaniac_date[1] ) ? $melomaniac_date[1] : '';
					$melomaniac_day     = isset( $melomaniac_date[2] ) ? $melomaniac_date[2] : '';
					?>
					<tr data-melomaniac-mb-queue-item data-product-id="<?php echo esc_attr( (string) $melomaniac_item['product_id'] ); ?>">
						<td>
							<a href="<?php echo esc_url( $melomaniac_item['edit_url'] ); ?>"><?php echo esc_html( $melomaniac_item['title'] ); ?></a>
						</td>
						<td>
							<form>
								<input type="hidden" name="artist" value="<?php echo esc_attr( $melomaniac_release->artist ); ?>" />
								<input type="hidden" name="title" value="<?php echo esc_attr( $melomaniac_release->title ); ?>" />
								<input type="hidden" name="barcode" value="<?php echo esc_attr( $melomaniac_release->barcode ); ?>" />
								<input type="hidden" name="label" value="<?php echo esc_attr( $melomaniac_release->label ); ?>" />
								<input type="hidden" name="catalog_number" value="<?php echo esc_attr( $melomaniac_release->catalog_number ); ?>" />
								<input type="hidden" name="country" value="<?php echo esc_attr( $melomaniac_release->country ); ?>" />
								<input type="hidden" name="year" value="<?php echo esc_attr( $melomaniac_release->year ); ?>" />
								<input type="hidden" name="month" value="<?php echo esc_attr( $melomaniac_month ); ?>" />
								<input type="hidden" name="day" value="<?php echo esc_attr( $melomaniac_day ); ?>" />
								<input type="hidden" name="format" value="<?php echo esc_attr( $melomaniac_release->format ); ?>" />
								<input type="hidden" name="status" value="<?php echo esc_attr( $melomaniac_release->status ); ?>" />
								<input type="hidden" name="language" value="<?php echo esc_attr( $melomaniac_release->language ); ?>" />
								<input type="hidden" name="script" value="<?php echo esc_attr( $melomaniac_release->script ); ?>" />
								<input type="hidden" name="packaging" value="<?php echo esc_attr( $melomaniac_release->packaging ); ?>" />
								<input type="hidden" name="tracklist" value="<?php echo esc_attr( $melomaniac_release->tracklist_as_text() ); ?>" />
								<button type="button" class="button" data-action="mb-queue-contribute">
									<?php esc_html_e( 'Aportar a MusicBrainz', 'melomaniac-sync' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
