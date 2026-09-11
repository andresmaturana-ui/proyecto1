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
	<h2>
		<?php
		echo esc_html(
			$melomaniac_prefill->is_usable()
				? __( 'Corregir los datos del disco', 'melomaniac-sync' )
				: __( 'Cargar el disco a mano', 'melomaniac-sync' )
		);
		?>
	</h2>

	<?php if ( $melomaniac_prefill->is_usable() ) : ?>
		<p class="melomaniac-manual-intro">
			<?php esc_html_e( 'Los datos que encontramos ya están cargados. Corrige lo que esté mal y crea el producto.', 'melomaniac-sync' ); ?>
		</p>
	<?php elseif ( '' !== $melomaniac_barcode ) : ?>
		<p class="melomaniac-manual-intro">
			<?php
			printf(
				/* translators: %s: barcode. */
				esc_html__( 'No encontramos ningún disco con el código %s. Completa los datos y creamos el borrador igual.', 'melomaniac-sync' ),
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
					<label for="melomaniac-manual-year"><?php esc_html_e( 'Fecha de lanzamiento', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<?php
					$melomaniac_date  = explode( '-', $melomaniac_prefill->release_date );
					$melomaniac_month = isset( $melomaniac_date[1] ) ? $melomaniac_date[1] : '';
					$melomaniac_day   = isset( $melomaniac_date[2] ) ? $melomaniac_date[2] : '';
					?>
					<input
						type="number"
						id="melomaniac-manual-year"
						name="year"
						class="small-text"
						min="1880"
						max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ); ?>"
						placeholder="<?php esc_attr_e( 'Año', 'melomaniac-sync' ); ?>"
						value="<?php echo esc_attr( $melomaniac_prefill->year ); ?>"
					/>
					<input
						type="number"
						id="melomaniac-manual-month"
						name="month"
						class="small-text"
						min="1"
						max="12"
						placeholder="<?php esc_attr_e( 'Mes', 'melomaniac-sync' ); ?>"
						value="<?php echo esc_attr( $melomaniac_month ); ?>"
					/>
					<input
						type="number"
						id="melomaniac-manual-day"
						name="day"
						class="small-text"
						min="1"
						max="31"
						placeholder="<?php esc_attr_e( 'Día', 'melomaniac-sync' ); ?>"
						value="<?php echo esc_attr( $melomaniac_day ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Con el año basta. El mes y el día son opcionales.', 'melomaniac-sync' ); ?>
					</p>
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
					<?php
					// At least five lines, more when the release brought more, capped
					// so a box set does not take over the screen.
					$melomaniac_rows = max( 5, min( 15, count( $melomaniac_prefill->tracklist ) + 1 ) );
					?>
					<textarea
						id="melomaniac-manual-tracklist"
						name="tracklist"
						rows="<?php echo esc_attr( (string) $melomaniac_rows ); ?>"
						class="large-text code"
					><?php
						echo esc_textarea( $melomaniac_prefill->tracklist_as_text() );
					?></textarea>
					<p class="description">
						<?php esc_html_e( 'Opcional. Una canción por línea, con la duración después de una barra vertical si la tienes: A1 Blue Monday | 7:29', 'melomaniac-sync' ); ?>
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

		<details class="melomaniac-optional">
			<summary><?php esc_html_e( 'Datos adicionales para aportar a MusicBrainz (opcional)', 'melomaniac-sync' ); ?></summary>

			<p class="description">
				<?php esc_html_e( 'No hace falta abrir esto para crear el producto. Se guarda con valores razonables: disco oficial, un álbum, un solo disco. Ábrelo solo si el disco es distinto y quieres que el aporte a MusicBrainz salga exacto.', 'melomaniac-sync' ); ?>
			</p>

		<table class="form-table" role="presentation">
			<tbody>
			<?php
			$melomaniac_selects = array(
				'status'         => array(
					'label'   => __( 'Estado', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::statuses(),
					'value'   => '' !== $melomaniac_prefill->status ? $melomaniac_prefill->status : 'Official',
					'help'    => __( 'Oficial es lo normal. Promocional es una copia que no se vendió en tiendas.', 'melomaniac-sync' ),
				),
				'release_type'   => array(
					'label'   => __( 'Tipo', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::release_types(),
					'value'   => '' !== $melomaniac_prefill->release_type ? $melomaniac_prefill->release_type : 'Album',
					'help'    => '',
				),
				'secondary_type' => array(
					'label'   => __( 'Subtipo', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::secondary_types(),
					'value'   => $melomaniac_prefill->secondary_type,
					'help'    => __( 'Se suma al tipo: un compilado en vivo es Álbum + En vivo.', 'melomaniac-sync' ),
				),
				'packaging'      => array(
					'label'   => __( 'Empaque', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::packagings(),
					'value'   => $melomaniac_prefill->packaging,
					'help'    => '',
				),
				'language'       => array(
					'label'   => __( 'Idioma', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::languages(),
					'value'   => $melomaniac_prefill->language,
					'help'    => '',
				),
				'script'         => array(
					'label'   => __( 'Escritura', 'melomaniac-sync' ),
					'options' => Melomaniac_Sync_Release_DTO::scripts(),
					'value'   => '' !== $melomaniac_prefill->script ? $melomaniac_prefill->script : 'Latn',
					'help'    => '',
				),
			);

			foreach ( $melomaniac_selects as $melomaniac_name => $melomaniac_field ) :
				?>
				<tr>
					<th scope="row">
						<label for="melomaniac-manual-<?php echo esc_attr( $melomaniac_name ); ?>">
							<?php echo esc_html( $melomaniac_field['label'] ); ?>
						</label>
					</th>
					<td>
						<select
							id="melomaniac-manual-<?php echo esc_attr( $melomaniac_name ); ?>"
							name="<?php echo esc_attr( $melomaniac_name ); ?>"
						>
							<?php foreach ( $melomaniac_field['options'] as $melomaniac_value => $melomaniac_label ) : ?>
								<option value="<?php echo esc_attr( $melomaniac_value ); ?>" <?php selected( $melomaniac_field['value'], $melomaniac_value ); ?>>
									<?php echo esc_html( $melomaniac_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php if ( '' !== $melomaniac_field['help'] ) : ?>
							<p class="description"><?php echo esc_html( $melomaniac_field['help'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row">
					<label for="melomaniac-manual-medium-count"><?php esc_html_e( 'Cantidad de discos', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="melomaniac-manual-medium-count"
						name="medium_count"
						class="small-text"
						min="1"
						max="50"
						value="<?php echo esc_attr( (string) $melomaniac_prefill->medium_count ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'Un LP doble son 2. Un box set de 5 CDs son 5.', 'melomaniac-sync' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>
		</details>

		<h3><?php esc_html_e( 'Precio, stock y clasificación', 'melomaniac-sync' ); ?></h3>
		<?php
		Melomaniac_Sync_Admin::render_view(
			'partial-product-fields',
			array(
				'prefix'     => 'melomaniac-manual',
				'tags'       => array(),
				'categories' => Melomaniac_Sync_Catalog::categories(),
			)
		);
		?>

		<?php submit_button( __( 'Crear producto como borrador', 'melomaniac-sync' ) ); ?>

		<p>
			<button type="button" class="button" data-melomaniac-contribute-musicbrainz>
				<?php esc_html_e( 'Aportar este disco a MusicBrainz', 'melomaniac-sync' ); ?>
			</button>
			<span class="description">
				<?php esc_html_e( 'Abre el formulario de MusicBrainz con estos datos ya escritos, en una pestaña nueva, para que lo revises y lo envíes con tu propia cuenta. Melomaniac Sync no puede crear el disco por ti: MusicBrainz revisa cada aporte a mano.', 'melomaniac-sync' ); ?>
			</span>
		</p>
	</form>
</div>
