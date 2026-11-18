<?php
/**
 * Each part's layer image, made at the sizes the picture draws it (D-194).
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use PHPUnit\Framework\TestCase;

final class PartRenditionsTest extends TestCase {

	private InMemoryKits $kits;

	private InMemorySpecIndex $index;

	private InMemoryLayerImages $layers;

	private InMemoryLayerRenditions $renditions;

	protected function setUp(): void {
		$this->kits       = new InMemoryKits();
		$this->index      = new InMemorySpecIndex();
		$this->layers     = new InMemoryLayerImages();
		$this->renditions = new InMemoryLayerRenditions();
	}

	private function made(): PartRenditions {
		return new PartRenditions( $this->kits, $this->index, $this->layers, $this->renditions );
	}

	/**
	 * A part with a deck's width, indexed.
	 *
	 * @param int ...$ids Their IDs.
	 */
	private function indexed( int ...$ids ): void {
		foreach ( $ids as $id ) {
			$this->index->replace( $id, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), ( new FakeClock() )->now() );
		}
	}

	/**
	 * A layer image for a part.
	 *
	 * @param int       $part       The part.
	 * @param int       $attachment Its image.
	 * @param bool|null $readable   Whether its facts can be read.
	 */
	private function layer( int $part, int $attachment, bool $readable = true ): void {
		$this->layers->attachments[ $part ] = $attachment;
		if ( $readable ) {
			$this->layers->images[ $attachment ] = new ImageFacts( 'image/png', 420, 1626, true );
		}
	}

	public function test_one_call_skips_a_part_it_cannot_size_and_goes_on_to_the_next(): void {
		// 1 has no layer image; 2's cannot be read; 3 is not indexed yet; 4 is drawn.
		$this->indexed( 1, 2, 4 );
		$this->layer( 2, 20, false );
		$this->layer( 3, 30 );
		$this->layer( 4, 40 );
		$this->kits->slotsOf = array_fill_keys( array( 1, 2, 3, 4 ), array( 'deck' ) );

		$this->made()->make( 1, 2, 3, 4 );
		$specs = $this->index->specsOf( 4 )[4];
		self::assertSame( array( array( 40, Renditions::drawn( 'deck', $specs, 420, 1626 ) ) ), $this->renditions->made );
	}

	public function test_every_slot_that_offers_it_adds_its_sizes_once_and_a_slot_not_drawn_adds_none(): void {
		$this->indexed( 5 );
		$this->layer( 5, 50 );
		// Two kits' decks, then the wheels: the second deck's sizes are the first's.
		$this->kits->slotsOf = array( 5 => array( 'deck', 'risers', 'deck', 'wheels' ) );

		$this->made()->make( 5 );
		$specs = $this->index->specsOf( 5 )[5];
		self::assertSame(
			array( array( 50, array_merge( Renditions::drawn( 'deck', $specs, 420, 1626 ), Renditions::drawn( 'wheels', $specs, 420, 1626 ) ) ) ),
			$this->renditions->made,
			'a list, the deck\'s sizes then the wheels\', each once'
		);
	}

	public function test_it_asks_which_slots_offer_a_part_in_every_kit(): void {
		$this->kits->add( 3, Shop::kit(), Shop::parts() );
		$this->kits->add( 9, Shop::kit(), Shop::parts() );
		$this->indexed( 6 );
		$this->layer( 6, 60 );

		$this->made()->make( 6 );
		self::assertSame( array( array( $this->kits->kits[3]['kit'], $this->kits->kits[9]['kit'] ) ), $this->kits->slotsAsked );
	}

	public function test_a_drawn_part_whose_layer_image_has_no_rendition_is_counted_and_one_with_renditions_is_not(): void {
		// Cowork's M7 review, F2: Site Health says when parts with layer images have no renditions (D-209).
		$this->kits->add( 3, Shop::kit(), Shop::parts() );
		self::assertSame( 0, $this->made()->unrendered(), 'no part has a layer image' );

		// Decks 101, 102 and 103: 101 rendered, 102 not, 103 with no layer image; a riser's layer is never drawn.
		$risers = array_map( static fn( $part ): int => $part->id, $this->kits->parts[3]['risers'] ?? array() );
		$this->indexed( 101, 102, ...$risers );
		$this->layer( 101, 11 );
		$this->layer( 102, 12 );
		foreach ( $risers as $riser ) {
			$this->layer( $riser, 900 + $riser );
			$this->kits->slotsOf[ $riser ] = array( 'risers' );
		}
		$this->layers->layered = array_merge( array( 101, 102 ), $risers );
		$this->kits->slotsOf  += array(
			101 => array( 'deck' ),
			102 => array( 'deck' ),
		);
		$this->layers->factsRead = 0;
		$this->made()->make( 101 );
		self::assertSame( 1, $this->layers->factsRead, 'making the renditions reads the file itself' );
		$this->layers->factsRead = 0;
		self::assertSame( 1, $this->made()->unrendered(), '102 alone' );
		// Site Health's direct test: the stored pixels, no image file opened (the spec review of the fixes).
		self::assertSame( 0, $this->layers->factsRead );

		$this->made()->make( 102 );
		self::assertSame( 0, $this->made()->unrendered() );
	}

	public function test_parts_with_no_layer_image_are_answered_without_reading_the_shops_kits(): void {
		$this->kits->add( 3, Shop::kit(), Shop::parts() );
		$this->indexed( 101 );
		$this->layers->layered = array( 900 );
		$this->kits->read      = 0;
		self::assertSame( array(), $this->made()->missing( 101, 102 ) );
		self::assertSame( 0, $this->kits->read, 'a reindex asks this of every batch, and most parts have no layer image' );
	}

	public function test_the_parts_offered_in_drawn_slots_are_a_list_each_once(): void {
		$this->kits->add( 3, Shop::kit(), Shop::parts() );
		$this->kits->add( 9, Shop::kit(), Shop::parts() );
		$offered = ( new \ReflectionMethod( PartRenditions::class, 'offered' ) )->invoke( $this->made() );
		self::assertIsArray( $offered );
		self::assertTrue( array_is_list( $offered ), 'a list, numbered from 0' );
		self::assertSame( array_unique( $offered ), $offered, 'two kits that offer a part list it once' );
		self::assertContains( 101, $offered );
	}
}
