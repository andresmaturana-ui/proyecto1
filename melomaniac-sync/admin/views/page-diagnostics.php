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
					esc_html__( 'Subí el tiempo de espera en %s. Si la conexión es lenta pero funciona, con 30 segundos suele alcanzar.', 'melomaniac-sync' ),
					'<a href="' . esc_url( Melomaniac_Sync_Admin_Menu::settings_url() ) . '">' . esc_html__( 'Ajustes', 'melomaniac-sync' ) . '</a>'
				);
				?>
			</li>
			<li><?php esc_html_e( 'Si arriba dice que no resolvió el dominio, es DNS del hosting.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'Si resolvió solo a IPv6 y falla, el hosting no tiene salida IPv6 funcionando. Pediles que la arreglen o que fuercen IPv4.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'Si no pasa nada de lo anterior, pedile a tu hosting que permita conexiones HTTPS salientes a musicbrainz.org, coverartarchive.org y api.discogs.com. Muchos hostings compartidos las bloquean por defecto.', 'melomaniac-sync' ); ?></li>
			<li><?php esc_html_e( 'MusicBrainz bloquea las IP que hacen más de una petición por segundo. Si compartís IP con otros sitios, puede que el bloqueo no sea por tu tienda.', 'melomaniac-sync' ); ?></li>
		</ol>
	</div>
</div>
