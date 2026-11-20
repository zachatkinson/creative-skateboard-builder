<?php
/**
 * The Catalogue check.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryImportStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCatalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryReindexStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecSource;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Catalogue check: for each kit, the parts that can fill a slot but lack a spec one of their rules
 * needs, parts whose sources disagree, presets that no longer fit or hold a sold-out part, and slots with nothing in
 * stock. Resilience: a failure shows in the Catalogue check.
 */
final class CatalogueCheckTest extends TestCase {

	private InMemoryKits $kits;

	private InMemoryPartCatalogue $catalogue;

	private InMemorySpecSource $source;

	private InMemoryReindexStore $reindex;

	private InMemoryImportStore $import;

	private FakeQueue $queue;

	private CatalogueCheck $check;

	private InMemoryLayerImages $layers;

	private InMemoryPartLooks $looks;

	protected function setUp(): void {
		$this->kits      = new InMemoryKits();
		$this->catalogue = new InMemoryPartCatalogue();
		$this->source    = new InMemorySpecSource();
		$this->reindex   = new InMemoryReindexStore();
		$this->import    = new InMemoryImportStore();
		$this->queue     = new FakeQueue();
		$indexer         = new SpecIndexer( new InMemorySpecIndex(), new InMemoryShopSettings(), new FakeClock(), $this->source );
		$this->layers    = new InMemoryLayerImages();
		$this->looks     = new InMemoryPartLooks();
		$this->check     = ( new CatalogueCheck( $this->kits, $this->catalogue, $indexer, $this->reindex, $this->import, $this->queue ) )->withLayers( $this->layers )->withLooks( $this->looks );
	}

	/**
	 * A kit's slots with one good part each.
	 *
	 * @return array<string, list<Part>>
	 */
	private static function stocked(): array {
		return array(
			'deck'     => array( Builds::deck() ),
			'trucks'   => array( Builds::truck( '149' ) ),
			'wheels'   => array( Builds::wheel( '54' ) ),
			'risers'   => array( Builds::riser( '1/8' ) ),
			'bearings' => array( Builds::bearings() ),
			'hardware' => array( Builds::bolts() ),
			'grip'     => array( Builds::grip() ),
		);
	}

	/**
	 * Findings as text, one a line: code, part, kit, slot, spec and the values.
	 *
	 * @param list<CheckFinding> $found The findings.
	 * @return list<string>
	 */
	private static function lines( array $found ): array {
		return array_map(
			static fn( CheckFinding $finding ): string => implode( ' ', array_filter( array( $finding->code, 'part=' . $finding->part, 'kit=' . $finding->kit, $finding->slot, $finding->spec, http_build_query( $finding->values ) ), static fn( string $piece ): bool => '' !== $piece ) ),
			$found
		);
	}

	/**
	 * A presets document.
	 *
	 * @param array<string, int> $picks The picks.
	 * @return array<string, mixed>
	 */
	private static function preset( array $picks ): array {
		return array(
			'schema_version' => 1,
			'presets'        => array(
				array(
					'name'  => 'Street',
					'picks' => $picks,
				),
			),
		);
	}

	private const PICKS = array(
		'deck'     => 100,
		'trucks'   => 149,
		'wheels'   => 300,
		'bearings' => 500,
		'hardware' => 600,
		'grip'     => 700,
	);

	public function test_a_shop_with_nothing_wrong_has_no_findings(): void {
		self::assertSame( array(), $this->check->run() );
		$this->kits->add( 5, KitDocuments::kit( presets: self::preset( self::PICKS ) ), self::stocked() );
		self::assertSame( array(), self::lines( $this->check->run() ) );
	}

	public function test_a_part_that_lacks_a_spec_a_rule_needs_is_listed_with_the_spec_and_the_rule(): void {
		$parts             = self::stocked();
		$parts['deck'][]   = Parts::of( array( 'deck_length' => '32', 'hole_pattern' => 'new_school' ), 101 );
		$parts['wheels'][] = Parts::of( array( 'wheel_diameter' => '54' ), 301 );
		$this->kits->add( 5, KitDocuments::kit(), $parts );
		self::assertSame(
			array(
				'spec_missing part=101 kit=5 deck deck_width rule=r1',
				'spec_missing part=301 kit=5 wheels set_size rule=r7',
			),
			self::lines( $this->check->run() )
		);
	}

	public function test_a_part_a_rule_assumed_something_of_is_listed_with_the_spec_to_fill_in(): void {
		$parts             = self::stocked();
		$parts['deck'][]   = Parts::of( array( 'deck_width' => '8.5', 'set_size' => 1 ), 101 );
		$parts['trucks'][] = Parts::indy( '139', array( 'baseplate' => 'new_school', 'height_class' => 'low', 'set_size' => 2 ) );
		$this->kits->add( 5, KitDocuments::kit(), $parts );
		$lines = self::lines( $this->check->run() );
		self::assertContains( 'spec_assumed part=101 kit=5 deck hole_pattern note=pattern_assumed', $lines );
		self::assertContains( 'spec_assumed part=101 kit=5 deck deck_length note=length_unchecked', $lines );
		self::assertContains( 'spec_assumed part=139 kit=5 trucks axle_diameter note=axle_assumed', $lines );
		self::assertContains( 'spec_assumed part=139 kit=5 trucks max_wheel note=limit_from_class', $lines );
		self::assertCount( 4, $lines );
	}

	// The auditor's should-fix 11 and the spec reviewer's 3, milestone 4: a kit whose settings stopped reading was left
	// out of the check, so nothing told the owner that no shopper could open it.
	public function test_a_kit_whose_settings_do_not_read_is_listed(): void {
		$this->kits->add( 5, KitDocuments::kit(), self::stocked() );
		$this->kits->unread = array( 9, 12 );
		self::assertSame( array( 'kit_unread part=0 kit=9', 'kit_unread part=0 kit=12' ), self::lines( $this->check->run() ) );
	}

	public function test_a_slot_with_no_parts_or_none_in_stock_is_listed(): void {
		$parts             = self::stocked();
		$parts['grip']     = array();
		$parts['bearings'] = array( Parts::of( array( 'bearing_bore' => '8', 'set_size' => 8 ), 500, 1000, '', false ), Parts::of( array( 'bearing_bore' => '8', 'set_size' => 8 ), 501, 1000, '', false ) );
		$parts['wheels'][] = Parts::of( array( 'wheel_diameter' => '52', 'set_size' => 4 ), 301, 1000, '', false );
		unset( $parts['risers'] );
		$this->kits->add( 5, KitDocuments::kit(), $parts );
		self::assertSame(
			array(
				'slot_empty part=0 kit=5 risers',
				'slot_out_of_stock part=0 kit=5 bearings',
				'slot_empty part=0 kit=5 grip',
			),
			self::lines( $this->check->run() )
		);
	}

	public function test_a_preset_that_no_longer_fits_or_holds_a_part_that_is_sold_out_or_gone_is_listed(): void {
		$parts           = self::stocked();
		$parts['deck']   = array( Builds::deck( '7.5' ) );
		$parts['wheels'] = array( Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 300, 1000, '', false ), Builds::wheel( '52', 301 ) );
		$parts['grip']   = array( Builds::grip( '9', 701 ) );
		// The grip the preset names is another slot's part now, which does not make it this slot's.
		$parts['risers'] = array( Builds::riser( '1/8' ), Builds::grip() );
		$this->kits->add( 5, KitDocuments::kit( presets: self::preset( self::PICKS ) ), $parts );
		$lines = self::lines( $this->check->run() );
		self::assertContains( 'preset_broken part=700 kit=5 grip preset=Street&problem=part_not_in_slot', $lines, implode( "\n", $lines ) );
		self::assertContains( 'preset_broken part=300 kit=5 wheels preset=Street&problem=part_sold_out', $lines );
		self::assertContains( 'preset_broken part=100 kit=5 deck preset=Street&problem=part_blocked', $lines );
		self::assertContains( 'preset_broken part=149 kit=5 trucks preset=Street&problem=part_blocked', $lines );
		self::assertSame( 4, count( array_filter( $lines, static fn( string $line ): bool => str_starts_with( $line, 'preset_broken' ) ) ), implode( "\n", $lines ) );

		// A preset whose first part is gone still has its later problems said, and a part that is nobody's is gone too.
		$parts['deck'] = array( Builds::deck( '8.5', 101 ) );
		$this->kits->add( 5, KitDocuments::kit( presets: self::preset( array( 'risers' => 999 ) + self::PICKS ) ), $parts );
		self::assertSame(
			array(
				'spec_missing part=700 kit=5 risers riser_thickness rule=r3',
				'preset_broken part=999 kit=5 risers preset=Street&problem=part_not_in_slot',
				'preset_broken part=100 kit=5 deck preset=Street&problem=part_not_in_slot',
				'preset_broken part=700 kit=5 grip preset=Street&problem=part_not_in_slot',
				'preset_broken part=300 kit=5 wheels preset=Street&problem=part_sold_out',
			),
			self::lines( $this->check->run() )
		);
	}

	public function test_each_kit_is_checked_and_named_by_its_id(): void {
		$parts         = self::stocked();
		$parts['grip'] = array();
		$this->kits->add( 5, KitDocuments::kit(), self::stocked() );
		$this->kits->add( 6, KitDocuments::kit(), $parts );
		self::assertSame( array( 'slot_empty part=0 kit=6 grip' ), self::lines( $this->check->run() ) );
	}

	public function test_parts_whose_sources_disagree_and_values_that_cannot_be_read_are_listed_with_no_kit(): void {
		$this->catalogue->products = array(
			7 => array( 7 ),
			8 => array( 8 ),
			9 => array( 9 ),
		);
		$this->source->says        = array(
			7 => array(
				Reading::of( SpecKey::DeckWidth, Parts::inches( '8.5' ), Origin::Field ),
				Reading::of( SpecKey::DeckWidth, Parts::inches( '8.25' ), Origin::Attribute, 'pa_width' ),
				Reading::of( SpecKey::DeckWidth, Parts::inches( '8' ), Origin::Table, '4:149' ),
				Reading::of( SpecKey::Wheelbase, Parts::inches( '14' ), Origin::Field ),
			),
			9 => array( new Unread( 'axle_width', 'wide', Origin::Attribute, 'pa_axle', 'measure_malformed' ) ),
		);
		self::assertSame(
			array(
				'sources_disagree part=7 kit=0 deck_width kept=8.0000&kept_from=table&kept_ref=4%3A149&other=8.5000&other_from=field&other_ref=',
				'sources_disagree part=7 kit=0 deck_width kept=8.0000&kept_from=table&kept_ref=4%3A149&other=8.2500&other_from=attribute&other_ref=pa_width',
				'value_unread part=9 kit=0 axle_width text=wide&from=attribute&ref=pa_axle&reason=measure_malformed',
			),
			self::lines( $this->check->run() )
		);
	}

	public function test_every_part_is_read_a_batch_at_a_time(): void {
		for ( $id = 1; $id <= CatalogueCheck::BATCH + 1; $id++ ) {
			$this->catalogue->products[ $id ] = array( $id );
		}
		$this->source->says[ CatalogueCheck::BATCH + 1 ] = array( new Unread( 'axle_width', 'x', Origin::Field, '', 'measure_malformed' ) );
		$this->source->says[1]                           = array( new Unread( 'axle_width', 'y', Origin::Field, '', 'measure_malformed' ) );
		$found = $this->check->run();
		self::assertSame( array( 1, CatalogueCheck::BATCH + 1 ), array_column( $found, 'part' ) );
		self::assertSame( array( 1, 2 ), $this->catalogue->pages );
		self::assertSame( array( CatalogueCheck::BATCH, 1 ), array_map( 'count', $this->source->asked ) );

		// A shop of exactly one batch asks for the page after it, which is empty, and no source is asked for nothing.
		unset( $this->catalogue->products[ CatalogueCheck::BATCH + 1 ] );
		$this->catalogue->pages = array();
		$this->source->asked    = array();
		$this->check->run();
		self::assertSame( array( 1, 2 ), $this->catalogue->pages );
		self::assertCount( 1, $this->source->asked );
	}

	public function test_background_work_that_failed_is_listed_first(): void {
		$this->reindex->state = ( new ReindexState( ReindexState::FAILED, 0, 0, 1 ) )->toArray();
		$this->import->state  = ( new ImportState( ImportState::FAILED, 't', 'id', '.', 1, array(), array(), 1 ) )->toArray();
		$this->queue->failures = 3;
		$parts                 = self::stocked();
		$parts['grip']         = array();
		$this->kits->add( 5, KitDocuments::kit(), $parts );
		self::assertSame(
			array(
				'reindex_failed part=0 kit=0',
				'import_failed part=0 kit=0',
				'jobs_failed part=0 kit=0 count=3',
				'slot_empty part=0 kit=5 grip',
			),
			self::lines( $this->check->run() )
		);

		$this->reindex->state  = ReindexState::begin( 1 )->toArray();
		$this->import->state   = ( new ImportState( ImportState::DONE, 't', 'id', '.', 1, array(), array(), 1 ) )->toArray();
		$this->queue->failures = 0;
		self::assertSame( array( 'slot_empty part=0 kit=5 grip' ), self::lines( $this->check->run() ) );
	}

	public function test_a_count_of_failed_jobs_is_text_as_every_value_is(): void {
		$this->queue->failures = 3;
		self::assertSame( array( 'count' => '3' ), $this->check->run()[0]->values );
	}

	public function test_failed_jobs_are_counted_from_when_the_last_reindex_finished(): void {
		$this->check->run();
		self::assertSame( 0, $this->queue->since );
		$this->reindex->state = ( new ReindexState( ReindexState::DONE, 3, 300, 100, 250 ) )->toArray();
		$this->check->run();
		self::assertSame( 250, $this->queue->since );
	}

	public function test_a_part_carries_how_many_the_shop_holds_when_it_counts_them(): void {
		self::assertNull( ( new Part( 1, '', Specs::none(), 100, true ) )->stock );
		self::assertSame( 3, ( new Part( 1, '', Specs::none(), 100, true, 3 ) )->stock );
		self::assertSame( 0, ( new Part( 1, '', Specs::none(), 100, false, 0 ) )->stock );
	}

	/**
	 * Spec, Admin, Milestone 7: "The Catalogue check lists the parts without a layer image, so the owner sees what
	 * the picture still lacks." Only the slots the picture draws, and after everything else (D-193).
	 */
	// D-208: a check that 0.6.0's container builds, in the request that puts 0.7.0's files in its place, has no layer
	// images to read, and finds what 0.6.0's did.
	public function test_a_check_built_without_layer_images_finds_no_missing_layer_and_the_rest_as_before(): void {
		$this->kits->add( 5, KitDocuments::kit(), array( 'trucks' => array( Builds::truck( '159' ) ) ) + self::stocked() );
		$this->layers->layered = array();
		$plain = new CatalogueCheck( $this->kits, $this->catalogue, new SpecIndexer( new InMemorySpecIndex(), new InMemoryShopSettings(), new FakeClock(), $this->source ), $this->reindex, $this->import, $this->queue );
		// withLayers() gives a copy: the check it was called on is still 0.6.0's.
		$plain->withLayers( $this->layers );
		$without = $plain->run();
		$with    = self::lines( $this->check->run() );
		self::assertContains( 'layer_missing part=159 kit=5 trucks', $with );
		self::assertSame( array_values( array_filter( $with, static fn( string $line ): bool => ! str_starts_with( $line, 'layer_missing' ) ) ), self::lines( $without ) );
	}

	/**
	 * Spec (configurator): "The Catalogue check says which variations share a photo, and so share a card." Said after
	 * everything else, as what the builder will do rather than a fix (D-281).
	 */
	public function test_the_variations_that_share_a_photo_and_so_a_card_are_said_after_everything_else(): void {
		$slots           = self::stocked();
		$slots['deck']   = array( Builds::deck( '8.0', 110 ), Builds::deck( '8.25', 111 ), Builds::deck( '8.5', 112 ), Builds::deck( '8.0', 120 ) );
		$slots['wheels'] = array( Builds::wheel( '54', 301 ), Builds::wheel( '56', 302 ) );
		$this->kits->add( 5, KitDocuments::kit(), $slots );
		$fact                     = static fn( int $product, int $photo, int $layer = 0 ): LookFact => new LookFact( $product, $photo, $layer, 'Board', array(), array(), false, 0, 0 );
		$this->looks->facts = array(
			110 => $fact( 10, 1 ),
			111 => $fact( 10, 1 ),
			112 => $fact( 10, 2 ),
			120 => $fact( 12, 3 ),
			301 => $fact( 30, 4, 7 ),
			302 => $fact( 30, 4, 8 ),
		);

		$lines = self::lines( $this->check->run() );
		self::assertSame( array( 'look_shared part=10 kit=5 deck parts=110%2C+111' ), array_values( array_filter( $lines, static fn( string $line ): bool => str_starts_with( $line, 'look_shared' ) ) ), 'a photo of its own is a card of its own, and so is another layer image' );
		self::assertStringStartsWith( 'look_shared', (string) end( $lines ), 'last' );
	}

	// The auditor's 1.0.0 minor 11: Site Health's copy works out no shared cards; the check it came from still does.
	public function test_without_looks_is_a_copy_that_asks_for_no_looks(): void {
		$this->kits->add( 5, KitDocuments::kit(), self::stocked() );
		$without = $this->check->withoutLooks();
		self::assertNotSame( $this->check, $without );
		$without->run();
		self::assertSame( array(), $this->looks->asked );
		$this->check->run();
		self::assertNotSame( array(), $this->looks->asked, 'the check it was called on' );
	}

	// D-208: withLooks() gives a copy, so the check it was called on still says nothing of shared cards.
	public function test_with_looks_gives_a_copy_and_leaves_the_check_it_was_called_on_as_it_was(): void {
		$this->kits->add( 5, KitDocuments::kit(), self::stocked() );
		$plain = new CatalogueCheck( $this->kits, $this->catalogue, new SpecIndexer( new InMemorySpecIndex(), new InMemoryShopSettings(), new FakeClock(), $this->source ), $this->reindex, $this->import, $this->queue );
		self::assertNotSame( $plain, $plain->withLooks( $this->looks ) );
		$plain->run();
		self::assertSame( array(), $this->looks->asked );
	}

	/**
	 * Spec (configurator): "The Catalogue check says which variations share a photo, and so share a card" (D-281). A
	 * card is its product, its photo and its layer image together: numbers that run into each other are not one.
	 */
	public function test_each_shared_card_of_each_kit_is_said_and_only_a_card_its_parts_all_share(): void {
		$slots           = self::stocked();
		$slots['deck']   = array( Builds::deck( '8.0', 110 ), Builds::deck( '8.25', 111 ), Builds::deck( '8.5', 112 ), Builds::deck( '8.0', 113 ), Builds::deck( '8.0', 114 ), Builds::deck( '8.0', 115 ), Builds::deck( '8.0', 116 ), Builds::deck( '8.0', 117 ) );
		$slots['wheels'] = array( Builds::wheel( '54', 301 ), Builds::wheel( '56', 302 ) );
		$this->kits->add( 5, KitDocuments::kit(), $slots );
		$this->kits->add( 6, KitDocuments::kit(), $slots );
		$fact               = static fn( int $product, int $photo, int $layer = 0 ): LookFact => new LookFact( $product, $photo, $layer, 'Board', array(), array(), false, 0, 0 );
		$this->looks->facts = array(
			// Product 1's photo 12 and product 11's photo 2.
			110 => $fact( 1, 12 ),
			111 => $fact( 11, 2 ),
			// Photo 1 with layer 23 and photo 12 with layer 3.
			112 => $fact( 3, 1, 23 ),
			113 => $fact( 3, 12, 3 ),
			// Two products with the same photo.
			114 => $fact( 4, 5 ),
			115 => $fact( 6, 5 ),
			116 => $fact( 7, 8 ),
			117 => $fact( 7, 8 ),
			301 => $fact( 30, 4, 7 ),
			302 => $fact( 30, 4, 7 ),
		);

		$lines = self::lines( $this->check->run() );
		self::assertSame(
			array(
				'look_shared part=7 kit=5 deck parts=116%2C+117',
				'look_shared part=30 kit=5 wheels parts=301%2C+302',
				'look_shared part=7 kit=6 deck parts=116%2C+117',
				'look_shared part=30 kit=6 wheels parts=301%2C+302',
			),
			array_values( array_filter( $lines, static fn( string $line ): bool => str_starts_with( $line, 'look_shared' ) ) )
		);
	}

	public function test_the_parts_of_the_slots_the_picture_draws_with_no_layer_image_are_listed_last(): void {
		$slots           = self::stocked();
		$slots['trucks'] = array( Builds::truck( '149' ), Builds::truck( '159' ) );
		$slots['wheels'] = array( Builds::wheel( '54' ), Builds::wheel( '52', 301 ) );
		$slots['deck'][] = Builds::deck( '8.25', 101 );
		$this->kits->add( 5, KitDocuments::kit(), $slots );
		$this->kits->add( 6, KitDocuments::kit(), array( 'risers' => array() ) + self::stocked() );
		$this->layers->layered = array( 100, 149, 300 );

		$lines = self::lines( $this->check->run() );
		self::assertSame(
			array(
				'slot_empty part=0 kit=6 risers',
				'layer_missing part=101 kit=5 deck',
				'layer_missing part=159 kit=5 trucks',
				'layer_missing part=301 kit=5 wheels',
				'layer_missing part=700 kit=5 grip',
				'layer_missing part=700 kit=6 grip',
			),
			array_values( array_filter( $lines, static fn( string $line ): bool => ! str_starts_with( $line, 'spec_' ) ) ),
			'risers, bearings and hardware are not drawn; each kit says its own; the rest come first'
		);
		self::assertSame( 'layer_missing', substr( (string) end( $lines ), 0, 13 ) );
	}

}
