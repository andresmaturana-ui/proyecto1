<?php
/**
 * Plan and monthly quota summary.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data Output of Melomaniac_Sync_Usage::summary().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $data ) || ! isset( $data['plan_label'] ) ) {
	return;
}

$melomaniac_limit     = isset( $data['limit'] ) ? $data['limit'] : null;
$melomaniac_used      = isset( $data['used'] ) ? (int) $data['used'] : 0;
$melomaniac_near      = ! empty( $data['near_limit'] );
$melomaniac_blocked   = null !== $melomaniac_limit && $melomaniac_used >= (int) $melomaniac_limit;
$melomaniac_upgrade   = isset( $data['upgrade_url'] ) ? (string) $data['upgrade_url'] : '';
$melomaniac_has_bulk  = Melomaniac_Sync_Licensing::has_bulk_upload();

$melomaniac_state = 'is-ok';

if ( $melomaniac_blocked ) {
	$melomaniac_state = 'is-blocked';
} elseif ( $melomaniac_near ) {
	$melomaniac_state = 'is-warning';
}
?>
<div class="melomaniac-plan <?php echo esc_attr( $melomaniac_state ); ?>">
	<p class="melomaniac-plan-title">
		<?php
		printf(
			/* translators: %s: plan name. */
			esc_html__( 'Plan actual: %s', 'melomaniac-sync' ),
			'<strong>' . esc_html( $data['plan_label'] ) . '</strong>'
		);
		?>
	</p>

	<?php if ( null === $melomaniac_limit ) : ?>
		<p class="melomaniac-plan-usage"><?php esc_html_e( 'Discos ilimitados este mes.', 'melomaniac-sync' ); ?></p>
	<?php else : ?>
		<p class="melomaniac-plan-usage">
			<?php
			printf(
				/* translators: 1: discs used this month, 2: monthly limit. */
				esc_html__( '%1$d de %2$d discos usados este mes.', 'melomaniac-sync' ),
				$melomaniac_used,
				(int) $melomaniac_limit
			);
			?>
		</p>

		<?php if ( $melomaniac_blocked ) : ?>
			<p class="melomaniac-plan-message"><?php echo esc_html( Melomaniac_Sync_Usage::limit_reached_message() ); ?></p>
		<?php elseif ( $melomaniac_near ) : ?>
			<p class="melomaniac-plan-message">
				<?php esc_html_e( 'Te estás acercando al tope del mes.', 'melomaniac-sync' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( '' !== $melomaniac_upgrade && ( $melomaniac_blocked || $melomaniac_near || ! $melomaniac_has_bulk ) ) : ?>
		<p class="melomaniac-plan-actions">
			<a href="<?php echo esc_url( $melomaniac_upgrade ); ?>" class="button <?php echo $melomaniac_blocked ? 'button-primary' : 'button-secondary'; ?>">
				<?php esc_html_e( 'Ver planes', 'melomaniac-sync' ); ?>
			</a>
			<?php if ( ! $melomaniac_has_bulk ) : ?>
				<span class="description">
					<?php esc_html_e( 'El plan Premium suma discos ilimitados y carga masiva por código de barra.', 'melomaniac-sync' ); ?>
				</span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
