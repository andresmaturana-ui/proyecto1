<?php
/**
 * Bulk import screen.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string             $batch_id       Batch being viewed, empty on the landing screen.
 *     @type array              $summary        Counts by status for that batch.
 *     @type array[]            $items          That batch's items.
 *     @type object[]           $recent_batches Recent batches, for the landing screen.
 *     @type array<int,string>  $categories     Product categories.
 *     @type string             $message        One-off error, e.g. an empty barcode list.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_batch_id = isset( $data['batch_id'] ) ? (string) $data['batch_id'] : '';
$melomaniac_summary  = isset( $data['summary'] ) ? $data['summary'] : array();
$melomaniac_items    = isset( $data['items'] ) ? $data['items'] : array();
$melomaniac_recent   = isset( $data['recent_batches'] ) ? $data['recent_batches'] : array();
$melomaniac_categories = isset( $data['categories'] ) ? $data['categories'] : array();
$melomaniac_message  = isset( $data['message'] ) ? (string) $data['message'] : '';

$melomaniac_labels = array(
	'pendiente'     => __( 'Pendiente', 'melomaniac-sync' ),
	'creado'        => __( 'Creado', 'melomaniac-sync' ),
	'no_encontrado' => __( 'No encontrado', 'melomaniac-sync' ),
	'duplicado'     => __( 'Duplicado', 'melomaniac-sync' ),
	'sin_cupo'      => __( 'Sin cupo', 'melomaniac-sync' ),
	'error'         => __( 'Error', 'melomaniac-sync' ),
	'cancelado'     => __( 'Cancelado', 'melomaniac-sync' ),
);
?>
<div class="wrap melomaniac-sync">
	<h1><?php esc_html_e( 'Carga masiva', 'melomaniac-sync' ); ?></h1>

	<?php if ( '' !== $melomaniac_message ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $melomaniac_message ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' === $melomaniac_batch_id ) : ?>

		<div class="melomaniac-card">
			<h2><?php esc_html_e( 'Cargar varios discos de una vez', 'melomaniac-sync' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Pega un código de barra por línea. Melomaniac Sync los busca uno por uno en segundo plano (respetando el límite de MusicBrainz) y crea un producto como borrador por cada uno que encuentre. Los que no encuentre quedan marcados para cargarlos a mano después.', 'melomaniac-sync' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( Melomaniac_Sync_Bulk_Page::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Melomaniac_Sync_Bulk_Page::ACTION_START ); ?>" />

				<p>
					<label for="melomaniac-bulk-barcodes"><?php esc_html_e( 'Códigos de barra', 'melomaniac-sync' ); ?></label><br />
					<textarea id="melomaniac-bulk-barcodes" name="barcodes" rows="12" class="large-text code" placeholder="016861979546&#10;0731458123452&#10;7501234567890" required></textarea>
				</p>

				<h3><?php esc_html_e( 'Precio, stock y clasificación', 'melomaniac-sync' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Se aplican por igual a todos los discos de esta carga.', 'melomaniac-sync' ); ?></p>
				<?php
				Melomaniac_Sync_Admin::render_view(
					'partial-product-fields',
					array(
						'prefix'     => 'melomaniac-bulk',
						'tags'       => array(),
						'categories' => $melomaniac_categories,
					)
				);
				?>

				<?php submit_button( __( 'Comenzar', 'melomaniac-sync' ) ); ?>
			</form>
		</div>

		<?php if ( ! empty( $melomaniac_recent ) ) : ?>
			<div class="melomaniac-card">
				<h2><?php esc_html_e( 'Cargas recientes', 'melomaniac-sync' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Fecha', 'melomaniac-sync' ); ?></th>
							<th><?php esc_html_e( 'Discos', 'melomaniac-sync' ); ?></th>
							<th><?php esc_html_e( 'Pendientes', 'melomaniac-sync' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $melomaniac_recent as $melomaniac_batch ) : ?>
							<tr>
								<td><?php echo esc_html( $melomaniac_batch->created_at ); ?></td>
								<td><?php echo esc_html( (string) $melomaniac_batch->total ); ?></td>
								<td><?php echo esc_html( (string) $melomaniac_batch->pendientes ); ?></td>
								<td>
									<a href="<?php echo esc_url( Melomaniac_Sync_Admin_Menu::bulk_url( array( 'batch' => $melomaniac_batch->batch_id ) ) ); ?>">
										<?php esc_html_e( 'Ver', 'melomaniac-sync' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	<?php else : ?>

		<div class="melomaniac-card" data-melomaniac-bulk-status data-batch-id="<?php echo esc_attr( $melomaniac_batch_id ); ?>">
			<h2><?php esc_html_e( 'Progreso de la carga', 'melomaniac-sync' ); ?></h2>

			<div class="melomaniac-bulk-summary" data-melomaniac-bulk-summary>
				<?php foreach ( $melomaniac_labels as $melomaniac_key => $melomaniac_label ) : ?>
					<span class="melomaniac-bulk-count" data-status="<?php echo esc_attr( $melomaniac_key ); ?>">
						<?php echo esc_html( $melomaniac_label ); ?>: <strong data-count><?php echo esc_html( (string) ( $melomaniac_summary[ $melomaniac_key ] ?? 0 ) ); ?></strong>
					</span>
				<?php endforeach; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="melomaniac-bulk-cancel-form">
				<?php wp_nonce_field( Melomaniac_Sync_Bulk_Page::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Melomaniac_Sync_Bulk_Page::ACTION_CANCEL ); ?>" />
				<input type="hidden" name="batch_id" value="<?php echo esc_attr( $melomaniac_batch_id ); ?>" />
				<?php submit_button( __( 'Cancelar lo que falta', 'melomaniac-sync' ), 'delete', 'submit', false ); ?>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Código', 'melomaniac-sync' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'melomaniac-sync' ); ?></th>
						<th><?php esc_html_e( 'Producto', 'melomaniac-sync' ); ?></th>
					</tr>
				</thead>
				<tbody data-melomaniac-bulk-items>
					<?php foreach ( $melomaniac_items as $melomaniac_item ) : ?>
						<tr data-barcode="<?php echo esc_attr( $melomaniac_item['barcode'] ); ?>">
							<td><?php echo esc_html( $melomaniac_item['barcode'] ); ?></td>
							<td data-cell="status">
								<?php echo esc_html( $melomaniac_labels[ $melomaniac_item['status'] ] ?? $melomaniac_item['status'] ); ?>
								<?php if ( ! empty( $melomaniac_item['message'] ) && 'creado' !== $melomaniac_item['status'] ) : ?>
									<span class="description"> — <?php echo esc_html( $melomaniac_item['message'] ); ?></span>
								<?php endif; ?>
							</td>
							<td data-cell="product">
								<?php if ( ! empty( $melomaniac_item['editUrl'] ) ) : ?>
									<a href="<?php echo esc_url( $melomaniac_item['editUrl'] ); ?>"><?php echo esc_html( $melomaniac_item['name'] ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p><a href="<?php echo esc_url( Melomaniac_Sync_Admin_Menu::bulk_url() ); ?>">&larr; <?php esc_html_e( 'Cargar otro lote', 'melomaniac-sync' ); ?></a></p>

	<?php endif; ?>
</div>
