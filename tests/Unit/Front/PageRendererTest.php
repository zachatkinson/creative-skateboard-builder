<?php
/**
 * The page's renderer: the layout's layers as the page stacks them.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layer;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Service\LayerArt;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The picture: "One layout, drawn twice. The domain lays the board out from the specs. The page stacks the
 * layers as images." Plan, milestone 7: each layer has "its place and size in the layout's units; its sources and
 * its width and height", and a part without a layer image keeps its labelled photo (D-195).
 */
final class PageRendererTest extends TestCase {

	/**
	 * An 8.25 in deck, 32 in long, with its two trucks 200 mm either side of its middle and a wheel on the nose's left.
	 */
	private static function layout(): Layout {
		return new Layout(
			2095500,
			8128000,
			array(
				new Layer( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ),
				new Layer( SlotKey::TRUCKS, 200, 0, 2000000, 2095500, 762000 ),
				new Layer( SlotKey::TRUCKS, 200, 0, -2000000, 2095500, 762000 ),
				new Layer( SlotKey::WHEELS, 300, -700000, 2000000, 324000, 540000 ),
			),
			array()
		);
	}

	private static function art( int $attachment, bool $made = true ): LayerArt {
		return new LayerArt(
			$attachment,
			'https://example.com/master-' . $attachment . '.png',
			420,
			1626,
			$made ? array(
				'image/avif' => array(
					array( 'url' => 'https://example.com/a-105.avif', 'width' => 105, 'height' => 407 ),
					array( 'url' => 'https://example.com/a-210.avif', 'width' => 210, 'height' => 813 ),
				),
				'image/webp' => array(
					array( 'url' => 'https://example.com/a-105.webp', 'width' => 105, 'height' => 407 ),
				),
			) : array()
		);
	}

	public function test_each_layer_with_art_is_placed_from_the_layouts_top_left_in_stack_order(): void {
		$drawn = ( new PageRenderer() )->render( self::layout(), array( 100 => self::art( 10 ), 200 => self::art( 20, false ) ) );
		self::assertSame( 2095500, $drawn['width'] );
		self::assertSame( 8128000, $drawn['height'] );
		self::assertSame(
			array(
				array( SlotKey::DECK, 100, 0, 0, 2095500, 8128000 ),
				array( SlotKey::TRUCKS, 200, 0, 1683000, 2095500, 762000 ),
				array( SlotKey::TRUCKS, 200, 0, 5683000, 2095500, 762000 ),
			),
			array_map( static fn( array $layer ): array => array( $layer['slot'], $layer['part'], $layer['x'], $layer['y'], $layer['width'], $layer['height'] ), $drawn['layers'] ),
			'the nose is at the top; the wheels have no layer image, so they are not drawn'
		);
	}

	public function test_a_layer_offers_its_renditions_by_type_and_falls_back_to_the_master(): void {
		$drawn = ( new PageRenderer() )->render( self::layout(), array( 100 => self::art( 10 ), 200 => self::art( 20, false ) ) );
		self::assertSame(
			array(
				'src'     => 'https://example.com/master-10.png',
				'width'   => 420,
				'height'  => 1626,
				'sources' => array(
					array( 'type' => 'image/avif', 'srcset' => 'https://example.com/a-105.avif 105w, https://example.com/a-210.avif 210w' ),
					array( 'type' => 'image/webp', 'srcset' => 'https://example.com/a-105.webp 105w' ),
				),
			),
			$drawn['layers'][0]['image']
		);
		self::assertSame( array(), $drawn['layers'][1]['image']['sources'], 'renditions not made yet: the master alone' );
	}

	public function test_a_wheel_off_the_middle_is_placed_by_its_centre(): void {
		$drawn = ( new PageRenderer() )->render( self::layout(), array( 300 => self::art( 30 ) ) );
		self::assertSame( array( 185750, 1794000 ), array( $drawn['layers'][0]['x'], $drawn['layers'][0]['y'] ) );
	}

	public function test_nothing_laid_out_draws_nothing(): void {
		self::assertSame(
			array(
				'width'  => 0,
				'height' => 0,
				'layers' => array(),
			),
			( new PageRenderer() )->render( new Layout( 0, 0, array(), array() ), array( 100 => self::art( 10 ) ) )
		);
	}
}
