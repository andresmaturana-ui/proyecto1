<?php
/**
 * Preview of a matched release.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type Melomaniac_Sync_Release_DTO $release Matched release.
 *     @type string                      $barcode Normalised barcode.
 * }
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $data['release'] ) || ! $data['release'] instanceof Melomaniac_Sync_Release_DTO ) {
	return;
}

/** @var Melomaniac_Sync_Release_DTO $melomaniac_release */
$melomaniac_release = $data['release'];
$melomaniac_barcode = isset( $data['barcode'] ) ? (string) $data['barcode'] : $melomaniac_release->barcode;

$melomaniac_meta = array(
	__( 'Año', 'melomaniac-sync' )                => $melomaniac_release->year,
	__( 'Sello', 'melomaniac-sync' )              => $melomaniac_release->label,
	__( 'Número de catálogo', 'melomaniac-sync' ) => $melomaniac_release->catalog_number,
	__( 'Formato', 'melomaniac-sync' )            => '' !== $melomaniac_release->format_detail ? $melomaniac_release->format_detail : $melomaniac_release->format_label(),
	__( 'País de prensaje', 'melomaniac-sync' )   => $melomaniac_release->country,
	__( 'Código de barra', 'melomaniac-sync' )    => $melomaniac_barcode,
);
?>
<div class="melomaniac-card melomaniac-preview">
	<div class="melomaniac-preview-cover">
		<?php if ( '' !== $melomaniac_release->cover_thumb_url ) : ?>
			<img
				src="<?php echo esc_url( $melomaniac_release->cover_thumb_url ); ?>"
				alt="<?php echo esc_attr( $melomaniac_release->display_name() ); ?>"
				width="220"
				height="220"
				loading="lazy"
			/>
		<?php else : ?>
			<div class="melomaniac-preview-cover-empty">
				<span><?php esc_html_e( 'Sin portada en Cover Art Archive', 'melomaniac-sync' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<div class="melomaniac-preview-body">
		<h2 class="melomaniac-preview-title"><?php echo esc_html( $melomaniac_release->title ); ?></h2>
		<p class="melomaniac-preview-artist"><?php echo esc_html( $melomaniac_release->artist ); ?></p>

		<table class="melomaniac-preview-meta">
			<tbody>
			<?php foreach ( $melomaniac_meta as $melomaniac_label => $melomaniac_value ) : ?>
				<?php if ( '' === trim( (string) $melomaniac_value ) ) { continue; } ?>
				<tr>
					<th scope="row"><?php echo esc_html( $melomaniac_label ); ?></th>
					<td><?php echo esc_html( $melomaniac_value ); ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! empty( $melomaniac_release->genres ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Género', 'melomaniac-sync' ); ?></th>
					<td><?php echo esc_html( implode( ', ', $melomaniac_release->genres ) ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $melomaniac_release->tracklist ) ) : ?>
			<details class="melomaniac-tracklist">
				<summary>
					<?php
					printf(
						/* translators: %d: number of tracks. */
						esc_html( _n( '%d canción', '%d canciones', count( $melomaniac_release->tracklist ), 'melomaniac-sync' ) ),
						count( $melomaniac_release->tracklist )
					);
					?>
				</summary>
				<ol>
					<?php foreach ( $melomaniac_release->tracklist as $melomaniac_track ) : ?>
						<li>
							<?php echo esc_html( isset( $melomaniac_track['title'] ) ? $melomaniac_track['title'] : '' ); ?>
							<?php if ( ! empty( $melomaniac_track['length'] ) ) : ?>
								<span class="melomaniac-track-length">(<?php echo esc_html( $melomaniac_track['length'] ); ?>)</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</details>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'MusicBrainz no tiene la lista de canciones de esta edición.', 'melomaniac-sync' ); ?></p>
		<?php endif; ?>

		<?php
		Melomaniac_Sync_Admin::render_view(
			'partial-product-fields',
			array(
				'prefix'     => 'melomaniac-preview',
				'tags'       => Melomaniac_Sync_Settings::genre_tag_enabled() ? $melomaniac_release->genres : array(),
				'categories' => Melomaniac_Sync_Catalog::categories(),
			)
		);
		?>

		<p class="melomaniac-preview-actions">
			<button
				type="button"
				class="button button-primary button-hero melomaniac-create-product"
				data-source="<?php echo esc_attr( $melomaniac_release->source ); ?>"
				data-release-id="<?php echo esc_attr( $melomaniac_release->source_id() ); ?>"
				data-barcode="<?php echo esc_attr( $melomaniac_barcode ); ?>"
			>
				<?php esc_html_e( 'Crear producto', 'melomaniac-sync' ); ?>
			</button>
			<button
				type="button"
				class="button-link melomaniac-reject-match"
				data-source="<?php echo esc_attr( $melomaniac_release->source ); ?>"
				data-release-id="<?php echo esc_attr( $melomaniac_release->source_id() ); ?>"
				data-barcode="<?php echo esc_attr( $melomaniac_barcode ); ?>"
			>
				<?php esc_html_e( 'No es este disco, corregirlo a mano', 'melomaniac-sync' ); ?>
			</button>
		</p>

		<?php if ( 'musicbrainz' === $melomaniac_release->source && '' !== $melomaniac_release->mbid ) : ?>
			<p class="description">
				<a href="<?php echo esc_url( 'https://musicbrainz.org/release/' . $melomaniac_release->mbid ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Ver esta edición en MusicBrainz', 'melomaniac-sync' ); ?>
				</a>
			</p>
		<?php elseif ( 'discogs' === $melomaniac_release->source && '' !== $melomaniac_release->discogs_id ) : ?>
			<p class="description">
				<?php esc_html_e( 'Datos tomados de Discogs, porque MusicBrainz no tenía este disco.', 'melomaniac-sync' ); ?>
				<a href="<?php echo esc_url( 'https://www.discogs.com/release/' . $melomaniac_release->discogs_id ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Ver esta edición en Discogs', 'melomaniac-sync' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</div>
