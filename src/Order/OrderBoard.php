<?php
/**
 * The board on the order screen and the ticket.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page's renderer draws a layout in millimetres (Front\PageRenderer); the board on an order shows each layer as
 * a share of itself, with the custom properties the builder's board uses, so one size in the stylesheet keeps every
 * layer to scale (spec: Cart, checkout and orders, Orders; D-195).
 *
 * @since 0.8.0
 */
final class OrderBoard {

	/**
	 * The board's rules, which the Build box and the ticket share: the layers placed as the builder's are, on a board
	 * of a fixed height whose width follows the build's own shape. It is drawn left to right in any language, so the
	 * wheels stay where they are on a right-to-left screen (D-203).
	 *
	 * @since 0.8.0
	 */
	public const string STYLES = '.cbuild-order__board{position:relative;display:block;block-size:16rem;aspect-ratio:var(--cbuild-board-ratio,1 / 4)}.cbuild-order__layer{position:absolute;inset-block-start:var(--cbuild-layer-y);inset-inline-start:var(--cbuild-layer-x);inline-size:var(--cbuild-layer-width);block-size:var(--cbuild-layer-height)}.cbuild-order__image{display:block;inline-size:100%;block-size:100%;object-fit:contain}';

	/**
	 * A drawn layout as the templates show it, or null where nothing is drawn.
	 *
	 * @since 0.8.0
	 *
	 * @param array{width: int, height: int, layers: list<array{slot: string, part: int, x: int, y: int, width: int, height: int, image: array{src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}}>} $board The renderer's board.
	 * @return array{style: string, layers: list<array{slot: string, style: string, src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}>}|null
	 */
	public static function placed( array $board ): ?array {
		$width  = $board['width'];
		$height = $board['height'];
		if ( $width < 1 || $height < 1 || array() === $board['layers'] ) {
			return null;
		}
		$layers = array();
		foreach ( $board['layers'] as $layer ) {
			$layers[] = array(
				'slot'    => $layer['slot'],
				'style'   => sprintf(
					'--cbuild-layer-x:%1$s%%;--cbuild-layer-y:%2$s%%;--cbuild-layer-width:%3$s%%;--cbuild-layer-height:%4$s%%',
					self::share( $layer['x'], $width ),
					self::share( $layer['y'], $height ),
					self::share( $layer['width'], $width ),
					self::share( $layer['height'], $height )
				),
				'src'     => $layer['image']['src'],
				'width'   => $layer['image']['width'],
				'height'  => $layer['image']['height'],
				'sources' => $layer['image']['sources'],
			);
		}
		return array(
			'style'  => '--cbuild-board-ratio:' . $width . ' / ' . $height,
			'layers' => $layers,
		);
	}

	/**
	 * A part of a whole as a percentage with at most four decimals, a point whatever the locale.
	 *
	 * @param int $part  The part.
	 * @param int $whole The whole, more than 0.
	 */
	private static function share( int $part, int $whole ): string {
		return rtrim( rtrim( sprintf( '%.4F', 100 * $part / $whole ), '0' ), '.' );
	}
}
