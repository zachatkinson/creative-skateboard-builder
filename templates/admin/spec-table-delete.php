<?php
/**
 * WooCommerce > Builder: the question before a chart is deleted.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var int    $id    The chart's ID.
 * @var string $name  The maker's name.
 * @var string $nonce The forms' nonce action.
 * @var string $back  The page's own address.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1>
		<?php
		/* translators: %s: a truck maker's name, such as Independent. */
		echo esc_html( sprintf( __( 'Delete the chart %s?', 'creative-skateboard-builder' ), $name ) );
		?>
	</h1>
	<p><?php esc_html_e( 'A part that names this chart keeps the name and loses the chart\'s figures, so the builder stops offering it until it has figures of its own. The Catalogue check lists each one. A deleted chart cannot be brought back.', 'creative-skateboard-builder' ); ?></p>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php wp_nonce_field( $nonce ); ?>
		<input type="hidden" name="cbuild_action" value="delete" />
		<input type="hidden" name="cbuild_table" value="<?php echo esc_attr( (string) $id ); ?>" />
		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Delete the chart', 'creative-skateboard-builder' ); ?></button>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'table', $id, $back ) ); ?>"><?php esc_html_e( 'Keep it', 'creative-skateboard-builder' ); ?></a>
		</p>
	</form>
</div>
