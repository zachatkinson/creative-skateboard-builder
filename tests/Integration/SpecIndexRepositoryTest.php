<?php
/**
 * The specs index's repository.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Spec\IndexedSpec;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Repository\SpecIndexRepository;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\SpecIndexCases;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The port's cases on the real table, what the table holds after a write, rows this version cannot read, and the
 * plans of the queries (roadmap, milestone 3: the query plans use the index).
 */
final class SpecIndexRepositoryTest extends IntegrationTestCase {

	use SpecIndexCases;

	protected function index(): SpecIndex {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::specs() ) ); // phpcs:ignore
		return $this->service( SpecIndex::class );
	}

	public function test_the_container_gives_the_repository(): void {
		self::assertInstanceOf( SpecIndexRepository::class, $this->service( SpecIndex::class ) );
	}

	public function test_a_measure_a_flag_and_a_count_go_in_the_number_column_and_a_choice_in_the_text_column(): void {
		global $wpdb;
		$index = $this->index();
		$index->replace(
			41,
			array(
				new IndexedSpec( SpecKey::AxleWidth, '8.5000', Origin::Table, 'independent:149' ),
				new IndexedSpec( SpecKey::HeightClass, 'mid', Origin::Table, 'independent:149' ),
				new IndexedSpec( SpecKey::SetSize, '2', Origin::Field ),
				new IndexedSpec( SpecKey::SpacersBuiltIn, '0', Origin::Field ),
			),
			new DateTimeImmutable( '2026-11-10 09:30:00', new DateTimeZone( 'America/Toronto' ) )
		);
		$stored = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE part_id = 41 ORDER BY spec', Tables::specs() ), ARRAY_A ); // phpcs:ignore
		self::assertSame(
			array(
				array( 'part_id' => '41', 'spec' => 'axle_width', 'number' => '8.5000', 'text' => null, 'source' => 'table', 'source_ref' => 'independent:149', 'indexed_at' => '2026-11-10 14:30:00' ),
				array( 'part_id' => '41', 'spec' => 'height_class', 'number' => null, 'text' => 'mid', 'source' => 'table', 'source_ref' => 'independent:149', 'indexed_at' => '2026-11-10 14:30:00' ),
				array( 'part_id' => '41', 'spec' => 'set_size', 'number' => '2.0000', 'text' => null, 'source' => 'field', 'source_ref' => '', 'indexed_at' => '2026-11-10 14:30:00' ),
				array( 'part_id' => '41', 'spec' => 'spacers_built_in', 'number' => '0.0000', 'text' => null, 'source' => 'field', 'source_ref' => '', 'indexed_at' => '2026-11-10 14:30:00' ),
			),
			$stored,
			'the time is stored in UTC whatever zone it was given in'
		);
		$specs = $index->specsOf( 41 )[41];
		self::assertSame( 2, $specs->count( SpecKey::SetSize ) );
		self::assertFalse( $specs->flag( SpecKey::SpacersBuiltIn ) );
		self::assertTrue( $specs->has( SpecKey::SpacersBuiltIn ), 'a flag that says no is still a spec the part has' );
	}

	public function test_a_row_this_version_cannot_read_is_passed_over(): void {
		global $wpdb;
		$index = $this->index();
		$index->replace( 51, array( new IndexedSpec( SpecKey::DeckWidth, '8.0000', Origin::Field ) ), new DateTimeImmutable( '2026-11-10 12:00:00', new DateTimeZone( 'UTC' ) ) );
		$row = array( 'part_id' => 51, 'source' => 'field', 'indexed_at' => '2026-11-10 12:00:00' );
		// A key a newer version added, a source a newer version added, and a value in the wrong column.
		$wpdb->insert( Tables::specs(), array( 'spec' => 'concave', 'text' => 'steep' ) + $row ); // phpcs:ignore
		$wpdb->insert( Tables::specs(), array( 'spec' => 'deck_length', 'number' => '32', 'source' => 'feed' ) + $row ); // phpcs:ignore
		$wpdb->insert( Tables::specs(), array( 'spec' => 'hole_pattern', 'number' => '1' ) + $row ); // phpcs:ignore
		$wpdb->insert( Tables::specs(), array( 'spec' => 'wheelbase', 'text' => '14' ) + $row ); // phpcs:ignore

		$rows = $index->rowsOf( 51 );
		self::assertCount( 1, $rows );
		self::assertSame( SpecKey::DeckWidth, $rows[0]->key );
		$specs = $index->specsOf( 51 )[51];
		self::assertSame( array( 'deck_length' => '32.0000', 'deck_width' => '8.0000' ), self::sorted( $specs->toIndex() ), 'a value is read whatever source wrote it; a source is listed only when known' );
	}

	/**
	 * Sorted by key.
	 *
	 * @param array<string, string> $rows Rows.
	 * @return array<string, string>
	 */
	private static function sorted( array $rows ): array {
		ksort( $rows );
		return $rows;
	}

	public function test_every_read_uses_an_index(): void {
		global $wpdb;
		$index = $this->index();
		$at    = new DateTimeImmutable( '2026-11-10 12:00:00', new DateTimeZone( 'UTC' ) );
		// Enough rows that the optimiser has a choice to make: 600 parts, three specs each.
		for ( $part = 1000; $part < 1600; $part++ ) {
			$index->replace(
				$part,
				array(
					new IndexedSpec( SpecKey::DeckWidth, number_format( 7.5 + ( $part % 16 ) / 8, 4 ), Origin::Field ),
					new IndexedSpec( SpecKey::DeckLength, '31.5000', Origin::Field ),
					new IndexedSpec( SpecKey::HolePattern, 0 === $part % 3 ? 'both' : 'new_school', Origin::Field ),
				),
				$at
			);
		}
		$wpdb->query( $wpdb->prepare( 'ANALYZE TABLE %i', Tables::specs() ) ); // phpcs:ignore

		$plans = array(
			'spec_number' => $wpdb->prepare( SpecIndexRepository::BETWEEN, Tables::specs(), 'deck_width', '8.0000', '8.2500', 500 ),
			'spec_text'   => $wpdb->prepare( SpecIndexRepository::WITH, Tables::specs(), 'hole_pattern', 'both', 500 ),
			'PRIMARY'     => $wpdb->prepare( 'SELECT part_id, spec, number, text FROM %i WHERE part_id IN (%d, %d, %d)', Tables::specs(), 1000, 1200, 1599 ),
		);
		$walk = $wpdb->get_results( 'EXPLAIN ' . $wpdb->prepare( SpecIndexRepository::AFTER, Tables::specs(), 1300, 100 ), ARRAY_A ); // phpcs:ignore
		self::assertSame( 'PRIMARY', $walk[0]['key'], 'the reindex walks the primary key' );
		self::assertNotSame( 'ALL', $walk[0]['type'] );
		self::assertStringNotContainsString( 'filesort', (string) $walk[0]['Extra'] );
		foreach ( $plans as $key => $sql ) {
			$plan = $wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A ); // phpcs:ignore
			self::assertCount( 1, $plan, $key );
			self::assertSame( $key, $plan[0]['key'], $sql );
			self::assertNotSame( 'ALL', $plan[0]['type'], 'a full scan: ' . $sql );
			self::assertStringNotContainsString( 'filesort', (string) $plan[0]['Extra'], $sql );
			self::assertLessThan( 1800, (int) $plan[0]['rows'], 'it reads a part of the table, not all 1,800 rows: ' . $sql );
		}
		self::assertCount( 111, $index->partsBetween( SpecKey::DeckWidth, \CreativeBranding\SkateboardBuilder\Domain\Measure\Measure::inches( 80000 ), \CreativeBranding\SkateboardBuilder\Domain\Measure\Measure::inches( 82500 ), 500 ) );
		self::assertCount( 200, $index->partsWith( SpecKey::HolePattern, 'both', 500 ) );
	}
}
