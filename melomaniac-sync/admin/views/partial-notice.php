<?php
/**
 * Inline notice.
 *
 * @package Melomaniac_Sync
 *
 * @var array $data {
 *     @type string $type       success, warning or error.
 *     @type string $message    Message body.
 *     @type string $link       Optional action URL.
 *     @type string $link_label Optional action label.
 * }
 */

defined( 'ABSPATH' ) || exit;

$melomaniac_types = array(
	'success' => 'notice-success',
	'warning' => 'notice-warning',
	'error'   => 'notice-error',
	'info'    => 'notice-info',
);

$melomaniac_type  = isset( $data['type'], $melomaniac_types[ $data['type'] ] ) ? $melomaniac_types[ $data['type'] ] : 'notice-info';
$melomaniac_link  = isset( $data['link'] ) ? (string) $data['link'] : '';
$melomaniac_label = isset( $data['link_label'] ) ? (string) $data['link_label'] : '';
?>
<div class="notice <?php echo esc_attr( $melomaniac_type ); ?> melomaniac-notice">
	<p>
		<?php echo esc_html( isset( $data['message'] ) ? $data['message'] : '' ); ?>
		<?php if ( '' !== $melomaniac_link && '' !== $melomaniac_label ) : ?>
			<a href="<?php echo esc_url( $melomaniac_link ); ?>"><?php echo esc_html( $melomaniac_label ); ?></a>
		<?php endif; ?>
	</p>
</div>
