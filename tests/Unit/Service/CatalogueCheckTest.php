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
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCatalogue;
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

	protected function setUp(): void {
		$this->kits      = new InMemoryKits();
		$this->catalogue = new InMemoryPartCatalogue();
		$this->source    = new InMemorySpecSource();
		$this->reindex   = new InMemoryReindexStore();
		$this->import    = new InMemoryImportStore();
		$this->queue     = new FakeQueue();
		$indexer         = new SpecIndexer( new InMemorySpecIndex(), new InMemoryShopSettings(), new FakeClock(), $this->source );
		$this->check     = new CatalogueCheck( $this->kits, $this->catalogue, $indexer, $this->reindex, $this->import, $this->queue );
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

	public function test_a_part_carries_how_many_the_shop_holds_when_it_counts_them(): void {
		self::assertNull( ( new Part( 1, '', Specs::none(), 100, true ) )->stock );
		self::assertSame( 3, ( new Part( 1, '', Specs::none(), 100, true, 3 ) )->stock );
		self::assertSame( 0, ( new Part( 1, '', Specs::none(), 100, false, 0 ) )->stock );
	}
}
