<?php
/**
 * The layer image check the Layer image field and the specs CSV share.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Service\LayerCheck;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLayers;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecSource;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * Cowork's 1.0.0 request (D-287): an image set from the CSV is checked by the Layer image field's rules.
 */
final class LayerCheckTest extends TestCase {

	private InMemoryLayerImages $images;

	private InMemoryKits $kits;

	private InMemorySpecSource $source;

	private LayerCheck $check;

	protected function setUp(): void {
		$this->images = new InMemoryLayerImages();
		$this->kits   = new InMemoryKits();
		$this->source = new InMemorySpecSource();
		$this->check  = new LayerCheck( $this->images, new SpecIndexer( new InMemorySpecIndex(), new InMemoryShopSettings(), new FakeClock(), $this->source ), $this->kits );
		$this->kits->add( 5, KitDocuments::kit(), array() );
		$this->kits->slotsOf[7] = array( 'trucks', 'deck' );
		$this->source->says     = array(
			7 => array(
				Reading::of( SpecKey::DeckWidth, Parts::inches( '8.25' ), Origin::Field ),
				Reading::of( SpecKey::DeckLength, Parts::inches( '32' ), Origin::Field ),
			),
		);
	}

	public function test_a_part_is_drawn_at_the_size_of_the_first_slot_it_fills_that_the_picture_draws(): void {
		$deck = Layout::drawn( 'deck', Specs::fromIndex( array( 'deck_width' => '8.2500', 'deck_length' => '32.0000' ) ) );
		self::assertNotNull( $deck );
		self::assertSame( $deck, $this->check->size( 7 ) );
		self::assertEquals( array( KitDocuments::kit() ), $this->kits->slotsAsked[0] );
		$this->kits->add( 6, KitDocuments::kit(), array() );
		$this->check->size( 7 );
		self::assertCount( 2, $this->kits->slotsAsked[1], 'a kit made since is seen' );
		self::assertNull( $this->check->size( 8 ), 'a part no kit draws' );
		self::assertNull( $this->check->size( 0 ) );
	}

	public function test_an_image_is_checked_by_the_fields_rules_at_the_parts_size(): void {
		$needs = LayerImage::needs( (array) $this->check->size( 7 ) );
		$this->images->images = array(
			11 => new ImageFacts( 'image/png', $needs[0], $needs[1], true ),
			12 => new ImageFacts( 'image/png', 40, 160, true ),
			13 => new ImageFacts( 'image/jpeg', 4000, 16000, null ),
			14 => new ImageFacts( 'image/png', $needs[0], $needs[1], false ),
		);
		self::assertSame( array(), $this->check->problems( 11, $this->check->size( 7 ) ) );
		self::assertSame(
			array(
				array(
					'code' => 'layer_too_small',
					'text' => "image/png\t" . $needs[0] . "\t" . $needs[1],
				),
			),
			$this->check->problems( 12, $this->check->size( 7 ) )
		);
		self::assertSame( array( 'layer_wrong_type' ), array_column( $this->check->problems( 13, $this->check->size( 7 ) ), 'code' ) );
		self::assertSame( array( 'layer_opaque' ), array_column( $this->check->problems( 14, $this->check->size( 7 ) ), 'code' ) );
		self::assertSame( array(), $this->check->problems( 12, null ), 'no size to hold a part no kit draws to' );
		self::assertSame(
			array(
				array(
					'code' => LayerCheck::NOT_IMAGE,
					'text' => '99',
				),
			),
			$this->check->problems( 99, null )
		);
		self::assertSame( array( LayerCheck::NOT_IMAGE ), array_column( $this->check->problems( 0, null ), 'code' ) );
	}

	// D-287: every problem an image has is said, and with no size to hold it to, the sentence's sizes are 0.
	public function test_each_problem_of_an_image_is_said_and_with_no_size_it_needs_none(): void {
		$needs                = LayerImage::needs( (array) $this->check->size( 7 ) );
		$this->images->images = array(
			12 => new ImageFacts( 'image/png', 40, 160, false ),
			13 => new ImageFacts( 'image/jpeg', 4000, 16000, null ),
		);
		self::assertSame(
			array(
				array(
					'code' => 'layer_opaque',
					'text' => "image/png\t" . $needs[0] . "\t" . $needs[1],
				),
				array(
					'code' => 'layer_too_small',
					'text' => "image/png\t" . $needs[0] . "\t" . $needs[1],
				),
			),
			$this->check->problems( 12, $this->check->size( 7 ) )
		);
		self::assertSame(
			array(
				array(
					'code' => 'layer_wrong_type',
					'text' => "image/jpeg\t0\t0",
				),
			),
			$this->check->problems( 13, null )
		);
	}

	// Attachment 0 is no image, whatever the Media Library answers for it (get_post( 0 ) is the current post).
	public function test_attachment_0_is_no_image_and_part_0_is_drawn_at_no_size(): void {
		$this->images->images = array( 0 => new ImageFacts( 'image/png', 4000, 16000, true ) );
		self::assertSame( array( LayerCheck::NOT_IMAGE ), array_column( $this->check->problems( 0, null ), 'code' ) );
		$this->kits->slotsOf[0]  = array( 'deck' );
		$this->source->says[0]   = $this->source->says[7];
		self::assertNull( $this->check->size( 0 ) );
	}

	// Zach's call (D-287): a file name that names more than one image is refused, and the images are named.
	public function test_a_cell_names_an_image_by_its_id_or_its_file_name_which_must_be_one_images(): void {
		$layers        = new InMemoryPartLayers();
		$layers->files = array(
			11 => 'deck-katie.png',
			12 => 'deck-kelly.webp',
			14 => 'deck-kelly.webp',
		);
		self::assertSame( array( 40, array() ), LayerCheck::named( '40', $layers ), 'digits are an ID, checked after' );
		self::assertSame( array( 11, array() ), LayerCheck::named( 'deck-katie.png', $layers ) );
		self::assertSame(
			array(
				0,
				array(
					array(
						'code' => LayerCheck::NOT_FOUND,
						'text' => 'deck-katy.png',
					),
				),
			),
			LayerCheck::named( 'deck-katy.png', $layers )
		);
		self::assertSame(
			array(
				0,
				array(
					array(
						'code' => LayerCheck::AMBIGUOUS,
						'text' => "deck-kelly.webp\t12, 14",
					),
				),
			),
			LayerCheck::named( 'deck-kelly.webp', $layers )
		);
	}
}
