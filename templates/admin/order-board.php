<?php
/**
 * A build's picture on an order: the board's layers from the bottom, described by the build in one sentence, so
 * each layer is empty to a screen reader (spec: Cart, checkout and orders, Orders). Shared by the Build box and the
 * assembly ticket.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var array{style: string, layers: list<array{slot: string, style: string, src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}>} $cbuild_board       The board.
 * @var string                                                                                                                                                             $cbuild_described The build in a sentence.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<figure class="cbuild-order__picture">
	<div class="cbuild-order__board" dir="ltr" style="<?php echo esc_attr( $cbuild_board['style'] ); ?>">
		<?php foreach ( $cbuild_board['layers'] as $cbuild_layer ) : ?>
			<picture class="cbuild-order__layer cbuild-order__layer--<?php echo esc_attr( $cbuild_layer['slot'] ); ?>" style="<?php echo esc_attr( $cbuild_layer['style'] ); ?>">
				<?php foreach ( $cbuild_layer['sources'] as $cbuild_source ) : ?>
					<source type="<?php echo esc_attr( $cbuild_source['type'] ); ?>" srcset="<?php echo esc_attr( $cbuild_source['srcset'] ); ?>" sizes="16rem">
				<?php endforeach; ?>
				<img class="cbuild-order__image" src="<?php echo esc_url( $cbuild_layer['src'] ); ?>" width="<?php echo esc_attr( (string) $cbuild_layer['width'] ); ?>" height="<?php echo esc_attr( (string) $cbuild_layer['height'] ); ?>" alt="" decoding="async">
			</picture>
		<?php endforeach; ?>
	</div>
	<figcaption><?php echo esc_html( $cbuild_described ); ?></figcaption>
</figure>
