<?php
/**
 * What any specs index must do.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The port's cases, run on the table's repository (integration) and on the in-memory index the unit tests of every
 * service use, so the two cannot drift apart.
 */
trait SpecIndexCases {

	/**
	 * The index under test, empty.
	 */
	abstract protected function index(): SpecIndex;

	private static function when( string $time = '2026-11-10 12:00:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $time, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A deck: a measure in each unit's column, a choice, and where each came from.
	 *
	 * @return list<IndexedSpec>
	 */
	private static function deck( string $width = '8.2500' ): array {
		return array(
			new IndexedSpec( SpecKey::DeckWidth, $width, Origin::Attribute, 'pa_width' ),
			new IndexedSpec( SpecKey::DeckLength, '31.8750', Origin::Field ),
			new IndexedSpec( SpecKey::HolePattern, 'both', Origin::Field ),
		);
	}

	public function test_a_parts_specs_come_back_as_the_domain_reads_them(): void {
		$index = $this->index();
		$index->replace( 11, self::deck(), self::when() );
		$index->replace(
			12,
			array(
				new IndexedSpec( SpecKey::SetSize, '4', Origin::Field ),
				new IndexedSpec( SpecKey::SpacersBuiltIn, '1', Origin::Field ),
				new IndexedSpec( SpecKey::WheelDiameter, '54.0000', Origin::Attribute, 'pa_diameter' ),
			),
			self::when()
		);

		$specs = $index->specsOf( 11, 12, 13 );
		self::assertSame( array( 11, 12 ), array_keys( $specs ), 'a part with no row is not in the answer' );
		self::assertEquals(
			Specs::none()->with( SpecKey::DeckWidth, Measure::inches( 82500 ) )->with( SpecKey::DeckLength, Measure::inches( 318750 ) )->with( SpecKey::HolePattern, 'both' ),
			Specs::fromIndex( $specs[11]->toIndex() )
		);
		self::assertTrue( Measure::inches( 82500 )->equals( $specs[11]->measure( SpecKey::DeckWidth ) ?? Measure::inches( 0 ) ) );
		self::assertSame( 'both', $specs[11]->choice( SpecKey::HolePattern ) );
		self::assertSame( 4, $specs[12]->count( SpecKey::SetSize ), 'a count is whole digits, not the column\'s decimal' );
		self::assertTrue( $specs[12]->flag( SpecKey::SpacersBuiltIn ) );
		self::assertTrue( Measure::millimetres( 540000 )->equals( $specs[12]->measure( SpecKey::WheelDiameter ) ?? Measure::millimetres( 0 ) ) );
		self::assertSame( array(), $index->specsOf(), 'no part asked for' );
		self::assertSame( array(), $index->specsOf( 99 ) );
	}

	public function test_a_parts_rows_carry_their_sources_in_the_order_of_the_keys(): void {
		$index = $this->index();
		$index->replace( 11, array_reverse( self::deck() ), self::when() );
		$rows = $index->rowsOf( 11 );
		self::assertSame( array( SpecKey::DeckWidth, SpecKey::DeckLength, SpecKey::HolePattern ), array_map( static fn( IndexedSpec $row ): SpecKey => $row->key, $rows ) );
		self::assertEquals( self::deck(), $rows );
		self::assertSame( 'pa_width', $rows[0]->ref );
		self::assertSame( Origin::Attribute, $rows[0]->origin );
		self::assertSame( array(), $index->rowsOf( 12 ) );
	}

	public function test_replacing_writes_the_new_values_and_removes_a_spec_the_part_no_longer_has(): void {
		$index = $this->index();
		$index->replace( 11, self::deck(), self::when() );
		$index->replace( 12, self::deck( '8.5000' ), self::when() );
		$index->replace(
			11,
			array(
				new IndexedSpec( SpecKey::DeckWidth, '8.3750', Origin::Field ),
				new IndexedSpec( SpecKey::HolePattern, 'new_school', Origin::Table, 'decks:8.375' ),
			),
			self::when( '2026-11-11 08:00:00' )
		);
		self::assertEquals(
			array(
				new IndexedSpec( SpecKey::DeckWidth, '8.3750', Origin::Field ),
				new IndexedSpec( SpecKey::HolePattern, 'new_school', Origin::Table, 'decks:8.375' ),
			),
			$index->rowsOf( 11 ),
			'the length is gone, the width and its source are the new ones'
		);
		self::assertEquals( self::deck( '8.5000' ), $index->rowsOf( 12 ), 'another part is not touched' );
	}

	public function test_the_same_rows_twice_leave_the_same_index(): void {
		$index = $this->index();
		$index->replace( 11, self::deck(), self::when() );
		$once = $index->rowsOf( 11 );
		$index->replace( 11, self::deck(), self::when() );
		self::assertEquals( $once, $index->rowsOf( 11 ) );
		self::assertCount( 3, $index->rowsOf( 11 ) );
	}

	public function test_no_rows_and_forget_both_remove_a_part(): void {
		$index = $this->index();
		foreach ( array( 11, 12, 13, 14 ) as $part ) {
			$index->replace( $part, self::deck(), self::when() );
		}
		$index->replace( 11, array(), self::when() );
		self::assertSame( array( 12, 13, 14 ), array_keys( $index->specsOf( 11, 12, 13, 14 ) ) );
		$index->forget( 12, 14, 99 );
		self::assertSame( array( 13 ), array_keys( $index->specsOf( 11, 12, 13, 14 ) ) );
		$index->forget();
		self::assertSame( array( 13 ), array_keys( $index->specsOf( 13 ) ), 'forgetting no part forgets nothing' );
	}

	public function test_parts_by_a_measure_between_two_values_smallest_first(): void {
		$index = $this->index();
		foreach ( array( 21 => '8.5000', 22 => '8.0000', 23 => '8.2500', 24 => '8.2500', 25 => '9.0000', 26 => '7.7500' ) as $part => $width ) {
			$index->replace( $part, self::deck( $width ), self::when() );
		}
		// A length inside the range must not be read as a width.
		$index->replace( 27, array( new IndexedSpec( SpecKey::DeckLength, '8.2500', Origin::Field ) ), self::when() );

		self::assertSame( array( 22, 23, 24, 21 ), $index->partsBetween( SpecKey::DeckWidth, Measure::inches( 80000 ), Measure::inches( 85000 ), 10 ), 'both ends included, ties by ID' );
		self::assertSame( array( 22, 23 ), $index->partsBetween( SpecKey::DeckWidth, Measure::inches( 80000 ), Measure::inches( 85000 ), 2 ) );
		self::assertSame( array( 23, 24 ), $index->partsBetween( SpecKey::DeckWidth, Measure::millimetres( 2095500 ), Measure::millimetres( 2095500 ), 10 ), 'asked in millimetres, read in the key\'s inches' );
		self::assertSame( array(), $index->partsBetween( SpecKey::DeckWidth, Measure::inches( 100000 ), Measure::inches( 110000 ), 10 ) );
		self::assertSame( array(), $index->partsBetween( SpecKey::HolePattern, Measure::inches( 0 ), Measure::inches( 990000 ), 10 ), 'not a measure' );
	}

	public function test_parts_by_a_choice_by_id(): void {
		$index = $this->index();
		foreach ( array( 33 => 'both', 31 => 'new_school', 32 => 'both', 34 => 'old_school' ) as $part => $pattern ) {
			$index->replace( $part, array( new IndexedSpec( SpecKey::HolePattern, $pattern, Origin::Field ) ), self::when() );
		}
		$index->replace( 35, array( new IndexedSpec( SpecKey::Baseplate, 'both', Origin::Field ) ), self::when() );
		self::assertSame( array( 32, 33 ), $index->partsWith( SpecKey::HolePattern, 'both', 10 ) );
		self::assertSame( array( 32 ), $index->partsWith( SpecKey::HolePattern, 'both', 1 ) );
		self::assertSame( array(), $index->partsWith( SpecKey::HolePattern, 'none', 10 ) );
		self::assertSame( array(), $index->partsWith( SpecKey::DeckWidth, '8.2500', 10 ), 'not a choice' );
	}

	public function test_the_parts_the_index_holds_after_one_by_id_each_once(): void {
		$index = $this->index();
		foreach ( array( 45, 41, 43, 42, 44 ) as $part ) {
			$index->replace( $part, self::deck(), self::when() );
		}
		self::assertSame( array( 41, 42, 43 ), $index->partsAfter( 0, 3 ), 'a part with three rows is there once' );
		self::assertSame( array( 44, 45 ), $index->partsAfter( 43, 3 ) );
		self::assertSame( array(), $index->partsAfter( 45, 3 ) );
	}
}
