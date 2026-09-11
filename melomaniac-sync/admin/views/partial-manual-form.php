<?php
/**
 * Manual entry form, used when MusicBrainz has no match.
 *
 * Phase 4 reuses this same form as the contribution form.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string                           $barcode Normalised barcode.
 *     @type Melomaniac_Sync_Release_DTO|null $release Prefill, when available.
 *     @type array<string,string>             $formats Format keys and labels.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_barcode = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';
$melomaniac_formats = isset( $data['formats'] ) && is_array( $data['formats'] )
	? $data['formats']
	: Melomaniac_Sync_Release_DTO::formats();

$melomaniac_prefill = isset( $data['release'] ) && $data['release'] instanceof Melomaniac_Sync_Release_DTO
	? $data['release']
	: new Melomaniac_Sync_Release_DTO();
?>
<div class="melomaniac-card melomaniac-manual">
	<h2><?php esc_html_e( 'Cargar el disco a mano', 'melomaniac-sync' ); ?></h2>

	<?php if ( '' !== $melomaniac_barcode ) : ?>
		<p class="melomaniac-manual-intro">
			<?php
			printf(
				/* translators: %s: barcode. */
				esc_html__( 'MusicBrainz no tiene ningún disco con el código %s. Completa los datos y creamos el borrador igual.', 'melomaniac-sync' ),
				'<code>' . esc_html( $melomaniac_barcode ) . '</code>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php
	Melomaniac_Sync_Admin::render_view(
		'partial-reference-links',
		array(
			'barcode' => $melomaniac_barcode,
			'extra'   => trim( $melomaniac_prefill->artist . ' ' . $melomaniac_prefill->title ),
		)
	);
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="melomaniac-manual-form">
		<?php wp_nonce_field( Melomaniac_Sync_Admin::NONCE_ACTION ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Melomaniac_Sync_Manual_Entry_Handler::ACTION ); ?>" />
		<input type="hidden" name="barcode" value="<?php echo esc_attr( $melomaniac_barcode ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-artist"><?php esc_html_e( 'Artista', 'melomaniac-sync' ); ?> <span class="melomaniac-required">*</span></label>
				</th>
				<td>
					<input type="text" id="melomaniac-manual-artist" name="artist" class="regular-text" value="<?php echo esc_attr( $melomaniac_prefill->artist ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-title"><?php esc_html_e( 'Título del álbum', 'melomaniac-sync' ); ?> <span class="melomaniac-required">*</span></label>
				</th>
				<td>
					<input type="text" id="melomaniac-manual-title" name="title" class="regular-text" value="<?php echo esc_attr( $melomaniac_prefill->title ); ?>" required />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-label"><?php esc_html_e( 'Sello', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input type="text" id="melomaniac-manual-label" name="label" class="regular-text" value="<?php echo esc_attr( $melomaniac_prefill->label ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-year"><?php esc_html_e( 'Año', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="melomaniac-manual-year"
						name="year"
						class="small-text"
						min="1880"
						max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ); ?>"
						value="<?php echo esc_attr( $melomaniac_prefill->year ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-format"><?php esc_html_e( 'Formato', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<select id="melomaniac-manual-format" name="format">
						<?php foreach ( $melomaniac_formats as $melomaniac_key => $melomaniac_label ) : ?>
							<option value="<?php echo esc_attr( $melomaniac_key ); ?>" <?php selected( $melomaniac_prefill->format, $melomaniac_key ); ?>>
								<?php echo esc_html( $melomaniac_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input
						type="text"
						id="melomaniac-manual-format-detail"
						name="format_detail"
						class="regular-text"
						value="<?php echo esc_attr( $melomaniac_prefill->format_detail ); ?>"
						placeholder="<?php esc_attr_e( 'Detalle: LP 12", doble, 180g, digipak…', 'melomaniac-sync' ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'El detalle es libre y se guarda tal cual en la ficha del producto.', 'melomaniac-sync' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-country"><?php esc_html_e( 'País de prensaje', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input type="text" id="melomaniac-manual-country" name="country" class="regular-text" value="<?php echo esc_attr( $melomaniac_prefill->country ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-catalog"><?php esc_html_e( 'Número de catálogo', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input type="text" id="melomaniac-manual-catalog" name="catalog_number" class="regular-text" value="<?php echo esc_attr( $melomaniac_prefill->catalog_number ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-tracklist"><?php esc_html_e( 'Lista de canciones', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<textarea id="melomaniac-manual-tracklist" name="tracklist" rows="10" class="large-text code"><?php
						echo esc_textarea( $melomaniac_prefill->tracklist_as_text() );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Una canción por línea. Puedes agregar la duración después de una barra vertical, por ejemplo: A1 Blue Monday | 7:29', 'melomaniac-sync' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Portada', 'melomaniac-sync' ); ?></th>
				<td>
					<div class="melomaniac-cover-field">
						<input type="hidden" id="melomaniac-manual-cover" name="cover_attachment_id" value="<?php echo esc_attr( (string) $melomaniac_prefill->cover_attachment_id ); ?>" />
						<div id="melomaniac-cover-preview" class="melomaniac-cover-preview">
							<?php
							if ( $melomaniac_prefill->cover_attachment_id > 0 ) {
								echo wp_get_attachment_image( $melomaniac_prefill->cover_attachment_id, 'medium' );
							}
							?>
						</div>
						<button type="button" class="button" id="melomaniac-cover-select">
							<?php esc_html_e( 'Subir o elegir una imagen', 'melomaniac-sync' ); ?>
						</button>
						<button type="button" class="button-link" id="melomaniac-cover-remove" <?php echo esc_attr( $melomaniac_prefill->cover_attachment_id > 0 ? '' : 'hidden' ); ?>>
							<?php esc_html_e( 'Quitar', 'melomaniac-sync' ); ?>
						</button>
					</div>
				</td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Crear producto como borrador', 'melomaniac-sync' ) ); ?>
	</form>
</div>
