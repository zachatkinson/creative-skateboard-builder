<?php
/**
 * The specs sheet, written.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Service\Csv;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecSheet;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLayers;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartSpecs;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Import and export: a row per part, keyed on the ID and the SKU; Security: a cell that starts with =,
 * +, - or @ is neutralised.
 */
final class SpecExportTest extends TestCase {

	private InMemoryPartSpecs $parts;

	private InMemorySpecTables $tables;

	private SpecExport $export;

	protected function setUp(): void {
		$this->parts  = new InMemoryPartSpecs();
		$this->tables = new InMemorySpecTables();
		$this->export = new SpecExport( $this->parts, $this->tables );
	}

	public function test_the_columns_are_the_keys_the_name_the_chart_and_every_spec(): void {
		$columns = SpecSheet::columns();
		self::assertSame( array( 'id', 'sku', 'name', 'spec_table', 'spec_size', 'layer_image' ), array_slice( $columns, 0, 6 ) );
		self::assertSame( array_map( static fn( SpecKey $key ): string => $key->value, SpecKey::cases() ), array_slice( $columns, 6 ) );
		self::assertSame( array( $columns ), Csv::parse( $this->export->header() ) );
	}

	public function test_a_heading_row_is_mapped_whatever_its_case_and_a_column_there_twice_is_named(): void {
		$map = SpecSheet::map( array( 'SKU', 'Notes', 'sku', 'sku', 'deck_width', 'Deck_Width', 'spec_size' ) );
		self::assertSame(
			array(
				'sku'        => 0,
				'deck_width' => 4,
				'spec_size'  => 6,
			),
			$map['columns']
		);
		self::assertSame( array( 'sku', 'deck_width' ), $map['twice'] );
	}

	public function test_a_row_holds_what_the_part_holds_itself(): void {
		$chart = $this->tables->create( 'Independent', SpecTable::read( array() ) );
		$this->parts->add( 7, 'YEA-DK-109-7750', array( 'deck_width' => '7.7500' ), 0, '', 'Katie 7.75' );
		$this->parts->add( 9, 'IND-TR-149', array(), $chart, '149', 'Stage 11' );
		$this->parts->add( 11, '', array(), 99, 'gone' );

		$rows = Csv::parse( (string) $this->export->page( 1 ) );
		self::assertCount( 3, $rows );
		$first = array_combine( SpecSheet::columns(), $rows[0] );
		self::assertSame( '7', $first['id'] );
		self::assertSame( 'YEA-DK-109-7750', $first['sku'] );
		self::assertSame( 'Katie 7.75', $first['name'] );
		self::assertSame( '', $first['spec_table'] );
		self::assertSame( '7.7500', $first['deck_width'] );
		self::assertSame( '', $first['axle_width'] );
		$second = array_combine( SpecSheet::columns(), $rows[1] );
		self::assertSame( 'Independent', $second['spec_table'] );
		self::assertSame( '149', $second['spec_size'] );
		// A chart that is gone has no name to write.
		self::assertSame( array( '11', '', '', '', 'gone' ), array_slice( $rows[2], 0, 5 ) );
	}

	public function test_a_cell_a_spreadsheet_would_run_is_neutralised(): void {
		$this->parts->add( 7, '=HYPERLINK("http://example.com")', array(), 0, '@size', '+1' );
		$page = (string) $this->export->page( 1 );
		self::assertStringContainsString( "7,\"'=HYPERLINK(\"\"http://example.com\"\")\",'+1,,'@size,", $page );
	}

	public function test_the_sheet_is_written_a_page_at_a_time_to_the_last(): void {
		for ( $id = 1; $id <= SpecExport::PAGE + 1; $id++ ) {
			$this->parts->add( $id, 'S' . $id );
		}
		self::assertCount( SpecExport::PAGE, Csv::parse( (string) $this->export->page( 1 ) ) );
		self::assertCount( 1, Csv::parse( (string) $this->export->page( 2 ) ) );
		self::assertNull( $this->export->page( 3 ) );

		$rows = Csv::parse( $this->export->text() );
		self::assertCount( SpecExport::PAGE + 2, $rows );
		self::assertSame( 'id', $rows[0][0] );
		self::assertSame( '1', $rows[1][0] );
		self::assertSame( (string) ( SpecExport::PAGE + 1 ), $rows[ SpecExport::PAGE + 1 ][0] );
	}

	public function test_a_product_gone_since_the_page_was_listed_is_left_out(): void {
		$this->parts->add( 1, 'A' );
		$this->parts->add( 2, 'B' );
		$gone = new class( $this->parts ) implements \CreativeBranding\SkateboardBuilder\Port\PartSpecs {
			public function __construct( private InMemoryPartSpecs $inner ) {}
			public function page( int $page, int $size ): array {
				return $this->inner->page( $page, $size );
			}
			public function read( int ...$ids ): array {
				return $this->inner->read( 2 );
			}
			public function bySku( string ...$skus ): array {
				return array();
			}
			public function write( int $id, array $fields, int $table, string $size ): void {}
		};
		$rows = Csv::parse( (string) ( new SpecExport( $gone, $this->tables ) )->page( 1 ) );
		self::assertSame( array( '2' ), array_column( $rows, 0 ) );
	}

	// D-287: a part's own layer image is written by its file name; one with none, or one the layer images know nothing
	// of, writes the cell empty, whatever the Media Library names attachment 0 or 1.
	public function test_the_layer_image_column_names_a_parts_own_image_alone(): void {
		$this->parts->add( 7, 'DK-7', array(), 0, '', 'Katie' );
		$this->parts->add( 8, 'DK-8', array(), 0, '', 'Kelly' );
		$this->parts->add( 9, 'DK-9', array(), 0, '', 'Twin' );
		$layers        = new InMemoryPartLayers();
		$layers->own   = array(
			7 => 11,
			8 => 0,
		);
		$layers->files = array(
			0  => 'zero.png',
			1  => 'one.png',
			11 => 'katie.png',
		);
		$rows = Csv::parse( (string) $this->export->withLayers( $layers )->page( 1 ) );
		self::assertSame( array( 'katie.png', '', '' ), array_column( $rows, 5 ) );
	}
}
