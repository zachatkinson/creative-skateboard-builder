<?php
/**
 * The board on the order screen and the ticket.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Order;

use CreativeBranding\SkateboardBuilder\Order\OrderBoard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Cart, checkout and orders, Orders: the layers in the order's Build box and on the assembly ticket. The page's
 * renderer places each layer in millimetres; the board shows each as a share of itself, so one size in the stylesheet
 * keeps every layer to scale, as the builder's board does.
 */
#[CoversClass( OrderBoard::class )]
final class OrderBoardTest extends TestCase {

	public function test_each_layer_is_placed_as_a_share_of_the_board_from_the_bottom(): void {
		$placed = OrderBoard::placed(
			array(
				'width'  => 216,
				'height' => 813,
				'layers' => array(
					array(
						'slot'   => 'deck',
						'part'   => 103,
						'x'      => 0,
						'y'      => 0,
						'width'  => 216,
						'height' => 813,
						'image'  => array(
							'src'     => 'https://shop.test/deck.png',
							'width'   => 432,
							'height'  => 1626,
							'sources' => array(
								array(
									'type'   => 'image/avif',
									'srcset' => 'https://shop.test/deck-108.avif 108w, https://shop.test/deck-216.avif 216w',
								),
							),
						),
					),
					array(
						'slot'   => 'trucks',
						'part'   => 149,
						'x'      => 3,
						'y'      => 120,
						'width'  => 210,
						'height' => 60,
						'image'  => array(
							'src'     => 'https://shop.test/trucks.png',
							'width'   => 420,
							'height'  => 120,
							'sources' => array(),
						),
					),
				),
			)
		);
		self::assertNotNull( $placed );
		self::assertSame( '--cbuild-board-ratio:216 / 813', $placed['style'] );
		self::assertCount( 2, $placed['layers'] );
		self::assertSame(
			array(
				'slot'    => 'deck',
				'style'   => '--cbuild-layer-x:0%;--cbuild-layer-y:0%;--cbuild-layer-width:100%;--cbuild-layer-height:100%',
				'src'     => 'https://shop.test/deck.png',
				'width'   => 432,
				'height'  => 1626,
				'sources' => array(
					array(
						'type'   => 'image/avif',
						'srcset' => 'https://shop.test/deck-108.avif 108w, https://shop.test/deck-216.avif 216w',
					),
				),
			),
			$placed['layers'][0]
		);
		self::assertSame( '--cbuild-layer-x:1.3889%;--cbuild-layer-y:14.7601%;--cbuild-layer-width:97.2222%;--cbuild-layer-height:7.3801%', $placed['layers'][1]['style'] );
		self::assertSame( array(), $placed['layers'][1]['sources'] );
	}

	public function test_a_board_with_no_layer_or_no_size_is_not_drawn(): void {
		self::assertNull(
			OrderBoard::placed(
				array(
					'width'  => 216,
					'height' => 813,
					'layers' => array(),
				)
			)
		);
		$layer = array(
			'slot'   => 'deck',
			'part'   => 103,
			'x'      => 0,
			'y'      => 0,
			'width'  => 1,
			'height' => 1,
			'image'  => array(
				'src'     => 'https://shop.test/deck.png',
				'width'   => 1,
				'height'  => 1,
				'sources' => array(),
			),
		);
		self::assertNull(
			OrderBoard::placed(
				array(
					'width'  => 0,
					'height' => 813,
					'layers' => array( $layer ),
				)
			)
		);
		self::assertNull(
			OrderBoard::placed(
				array(
					'width'  => 216,
					'height' => 0,
					'layers' => array( $layer ),
				)
			)
		);
	}

	public function test_a_board_one_millimetre_wide_or_long_is_drawn(): void {
		$layer = array(
			'slot'   => 'deck',
			'part'   => 103,
			'x'      => 0,
			'y'      => 0,
			'width'  => 1,
			'height' => 1,
			'image'  => array(
				'src'     => 'https://shop.test/deck.png',
				'width'   => 2,
				'height'  => 2,
				'sources' => array(),
			),
		);
		self::assertSame( '--cbuild-board-ratio:1 / 813', OrderBoard::placed( array( 'width' => 1, 'height' => 813, 'layers' => array( $layer ) ) )['style'] ?? null );
		self::assertSame( '--cbuild-board-ratio:216 / 1', OrderBoard::placed( array( 'width' => 216, 'height' => 1, 'layers' => array( $layer ) ) )['style'] ?? null );
	}
}
