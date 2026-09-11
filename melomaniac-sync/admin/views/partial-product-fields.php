<?php
/**
 * Price, stock, categories and tags, chosen before the product is created.
 *
 * Shared by the release preview and the manual entry form so both offer the
 * same decisions in the same order.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string            $prefix     Field name prefix, so ids stay unique.
 *     @type string[]          $tags       Tag names to prefill.
 *     @type array<int,string> $categories Existing product categories.
 *     @type int[]             $selected   Categories ticked by default.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_prefix     = isset( $data['prefix'] ) ? (string) $data['prefix'] : 'melomaniac-fields';
$melomaniac_tags       = isset( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : array();
$melomaniac_categories = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$melomaniac_selected   = isset( $data['selected'] ) && is_array( $data['selected'] ) ? array_map( 'intval', $data['selected'] ) : array();
?>
<div class="melomaniac-fields" data-melomaniac-fields>
	<div class="melomaniac-fields-row">
		<p class="melomaniac-field">
			<label for="<?php echo esc_attr( $melomaniac_prefix ); ?>-price">
				<?php esc_html_e( 'Precio', 'melomaniac-sync' ); ?>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( $melomaniac_prefix ); ?>-price"
				name="price"
				class="melomaniac-field-price"
				inputmode="decimal"
				value="<?php echo esc_attr( Melomaniac_Sync_Settings::default_price() ); ?>"
			/>
		</p>

		<p class="melomaniac-field">
			<label for="<?php echo esc_attr( $melomaniac_prefix ); ?>-stock">
				<?php esc_html_e( 'Cantidad', 'melomaniac-sync' ); ?>
			</label>
			<input
				type="number"
				id="<?php echo esc_attr( $melomaniac_prefix ); ?>-stock"
				name="stock"
				class="melomaniac-field-stock"
				min="0"
				step="1"
				value="<?php echo esc_attr( (string) Melomaniac_Sync_Settings::default_stock() ); ?>"
			/>
		</p>
	</div>

	<div class="melomaniac-fields-row">
		<div class="melomaniac-field melomaniac-field-wide">
			<label for="<?php echo esc_attr( $melomaniac_prefix ); ?>-categories">
				<?php esc_html_e( 'Categorías', 'melomaniac-sync' ); ?>
			</label>

			<?php if ( empty( $melomaniac_categories ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Todavía no hay categorías en la tienda. Escribe una abajo y se crea.', 'melomaniac-sync' ); ?>
				</p>
			<?php else : ?>
				<select
					id="<?php echo esc_attr( $melomaniac_prefix ); ?>-categories"
					name="category_ids[]"
					class="melomaniac-field-categories"
					multiple
					size="<?php echo esc_attr( (string) min( 6, max( 3, count( $melomaniac_categories ) ) ) ); ?>"
				>
					<?php foreach ( $melomaniac_categories as $melomaniac_id => $melomaniac_name ) : ?>
						<option
							value="<?php echo esc_attr( (string) $melomaniac_id ); ?>"
							<?php selected( in_array( (int) $melomaniac_id, $melomaniac_selected, true ) ); ?>
						>
							<?php echo esc_html( $melomaniac_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Puedes elegir varias con Ctrl (Cmd en Mac). Si no eliges ninguna, se asigna sola según el formato.', 'melomaniac-sync' ); ?>
				</p>
			<?php endif; ?>

			<input
				type="text"
				id="<?php echo esc_attr( $melomaniac_prefix ); ?>-new-category"
				name="new_category"
				class="melomaniac-field-new-category"
				placeholder="<?php esc_attr_e( 'Crear una categoría nueva', 'melomaniac-sync' ); ?>"
			/>
		</div>

		<div class="melomaniac-field melomaniac-field-wide">
			<label for="<?php echo esc_attr( $melomaniac_prefix ); ?>-tags">
				<?php esc_html_e( 'Etiquetas', 'melomaniac-sync' ); ?>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( $melomaniac_prefix ); ?>-tags"
				name="tags"
				class="melomaniac-field-tags"
				value="<?php echo esc_attr( implode( ', ', $melomaniac_tags ) ); ?>"
				placeholder="<?php esc_attr_e( 'thrash metal, chileno', 'melomaniac-sync' ); ?>"
			/>
			<p class="description">
				<?php esc_html_e( 'Separadas por coma. Las que no existan se crean.', 'melomaniac-sync' ); ?>
			</p>
		</div>
	</div>
</div>
