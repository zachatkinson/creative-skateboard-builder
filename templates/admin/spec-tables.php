<?php
/**
 * WooCommerce > Builder: the makers' charts, and the form that adds one.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{name: string, sizes: int, url: string}> $charts  The charts, by name.
 * @var string                                             $notice  What happened, '' when nothing did.
 * @var bool                                               $failed  Whether the notice is of something refused.
 * @var string                                             $name    The name typed for a new chart, when it was refused.
 * @var string                                             $problem What was wrong with it, '' when nothing was.
 * @var string                                             $nonce   The forms' nonce action.
 * @var string                                             $back    The page's own address.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Builder', 'creative-skateboard-builder' ); ?></h1>
	<?php if ( '' !== $notice ) : ?>
		<div class="notice <?php echo $failed ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<h2><?php esc_html_e( 'Spec tables', 'creative-skateboard-builder' ); ?></h2>
	<p><?php esc_html_e( 'A truck maker\'s size chart: for each size, the axle width, the decks it fits and the rest. A truck that names its size in a chart, on its Part specs, takes that row\'s figures.', 'creative-skateboard-builder' ); ?></p>
	<?php if ( array() === $charts ) : ?>
		<p><?php esc_html_e( 'There are no charts yet.', 'creative-skateboard-builder' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Maker', 'creative-skateboard-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sizes', 'creative-skateboard-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $charts as $cbuild_chart ) : ?>
					<tr>
						<th scope="row"><a href="<?php echo esc_url( $cbuild_chart['url'] ); ?>"><?php echo esc_html( $cbuild_chart['name'] ); ?></a></th>
						<td><?php echo esc_html( number_format_i18n( $cbuild_chart['sizes'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Add a chart', 'creative-skateboard-builder' ); ?></h2>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php wp_nonce_field( $nonce ); ?>
		<input type="hidden" name="cbuild_action" value="add" />
		<?php if ( '' !== $problem ) : ?>
			<div class="notice notice-error inline" id="cbuild-name-problem"><p><?php echo esc_html( $problem ); ?></p></div>
		<?php endif; ?>
		<p>
			<label for="cbuild-name"><?php esc_html_e( 'Maker\'s name', 'creative-skateboard-builder' ); ?></label><br />
			<input
				type="text"
				class="regular-text"
				id="cbuild-name"
				name="cbuild_name"
				value="<?php echo esc_attr( $name ); ?>"
				autocomplete="off"
				<?php if ( '' !== $problem ) : ?>
					aria-invalid="true" aria-describedby="cbuild-name-problem"
				<?php endif; ?>
			/>
		</p>
		<?php submit_button( __( 'Add chart', 'creative-skateboard-builder' ), 'secondary', 'submit', true ); ?>
	</form>
</div>
