<?php
/**
 * Parts for the domain's tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Domain;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;

/**
 * Parts written the way a size chart is: `Parts::of( array( 'deck_width' => '8.5' ) )`. A measure is text in the
 * spec's own unit, a choice its value, a flag a boolean and a count a whole number.
 */
final class Parts {

	/**
	 * A part with these specs.
	 *
	 * @param array<string, string|bool|int> $specs   The specs by key.
	 * @param int                            $id      The part's id.
	 * @param int                            $price   Its price in minor units.
	 * @param string                         $brand   Its brand.
	 * @param bool                           $inStock Whether it is in stock.
	 */
	public static function of( array $specs = array(), int $id = 1, int $price = 1000, string $brand = '', bool $inStock = true ): Part {
		$held = Specs::none();
		foreach ( $specs as $name => $value ) {
			$key  = SpecKey::from( $name );
			$unit = $key->unit();
			$held = $held->with( $key, null !== $unit && is_string( $value ) ? MeasureParser::parse( $value, $unit ) : $value );
		}
		return new Part( $id, $brand, $held, $price, $inStock );
	}

	/**
	 * Picks from parts by slot.
	 *
	 * @param array<string, Part> $parts The parts by slot key.
	 */
	public static function picks( array $parts ): Picks {
		$picks = Picks::none();
		foreach ( $parts as $slot => $part ) {
			$picks = $picks->with( $slot, $part );
		}
		return $picks;
	}

	/**
	 * Inches from text.
	 *
	 * @param string $text The length.
	 */
	public static function inches( string $text ): Measure {
		return MeasureParser::parse( $text, Unit::Inch );
	}

	/**
	 * Millimetres from text.
	 *
	 * @param string $text The length.
	 */
	public static function mm( string $text ): Measure {
		return MeasureParser::parse( $text, Unit::Millimetre );
	}

	/**
	 * Independent's trucks from the spec's table under R1, by size number: axle and deck range.
	 */
	public const array INDY = array(
		'109' => array( '6.9', '6.25', '7.6' ),
		'129' => array( '7.6', '7.4', '7.8' ),
		'139' => array( '8.0', '7.8', '8.2' ),
		'144' => array( '8.25', '8.2', '8.375' ),
		'146' => array( '8.375', '8.125', '8.625' ),
		'149' => array( '8.5', '8.375', '8.6' ),
		'151' => array( '8.625', '8.375', '8.875' ),
		'159' => array( '8.75', '8.6', '9.0' ),
		'166' => array( '9.0', '8.5', '9.5' ),
		'169' => array( '9.125', '9.0', '9.5' ),
		'215' => array( '10.0', '9.5', '10.5' ),
	);

	/**
	 * An Independent truck by its size number, with more specs if wanted.
	 *
	 * @param string                         $size  The size number.
	 * @param array<string, string|bool|int> $specs Specs to add or replace.
	 */
	public static function indy( string $size, array $specs = array() ): Part {
		[ $axle, $min, $max ] = self::INDY[ $size ];
		return self::of(
			$specs + array(
				'axle_width' => $axle,
				'deck_min'   => $min,
				'deck_max'   => $max,
			),
			(int) $size,
			2999,
			'independent'
		);
	}
}
