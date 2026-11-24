<?php
/**
 * LayerArtwork: each drawn part's layer image and the renditions made of it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use PHPUnit\Framework\TestCase;

/**
 * Spec, The picture: "Each part that has a layer image is drawn to scale"; "Where a part has no layer image yet, the
 * picture shows its product photo instead." The page offers the renditions the part's job made at the sizes its slot
 * draws it, and the master until they are made (D-194, D-195).
 */
final class LayerArtworkTest extends TestCase {

	public function test_each_drawn_part_with_a_layer_image_has_its_master_and_the_renditions_made_at_its_size(): void {
		$deck   = Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32' ), 100 );
		$trucks = Parts::of( array( 'axle_width' => '8.25' ), 200 );
		$wheels = Parts::of( array( 'wheel_diameter' => '54' ), 300 );
		$picks  = array( SlotKey::DECK => $deck, SlotKey::TRUCKS => $trucks, SlotKey::WHEELS => $wheels );
		$layout = Layout::of( Parts::picks( $picks ) );

		$layers              = new InMemoryLayerImages();
		$layers->attachments = array( 100 => 10, 200 => 20 );
		$layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			20 => new ImageFacts( 'image/png', 420, 152, true ),
		);
		$renditions = new InMemoryLayerRenditions();
		$sizes      = Renditions::drawn( SlotKey::DECK, $deck->specs, 420, 1626 );
		$renditions->make( 10, $sizes );

		$art = ( new LayerArtwork( $layers, $renditions ) )->of( $layout, $picks );
		self::assertSame( array( 100, 200 ), array_keys( $art ), 'the wheels have no layer image; each part once, though the trucks are two layers' );
		self::assertSame( array( 10, 'https://example.com/layer-10.png', 420, 1626 ), array( $art[100]->attachment, $art[100]->url, $art[100]->width, $art[100]->height ) );
		self::assertSame( $sizes, array_map( static fn( array $file ): array => array( $file['width'], $file['height'] ), $art[100]->sources['image/webp'] ) );
		self::assertSame( array(), $art[200]->sources, 'nothing made yet: the master alone' );
		self::assertSame( array( array( 100, 200, 300 ) ), $layers->together, 'the drawn parts\' layer images asked for together, once (D-202)' );
	}

	// Issue #46: the grip and the hardware are drawn on the top alone, so their art is found from it.
	public function test_the_parts_only_the_top_draws_have_their_art_found_with_the_rest_in_one_ask(): void {
		$deck     = Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32' ), 100 );
		$grip     = Parts::of( array(), 700 );
		$hardware = Parts::of( array( 'bolt_length' => '1' ), 600 );
		$picks    = array( SlotKey::DECK => $deck, SlotKey::GRIP => $grip, SlotKey::HARDWARE => $hardware );

		$layers              = new InMemoryLayerImages();
		$layers->attachments = array( 100 => 10, 700 => 70, 600 => 60 );
		$layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			70 => new ImageFacts( 'image/png', 51, 1626, true ),
			60 => new ImageFacts( 'image/png', 40, 40, true ),
		);
		$art = ( new LayerArtwork( $layers, new InMemoryLayerRenditions() ) )->of( Layout::of( Parts::picks( $picks ) ), $picks );

		self::assertSame( array( 100, 700, 600 ), array_keys( $art ), 'the hardware\'s eight heads read once' );
		self::assertSame( array( array( 100, 700, 600 ) ), $layers->together );
	}

	public function test_an_image_no_longer_in_the_media_library_is_no_art_and_nothing_laid_out_reads_nothing(): void {
		$deck                = Parts::of( array( 'deck_width' => '8.25' ), 100 );
		$layers              = new InMemoryLayerImages();
		$layers->attachments = array( 100 => 10 );
		$art                 = new LayerArtwork( $layers, new InMemoryLayerRenditions() );
		self::assertSame( array(), $art->of( Layout::of( Parts::picks( array( SlotKey::DECK => $deck ) ) ), array( SlotKey::DECK => $deck ) ) );
		self::assertSame( array(), $art->of( new Layout( 0, 0, array(), array() ), array() ) );
	}

	public function test_a_layer_is_drawn_only_from_the_part_handed_in_for_its_slot(): void {
		$deck                = Parts::of( array( 'deck_width' => '8.25' ), 100 );
		$trucks              = Parts::of( array( 'axle_width' => '8.25' ), 200 );
		$layout              = Layout::of( Parts::picks( array( SlotKey::DECK => $deck, SlotKey::TRUCKS => $trucks ) ) );
		$layers              = new InMemoryLayerImages();
		$layers->attachments = array( 100 => 10, 200 => 20 );
		$layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			20 => new ImageFacts( 'image/png', 420, 152, true ),
		);
		$art                 = new LayerArtwork( $layers, new InMemoryLayerRenditions() );
		self::assertSame( array( 200 ), array_keys( $art->of( $layout, array( SlotKey::TRUCKS => $trucks ) ) ), 'no part for the deck\'s slot' );
		self::assertSame( array( 200 ), array_keys( $art->of( $layout, array( SlotKey::DECK => $trucks, SlotKey::TRUCKS => $trucks ) ) ), 'the trucks handed in as the deck' );
	}

	// D-308: each deck on the deck step, in one look-up, by its ID: one with no layer image, or one whose image is gone
	// from the Media Library, has none.
	public function test_each_deck_with_a_layer_image_has_its_art_and_one_whose_image_is_gone_has_none(): void {
		$decks               = array(
			7 => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32' ), 100 ),
			3 => Parts::of( array( 'deck_width' => '8.5', 'deck_length' => '32' ), 101 ),
			Parts::of( array( 'deck_width' => '8', 'deck_length' => '32' ), 102 ),
			Parts::of( array( 'deck_width' => '8', 'deck_length' => '32' ), 103 ),
		);
		$layers              = new InMemoryLayerImages();
		$layers->attachments = array( 100 => 10, 101 => 11, 102 => 12 );
		$layers->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			11 => new ImageFacts( 'image/png', 432, 1626, true ),
		);
		$art = ( new LayerArtwork( $layers, new InMemoryLayerRenditions() ) )->decks( $decks );
		self::assertSame( array( 100, 101 ), array_keys( $art ), 'the image of 102 is gone; 103 has none' );
		self::assertSame( array( 10, 432 ), array( $art[100]->attachment, $art[101]->width ) );
		self::assertSame( array( array( 100, 101, 102, 103 ) ), $layers->together, 'asked for together, once' );
		self::assertSame( array(), ( new LayerArtwork( $layers, new InMemoryLayerRenditions() ) )->decks( array() ) );
	}
}
