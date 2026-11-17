<?php
/**
 * ComposedRenderer: the second renderer of the one layout.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerArt;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Service\ComposedRenderer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The picture: "One layout, drawn twice." Audit, DRY: "A test draws one layout both ways and compares where
 * each layer lands" (plan, milestone 7; D-195, D-196).
 */
final class ComposedRendererTest extends TestCase {

	public function test_the_composer_and_the_page_place_each_layer_at_the_same_share_of_the_board(): void {
		$picks  = array(
			SlotKey::DECK   => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32' ), 100 ),
			SlotKey::TRUCKS => Parts::of( array( 'axle_width' => '8.25' ), 200 ),
		);
		$layout = Layout::of( Parts::picks( $picks ) );
		// Images the very shape of their layers, so each fills its box and the two renderers can be compared.
		$art    = array(
			100 => new LayerArt( 10, 'https://example.com/10.png', 2095500, 8128000, array() ),
			200 => new LayerArt( 20, 'https://example.com/20.png', 2095500, 762000, array() ),
		);
		$canvas = new Canvas( 1000, 4000, 'image/webp' );
		$drawn  = ( new ComposedRenderer( $canvas ) )->render( $layout, $art );
		self::assertEquals( Composition::of( $canvas, $layout, $art ), $drawn );
		$page = ( new PageRenderer() )->render( $layout, $art );
		self::assertCount( 3, $drawn->placements );
		$board = array( $drawn->placements[0]->x, $drawn->placements[0]->y, $drawn->placements[0]->width, $drawn->placements[0]->height );
		foreach ( $page['layers'] as $at => $layer ) {
			$placed = $drawn->placements[ $at ];
			self::assertEqualsWithDelta( $layer['y'] / $page['height'], ( $placed->y - $board[1] ) / $board[3], 0.002, $layer['slot'] . ' down' );
			self::assertEqualsWithDelta( $layer['height'] / $page['height'], $placed->height / $board[3], 0.002, $layer['slot'] . ' tall' );
			self::assertEqualsWithDelta( $layer['x'] / $page['width'], ( $placed->x - $board[0] ) / $board[2], 0.002, $layer['slot'] . ' across' );
		}
	}
}
