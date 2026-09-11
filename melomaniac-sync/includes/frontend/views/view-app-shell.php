<?php
/**
 * App shell: the single HTML page the installable web app boots from.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string $rest_url      Base URL of the melomaniac-sync/v1 namespace.
 *     @type string $manifest_url  Manifest URL.
 *     @type string $sw_url        Service worker URL.
 *     @type string $app_js_url    App script URL.
 *     @type string $app_css_url   App stylesheet URL.
 *     @type string $camera_js_url Shared camera scanner script URL.
 *     @type string $zxing_js_url  Vendored ZXing decoder, the camera scanner's fallback.
 *     @type string $icon_url      App icon URL.
 *     @type string $site_name     Store name.
 *     @type string $profile_url   Where to create an Application Password.
 *     @type string $settings_url  The plugin's settings screen in wp-admin.
 *     @type string $version       Plugin version, for the app footer.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_app = wp_parse_args(
	$data,
	array(
		'rest_url'      => '',
		'manifest_url'  => '',
		'sw_url'        => '',
		'app_js_url'    => '',
		'app_css_url'   => '',
		'camera_js_url' => '',
		'zxing_js_url'  => '',
		'icon_url'      => '',
		'site_name'     => '',
		'profile_url'   => '',
		'settings_url'  => '',
		'version'       => '',
	)
);
?>
<!DOCTYPE html>
<html lang="es-CL">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<meta name="theme-color" content="#111318" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr__( 'Melomaniac', 'melomaniac-sync' ); ?>" />
<title><?php echo esc_html( $melomaniac_app['site_name'] . ' · ' . __( 'Melomaniac Sync', 'melomaniac-sync' ) ); ?></title>
<link rel="manifest" href="<?php echo esc_url( $melomaniac_app['manifest_url'] ); ?>" />
<link rel="icon" href="<?php echo esc_url( $melomaniac_app['icon_url'] ); ?>" />
<link rel="apple-touch-icon" href="<?php echo esc_url( $melomaniac_app['icon_url'] ); ?>" />
<link rel="stylesheet" href="<?php echo esc_url( $melomaniac_app['app_css_url'] ); ?>" />
</head>
<body>
<div id="app" class="melomaniac-app" data-screen="connect">

	<header class="melomaniac-app-header">
		<span class="melomaniac-app-title"><?php echo esc_html( $melomaniac_app['site_name'] ); ?></span>
		<button type="button" class="melomaniac-app-icon-btn" data-action="open-menu" aria-label="<?php esc_attr_e( 'Menú', 'melomaniac-sync' ); ?>" hidden>&#8942;</button>
	</header>

	<div class="melomaniac-app-menu" data-app-menu hidden>
		<p class="melomaniac-app-menu-user" data-menu-username></p>
		<a class="melomaniac-btn-link" href="<?php echo esc_url( $melomaniac_app['settings_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir ajustes', 'melomaniac-sync' ); ?> &#8599;</a>
		<button type="button" class="melomaniac-btn-link" data-action="disconnect"><?php esc_html_e( 'Cerrar sesión', 'melomaniac-sync' ); ?></button>
	</div>

	<main class="melomaniac-app-main">

		<section class="melomaniac-screen" data-screen-name="connect">
			<h1><?php esc_html_e( 'Conectar la app', 'melomaniac-sync' ); ?></h1>
			<p class="melomaniac-app-lead">
				<?php esc_html_e( 'Necesitas una contraseña de aplicación de tu usuario de WordPress. Se crea una sola vez.', 'melomaniac-sync' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $melomaniac_app['profile_url'] ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Crear una contraseña de aplicación', 'melomaniac-sync' ); ?> &#8599;
				</a>
			</p>
			<form data-form="connect">
				<label for="melomaniac-connect-user"><?php esc_html_e( 'Usuario', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-connect-user" name="username" autocomplete="username" required />

				<label for="melomaniac-connect-pass"><?php esc_html_e( 'Contraseña de aplicación', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-connect-pass" name="password" autocomplete="off" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required />

				<p class="melomaniac-app-error" data-connect-error hidden></p>

				<button type="submit" class="melomaniac-btn melomaniac-btn-primary"><?php esc_html_e( 'Conectar', 'melomaniac-sync' ); ?></button>
			</form>
		</section>

		<section class="melomaniac-screen" data-screen-name="scan" hidden>
			<div class="melomaniac-quota" data-quota hidden></div>

			<div class="melomaniac-camera-wrap">
				<video data-camera-video playsinline muted hidden></video>
				<p class="melomaniac-camera-hint" data-camera-hint></p>
				<button type="button" class="melomaniac-btn" data-action="toggle-camera"><?php esc_html_e( 'Usar la cámara', 'melomaniac-sync' ); ?></button>
			</div>

			<form data-form="barcode">
				<label for="melomaniac-barcode"><?php esc_html_e( 'Código de barra', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-barcode" name="barcode" inputmode="numeric" autocomplete="off" required />
				<button type="submit" class="melomaniac-btn melomaniac-btn-primary"><?php esc_html_e( 'Buscar', 'melomaniac-sync' ); ?></button>
			</form>

			<button type="button" class="melomaniac-btn-link" data-action="go-manual"><?php esc_html_e( 'Cargar un disco a mano', 'melomaniac-sync' ); ?></button>

			<p class="melomaniac-app-error" data-scan-error hidden></p>
			<div class="melomaniac-spinner" data-scan-loading hidden></div>
		</section>

		<section class="melomaniac-screen" data-screen-name="candidates" hidden>
			<h1><?php esc_html_e( 'Elige el disco', 'melomaniac-sync' ); ?></h1>
			<div data-candidate-list class="melomaniac-candidate-list"></div>
			<button type="button" class="melomaniac-btn-link" data-action="go-manual-from-candidates"><?php esc_html_e( 'Ninguno es, cargar a mano', 'melomaniac-sync' ); ?></button>
			<button type="button" class="melomaniac-btn-link" data-action="back-to-scan"><?php esc_html_e( '&larr; Volver', 'melomaniac-sync' ); ?></button>
		</section>

		<section class="melomaniac-screen" data-screen-name="review" hidden>
			<h1><?php esc_html_e( 'Confirmar disco', 'melomaniac-sync' ); ?></h1>
			<div data-review-summary class="melomaniac-review-summary"></div>
			<div data-review-tracklist class="melomaniac-review-tracklist"></div>

			<form data-form="review">
				<label for="melomaniac-review-photo"><?php esc_html_e( 'Portada', 'melomaniac-sync' ); ?></label>
				<p class="melomaniac-app-lead" data-review-cover-hint></p>
				<div class="melomaniac-cover-preview" data-review-cover-preview hidden></div>
				<input type="file" id="melomaniac-review-photo" accept="image/*" capture="environment" data-review-cover-input />

				<div data-fields-mount></div>
				<button type="submit" class="melomaniac-btn melomaniac-btn-primary"><?php esc_html_e( 'Crear producto', 'melomaniac-sync' ); ?></button>
			</form>

			<button type="button" class="melomaniac-btn-link" data-action="go-manual-from-review"><?php esc_html_e( 'No es este disco, corregir a mano', 'melomaniac-sync' ); ?></button>
			<button type="button" class="melomaniac-btn-link" data-action="back-to-candidates"><?php esc_html_e( '&larr; Volver', 'melomaniac-sync' ); ?></button>
			<p class="melomaniac-app-error" data-review-error hidden></p>
		</section>

		<section class="melomaniac-screen" data-screen-name="manual" hidden>
			<h1 data-manual-title><?php esc_html_e( 'Cargar el disco a mano', 'melomaniac-sync' ); ?></h1>
			<p class="melomaniac-app-lead" data-manual-intro hidden></p>

			<form data-form="manual">
				<label for="melomaniac-manual-artist"><?php esc_html_e( 'Artista', 'melomaniac-sync' ); ?> *</label>
				<input type="text" id="melomaniac-manual-artist" name="artist" required />

				<label for="melomaniac-manual-title"><?php esc_html_e( 'Título del álbum', 'melomaniac-sync' ); ?> *</label>
				<input type="text" id="melomaniac-manual-title" name="title" required />

				<label for="melomaniac-manual-label"><?php esc_html_e( 'Sello', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-manual-label" name="label" />

				<div class="melomaniac-app-row">
					<div>
						<label for="melomaniac-manual-year"><?php esc_html_e( 'Año', 'melomaniac-sync' ); ?></label>
						<input type="number" id="melomaniac-manual-year" name="year" min="1880" />
					</div>
					<div>
						<label for="melomaniac-manual-format"><?php esc_html_e( 'Formato', 'melomaniac-sync' ); ?></label>
						<select id="melomaniac-manual-format" name="format" data-formats-mount></select>
					</div>
				</div>

				<label for="melomaniac-manual-country"><?php esc_html_e( 'País de prensaje', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-manual-country" name="country" />

				<label for="melomaniac-manual-catalog"><?php esc_html_e( 'Número de catálogo', 'melomaniac-sync' ); ?></label>
				<input type="text" id="melomaniac-manual-catalog" name="catalog_number" />

				<label for="melomaniac-manual-tracklist"><?php esc_html_e( 'Lista de canciones (opcional)', 'melomaniac-sync' ); ?></label>
				<textarea id="melomaniac-manual-tracklist" name="tracklist" rows="5"></textarea>

				<label for="melomaniac-manual-photo"><?php esc_html_e( 'Foto de la portada (opcional)', 'melomaniac-sync' ); ?></label>
				<input type="file" id="melomaniac-manual-photo" accept="image/*" capture="environment" data-cover-input />
				<div class="melomaniac-cover-preview" data-cover-preview hidden></div>

				<div data-fields-mount></div>

				<button type="submit" class="melomaniac-btn melomaniac-btn-primary"><?php esc_html_e( 'Crear producto', 'melomaniac-sync' ); ?></button>
			</form>

			<button type="button" class="melomaniac-btn-link" data-action="back-to-scan"><?php esc_html_e( '&larr; Volver al escaneo', 'melomaniac-sync' ); ?></button>
			<p class="melomaniac-app-error" data-manual-error hidden></p>
		</section>

		<section class="melomaniac-screen" data-screen-name="done" hidden>
			<h1><?php esc_html_e( '¡Listo!', 'melomaniac-sync' ); ?></h1>
			<p data-done-message></p>
			<p><a data-done-edit-link href="#" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir en el editor', 'melomaniac-sync' ); ?> &#8599;</a></p>
			<button type="button" class="melomaniac-btn melomaniac-btn-primary" data-action="scan-another"><?php esc_html_e( 'Escanear otro', 'melomaniac-sync' ); ?></button>
		</section>

	</main>

	<footer class="melomaniac-app-footer">
		<span class="melomaniac-app-version">v<?php echo esc_html( $melomaniac_app['version'] ); ?></span>
	</footer>

</div>

<script>
window.MelomaniacSyncApp = {
	restUrl: <?php echo wp_json_encode( $melomaniac_app['rest_url'] ); ?>,
	swUrl: <?php echo wp_json_encode( $melomaniac_app['sw_url'] ); ?>
};
</script>
<script src="<?php echo esc_url( $melomaniac_app['zxing_js_url'] ); ?>"></script>
<script src="<?php echo esc_url( $melomaniac_app['camera_js_url'] ); ?>"></script>
<script src="<?php echo esc_url( $melomaniac_app['app_js_url'] ); ?>"></script>
</body>
</html>
