<?php
/**
 * Scan screen.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type array|null          $notice  Notice to display.
 *     @type string              $barcode Prefilled barcode.
 *     @type array<string,string> $formats Format keys and labels.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_notice  = isset( $data['notice'] ) ? $data['notice'] : null;
$melomaniac_barcode = isset( $data['barcode'] ) ? (string) $data['barcode'] : '';
$melomaniac_quota   = isset( $data['quota'] ) ? $data['quota'] : array();
?>
<div class="wrap melomaniac-sync">
	<h1><?php esc_html_e( 'Escanear disco', 'melomaniac-sync' ); ?></h1>

	<p class="melomaniac-intro">
		<?php esc_html_e( 'Escanea el código de barra del disco y Melomaniac Sync busca sus datos en MusicBrainz para crear el producto como borrador.', 'melomaniac-sync' ); ?>
	</p>

	<?php
	if ( is_array( $melomaniac_notice ) ) {
		Melomaniac_Sync_Admin::render_view( 'partial-notice', $melomaniac_notice );
	}

	Melomaniac_Sync_Admin::render_view( 'partial-plan-summary', $melomaniac_quota );
	?>

	<div class="melomaniac-card melomaniac-scan-box">
		<form id="melomaniac-scan-form" class="melomaniac-scan-form">
			<label class="screen-reader-text" for="melomaniac-barcode">
				<?php esc_html_e( 'Código de barra', 'melomaniac-sync' ); ?>
			</label>
			<input
				type="text"
				id="melomaniac-barcode"
				name="barcode"
				class="melomaniac-barcode-input"
				value="<?php echo esc_attr( $melomaniac_barcode ); ?>"
				placeholder="<?php esc_attr_e( 'Ej. 0602577089664', 'melomaniac-sync' ); ?>"
				inputmode="numeric"
				autocomplete="off"
				spellcheck="false"
				autofocus
			/>
			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Buscar', 'melomaniac-sync' ); ?>
			</button>
			<button type="button" id="melomaniac-camera-toggle" class="button button-hero">
				<?php esc_html_e( 'Escanear con la cámara', 'melomaniac-sync' ); ?>
			</button>
		</form>

		<p class="description">
			<?php esc_html_e( 'Con un lector USB, deja el cursor en el campo y dispáralo: el lector escribe el código y confirma solo.', 'melomaniac-sync' ); ?>
		</p>

		<div id="melomaniac-camera" class="melomaniac-camera" hidden>
			<video id="melomaniac-camera-video" class="melomaniac-camera-video" playsinline muted></video>
			<p class="description"><?php esc_html_e( 'Acerca el código de barra al centro del recuadro.', 'melomaniac-sync' ); ?></p>
		</div>

		<p id="melomaniac-camera-message" class="melomaniac-camera-message" hidden></p>
	</div>

	<div id="melomaniac-status" class="melomaniac-status" role="status" aria-live="polite"></div>

	<div id="melomaniac-result" class="melomaniac-result"></div>

	<p class="melomaniac-manual-trigger">
		<button type="button" id="melomaniac-manual-toggle" class="button-link">
			<?php esc_html_e( 'Cargar un disco manualmente, sin buscarlo', 'melomaniac-sync' ); ?>
		</button>
	</p>
</div>
