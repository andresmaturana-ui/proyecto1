<?php
/**
 * Settings screen.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type array              $settings   Current settings.
 *     @type array              $fields     Description field labels.
 *     @type array              $enabled    Which description fields are on.
 *     @type array              $formats    Format labels.
 *     @type array              $map        Format to category mapping.
 *     @type array<int,string>  $categories Product categories.
 *     @type string             $app_url    Installable web app URL.
 *     @type string             $flash      One-off confirmation message.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_settings   = isset( $data['settings'] ) ? $data['settings'] : array();
$melomaniac_fields     = isset( $data['fields'] ) ? $data['fields'] : array();
$melomaniac_enabled    = isset( $data['enabled'] ) ? $data['enabled'] : array();
$melomaniac_formats    = isset( $data['formats'] ) ? $data['formats'] : array();
$melomaniac_map        = isset( $data['map'] ) ? $data['map'] : array();
$melomaniac_categories = isset( $data['categories'] ) ? $data['categories'] : array();
$melomaniac_app_url    = isset( $data['app_url'] ) ? (string) $data['app_url'] : '';
$melomaniac_flash      = isset( $data['flash'] ) ? (string) $data['flash'] : '';
?>
<div class="wrap melomaniac-sync">
	<h1><?php esc_html_e( 'Ajustes de Melomaniac Sync', 'melomaniac-sync' ); ?></h1>

	<?php if ( '' !== $melomaniac_flash ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $melomaniac_flash ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( Melomaniac_Sync_Admin_Menu::settings_url() ); ?>">
		<?php wp_nonce_field( Melomaniac_Sync_Settings_Page::NONCE_ACTION ); ?>

		<h2><?php esc_html_e( 'Fuentes de datos', 'melomaniac-sync' ); ?></h2>
		<p class="description melomaniac-settings-intro">
			<?php esc_html_e( 'Melomaniac Sync busca primero en MusicBrainz, que es abierto y no necesita credenciales. Si el disco no está ahí, consulta Discogs con el token de tu tienda.', 'melomaniac-sync' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="melomaniac-api-contact"><?php esc_html_e( 'Contacto para MusicBrainz', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="melomaniac-api-contact"
						name="api_contact"
						class="regular-text"
						value="<?php echo esc_attr( isset( $melomaniac_settings['api_contact'] ) ? $melomaniac_settings['api_contact'] : '' ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Un email o una URL de la tienda. MusicBrainz exige poder identificar quién consulta; si no es real, pueden bloquear tu IP.', 'melomaniac-sync' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-discogs-token"><?php esc_html_e( 'Token de Discogs', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="melomaniac-discogs-token"
						name="discogs_token"
						class="regular-text"
						autocomplete="off"
						value="<?php echo esc_attr( isset( $melomaniac_settings['discogs_token'] ) ? $melomaniac_settings['discogs_token'] : '' ); ?>"
					/>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to the Discogs developer settings. */
							esc_html__( 'Genera un token personal en %s. Es de tu tienda: no lo compartas y no lo pongas en ningún archivo del plugin.', 'melomaniac-sync' ),
							'<a href="https://www.discogs.com/settings/developers" target="_blank" rel="noopener noreferrer">Discogs → Settings → Developers</a>'
						);
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Sin token, Discogs no se consulta y los discos que MusicBrainz no tenga se cargan a mano.', 'melomaniac-sync' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Respaldo en Discogs', 'melomaniac-sync' ); ?></th>
				<td>
					<label for="melomaniac-discogs-enabled">
						<input
							type="checkbox"
							id="melomaniac-discogs-enabled"
							name="discogs_enabled"
							value="1"
							<?php checked( ! empty( $melomaniac_settings['discogs_enabled'] ) ); ?>
						/>
						<?php esc_html_e( 'Consultar Discogs cuando MusicBrainz no encuentre el disco', 'melomaniac-sync' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Productos nuevos', 'melomaniac-sync' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="melomaniac-product-status"><?php esc_html_e( 'Estado al crearse', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<?php
					$melomaniac_statuses = array(
						'draft'   => __( 'Borrador', 'melomaniac-sync' ),
						'pending' => __( 'Pendiente de revisión', 'melomaniac-sync' ),
						'publish' => __( 'Publicado', 'melomaniac-sync' ),
					);
					$melomaniac_current  = isset( $melomaniac_settings['product_status'] ) ? $melomaniac_settings['product_status'] : 'draft';
					?>
					<select id="melomaniac-product-status" name="product_status">
						<?php foreach ( $melomaniac_statuses as $melomaniac_key => $melomaniac_label ) : ?>
							<option value="<?php echo esc_attr( $melomaniac_key ); ?>" <?php selected( $melomaniac_current, $melomaniac_key ); ?>>
								<?php echo esc_html( $melomaniac_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Borrador es lo recomendado: te deja revisar precio y stock antes de que el disco salga a la venta.', 'melomaniac-sync' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-default-price"><?php esc_html_e( 'Precio por defecto', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="melomaniac-default-price"
						name="default_price"
						class="small-text"
						value="<?php echo esc_attr( isset( $melomaniac_settings['default_price'] ) ? $melomaniac_settings['default_price'] : '0' ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-default-stock"><?php esc_html_e( 'Stock por defecto', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="melomaniac-default-stock"
						name="default_stock"
						class="small-text"
						min="0"
						value="<?php echo esc_attr( (string) ( isset( $melomaniac_settings['default_stock'] ) ? $melomaniac_settings['default_stock'] : 1 ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Portada', 'melomaniac-sync' ); ?></th>
				<td>
					<label for="melomaniac-import-cover">
						<input
							type="checkbox"
							id="melomaniac-import-cover"
							name="import_cover"
							value="1"
							<?php checked( ! empty( $melomaniac_settings['import_cover'] ) ); ?>
						/>
						<?php esc_html_e( 'Descargar la portada a la biblioteca de medios', 'melomaniac-sync' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Etiqueta de género', 'melomaniac-sync' ); ?></th>
				<td>
					<label for="melomaniac-genre-tag">
						<input
							type="checkbox"
							id="melomaniac-genre-tag"
							name="genre_tag"
							value="1"
							<?php checked( ! empty( $melomaniac_settings['genre_tag'] ) ); ?>
						/>
						<?php esc_html_e( 'Agregar el género principal como etiqueta del producto', 'melomaniac-sync' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Qué entra en la descripción', 'melomaniac-sync' ); ?></h2>
		<p class="description melomaniac-settings-intro">
			<?php esc_html_e( 'Los campos que desmarques no aparecen en la ficha, aunque Melomaniac Sync los haya encontrado.', 'melomaniac-sync' ); ?>
		</p>

		<fieldset class="melomaniac-field-toggles">
			<?php foreach ( $melomaniac_fields as $melomaniac_key => $melomaniac_label ) : ?>
				<label for="melomaniac-field-<?php echo esc_attr( $melomaniac_key ); ?>">
					<input
						type="checkbox"
						id="melomaniac-field-<?php echo esc_attr( $melomaniac_key ); ?>"
						name="description_fields[<?php echo esc_attr( $melomaniac_key ); ?>]"
						value="1"
						<?php checked( ! empty( $melomaniac_enabled[ $melomaniac_key ] ) ); ?>
					/>
					<?php echo esc_html( $melomaniac_label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<h2><?php esc_html_e( 'Categoría por formato', 'melomaniac-sync' ); ?></h2>
		<p class="description melomaniac-settings-intro">
			<?php esc_html_e( 'Si dejas "Detectar sola", Melomaniac Sync busca entre tus categorías una que coincida con el formato.', 'melomaniac-sync' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
			<?php foreach ( $melomaniac_formats as $melomaniac_format => $melomaniac_label ) : ?>
				<?php if ( 'other' === $melomaniac_format ) { continue; } ?>
				<tr>
					<th scope="row">
						<label for="melomaniac-map-<?php echo esc_attr( $melomaniac_format ); ?>"><?php echo esc_html( $melomaniac_label ); ?></label>
					</th>
					<td>
						<select id="melomaniac-map-<?php echo esc_attr( $melomaniac_format ); ?>" name="category_map[<?php echo esc_attr( $melomaniac_format ); ?>]">
							<option value="0"><?php esc_html_e( 'Detectar sola', 'melomaniac-sync' ); ?></option>
							<?php foreach ( $melomaniac_categories as $melomaniac_id => $melomaniac_name ) : ?>
								<option value="<?php echo esc_attr( (string) $melomaniac_id ); ?>" <?php selected( isset( $melomaniac_map[ $melomaniac_format ] ) ? (int) $melomaniac_map[ $melomaniac_format ] : 0, $melomaniac_id ); ?>>
									<?php echo esc_html( $melomaniac_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'App web', 'melomaniac-sync' ); ?></h2>
		<p class="description melomaniac-settings-intro">
			<?php esc_html_e( 'Una app que se abre desde el navegador del teléfono, para escanear y crear productos sin entrar a wp-admin. Se instala agregándola a la pantalla de inicio.', 'melomaniac-sync' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Habilitar la app', 'melomaniac-sync' ); ?></th>
				<td>
					<label for="melomaniac-app-enabled">
						<input
							type="checkbox"
							id="melomaniac-app-enabled"
							name="app_enabled"
							value="1"
							<?php checked( ! empty( $melomaniac_settings['app_enabled'] ) ); ?>
						/>
						<?php esc_html_e( 'Sirve la app en la dirección de abajo', 'melomaniac-sync' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="melomaniac-app-url"><?php esc_html_e( 'Dirección de la app', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input type="text" id="melomaniac-app-url" class="regular-text" readonly value="<?php echo esc_attr( $melomaniac_app_url ); ?>" onclick="this.select();" />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to create an application password. */
							esc_html__( 'Ábrela desde el teléfono. Cada persona que la use necesita su propia contraseña de aplicación, que se crea una vez desde %s.', 'melomaniac-sync' ),
							'<a href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'su perfil de WordPress', 'melomaniac-sync' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Diagnóstico', 'melomaniac-sync' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="melomaniac-http-timeout"><?php esc_html_e( 'Tiempo de espera', 'melomaniac-sync' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						id="melomaniac-http-timeout"
						name="http_timeout"
						class="small-text"
						min="5"
						max="60"
						value="<?php echo esc_attr( (string) ( isset( $melomaniac_settings['http_timeout'] ) ? $melomaniac_settings['http_timeout'] : 15 ) ); ?>"
					/>
					<?php esc_html_e( 'segundos', 'melomaniac-sync' ); ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to the diagnostics screen. */
							esc_html__( 'Cuánto espera a MusicBrainz, Cover Art Archive y Discogs antes de darse por vencido. Si ves errores de "timed out", subilo y revisa %s.', 'melomaniac-sync' ),
							'<a href="' . esc_url( Melomaniac_Sync_Admin_Menu::diagnostics_url() ) . '">' . esc_html__( 'Diagnóstico', 'melomaniac-sync' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Registro', 'melomaniac-sync' ); ?></th>
				<td>
					<label for="melomaniac-logging">
						<input
							type="checkbox"
							id="melomaniac-logging"
							name="logging_enabled"
							value="1"
							<?php checked( ! empty( $melomaniac_settings['logging_enabled'] ) ); ?>
						/>
						<?php esc_html_e( 'Anotar fallas de MusicBrainz, Discogs y portadas en el log de WordPress', 'melomaniac-sync' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Para que el archivo se escriba, wp-config.php necesita WP_DEBUG y WP_DEBUG_LOG en true. Déjalo apagado salvo que estés buscando un problema.', 'melomaniac-sync' ); ?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Guardar ajustes', 'melomaniac-sync' ), 'primary', 'melomaniac_sync_settings_submit' ); ?>
	</form>
</div>
