<?php
/**
 * The seed spec tables and their migration.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Domain\Spec\TableRow;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SeedSpecTables;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SpecTablesMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Roadmap, milestone 3: the seed migration is idempotent and overwrites nothing the shop edited. Spec, R1: the seed
 * tables.
 */
final class SpecTablesMigrationTest extends TestCase {

	/**
	 * A seed's rows as the spec's table writes them: size, axle, narrowest deck, widest deck.
	 *
	 * @param string $seed The seed.
	 * @return list<string>
	 */
	private static function chart( string $seed ): array {
		return array_map(
			static fn( TableRow $row ): string => implode( ' ', array( $row->size, $row->values['axle_width'] ?? '-', $row->values['deck_min'] ?? '-', $row->values['deck_max'] ?? '-' ) ),
			SeedSpecTables::all()[ $seed ]['table']->rows
		);
	}

	public function test_the_seeds_are_the_specs_table_under_r1(): void {
		self::assertSame( array( 'independent', 'thunder', 'venture' ), array_keys( SeedSpecTables::all() ) );
		self::assertSame( array( 'Independent', 'Thunder', 'Venture' ), array_column( SeedSpecTables::all(), 'name' ) );
		self::assertSame(
			array(
				'109 6.9000 6.2500 7.6000',
				'129 7.6000 7.4000 7.8000',
				'139 8.0000 7.8000 8.2000',
				'144 8.2500 8.2000 8.3750',
				'146 8.3750 8.1250 8.6250',
				'149 8.5000 8.3750 8.6000',
				'151 8.6250 8.3750 8.8750',
				'159 8.7500 8.6000 9.0000',
				'166 9.0000 8.5000 9.5000',
				'169 9.1250 9.0000 9.5000',
				'215 10.0000 9.5000 10.5000',
			),
			self::chart( 'independent' )
		);
		self::assertSame(
			array(
				'143 7.0000 - -',
				'145 7.6000 7.3800 8.0000',
				'147 8.0000 7.7500 8.2500',
				'148 8.2500 8.0000 8.5000',
				'149 8.5000 8.2500 8.7500',
				'151 8.7500 8.5000 9.0000',
				'161 9.1250 - -',
			),
			self::chart( 'thunder' ),
			'two sizes have no published range'
		);
		self::assertSame(
			array(
				'5.0 7.6200 - 7.9000',
				'5.2 8.0000 7.9000 8.1000',
				'5.6 8.2500 8.1000 8.3000',
				'5.8 8.5000 8.4000 8.6000',
				'6.1 8.7500 8.6200 -',
			),
			self::chart( 'venture' ),
			'the smallest has no narrowest deck and the largest no widest (D-062)'
		);
	}

	public function test_every_seeded_value_reads_as_its_spec_in_the_form_the_index_stores(): void {
		$rows = 0;
		foreach ( SeedSpecTables::all() as $chart ) {
			foreach ( $chart['table']->rows as $row ) {
				++$rows;
				self::assertNotSame( '', $row->source );
				self::assertStringContainsString( 'https://', $row->source );
				foreach ( $row->values as $key => $text ) {
					$reading = Reading::from( SpecKey::from( $key ), $text, Origin::Table );
					self::assertInstanceOf( Reading::class, $reading, $chart['name'] . ' ' . $row->size . ' ' . $key );
					self::assertSame( $text, $reading->text, 'already in the stored form' );
				}
			}
		}
		self::assertSame( 23, $rows );
	}

	public function test_a_row_says_the_day_it_was_read_or_none_when_it_is_the_specs_own_figure(): void {
		$days = array();
		foreach ( SeedSpecTables::all() as $seed => $chart ) {
			foreach ( $chart['table']->rows as $row ) {
				$days[ $seed ][ $row->readOn ][] = $row->size;
			}
		}
		self::assertSame( array( '2026-11-10' ), array_keys( $days['independent'] ) );
		self::assertSame( array( '2026-11-10' ), array_keys( $days['venture'] ) );
		self::assertSame( array( '143', '148', '161' ), $days['thunder'][''], 'not on the page read on that day' );
		self::assertSame( array( '145', '147', '149', '151' ), $days['thunder']['2026-11-10'] );
		$thunder = SeedSpecTables::all()['thunder']['table'];
		self::assertStringStartsWith( 'Warehouse Skateboards, ', $thunder->row( '148' )?->source ?? '' );
		self::assertStringStartsWith( 'Tactics, ', $thunder->row( '149' )?->source ?? '' );
		self::assertStringStartsWith( 'Independent, ', SeedSpecTables::all()['independent']['table']->row( '149' )?->source ?? '' );
		self::assertStringStartsWith( 'Rollersnakes, ', SeedSpecTables::all()['venture']['table']->row( '5.2' )?->source ?? '' );
	}

	public function test_the_specs_two_r1_cases_hold_on_the_seeds(): void {
		$indy  = SeedSpecTables::all()['independent']['table'];
		$fits = static function ( string $size, float $deck ) use ( $indy ): bool {
			$row = $indy->row( $size );
			self::assertNotNull( $row );
			return (float) $row->values['deck_min'] <= $deck && $deck <= (float) $row->values['deck_max'];
		};
		self::assertTrue( $fits( '139', 8.0 ), 'an 8.0 in deck with Indy 139 fits' );
		self::assertFalse( $fits( '144', 8.5 ), 'an 8.5 in deck with Indy 144 is outside 8.2 to 8.375' );
	}

	public function test_the_migration_makes_each_table_once_with_its_seed(): void {
		$tables    = new InMemorySpecTables();
		$migration = new SpecTablesMigration( $tables );
		self::assertSame( 'spec_tables_1', $migration->key() );
		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertSame( array( 'Independent', 'Thunder', 'Venture' ), array_column( $tables->made, 'name' ) );
		self::assertSame( array( 'independent', 'thunder', 'venture' ), array_column( $tables->made, 'seed' ) );
		self::assertEquals( SeedSpecTables::all()['thunder']['table'], $tables->made[2]['table'] );

		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertCount( 3, $tables->made, 'run twice, it makes nothing more' );
	}

	public function test_a_table_the_shop_changed_is_left_as_it_is(): void {
		$tables = new InMemorySpecTables();
		$edited = SpecTable::read( array( array( 'size' => '149', 'specs' => array( 'axle_width' => '8.4900' ) ) ) );
		$tables->create( 'Indy, corrected', $edited, 'independent' );
		( new SpecTablesMigration( $tables ) )->run( Cursor::start() );
		self::assertCount( 3, $tables->made );
		self::assertSame( 'Indy, corrected', $tables->made[1]['name'] );
		self::assertSame( $edited, $tables->made[1]['table'] );
		self::assertSame( array( 'independent', 'thunder', 'venture' ), array_column( $tables->made, 'seed' ) );
	}

	public function test_cut_off_between_two_tables_the_next_run_makes_the_rest(): void {
		$tables             = new InMemorySpecTables();
		$tables->fails      = new RuntimeException( 'the database went away' );
		$tables->failsAfter = 1;
		$migration          = new SpecTablesMigration( $tables );
		try {
			$migration->run( Cursor::start() );
			self::fail( 'the failure reaches the runner, which records it and tries again' );
		} catch ( RuntimeException $failure ) {
			self::assertSame( 'the database went away', $failure->getMessage() );
		}
		self::assertSame( array( 'independent' ), array_column( $tables->made, 'seed' ) );
		$tables->fails = null;
		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertSame( array( 'independent', 'thunder', 'venture' ), array_column( $tables->made, 'seed' ), 'each once' );
	}
}
