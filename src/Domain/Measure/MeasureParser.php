<?php
/**
 * Reads a measure from what a person typed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

use function array_map;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function strtolower;
use function strtr;
use function trim;

/**
 * For the edge: a spec field, an import row, an attribute's term (spec: Data model, Part specs). Reads a whole number,
 * a decimal in the shop's separator, a fraction (`7/8`), a mixed number (`8 1/4`, `8-1/4`, `8¼`) and a unit mark
 * (`"`, `in`, `mm`), and refuses everything else, a whole number followed by a fraction of one or more among it. A value marked in the other unit is converted. Never given a
 * product's name: a name is not a measurement.
 */
final class MeasureParser {

	/**
	 * The one-character fractions shops paste, as the fractions they are.
	 */
	private const array VULGAR = array(
		'¼' => '1/4',
		'½' => '1/2',
		'¾' => '3/4',
		'⅛' => '1/8',
		'⅜' => '3/8',
		'⅝' => '5/8',
		'⅞' => '7/8',
	);

	/**
	 * The unit marks, in lower case.
	 */
	private const array MARKS = array(
		'"'      => Unit::Inch,
		'″'      => Unit::Inch,
		'in'     => Unit::Inch,
		'in.'    => Unit::Inch,
		'inch'   => Unit::Inch,
		'inches' => Unit::Inch,
		'mm'     => Unit::Millimetre,
	);

	/**
	 * Read a measure.
	 *
	 * @since 0.2.0
	 *
	 * @param string $text      What was typed.
	 * @param Unit   $unit      The spec's unit: what an unmarked number is in, and what the answer is in.
	 * @param string $separator The shop's decimal separator.
	 *
	 * @throws InvalidMeasure When the text is not a measure.
	 */
	public static function parse( string $text, Unit $unit, string $separator = '.' ): Measure {
		$read = self::measureOrError( $text, $unit, $separator );
		if ( $read instanceof MeasureError ) {
			$refused = InvalidMeasure::because( $read );
			throw $refused;
		}
		return $read;
	}

	/**
	 * Read a measure, or null where `parse()` would refuse: for a reader that passes over what it cannot read.
	 *
	 * @since 0.2.0
	 *
	 * @param string $text      What was stored or typed.
	 * @param Unit   $unit      The spec's unit.
	 * @param string $separator The decimal separator.
	 */
	public static function read( string $text, Unit $unit, string $separator = '.' ): ?Measure {
		$read = self::measureOrError( $text, $unit, $separator );
		return $read instanceof Measure ? $read : null;
	}

	/**
	 * The measure, or why the text is not one.
	 *
	 * @param string $text      What was typed.
	 * @param Unit   $unit      The spec's unit.
	 * @param string $separator The decimal separator.
	 */
	private static function measureOrError( string $text, Unit $unit, string $separator ): Measure|MeasureError {
		$text = trim( strtr( $text, array_map( static fn( string $fraction ): string => ' ' . $fraction, self::VULGAR ) ) );
		$text = (string) preg_replace( '/[ \t]+/', ' ', $text );
		if ( '' === $separator ) {
			// With no separator the pattern would read "12345678" as 123456.78.
			return MeasureError::Malformed;
		}
		$mark = '(?<mark>"|″|in\.?|inch|inches|mm)';
		$sep  = preg_quote( $separator, '/' );
		$form = '/\A(?:(?<whole>\d{1,6})(?:' . $sep . '(?<places>\d+)|[ -](?<top>\d{1,4})\/(?<bottom>\d{1,4}))?|(?<top>\d{1,4})\/(?<bottom>\d{1,4})) ?' . $mark . '?\z/iJ';
		if ( 1 !== preg_match( $form, $text, $found, PREG_UNMATCHED_AS_NULL ) ) {
			return MeasureError::Malformed;
		}
		// Four places and the one that rounds them, whatever was typed.
		$tenThousandths = Rounding::decimal( (string) $found['whole'], (string) $found['places'] );
		if ( null !== $found['bottom'] ) {
			$top    = (int) $found['top'];
			$bottom = (int) $found['bottom'];
			if ( 0 === $bottom ) {
				return MeasureError::ZeroDenominator;
			}
			if ( null !== $found['whole'] && $top >= $bottom ) {
				// "8 5/4" is a slip of the hand, not nine and a quarter.
				return MeasureError::Malformed;
			}
			$tenThousandths += Rounding::halfUp( 10000 * $top, $bottom );
		}
		$marked = null === $found['mark'] ? $unit : self::MARKS[ strtolower( $found['mark'] ) ];
		return Measure::of( $tenThousandths, $marked )->to( $unit );
	}
}
