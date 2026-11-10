<?php
/**
 * A maker's chart and its rows.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Domain\Spec\TableRow;
use PHPUnit\Framework\TestCase;

/**
 * Whatever is stored reads as a table, and a table round-trips.
 */
final class SpecTableTest extends TestCase {

	private const INDY_149 = array(
		'size'    => '149',
		'specs'   => array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000' ),
		'source'  => 'Independent, https://independenttrucks.com/trucks',
		'read_on' => '2026-11-10',
	);

	public function test_a_row_reads_and_round_trips(): void {
		$row = TableRow::read( self::INDY_149 );
		self::assertNotNull( $row );
		self::assertSame( '149', $row->size );
		self::assertSame( array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000' ), $row->values );
		self::assertSame( 'Independent, https://independenttrucks.com/trucks', $row->source );
		self::assertSame( '2026-11-10', $row->readOn );
		self::assertSame( self::INDY_149, $row->toArray() );
	}

	public function test_a_row_needs_a_size_of_a_sensible_length(): void {
		self::assertNull( TableRow::read( 'a row' ) );
		self::assertNull( TableRow::read( array() ) );
		self::assertNull( TableRow::read( array( 'size' => 149 ) ) );
		self::assertNull( TableRow::read( array( 'size' => '  ' ) ) );
		self::assertNull( TableRow::read( array( 'size' => str_repeat( 'x', 41 ) ) ) );
		self::assertSame( str_repeat( 'x', 40 ), TableRow::read( array( 'size' => str_repeat( 'x', 40 ) ) )?->size );
		self::assertSame( str_repeat( 'x', 40 ), TableRow::read( array( 'size' => ' ' . str_repeat( 'x', 40 ) . ' ' ) )?->size, 'measured without the spaces around it' );
		self::assertSame( '5.2', TableRow::read( array( 'size' => ' 5.2 ' ) )?->size );
	}

	public function test_a_row_with_a_size_alone_has_nothing_else(): void {
		$row = TableRow::read( array( 'size' => '143' ) );
		self::assertSame( array( 'size' => '143', 'specs' => array(), 'source' => '', 'read_on' => '' ), $row?->toArray() );
		self::assertSame( array(), TableRow::read( array( 'size' => '143', 'specs' => 'none' ) )?->values );
	}

	public function test_a_value_that_is_not_text_or_is_blank_is_left_out_and_the_rest_trimmed(): void {
		$row = TableRow::read(
			array(
				'size'  => '149',
				'specs' => array( 'axle_width' => ' 8.5000 ', 'deck_min' => '', 'deck_max' => '  ', 'max_wheel' => 56, 7 => '8.0000', 'from_a_newer_version' => 'kept' ),
			)
		);
		self::assertSame( array( 'axle_width' => '8.5000', 'from_a_newer_version' => 'kept' ), $row?->values );
	}

	public function test_a_source_too_long_or_a_day_that_is_not_one_reads_as_not_said(): void {
		$read = static fn( mixed $source, mixed $day ): array => array_slice( TableRow::read( array( 'size' => '1', 'source' => $source, 'read_on' => $day ) )?->toArray() ?? array(), 2 );
		self::assertSame( array( 'source' => 'Tactics', 'read_on' => '2026-11-10' ), $read( ' Tactics ', '2026-11-10' ) );
		self::assertSame( array( 'source' => str_repeat( 's', 200 ), 'read_on' => '' ), $read( ' ' . str_repeat( 's', 200 ) . ' ', '10 Nov 2026' ) );
		self::assertSame( array( 'source' => '', 'read_on' => '' ), $read( str_repeat( 's', 201 ), 20261110 ) );
		self::assertSame( array( 'source' => '', 'read_on' => '' ), $read( array( 'Tactics' ), "2026-11-10\n" ) );
		self::assertSame( '', $read( '', 'on 2026-11-10' )['read_on'] );
		self::assertSame( '', $read( '', '2026-11-10 late' )['read_on'] );
		self::assertSame( '', $read( '', '2026-11-1' )['read_on'] );
		self::assertSame( '', $read( '', '26-11-10' )['read_on'] );
		self::assertSame( '', $read( '', '2026-1-10' )['read_on'] );
	}

	public function test_a_table_reads_its_rows_in_order_and_round_trips(): void {
		$stored = array( self::INDY_149, array( 'size' => '159', 'specs' => array( 'axle_width' => '8.7500' ), 'source' => '', 'read_on' => '' ) );
		$table  = SpecTable::read( $stored );
		self::assertSame( array( '149', '159' ), array_map( static fn( TableRow $row ): string => $row->size, $table->rows ) );
		self::assertSame( $stored, $table->toArray() );
		self::assertSame( '8.7500', $table->row( '159' )?->values['axle_width'] );
		self::assertSame( '149', $table->row( ' 149 ' )?->size );
		self::assertNull( $table->row( '169' ) );
		self::assertNull( $table->row( '' ) );
	}

	public function test_whatever_is_stored_reads_as_a_table(): void {
		self::assertSame( array(), SpecTable::read( null )->rows );
		self::assertSame( array(), SpecTable::read( 'rows' )->rows );
		self::assertSame( array(), SpecTable::read( array() )->toArray() );
		$table = SpecTable::read(
			array(
				'not a row',
				array( 'size' => '149', 'specs' => array( 'axle_width' => '8.5000' ) ),
				array( 'specs' => array( 'axle_width' => '9.0000' ) ),
				array( 'size' => '149', 'specs' => array( 'axle_width' => '1.0000' ) ),
				array( 'size' => '159' ),
			)
		);
		self::assertCount( 2, $table->rows );
		self::assertSame( '8.5000', $table->row( '149' )?->values['axle_width'], 'of two rows with one size the first is kept' );
		self::assertNotNull( $table->row( '159' ) );
	}
}
