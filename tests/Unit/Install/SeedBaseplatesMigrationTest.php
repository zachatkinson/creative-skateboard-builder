<?php
/**
 * The seeded charts' baseplates, and the upgrade that fills them (issue #39).
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SeedBaseplatesMigration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SeedSpecTables;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;

/**
 * Issue #39, from Claude in Cowork's review of 0.6.0-rc.1 (minor 22): R2 reads a truck's baseplate, and the seeded
 * charts gave none, so every truck read from a chart alone was refused. Zach's answers: the makers' own figures, not a
 * guess (13 Nov); Independent's "4 Holes" read as new school, since it says only its 6-hole plates take old school
 * (14 Nov). Thunder and Venture give none, and stay empty (D-189).
 */
final class SeedBaseplatesMigrationTest extends TestCase {

	/**
	 * Each seed's baseplates, by size, '-' where there is none.
	 *
	 * @param string $seed The seed.
	 * @return array<string, string>
	 */
	private static function baseplates( string $seed ): array {
		$plates = array();
		foreach ( SeedSpecTables::all()[ $seed ]['table']->rows as $row ) {
			$plates[ $row->size ] = $row->values['baseplate'] ?? '-';
		}
		return $plates;
	}

	public function test_a_new_sites_charts_carry_each_makers_baseplate_and_nothing_where_the_maker_gives_none(): void {
		self::assertSame(
			array(
				'109' => 'new_school',
				'129' => 'new_school',
				'139' => 'new_school',
				'144' => 'new_school',
				'146' => 'both',
				'149' => 'new_school',
				'151' => 'both',
				'159' => 'both',
				'166' => 'both',
				'169' => 'both',
				'215' => 'both',
			),
			self::baseplates( 'independent' )
		);
		self::assertSame( array( '-' ), array_values( array_unique( self::baseplates( 'thunder' ) ) ), 'Thunder\'s pages give no holes' );
		self::assertSame( array( '-' ), array_values( array_unique( self::baseplates( 'venture' ) ) ), 'Venture\'s pages give no holes' );
	}

	public function test_each_baseplate_says_which_page_of_the_makers_it_was_read_from_and_when(): void {
		foreach ( SeedSpecTables::all()['independent']['table']->rows as $row ) {
			self::assertStringStartsWith( 'Independent, https://independenttrucks.com/trucks; baseplate 2026-11-14: https://nhsskatedirect.com/products/', $row->source, $row->size );
			self::assertLessThanOrEqual( 200, strlen( $row->source ), 'within a row\'s source' );
		}
		self::assertStringContainsString( 'stage-4-', SeedSpecTables::all()['independent']['table']->row( '146' )?->source ?? '' );
		self::assertStringContainsString( '215', SeedSpecTables::all()['independent']['table']->row( '215' )?->source ?? '' );
	}

	public function test_an_upgrade_fills_only_the_empty_baseplates_of_a_seeded_chart_and_says_so(): void {
		$tables = new InMemorySpecTables();
		$old    = SpecTable::read(
			array(
				array( 'size' => '139', 'specs' => array( 'axle_width' => '8.0000' ), 'source' => 'Independent, https://independenttrucks.com/trucks', 'read_on' => '2026-11-10' ),
				array( 'size' => '159', 'specs' => array( 'axle_width' => '8.7500', 'baseplate' => 'new_school' ), 'source' => 'Measured', 'read_on' => '2026-11-12' ),
				array( 'size' => '300', 'specs' => array( 'axle_width' => '11.0000' ) ),
			)
		);
		$indy   = $tables->create( 'Indy', $old, 'independent' );
		$mine   = $tables->create( 'Our own', SpecTable::read( array( array( 'size' => '139', 'specs' => array( 'axle_width' => '8.0000' ) ) ) ) );
		$tables->create( 'Thunder', SeedSpecTables::all()['thunder']['table'], 'thunder' );
		$tables->updates = 0;
		$logger          = new FakeLogger();
		$migration       = new SeedBaseplatesMigration( $tables, $logger );

		self::assertSame( 'spec_tables_2', $migration->key() );
		self::assertTrue( $migration->run( Cursor::start() )->done );

		$rows = $tables->made[ $indy ]['table'];
		self::assertSame( 'new_school', $rows->row( '139' )?->values['baseplate'] );
		self::assertSame( 'Independent, https://independenttrucks.com/trucks; baseplate 2026-11-14: https://nhsskatedirect.com/products/stage-11-polished-standard-skateboard-trucks-independent', $rows->row( '139' )?->source );
		self::assertSame( '2026-11-10', $rows->row( '139' )?->readOn, 'the size chart\'s own day stays' );
		self::assertSame( 'new_school', $rows->row( '159' )?->values['baseplate'], 'a value the shop typed is kept' );
		self::assertSame( 'Measured', $rows->row( '159' )?->source );
		self::assertArrayNotHasKey( 'baseplate', $rows->row( '300' )?->values ?? array(), 'a size the maker does not list is left' );
		self::assertSame( 'Indy', $tables->made[ $indy ]['name'], 'the shop\'s name for it is kept' );
		self::assertArrayNotHasKey( 'baseplate', $tables->made[ $mine ]['table']->row( '139' )?->values ?? array(), 'a chart the shop made is not a seed' );
		self::assertSame( 1, $tables->updates, 'one save, which re-indexes the parts that name it; Thunder has nothing to fill' );
		self::assertSame(
			array(
				array(
					'level'   => Logger::INFO,
					'message' => 'Filled the baseplate of a seeded spec table\'s sizes from the maker\'s pages.',
					'context' => array(
						'table' => $indy,
						'sizes' => array( '139' ),
					),
				),
			),
			$logger->lines
		);

		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertSame( 1, $tables->updates, 'run again, it finds nothing to fill' );
		self::assertCount( 1, $logger->lines );
	}

	public function test_a_seed_with_no_chart_left_is_passed_over(): void {
		$tables = new InMemorySpecTables();
		self::assertTrue( ( new SeedBaseplatesMigration( $tables, new FakeLogger() ) )->run( Cursor::start() )->done );
		self::assertSame( 0, $tables->updates );
	}
}
