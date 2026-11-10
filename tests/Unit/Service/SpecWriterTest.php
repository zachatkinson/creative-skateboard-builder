<?php
/**
 * A part's specs, checked before they are stored.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Part specs: each field takes the shop's decimal separator or a fraction; one that fails is not saved
 * and the rest is.
 */
final class SpecWriterTest extends TestCase {

	private InMemorySpecTables $tables;

	private SpecWriter $writer;

	private int $chart;

	protected function setUp(): void {
		$this->tables = new InMemorySpecTables();
		$this->writer = new SpecWriter( $this->tables );
		$this->chart  = $this->tables->create(
			'Independent',
			SpecTable::read(
				array(
					array(
						'size'  => '149',
						'specs' => array( 'axle_width' => '8.5' ),
					),
				)
			)
		);
	}

	/**
	 * A draft's problems, as key:code.
	 *
	 * @param list<array{key: string, code: string, text: string}> $problems The problems.
	 * @return list<string>
	 */
	private static function codes( array $problems ): array {
		return array_map( static fn( array $problem ): string => $problem['key'] . ':' . $problem['code'], $problems );
	}

	public function test_a_value_is_stored_as_the_index_writes_it_however_it_was_typed(): void {
		foreach ( array( array( '8.25', '.' ), array( '8,25', ',' ), array( '8 1/4', ',' ), array( ' 8.25 in ', '.' ), array( '209.55mm', '.' ) ) as [ $typed, $separator ] ) {
			$draft = $this->writer->draft( array( 'deck_width' => $typed ), array(), $separator );
			self::assertSame( array( 'deck_width' => '8.2500' ), $draft->fields, $typed );
			self::assertSame( array(), $draft->problems, $typed );
		}
	}

	public function test_every_kind_of_spec_is_read_as_its_kind(): void {
		$draft = $this->writer->draft(
			array(
				'truck_height'     => '55',
				'hole_pattern'     => 'New_School',
				'spacers_built_in' => 'Yes',
				'set_size'         => '8',
			),
			array()
		);
		self::assertSame(
			array(
				'hole_pattern'     => 'new_school',
				'truck_height'     => '55.0000',
				'spacers_built_in' => '1',
				'set_size'         => '8',
			),
			$draft->fields,
			'in the spec keys\' own order'
		);
		self::assertSame( array(), $draft->problems );
	}

	public function test_a_blank_value_is_removed_and_one_not_sent_is_left_as_it_is(): void {
		$draft = $this->writer->draft(
			array(
				'deck_width'  => '  ',
				'deck_length' => '32',
			),
			array(
				'deck_width' => '8.2500',
				'wheelbase'  => ' 14.2500 ',
				'max_wheel'  => '',
			)
		);
		self::assertSame(
			array(
				'deck_length' => '32.0000',
				'wheelbase'   => '14.2500',
			),
			$draft->fields
		);
		self::assertSame( array(), $draft->problems );
	}

	public function test_a_value_that_is_wrong_is_named_and_what_was_stored_is_kept(): void {
		$draft = $this->writer->draft(
			array(
				'deck_width'       => 'wide',
				'deck_length'      => '-32',
				'wheelbase'        => '14 1/0',
				'hole_pattern'     => 'square',
				'spacers_built_in' => 'maybe',
				'set_size'         => '0',
				'bolt_length'      => '1',
			),
			array(
				'deck_width'  => ' 8.2500 ',
				'wheelbase'   => ' ',
				'bolt_length' => '0.8750',
			)
		);
		self::assertSame(
			array(
				'deck_width:measure_malformed',
				'deck_length:measure_malformed',
				'wheelbase:measure_zero_denominator',
				'hole_pattern:spec_unknown_choice',
				'spacers_built_in:spec_wrong_kind',
				'set_size:spec_not_positive',
			),
			self::codes( $draft->problems )
		);
		self::assertSame( 'wide', $draft->problems[0]['text'], 'with what was typed' );
		self::assertSame(
			array(
				'deck_width'  => '8.2500',
				'bolt_length' => '1.0000',
			),
			$draft->fields,
			'the old width is kept, the new bolt length saved, and nothing blank is stored'
		);
	}

	public function test_a_key_this_version_does_not_know_is_not_taken_and_one_already_stored_is_left(): void {
		$draft = $this->writer->draft(
			array(
				'kingpin'    => 'inverted',
				'deck_width' => '8',
			),
			array(
				'concave' => ' deep ',
				'rocker'  => ' ',
			)
		);
		self::assertSame(
			array(
				'deck_width' => '8.0000',
				'concave'    => 'deep',
			),
			$draft->fields,
			'a newer version may have written it'
		);
		self::assertSame( array(), $draft->problems );
	}

	public function test_a_table_and_a_size_it_has_are_named(): void {
		$draft = $this->writer->draft(
			array(),
			array(),
			'.',
			array(
				'table' => $this->chart,
				'size'  => ' 149 ',
			)
		);
		self::assertSame( $this->chart, $draft->table );
		self::assertSame( '149', $draft->size );
		self::assertSame( array(), $draft->problems );
	}

	public function test_a_table_alone_a_size_alone_and_neither_are_all_allowed(): void {
		$now = array(
			'table' => $this->chart,
			'size'  => '149',
		);
		foreach ( array( array( $this->chart, '' ), array( 0, '149' ), array( 0, '' ), array( -3, '' ) ) as [ $table, $size ] ) {
			$draft = $this->writer->draft( array(), array(), '.', compact( 'table', 'size' ), $now );
			self::assertSame( array( $table, $size ), array( $draft->table, $draft->size ) );
			self::assertSame( array(), $draft->problems );
		}
	}

	public function test_a_name_that_leads_nowhere_is_refused_and_the_old_one_kept(): void {
		$now = array(
			'table' => $this->chart,
			'size'  => '149',
		);
		$cases = array(
			'table_missing' => array( $this->chart + 1, '149' ),
			'size_missing'  => array( $this->chart, '159' ),
			'size_long'     => array( 0, str_repeat( 'x', 41 ) ),
		);
		foreach ( $cases as $code => [ $table, $size ] ) {
			$draft = $this->writer->draft(
				array(),
				array(),
				'.',
				array(
					'table' => $table,
					'size'  => ' ' . $size . ' ',
				),
				$now
			);
			self::assertSame( array( 'spec_table:' . $code ), self::codes( $draft->problems ) );
			self::assertSame( $size, $draft->problems[0]['text'] );
			self::assertSame( array( $this->chart, '149' ), array( $draft->table, $draft->size ), $code );
		}
		$draft = $this->writer->draft( array(), array(), '.', array( 'table' => 0, 'size' => ' ' . str_repeat( 'x', 40 ) . ' ' ) );
		self::assertSame( array(), $draft->problems, 'forty characters is a size' );
	}

	public function test_the_table_and_size_not_sent_are_left_as_they_are(): void {
		$draft = $this->writer->draft(
			array(),
			array(),
			'.',
			null,
			array(
				'table' => 99,
				'size'  => 'gone',
			)
		);
		self::assertSame( array( 99, 'gone' ), array( $draft->table, $draft->size ), 'not checked: nothing was sent' );
		self::assertSame( array(), $draft->problems );
		$none = $this->writer->draft( array(), array() );
		self::assertSame( array( 0, '' ), array( $none->table, $none->size ) );
	}
}
