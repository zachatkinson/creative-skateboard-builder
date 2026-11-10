<?php
/**
 * What a part stores of its own specs.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Data model: `_cbuild_specs` holds one value per spec key, and a part names a spec table and a size.
 */
final class StoredSpecsTest extends TestCase {

	public function test_the_values_that_are_text_and_not_blank_are_the_parts_fields(): void {
		self::assertSame(
			array(
				'deck_width' => '8.2500',
				'kingpin'    => 'inverted',
			),
			StoredSpecs::fields(
				array(
					'deck_width'  => ' 8.2500 ',
					'deck_length' => '  ',
					'wheelbase'   => 14,
					'kingpin'     => 'inverted',
					7             => 'seven',
				)
			)
		);
	}

	public function test_anything_that_is_not_a_list_of_fields_is_no_fields(): void {
		foreach ( array( '', null, 'deck_width', 8, false ) as $stored ) {
			self::assertSame( array(), StoredSpecs::fields( $stored ) );
		}
	}

	public function test_the_table_and_the_size_a_part_names(): void {
		self::assertSame(
			array(
				'table' => 12,
				'size'  => '149',
			),
			StoredSpecs::named(
				array(
					'table' => 12,
					'size'  => ' 149 ',
				)
			)
		);
		self::assertSame(
			array(
				'table' => 1,
				'size'  => '',
			),
			StoredSpecs::named( array( 'table' => 1 ) ),
			'a table alone'
		);
		self::assertSame(
			array(
				'table' => 0,
				'size'  => '149',
			),
			StoredSpecs::named( array( 'size' => '149' ) ),
			'a size alone'
		);
	}

	public function test_what_is_not_a_name_names_nothing(): void {
		$none = array(
			'table' => 0,
			'size'  => '',
		);
		$wrong = array(
			'',
			null,
			'12:149',
			array(
				'table' => '12',
				'size'  => 149,
			),
			array(
				'table' => 0,
				'size'  => ' ',
			),
			array( 'table' => -1 ),
		);
		foreach ( $wrong as $stored ) {
			self::assertSame( $none, StoredSpecs::named( $stored ) );
		}
	}
}
