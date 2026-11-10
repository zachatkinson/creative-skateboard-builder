<?php
/**
 * A line of the attribute mapping.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Spec;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use PHPUnit\Framework\TestCase;

/**
 * Spec: Data model, Part specs (the deck's width comes from the pa_width attribute, in inches).
 */
final class AttributeMapTest extends TestCase {

	public function test_a_saved_line_reads_and_goes_back_the_same(): void {
		$line = array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'mm' );
		$map  = AttributeMap::read( $line );
		self::assertNotNull( $map );
		self::assertSame( 'pa_width', $map->attribute );
		self::assertSame( SpecKey::DeckWidth, $map->spec );
		self::assertSame( Unit::Millimetre, $map->unit );
		self::assertSame( $line, $map->toArray() );
	}

	public function test_no_unit_or_one_that_is_not_a_unit_is_the_specs_own(): void {
		foreach ( array( array(), array( 'unit' => '' ), array( 'unit' => 'cm' ), array( 'unit' => 7 ) ) as $unit ) {
			$map = AttributeMap::read( array( 'attribute' => 'Width', 'spec' => 'deck_width' ) + $unit );
			self::assertNotNull( $map );
			self::assertNull( $map->unit );
			self::assertSame( array( 'attribute' => 'Width', 'spec' => 'deck_width', 'unit' => '' ), $map->toArray() );
		}
	}

	public function test_a_unit_is_kept_only_for_a_measure(): void {
		$map = AttributeMap::read( array( 'attribute' => 'pa_pattern', 'spec' => 'hole_pattern', 'unit' => 'in' ) );
		self::assertNotNull( $map );
		self::assertNull( $map->unit );
	}

	public function test_what_is_not_a_line_reads_as_none(): void {
		// What answers like an array and is not one was never stored by this plugin.
		self::assertNull( AttributeMap::read( new \ArrayObject( array( 'attribute' => 'pa_width', 'spec' => 'deck_width' ) ) ) );
		$longest = str_repeat( 'a', AttributeMap::MAX_NAME );
		self::assertSame( 190, AttributeMap::MAX_NAME );
		self::assertNotNull( AttributeMap::read( array( 'attribute' => $longest, 'spec' => 'deck_width' ) ) );
		$junk = array(
			'a word'         => 'pa_width',
			'nothing'        => null,
			'no attribute'   => array( 'spec' => 'deck_width' ),
			'an empty name'  => array( 'attribute' => '', 'spec' => 'deck_width' ),
			'a name too long' => array( 'attribute' => $longest . 'a', 'spec' => 'deck_width' ),
			'a name not text' => array( 'attribute' => 7, 'spec' => 'deck_width' ),
			'no spec'        => array( 'attribute' => 'pa_width' ),
			'an unknown spec' => array( 'attribute' => 'pa_width', 'spec' => 'concave' ),
			'a spec not text' => array( 'attribute' => 'pa_width', 'spec' => 3 ),
		);
		foreach ( $junk as $what => $line ) {
			self::assertNull( AttributeMap::read( $line ), $what );
		}
	}
}
