<?php
/**
 * Diagnostics screen.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type array[]             $probes      Probe results.
 *     @type bool                $ran         Whether the probes were run.
 *     @type array<string,string> $environment Environment facts.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_probes      = isset( $data['probes'] ) ? $data['probes'] : array();
$melomaniac_ran         = ! empty( $data['ran'] );
$melomaniac_environment = isset( $data['environment'] ) ? $data['environment'] : array();
$melomaniac_flushed     = ! empty( $data['flushed'] );
$melomaniac_current_plan = isset( $data['current_plan'] ) ? (string) $data['current_plan'] : 'free';
$melomaniac_test_plan    = isset( $data['test_plan'] ) ? (string) $data['test_plan'] : '';
$melomaniac_host_forced  = ! empty( $data['plan_forced_by_host'] );
$melomaniac_barcode     = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';
$melomaniac_result      = isset( $data['barcode_result'] ) ? $data['barcode_result'] : null;
$melomaniac_fs_loaded   = ! empty( $data['freemius_loaded'] );
$melomaniac_fs_by_host  = ! empty( $data['freemius_disabled_by_host'] );
$melomaniac_fs_option   = ! empty( $data['freemius_disabled_option'] );
$melomaniac_fs_toggled  = ! empty( $data['freemius_toggled'] );

$melomaniac_states = array(
	'ok'      => __( 'Bien', 'melomaniac-sync' ),
	'warn'    => __( 'Responde, con reparos', 'melomaniac-sync' ),
	'fail'    => __( 'Falla', 'melomaniac-sync' ),
	'skipped' => __( 'Sin probar', 'melomaniac-sync' ),
);
?>
<div class="wrap melomaniac-sync">
	<h1><?php esc_html_e( 'Diagnóstico', 'melomaniac-sync' ); ?></h1>

	<p class="melomaniac-intro">
		<?php esc_html_e( 'Comprueba si este servidor puede alcanzar los servicios que Melomaniac Sync necesita. Si un escaneo falla con "Operation timed out", la respuesta está acá.', 'melomaniac-sync' ); ?>
	</p>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Plan para pruebas', 'melomaniac-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Prueba las pantallas de cada plan sin depender de Freemius ni tener una suscripción real. No cambia el plan de verdad de la tienda, solo lo que el plugin cree mientras esto quede activado.', 'melomaniac-sync' ); ?>
		</p>

		<?php if ( $melomaniac_host_forced ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'El plan está fijado desde wp-config.php (MELOMANIAC_SYNC_FORCE_PLAN), así que este selector no tiene efecto hasta que lo quites de ahí.', 'melomaniac-sync' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" class="melomaniac-plan-test-form">
			<?php wp_nonce_field( Melomaniac_Sync_Diagnostics_Page::NONCE_PLAN ); ?>
			<label class="screen-reader-text" for="melomaniac-test-plan">
				<?php esc_html_e( 'Plan para pruebas', 'melomaniac-sync' ); ?>
			</label>
			<select id="melomaniac-test-plan" name="test_plan" <?php disabled( $melomaniac_host_forced ); ?>>
				<option value="" <?php selected( '' === $melomaniac_test_plan ); ?>>
					<?php esc_html_e( 'Usar el plan real de la tienda', 'melomaniac-sync' ); ?>
				</option>
				<?php foreach ( Melomaniac_Sync_Licensing::PLANS as $melomaniac_plan_key ) : ?>
					<option value="<?php echo esc_attr( $melomaniac_plan_key ); ?>" <?php selected( $melomaniac_test_plan, $melomaniac_plan_key ); ?>>
						<?php echo esc_html( Melomaniac_Sync_Licensing::plan_label( $melomaniac_plan_key ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" name="melomaniac_sync_set_test_plan" value="1" class="button" <?php disabled( $melomaniac_host_forced ); ?>>
				<?php esc_html_e( 'Aplicar', 'melomaniac-sync' ); ?>
			</button>
		</form>

		<p class="melomaniac-plan-test-current">
			<?php
			printf(
				/* translators: %s: plan name currently in effect. */
				esc_html__( 'Plan en efecto ahora mismo: %s', 'melomaniac-sync' ),
				'<strong>' . esc_html( Melomaniac_Sync_Licensing::plan_label( $melomaniac_current_plan ) ) . '</strong>'
			);

			if ( '' !== $melomaniac_test_plan && ! $melomaniac_host_forced ) {
				echo ' ' . esc_html__( '(prueba activa, no es el plan real)', 'melomaniac-sync' );
			}
			?>
		</p>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'SDK de Freemius', 'melomaniac-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Apágalo para probar el plugin sin que hable con Freemius en ningún momento: sin conexión de cuenta, sin pantalla de licencia, sin llamadas a freemius.com. Con esto apagado, el plan lo decide solo el selector de arriba.', 'melomaniac-sync' ); ?>
		</p>

		<?php if ( $melomaniac_fs_toggled ) : ?>
			<div class="notice notice-success inline">
				<p><?php esc_html_e( 'Guardado. El cambio se aplica en la próxima carga de página.', 'melomaniac-sync' ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $melomaniac_fs_by_host ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'wp-config.php ya lo tiene apagado (MELOMANIAC_SYNC_SKIP_FREEMIUS), así que este interruptor no hace nada hasta que lo quites de ahí.', 'melomaniac-sync' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" class="melomaniac-plan-test-form">
			<?php wp_nonce_field( Melomaniac_Sync_Diagnostics_Page::NONCE_FREEMIUS ); ?>
			<label for="melomaniac-freemius-disabled">
				<input
					type="checkbox"
					id="melomaniac-freemius-disabled"
					name="freemius_disabled"
					value="1"
					<?php checked( $melomaniac_fs_option ); ?>
					<?php disabled( $melomaniac_fs_by_host ); ?>
				/>
				<?php esc_html_e( 'Desactivar Freemius por completo', 'melomaniac-sync' ); ?>
			</label>
			<button type="submit" name="melomaniac_sync_set_freemius_disabled" value="1" class="button" <?php disabled( $melomaniac_fs_by_host ); ?>>
				<?php esc_html_e( 'Guardar', 'melomaniac-sync' ); ?>
			</button>
		</form>

		<p class="melomaniac-plan-test-current">
			<?php
			if ( $melomaniac_fs_loaded ) {
				esc_html_e( 'Ahora mismo: Freemius está cargado.', 'melomaniac-sync' );
			} elseif ( $melomaniac_fs_by_host ) {
				esc_html_e( 'Ahora mismo: Freemius no se cargó (apagado desde wp-config.php).', 'melomaniac-sync' );
			} else {
				esc_html_e( 'Ahora mismo: Freemius no se cargó (apagado desde este interruptor).', 'melomaniac-sync' );
			}
			?>
		</p>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Conexión con los servicios', 'melomaniac-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Hace una petición real a cada servicio y mide cuánto tarda. Puede demorar si el hosting está bloqueando la salida.', 'melomaniac-sync' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( Melomaniac_Sync_Diagnostics_Page::NONCE_ACTION ); ?>
			<p>
				<button type="submit" name="melomaniac_sync_run_probes" value="1" class="button button-primary">
					<?php esc_html_e( 'Probar la conexión ahora', 'melomaniac-sync' ); ?>
				</button>
			</p>
		</form>

		<?php if ( $melomaniac_ran ) : ?>
			<table class="widefat striped melomaniac-probe-table">
				<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Servicio', 'melomaniac-sync' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Resultado', 'melomaniac-sync' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Código', 'melomaniac-sync' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Tardó', 'melomaniac-sync' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Resolvió a', 'melomaniac-sync' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $melomaniac_probes as $melomaniac_probe ) : ?>
					<?php
					$melomaniac_state = isset( $melomaniac_probe['state'] ) ? $melomaniac_probe['state'] : 'fail';
					$melomaniac_ips   = array_filter(
						array(
							isset( $melomaniac_probe['ipv4'] ) ? $melomaniac_probe['ipv4'] : '',
							isset( $melomaniac_probe['ipv6'] ) ? $melomaniac_probe['ipv6'] : '',
						)
					);
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $melomaniac_probe['label'] ); ?></strong><br />
							<code><?php echo esc_html( $melomaniac_probe['host'] ); ?></code>
							<p class="description"><?php echo esc_html( $melomaniac_probe['purpose'] ); ?></p>
						</td>
						<td>
							<span class="melomaniac-probe-state is-<?php echo esc_attr( $melomaniac_state ); ?>">
								<?php echo esc_html( isset( $melomaniac_states[ $melomaniac_state ] ) ? $melomaniac_states[ $melomaniac_state ] : $melomaniac_state ); ?>
							</span>
							<?php if ( ! empty( $melomaniac_probe['message'] ) ) : ?>
								<p class="melomaniac-probe-message"><?php echo esc_html( $melomaniac_probe['message'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $melomaniac_probe['status_code'] > 0 ? (string) $melomaniac_probe['status_code'] : '—' ); ?></td>
						<td>
							<?php
							echo esc_html(
								$melomaniac_probe['elapsed_ms'] > 0
									? sprintf(
										/* translators: %d: milliseconds. */
										__( '%d ms', 'melomaniac-sync' ),
										(int) $melomaniac_probe['elapsed_ms']
									)
									: '—'
							);
							?>
						</td>
						<td>
							<?php echo esc_html( $melomaniac_ips ? implode( ', ', $melomaniac_ips ) : __( 'no resolvió', 'melomaniac-sync' ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description melomaniac-probe-note">
				<?php esc_html_e( 'Un código 400 o 404 también cuenta como conexión buena: significa que el servicio contestó. Lo único preocupante es que no conteste nada.', 'melomaniac-sync' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Probar un código de barra', 'melomaniac-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Le pregunta a cada fuente por separado, sin usar lo guardado. Sirve para ver qué contesta Discogs aunque MusicBrainz también tenga el disco, que es lo que el escaneo normal nunca te deja ver.', 'melomaniac-sync' ); ?>
		</p>

		<form method="post" class="melomaniac-probe-form">
			<?php wp_nonce_field( Melomaniac_Sync_Diagnostics_Page::NONCE_BARCODE ); ?>
			<label class="screen-reader-text" for="melomaniac-probe-barcode">
				<?php esc_html_e( 'Código de barra', 'melomaniac-sync' ); ?>
			</label>
			<input
				type="text"
				id="melomaniac-probe-barcode"
				name="probe_barcode"
				class="melomaniac-barcode-input"
				inputmode="numeric"
				autocomplete="off"
				value="<?php echo esc_attr( $melomaniac_barcode ); ?>"
				placeholder="<?php esc_attr_e( 'Ej. 016861979546', 'melomaniac-sync' ); ?>"
			/>
			<button type="submit" name="melomaniac_sync_probe_barcode" value="1" class="button button-primary">
				<?php esc_html_e( 'Preguntar a las dos fuentes', 'melomaniac-sync' ); ?>
			</button>
		</form>

		<?php if ( is_wp_error( $melomaniac_result ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $melomaniac_result->get_error_message() ); ?></p></div>
		<?php elseif ( is_array( $melomaniac_result ) ) : ?>
			<?php
			$melomaniac_mb = $melomaniac_result['musicbrainz'];
			$melomaniac_dc = $melomaniac_result['discogs'];

			if ( $melomaniac_mb['count'] > 0 ) {
				$melomaniac_verdict = __( 'MusicBrainz tiene el disco, así que el escaneo normal no va a consultar Discogs.', 'melomaniac-sync' );
			} elseif ( $melomaniac_dc['count'] > 0 ) {
				$melomaniac_verdict = __( 'MusicBrainz no lo tiene y Discogs sí: este código es justo el caso donde entra el respaldo.', 'melomaniac-sync' );
			} elseif ( '' !== $melomaniac_dc['error'] ) {
				$melomaniac_verdict = __( 'MusicBrainz no lo tiene y a Discogs no se le pudo preguntar. El escaneo va a ofrecer el formulario manual.', 'melomaniac-sync' );
			} else {
				$melomaniac_verdict = __( 'Ninguna de las dos lo tiene. El escaneo va a ofrecer el formulario manual.', 'melomaniac-sync' );
			}
			?>
			<p class="melomaniac-probe-verdict"><?php echo esc_html( $melomaniac_verdict ); ?></p>

			<table class="widefat striped melomaniac-probe-table">
				<tbody>
				<?php foreach ( array( $melomaniac_mb, $melomaniac_dc ) as $melomaniac_source ) : ?>
					<tr>
						<td style="width:12em;"><strong><?php echo esc_html( $melomaniac_source['label'] ); ?></strong></td>
						<td>
							<?php if ( '' !== $melomaniac_source['error'] ) : ?>
								<span class="melomaniac-probe-state is-<?php echo esc_attr( $melomaniac_source['available'] ? 'fail' : 'skipped' ); ?>">
									<?php echo esc_html( $melomaniac_source['available'] ? __( 'Falla', 'melomaniac-sync' ) : __( 'Sin probar', 'melomaniac-sync' ) ); ?>
								</span>
								<p class="melomaniac-probe-message"><?php echo esc_html( $melomaniac_source['error'] ); ?></p>
							<?php elseif ( 0 === $melomaniac_source['count'] ) : ?>
								<span class="melomaniac-probe-state is-warn"><?php esc_html_e( 'Sin resultados', 'melomaniac-sync' ); ?></span>
							<?php else : ?>
								<span class="melomaniac-probe-state is-ok">
									<?php
									printf(
										/* translators: %d: number of matching releases. */
										esc_html( _n( '%d edición', '%d ediciones', (int) $melomaniac_source['count'], 'melomaniac-sync' ) ),
										(int) $melomaniac_source['count']
									);
									?>
								</span>
								<ul class="melomaniac-probe-matches">
									<?php foreach ( $melomaniac_source['matches'] as $melomaniac_match ) : ?>
										<li>
											<strong><?php echo esc_html( $melomaniac_match['artist'] ); ?></strong>
											<?php echo esc_html( $melomaniac_match['title'] ); ?>
											<span class="melomaniac-candidate-meta">
												<?php
												echo esc_html(
													implode(
														' · ',
														array_filter(
															array(
																$melomaniac_match['year'],
																$melomaniac_match['label'],
																$melomaniac_match['format'],
															)
														)
													)
												);
												?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Búsquedas guardadas', 'melomaniac-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Melomaniac Sync guarda un día lo que encuentra, para no repetir peticiones. Si un disco quedó guardado con datos equivocados, vacía esto y vuelve a escanearlo.', 'melomaniac-sync' ); ?>
		</p>

		<?php if ( $melomaniac_flushed ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Se vaciaron las búsquedas guardadas.', 'melomaniac-sync' ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( Melomaniac_Sync_Diagnostics_Page::NONCE_FLUSH ); ?>
			<p>
				<button type="submit" name="melomaniac_sync_flush_cache" value="1" class="button">
					<?php esc_html_e( 'Vaciar las búsquedas guardadas', 'melomaniac-sync' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Entorno', 'melomaniac-sync' ); ?></h2>
		<table class="widefat striped">
			<tbody>
			<?php foreach ( $melomaniac_environment as $melomaniac_label => $melomaniac_value ) : ?>
				<tr>
					<td><?php echo esc_html( $melomaniac_label ); ?></td>
					<td><strong><?php echo esc_html( $melomaniac_value ); ?></strong></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="melomaniac-card">
		<h2><?php esc_html_e( 'Si el tiempo de espera se agota', 'melomaniac-sync' ); ?></h2>
		<ol class="melomaniac-troubleshoot">
			<li>
				<?php
				printf(
					/* translators: %s: link to the settings screen. */
					esc_html__( 'Sube el tiempo de espera en %s. Si la conexión es lenta pero funciona, con 30 segundos suele alcanzar.', 'melomaniac-sync' ),
					'<a href="' . esc_url( Melomaniac_Sync_Admin_Menu::settings_url() ) . '">' . esc_html__( 'Ajustes', 'melomaniac-sync' ) . '</a>'
				);
				?>
			</li>
			<li><?php esc_html_e( 'Si arriba dice que no resolvió el dominio, es DNS del hosting.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'Si resolvió solo a IPv6 y falla, el hosting no tiene salida IPv6 funcionando. Pídeles que la arreglen o que fuercen IPv4.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'Si no pasa nada de lo anterior, pídele a tu hosting que permita conexiones HTTPS salientes a musicbrainz.org, coverartarchive.org y api.discogs.com. Muchos hostings compartidos las bloquean por defecto.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'MusicBrainz bloquea las IP que hacen más de una petición por segundo. Si compartís IP con otros sitios, puede que el bloqueo no sea por tu tienda.', 'melomaniac-sync' ); ?></li>
		</ol>
	</div>
</div>
