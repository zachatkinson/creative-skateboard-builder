<?php
/**
 * CSV, read and written.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\Csv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Import and export; Security: a cell that starts with =, +, - or @ is neutralised when specs are
 * exported, so a spreadsheet can't run it.
 */
final class CsvTest extends TestCase {

	public function test_rows_and_cells_are_read_whatever_ends_a_line(): void {
		$rows = array( array( 'id', 'sku', 'deck_width' ), array( '12', 'YEA-DK', '8.25' ), array( '13', '', '' ) );
		foreach ( array( "\n", "\r\n", "\r" ) as $end ) {
			self::assertSame( $rows, Csv::parse( "id,sku,deck_width{$end}12,YEA-DK,8.25{$end}13,,{$end}" ), bin2hex( $end ) );
			self::assertSame( $rows, Csv::parse( "id,sku,deck_width{$end}12,YEA-DK,8.25{$end}13,," ), 'with no line break at the end' );
		}
	}

	public function test_a_quoted_cell_holds_commas_quotes_and_line_breaks(): void {
		self::assertSame(
			array(
				array( 'name', 'note' ),
				array( 'Deck, 8.25"', "two\nlines" ),
				array( 'a "b" c', "x\r\ny" ),
				array( 'plain"quote', 'mid"dle' ),
				array( '', 'after an empty quoted cell' ),
			),
			Csv::parse( "name,note\n\"Deck, 8.25\"\"\",\"two\nlines\"\n\"a \"\"b\"\" c\",\"x\r\ny\"\nplain\"quote,mid\"dle\n\"\",after an empty quoted cell\n" )
		);
	}

	public function test_cells_are_trimmed_and_a_row_with_nothing_in_it_is_left_out(): void {
		self::assertSame(
			array( array( 'id', 'sku' ), array( '12', 'A B' ), array( '', '0' ) ),
			Csv::parse( " id , sku \n\n ,  \n\"\",\"\"\n 12 ,\" A B \"\n\r\n,0\n" )
		);
		self::assertSame( array(), Csv::parse( '' ) );
		self::assertSame( array(), Csv::parse( "\n\n , \n" ) );
		self::assertSame( array( array( 'x' ) ), Csv::parse( 'x' ) );
	}

	public function test_a_semicolon_parts_the_cells_when_asked_and_is_found_from_the_first_line(): void {
		self::assertSame( array( array( 'id', 'deck_width' ), array( '12', '8,25' ) ), Csv::parse( "id;deck_width\n12;8,25\n", ';' ) );
		self::assertSame( array( array( 'id;deck_width' ) ), Csv::parse( 'id;deck_width' ), 'a comma unless told' );
		self::assertSame( ';', Csv::delimiter( "id;sku;deck_width\n12,5;a;b\n" ) );
		self::assertSame( ',', Csv::delimiter( "id,sku;x\n1;2;3;4\n" ), 'as many of each on the first line: a comma' );
		self::assertSame( ',', Csv::delimiter( "id,sku\n1;2;3;4\n" ), 'the first line decides' );
		self::assertSame( ';', Csv::delimiter( "\r\nid;sku\r\n" ), 'the first line with anything on it' );
		self::assertSame( ',', Csv::delimiter( '' ) );
		self::assertSame( ',', Csv::delimiter( "\n" ) );
	}

	/**
	 * Each start of a formula, and what does not start one.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function cells(): array {
		return array(
			'equals'            => array( '=1+1', "'=1+1" ),
			'plus'              => array( '+1', "'+1" ),
			'minus'             => array( '-1', "'-1" ),
			'at'                => array( '@SUM(A1)', "'@SUM(A1)" ),
			'tab'               => array( "\t=1", "'\t=1" ),
			'carriage return'   => array( "\r=1", "\"'\r=1\"" ),
			'a formula, quoted' => array( '=HYPERLINK("x","y")', '"\'=HYPERLINK(""x"",""y"")"' ),
			'a number'          => array( '8.25', '8.25' ),
			'a minus inside'    => array( 'YEA-DK-109', 'YEA-DK-109' ),
			'blank'             => array( '', '' ),
			'a comma'           => array( 'a,b', '"a,b"' ),
			'a semicolon'       => array( 'a;b', '"a;b"' ),
			'a quote'           => array( '8.25"', '"8.25"""' ),
			'a line break'      => array( "a\nb", "\"a\nb\"" ),
			'an apostrophe'     => array( "'quoted'", "'quoted'" ),
		);
	}

	#[DataProvider( 'cells' )]
	public function test_a_cell_a_spreadsheet_would_run_is_written_so_it_cannot( string $cell, string $written ): void {
		self::assertSame( 'id,' . $written . ",end\r\n", Csv::line( array( 'id', $cell, 'end' ) ) );
	}

	#[DataProvider( 'cells' )]
	public function test_what_is_written_reads_back_as_it_was( string $cell, string $written ): void {
		unset( $written );
		self::assertSame( array( array( 'id', trim( $cell ), 'end' ) ), Csv::parse( Csv::line( array( 'id', $cell, 'end' ) ) ) );
	}

	public function test_an_apostrophe_is_taken_off_only_before_the_start_of_a_formula(): void {
		self::assertSame( array( array( "'", "'a", '=1', "'''=1", '-' ) ), Csv::parse( "',\"'a\",'=1,'''=1,'-\n" ) );
		self::assertSame( "\r\n", Csv::line( array() ) );
		self::assertSame( "a\r\n", Csv::line( array( 'a' ) ) );
	}
}
