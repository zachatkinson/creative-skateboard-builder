<?php
/**
 * The makers' charts, added, saved and deleted.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\SpecTableEditor;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, WooCommerce > Builder, Spec tables: a row per size with its axle width, deck range, height, largest
 * wheel and baseplate holes, each row with its source and the date it was read.
 */
final class SpecTableEditorTest extends TestCase {

	private InMemorySpecTables $tables;

	private SpecTableEditor $editor;

	protected function setUp(): void {
		$this->tables = new InMemorySpecTables();
		$this->editor = new SpecTableEditor( $this->tables );
	}

	/**
	 * A row as the form sends it.
	 *
	 * @param string                $size   The size.
	 * @param array<string, string> $specs  The figures.
	 * @param string                $source The source.
	 * @param string                $read   The day read.
	 * @return array<string, mixed>
	 */
	private static function row( string $size, array $specs = array(), string $source = '', string $read = '' ): array {
		return array(
			'size'    => $size,
			'specs'   => $specs,
			'source'  => $source,
			'read_on' => $read,
		);
	}

	/**
	 * A draft's problems, as row:field:code.
	 *
	 * @param list<array{row: int, field: string, code: string}> $problems The problems.
	 * @return list<string>
	 */
	private static function codes( array $problems ): array {
		return array_map( static fn( array $problem ): string => implode( ':', $problem ), $problems );
	}

	public function test_a_chart_is_added_by_its_name_with_no_rows(): void {
		$draft = $this->editor->add( '  Independent ' );
		self::assertTrue( $draft->saved() );
		self::assertSame( 'Independent', $this->tables->made[ $draft->id ]['name'] );
		self::assertSame( array(), $this->tables->made[ $draft->id ]['table']->rows );
		self::assertSame( '', $this->tables->made[ $draft->id ]['seed'], 'not a seed: the migration alone makes those' );
	}

	public function test_a_chart_needs_a_name_of_190_characters_or_fewer(): void {
		$none = $this->editor->add( '   ' );
		self::assertFalse( $none->saved() );
		self::assertSame( 0, $none->id );
		self::assertSame( array( '0:name:name_missing' ), self::codes( $none->problems ) );
		self::assertSame( array( '0:name:name_long' ), self::codes( $this->editor->add( str_repeat( 'x', 191 ) )->problems ) );
		self::assertSame( array(), $this->tables->made, 'nothing was made' );
		self::assertTrue( $this->editor->add( ' ' . str_repeat( 'x', 190 ) . ' ' )->saved() );
	}

	public function test_rows_are_saved_as_the_index_writes_them_however_they_were_typed(): void {
		$id    = $this->editor->add( 'Indy' )->id;
		$draft = $this->editor->save(
			$id,
			' Independent ',
			array(
				self::row(
					' 149 ',
					array(
						'axle_width'   => '8,5',
						'deck_min'     => '8 3/8',
						'deck_max'     => ' 8,6 ',
						'truck_height' => '55',
						'max_wheel'    => '56mm',
						'baseplate'    => 'Both',
						'kingpin'      => 'inverted',
						'deck_length'  => '  ',
					),
					' Independent, https://example.com/chart ',
					'2026-11-10'
				),
				self::row( '', array( 'axle_width' => ' ' ), ' ', '' ),
				'not a row',
				self::row( '159', array( 'axle_width' => '8.75' ) ),
			),
			','
		);
		self::assertTrue( $draft->saved() );
		self::assertSame( $id, $draft->id );
		self::assertSame( 'Independent', $this->tables->made[ $id ]['name'] );
		self::assertSame(
			array(
				array(
					'size'    => '149',
					'specs'   => array(
						'axle_width'   => '8.5000',
						'deck_min'     => '8.3750',
						'deck_max'     => '8.6000',
						'truck_height' => '55.0000',
						'max_wheel'    => '56.0000',
						'baseplate'    => 'both',
					),
					'source'  => 'Independent, https://example.com/chart',
					'read_on' => '2026-11-10',
				),
				array(
					'size'    => '159',
					'specs'   => array( 'axle_width' => '8.7500' ),
					'source'  => '',
					'read_on' => '',
				),
			),
			$this->tables->made[ $id ]['table']->toArray(),
			'blank rows and what is not a row are no rows; a key this version does not know is not taken'
		);
	}

	public function test_everything_wrong_is_named_by_its_row_and_field_and_nothing_is_saved(): void {
		$id    = $this->editor->add( 'Indy' )->id;
		$draft = $this->editor->save(
			$id,
			' ',
			array(
				self::row( '149', array( 'axle_width' => 'wide' ) ),
				self::row( '', array( 'deck_min' => '8' ) ),
				self::row( str_repeat( 'x', 41 ), array(), str_repeat( 's', 201 ), '2026-02-30' ),
				self::row( '149', array( 'baseplate' => 'square' ), '', 'yesterday' ),
				self::row( '', array(), 'a source alone' ),
				self::row( '', array(), '', '2026-11-10' ),
			)
		);
		self::assertFalse( $draft->saved() );
		self::assertSame( $id, $draft->id );
		self::assertSame(
			array(
				'0:name:name_missing',
				'1:axle_width:measure_malformed',
				'2:size:size_missing',
				'3:size:size_long',
				'3:source:source_long',
				'3:read_on:date_invalid',
				'4:baseplate:spec_unknown_choice',
				'4:size:size_duplicate',
				'4:read_on:date_invalid',
				'5:size:size_missing',
				'6:size:size_missing',
			),
			self::codes( $draft->problems )
		);
		self::assertSame( 0, $this->tables->updates );
		self::assertSame( 'Indy', $this->tables->made[ $id ]['name'] );
	}

	// Claude in Cowork's review of milestone 3, minor 1.
	public function test_a_row_whose_narrowest_deck_is_wider_than_its_widest_is_refused(): void {
		$id    = $this->editor->add( 'Indy' )->id;
		$draft = $this->editor->save(
			$id,
			'Indy',
			array(
				self::row(
					'159',
					array(
						'deck_min' => '8.6',
						'deck_max' => '8 3/8',
					)
				),
				self::row(
					'149',
					array(
						'deck_min' => '8.5',
						'deck_max' => '8.5',
					)
				),
				self::row( '139', array( 'deck_min' => '9' ) ),
				self::row(
					'129',
					array(
						'deck_min' => 'wide',
						'deck_max' => '7',
					)
				),
			)
		);
		self::assertSame( 'deck_range_reversed', SpecTableEditor::RANGE );
		self::assertSame( array( '1:deck_min:deck_range_reversed', '4:deck_min:measure_malformed' ), self::codes( $draft->problems ) );
		self::assertSame( 0, $this->tables->updates );
	}

	// Issue #25: the range is held only where both of the row's own ends read, whatever another row's did.
	public function test_a_range_is_compared_only_where_both_of_its_own_rows_ends_read(): void {
		$id    = $this->editor->add( 'Indy' )->id;
		$draft = $this->editor->save(
			$id,
			'Indy',
			array(
				// Each would compare as reversed by the number it starts with.
				self::row(
					'129',
					array(
						'deck_min' => '9 wide',
						'deck_max' => '8',
					)
				),
				self::row(
					'139',
					array(
						'deck_min' => '9',
						'deck_max' => '8 wide',
					)
				),
				// Reversed, and its own ends both read: the rows above do not excuse it.
				self::row(
					'149',
					array(
						'deck_min' => '8.6',
						'deck_max' => '8',
					)
				),
			)
		);
		self::assertSame( array( '1:deck_min:measure_malformed', '2:deck_max:measure_malformed', '3:deck_min:deck_range_reversed' ), self::codes( $draft->problems ) );
	}

	public function test_a_name_is_another_charts_whatever_the_case_of_its_accented_letters(): void {
		$this->editor->add( 'Éclat' );
		self::assertSame( array( '0:name:name_taken' ), self::codes( $this->editor->add( 'éCLAT' )->problems ) );
	}

	// #72: without mbstring a name folds as PHP's strtolower() does, so an ASCII letter still matches in either case and
	// an accented one only in its own, as Words and WpTextFolding fall back.
	public function test_without_mbstring_a_name_is_another_charts_in_any_case_of_its_ascii_letters(): void {
		$this->editor->add( 'Éclat' );
		$GLOBALS['cbuild_test_no_mbstring'] = true;
		try {
			self::assertSame( array( '0:name:name_taken' ), self::codes( $this->editor->add( 'ÉCLAT' )->problems ) );
			self::assertTrue( $this->editor->add( 'éclat' )->saved(), 'its capital is not an ASCII letter' );
		} finally {
			unset( $GLOBALS['cbuild_test_no_mbstring'] );
		}
		self::assertSame( array( '0:name:name_taken' ), self::codes( $this->editor->add( 'ÉCLAT' )->problems ), 'and with it, as before' );
	}

	// Minor 2: two charts of one name made every import row naming either ambiguous, and the panel's list one group.
	public function test_a_chart_cannot_take_a_name_another_chart_has_whatever_its_case(): void {
		$indy    = $this->editor->add( 'Independent' )->id;
		$thunder = $this->editor->add( 'Thunder' )->id;
		self::assertSame( 'name_taken', SpecTableEditor::NAME_TAKEN );

		$again = $this->editor->add( ' INDEPENDENT ' );
		self::assertSame( 0, $again->id );
		self::assertSame( array( '0:name:name_taken' ), self::codes( $again->problems ) );
		self::assertCount( 2, $this->tables->made );

		self::assertSame( array( '0:name:name_taken' ), self::codes( $this->editor->save( $thunder, 'independent', array() )->problems ) );
		self::assertSame( 0, $this->tables->updates );

		// Its own name, in another case or as it was, is its own to keep.
		self::assertTrue( $this->editor->save( $indy, 'INDEPENDENT', array() )->saved() );
		self::assertTrue( $this->editor->save( $thunder, 'Thunder', array() )->saved() );
		self::assertTrue( $this->editor->add( 'Independent Trucks' )->saved() );
	}

	public function test_the_edges_of_what_a_row_takes(): void {
		$id    = $this->editor->add( 'Indy' )->id;
		$draft = $this->editor->save(
			$id,
			'Indy',
			array(
				self::row( str_repeat( 'x', 40 ), array(), str_repeat( 's', 200 ), '2024-02-29' ),
				self::row( 'a', array(), '', '2026-13-01' ),
				self::row( 'b', array(), '', '2026-1-1' ),
				self::row( 'c', array(), '', '2026-11-10 ' ),
				self::row( 'd', array(), '', 'x2026-11-10' ),
				self::row( 'e', array(), '', "2026-11-10\n" ),
			)
		);
		self::assertSame( array( '2:read_on:date_invalid', '3:read_on:date_invalid', '5:read_on:date_invalid' ), self::codes( $draft->problems ), 'forty and two hundred characters and a leap day pass; a day is trimmed' );
	}

	public function test_a_chart_holds_a_hundred_rows_and_no_more(): void {
		$id   = $this->editor->add( 'Indy' )->id;
		$rows = array();
		foreach ( range( 1, 100 ) as $size ) {
			$rows[] = self::row( (string) $size );
		}
		self::assertTrue( $this->editor->save( $id, 'Indy', $rows )->saved() );
		$rows[] = self::row( '' );
		self::assertTrue( $this->editor->save( $id, 'Indy', $rows )->saved(), 'a blank row is not counted' );
		$rows[] = self::row( '101' );
		self::assertSame( array( '0:size:rows_too_many' ), self::codes( $this->editor->save( $id, 'Indy', $rows )->problems ) );
		self::assertSame( 2, $this->tables->updates );
	}

	public function test_a_chart_that_is_not_there_is_not_saved_or_deleted(): void {
		$draft = $this->editor->save( 7, 'Indy', array( self::row( '149' ) ) );
		self::assertSame( array( '0:name:table_missing' ), self::codes( $draft->problems ) );
		self::assertSame( 7, $draft->id );
		self::assertSame( array(), $this->tables->made );
		self::assertFalse( $this->editor->delete( 7 ) );
	}

	public function test_a_chart_is_deleted(): void {
		$id   = $this->editor->add( 'Indy' )->id;
		$kept = $this->editor->add( 'Thunder' )->id;
		self::assertTrue( $this->editor->delete( $id ) );
		self::assertSame( array( $kept ), array_keys( $this->tables->made ) );
	}
}
