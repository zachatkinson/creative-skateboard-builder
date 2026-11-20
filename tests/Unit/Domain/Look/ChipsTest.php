<?php
/**
 * Chips.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Look\Chip;
use CreativeBranding\SkateboardBuilder\Domain\Look\ChipGroup;
use CreativeBranding\SkateboardBuilder\Domain\Look\Chips;
use CreativeBranding\SkateboardBuilder\Domain\Look\Facets;
use PHPUnit\Framework\TestCase;

/**
 * Filter chips from the step's own data (SPEC-CONFIGURATOR, Filters from the step's own data; D-247).
 */
final class ChipsTest extends TestCase {

	/**
	 * A wheel look.
	 *
	 * @param string       $key      Its key.
	 * @param string       $brand    Its brand.
	 * @param list<string> $sizes    The diameters it comes in.
	 * @param list<string> $hardness The hardnesses it comes in, or none.
	 */
	private static function wheel( string $key, string $brand, array $sizes, array $hardness = array() ): Facets {
		$facets = array(
			'brand'          => array( $brand ),
			'wheel_diameter' => $sizes,
		);
		if ( array() !== $hardness ) {
			$facets['pa_hardness'] = $hardness;
		}
		return new Facets( $key, $facets );
	}

	/**
	 * Five wheels: two brands, three sizes, two hardnesses.
	 *
	 * @return list<Facets>
	 */
	private static function wheels(): array {
		return array(
			self::wheel( 'a', 'spitfire', array( '52', '54' ), array( '99a' ) ),
			self::wheel( 'b', 'spitfire', array( '54' ), array( '101a' ) ),
			self::wheel( 'c', 'bones', array( '52' ), array( '99a' ) ),
			self::wheel( 'd', 'bones', array( '56' ), array( '99a', '101a' ) ),
			self::wheel( 'e', 'Bones', array( '54' ), array( '101a' ) ),
		);
	}

	/**
	 * The groups as facet => value => [count, chosen].
	 *
	 * @param list<ChipGroup> $groups The groups.
	 * @return array<string, array<string, array{int, bool}>>
	 */
	private static function shown( array $groups ): array {
		$shown = array();
		foreach ( $groups as $group ) {
			foreach ( $group->chips as $chip ) {
				$shown[ $group->name ][ $chip->value ] = array( $chip->count, $chip->chosen );
			}
		}
		return $shown;
	}

	/**
	 * The keys of the looks the choices leave.
	 *
	 * @param array<string, list<string>> $chosen The choices.
	 * @return list<string>
	 */
	private static function left( array $chosen ): array {
		return array_map( static fn( Facets $look ): string => $look->key, Chips::filter( self::wheels(), $chosen ) );
	}

	public function test_a_wheels_step_offers_brand_diameter_and_hardness_with_what_each_leaves(): void {
		self::assertSame(
			array(
				'brand'          => array(
					'bones'    => array( 2, false ),
					'Bones'    => array( 1, false ),
					'spitfire' => array( 2, false ),
				),
				'wheel_diameter' => array(
					'52' => array( 2, false ),
					'54' => array( 3, false ),
					'56' => array( 1, false ),
				),
				'pa_hardness'    => array(
					'99a'  => array( 3, false ),
					'101a' => array( 3, false ),
				),
			),
			self::shown( Chips::groups( self::wheels(), array( 'brand', 'wheel_diameter' ), array() ) )
		);
	}

	public function test_chips_in_one_group_widen_and_groups_narrow_each_other(): void {
		self::assertSame( array( 'a', 'b', 'c', 'd', 'e' ), self::left( array() ) );
		self::assertSame( array( 'a', 'c' ), self::left( array( 'wheel_diameter' => array( '52' ) ) ) );
		self::assertSame( array( 'a', 'c', 'd' ), self::left( array( 'wheel_diameter' => array( '52', '56' ) ) ) );
		self::assertSame( array( 'c', 'd' ), self::left( array( 'wheel_diameter' => array( '52', '56' ), 'brand' => array( 'bones' ) ) ) );
		self::assertSame( array( 'a', 'b', 'c', 'd', 'e' ), self::left( array( 'brand' => array() ) ), 'a group with nothing chosen narrows nothing' );
		self::assertSame( array(), self::left( array( 'pa_grade' => array( 'eight' ) ) ), 'a facet no look carries leaves none' );
	}

	public function test_a_chip_counts_with_the_other_groups_choices_and_not_its_own(): void {
		$shown = self::shown( Chips::groups( self::wheels(), array( 'brand', 'wheel_diameter' ), array( 'brand' => array( 'spitfire' ) ) ) );
		self::assertSame( array( 2, false ), $shown['brand']['bones'], 'its own group is not applied: choosing Bones as well adds two' );
		self::assertSame( array( 2, true ), $shown['brand']['spitfire'] );
		self::assertSame( array( '52' => array( 1, false ), '54' => array( 2, false ), '56' => array( 0, false ) ), $shown['wheel_diameter'], 'Spitfire is applied' );
		self::assertSame( array( '99a' => array( 1, false ), '101a' => array( 1, false ) ), $shown['pa_hardness'] );
	}

	public function test_a_facet_offered_only_when_four_in_five_looks_carry_it(): void {
		$looks = self::wheels();
		$four  = array( ...array_slice( $looks, 0, 4 ), self::wheel( 'f', 'bones', array( '54' ) ) );
		self::assertArrayHasKey( 'pa_hardness', self::shown( Chips::groups( $four, array( 'brand' ), array() ) ), '4 of 5 is 80 %' );
		$three = array( ...array_slice( $looks, 0, 3 ), self::wheel( 'f', 'bones', array( '54' ) ), self::wheel( 'g', 'bones', array( '54' ) ) );
		self::assertArrayNotHasKey( 'pa_hardness', self::shown( Chips::groups( $three, array( 'brand' ), array() ) ), '3 of 5 is 60 %' );
		$empty = array( ...array_slice( $looks, 0, 4 ), new Facets( 'f', array( 'brand' => array( 'bones' ), 'pa_hardness' => array() ) ) );
		self::assertArrayHasKey( 'pa_hardness', self::shown( Chips::groups( $empty, array( 'brand' ), array() ) ), 'an empty list is not carrying it, and four still do' );
		$many = array();
		for ( $i = 0; $i < 500; $i++ ) {
			$many[] = self::wheel( 'w' . $i, 'bones', array( '54' ), $i < 399 ? array( 0 === $i % 2 ? '99a' : '101a' ) : array() );
		}
		self::assertArrayNotHasKey( 'pa_hardness', self::shown( Chips::groups( $many, array( 'brand' ), array() ) ), '399 of 500 is under 80 %' );
	}

	public function test_the_facets_always_offered_come_first_then_the_most_carried_then_by_name(): void {
		$look   = static fn( string $key, array $more ): Facets => new Facets( $key, array( 'brand' => array( $key ) ) + $more );
		$looks  = array(
			$look( 'a', array( 'pa_zinc' => array( 'yes' ), 'pa_head' => array( 'allen' ), 'pa_colour' => array( 'black' ) ) ),
			$look( 'b', array( 'pa_zinc' => array( 'no' ), 'pa_head' => array( 'phillips' ), 'pa_colour' => array( 'silver' ) ) ),
			$look( 'c', array( 'pa_zinc' => array( 'no' ), 'pa_head' => array( 'allen' ), 'pa_colour' => array( 'black' ) ) ),
			$look( 'd', array( 'pa_zinc' => array( 'yes' ), 'pa_colour' => array( 'black' ), 'pa_head' => array( 'allen' ) ) ),
			$look( 'e', array( 'pa_zinc' => array( 'yes' ), 'pa_colour' => array( 'silver' ) ) ),
		);
		$names  = array_map( static fn( ChipGroup $group ): string => $group->name, Chips::groups( $looks, array( 'brand' ), array() ) );
		self::assertSame( array( 'brand', 'pa_colour', 'pa_zinc', 'pa_head' ), $names, 'colour and zinc on all five, by name; head on four' );
	}

	public function test_a_facet_with_one_value_has_no_chips_and_takes_no_place(): void {
		$look   = static fn( string $key, string $brand, string $size ): Facets => new Facets(
			$key,
			array(
				'brand'       => array( $brand ),
				'pa_a'        => array( 'one' ),
				'pa_b'        => array( $size ),
				'pa_c'        => array( $size ),
				'pa_d'        => array( $size ),
				'pa_e'        => array( $size ),
				'pa_f'        => array( $size ),
			)
		);
		$groups = Chips::groups( array( $look( 'x', 'mob', '1' ), $look( 'y', 'mob', '2' ) ), array( 'brand' ), array() );
		self::assertSame( array( 'pa_b', 'pa_c', 'pa_d', 'pa_e' ), array_map( static fn( ChipGroup $group ): string => $group->name, $groups ), 'one brand and one pa_a value offer no choice, and pa_f is a fifth' );
	}

	public function test_values_come_in_natural_order(): void {
		$looks  = array( self::wheel( 'a', 'x', array( '9' ) ), self::wheel( 'b', 'x', array( '10' ) ), self::wheel( 'c', 'x', array( '9.5' ) ) );
		$groups = Chips::groups( $looks, array( 'wheel_diameter' ), array() );
		self::assertSame( array( '9', '9.5', '10' ), array_map( static fn( Chip $chip ): string => $chip->value, $groups[0]->chips ) );
	}

	public function test_no_looks_offer_no_chips(): void {
		self::assertSame( array(), Chips::groups( array(), array( 'brand' ), array() ) );
	}
}
