<?php
/**
 * The indexer.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecSource;
use PHPUnit\Framework\TestCase;

/**
 * Roadmap, milestone 3: indexing from each source, which source wins, disagreements flagged; idempotent.
 */
final class SpecIndexerTest extends TestCase {

	private InMemorySpecIndex $index;

	private InMemoryShopSettings $settings;

	private FakeClock $clock;

	protected function setUp(): void {
		$this->index    = new InMemorySpecIndex();
		$this->settings = new InMemoryShopSettings();
		$this->clock    = new FakeClock();
	}

	private function fields(): InMemorySpecSource {
		return new InMemorySpecSource(
			array(
				7 => array(
					Reading::of( SpecKey::AxleWidth, Measure::inches( 85000 ), Origin::Field ),
					Reading::of( SpecKey::SetSize, 2, Origin::Field ),
				),
				8 => array( Reading::of( SpecKey::DeckWidth, Measure::inches( 85000 ), Origin::Field ) ),
			)
		);
	}

	private function attributes(): InMemorySpecSource {
		return new InMemorySpecSource(
			array(
				8 => array(
					Reading::of( SpecKey::DeckWidth, Measure::inches( 82500 ), Origin::Attribute, 'pa_width' ),
					new Unread( 'deck_length', 'long', Origin::Attribute, 'pa_length', 'measure_malformed' ),
				),
			)
		);
	}

	private function tables(): InMemorySpecSource {
		return new InMemorySpecSource(
			array(
				7 => array(
					Reading::of( SpecKey::AxleWidth, Measure::inches( 85000 ), Origin::Table, '12:149' ),
					Reading::of( SpecKey::MaxWheel, Measure::millimetres( 560000 ), Origin::Table, '12:149' ),
				),
			)
		);
	}

	public function test_each_source_feeds_the_index_and_the_shops_first_source_wins(): void {
		$fields  = $this->fields();
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $fields, $this->attributes(), $this->tables() );
		$sourced = $indexer->index( 7, 8, 9 );

		self::assertSame( array( 7, 8, 9 ), array_keys( $sourced ) );
		self::assertEquals(
			array(
				new IndexedSpec( SpecKey::AxleWidth, '8.5000', Origin::Table, '12:149' ),
				new IndexedSpec( SpecKey::MaxWheel, '56.0000', Origin::Table, '12:149' ),
				new IndexedSpec( SpecKey::SetSize, '2', Origin::Field ),
			),
			$this->index->rowsOf( 7 ),
			'the chart before the field, and what only one source says from that source'
		);
		self::assertEquals( array( new IndexedSpec( SpecKey::DeckWidth, '8.5000', Origin::Field ) ), $this->index->rowsOf( 8 ), 'the field before the attribute' );
		self::assertSame( array(), $this->index->rowsOf( 9 ) );
		self::assertSame( array( array( 7, 8, 9 ) ), $fields->asked, 'each source is asked once for the batch' );
		self::assertEquals( $this->clock->now(), $this->index->writtenAt[7] );
		self::assertEquals( $this->clock->now(), $this->index->writtenAt[8] );
	}

	public function test_a_disagreement_and_an_unread_value_come_back_with_the_part(): void {
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $this->fields(), $this->attributes(), $this->tables() );
		$sourced = $indexer->index( 7, 8, 9 );

		self::assertSame( array(), $sourced[7]->resolution->disagreements, 'the chart and the field agree on the axle' );
		self::assertSame( array(), $sourced[7]->unread );
		self::assertCount( 1, $sourced[8]->resolution->disagreements );
		self::assertSame( Origin::Field, $sourced[8]->resolution->disagreements[0]->kept->origin );
		self::assertSame( 'pa_width', $sourced[8]->resolution->disagreements[0]->others[0]->ref );
		self::assertEquals( array( new Unread( 'deck_length', 'long', Origin::Attribute, 'pa_length', 'measure_malformed' ) ), $sourced[8]->unread );
		self::assertSame( array(), $sourced[9]->resolution->kept );
		self::assertSame( array(), $sourced[9]->unread );
	}

	public function test_the_shops_order_decides(): void {
		$this->settings->order = SourceOrder::read( array( 'attribute', 'field', 'table' ) );
		$indexer               = new SpecIndexer( $this->index, $this->settings, $this->clock, $this->fields(), $this->attributes(), $this->tables() );
		$indexer->index( 7, 8 );
		self::assertSame( '8.2500', $this->index->rowsOf( 8 )[0]->text );
		self::assertSame( Origin::Attribute, $this->index->rowsOf( 8 )[0]->origin );
		self::assertSame( Origin::Field, $this->index->rowsOf( 7 )[0]->origin );
	}

	public function test_indexing_twice_leaves_the_index_as_indexing_once(): void {
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $this->fields(), $this->attributes(), $this->tables() );
		$indexer->index( 7, 8 );
		$once = $this->index->rows;
		$indexer->index( 7, 8 );
		$indexer->index( 8 );
		self::assertEquals( $once, $this->index->rows );
	}

	public function test_a_part_no_source_speaks_of_any_more_loses_its_rows(): void {
		$fields  = $this->fields();
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $fields );
		$indexer->index( 7, 8 );
		self::assertCount( 2, $this->index->rowsOf( 7 ) );

		// The product was deleted, or its fields emptied.
		unset( $fields->says[7] );
		$fields->says[8] = array();
		$indexer->index( 7, 8 );
		self::assertSame( array(), $this->index->rows, 'no row left for either' );
	}

	public function test_resolving_writes_nothing(): void {
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $this->fields(), $this->attributes() );
		$sourced = $indexer->resolve( 8 );
		self::assertSame( array( 8 ), array_keys( $sourced ) );
		self::assertSame( '8.5000', $sourced[8]->resolution->kept[0]->text );
		self::assertSame( array(), $this->index->rows );
		self::assertSame( array(), $indexer->resolve() );
	}

	public function test_a_source_answering_for_a_part_it_was_not_asked_about_is_not_heard(): void {
		$eager = new class() implements \CreativeBranding\SkateboardBuilder\Port\SpecSource {
			public function read( int ...$partIds ): array {
				return array(
					99 => array( Reading::of( SpecKey::SetSize, 4, Origin::Field ) ),
					7  => array( Reading::of( SpecKey::SetSize, 2, Origin::Field ) ),
				);
			}
		};
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock, $eager );
		$sourced = $indexer->index( 7 );
		self::assertSame( array( 7 ), array_keys( $sourced ) );
		self::assertSame( array( 7 ), array_keys( $this->index->rows ) );
		self::assertSame( '2', $this->index->rowsOf( 7 )[0]->text, 'and what it says of the part asked about still is' );
	}

	public function test_with_no_source_every_part_is_empty(): void {
		$this->index->replace( 7, array( new IndexedSpec( SpecKey::SetSize, '2', Origin::Field ) ), $this->clock->now() );
		$indexer = new SpecIndexer( $this->index, $this->settings, $this->clock );
		$sourced = $indexer->index( 7 );
		self::assertSame( array(), $sourced[7]->resolution->kept );
		self::assertSame( array(), $this->index->rows );
	}
}
