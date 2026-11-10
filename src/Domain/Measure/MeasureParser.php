<?php
/**
 * Reads a measure from what a person typed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

use function array_map;
use function intdiv;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function strtolower;
use function strtr;
use function substr;
use function trim;

/**
 * For the edge: a spec field, an import row, an attribute's term (spec: Data model, Part specs). Reads a whole number,
 * a decimal in the shop's separator, a fraction (`7/8`), a mixed number (`8 1/4`, `8-1/4`, `8¼`) and a unit mark
 * (`"`, `in`, `mm`), and refuses everything else. A value marked in the other unit is converted. Never given a
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
		$text = trim( strtr( $text, array_map( static fn( string $fraction ): string => ' ' . $fraction, self::VULGAR ) ) );
		$text = (string) preg_replace( '/[ \t]+/', ' ', $text );
		$mark = '(?<mark>"|″|in\.?|inch|inches|mm)';
		$sep  = preg_quote( $separator, '/' );
		$form = '/\A(?:(?<whole>\d{1,6})(?:' . $sep . '(?<places>\d+)|[ -](?<top>\d{1,4})\/(?<bottom>\d{1,4}))?|(?<top>\d{1,4})\/(?<bottom>\d{1,4})) ?' . $mark . '?\z/iJ';
		if ( 1 !== preg_match( $form, $text, $found, PREG_UNMATCHED_AS_NULL ) ) {
			throw InvalidMeasure::malformed();
		}
		$tenThousandths = 10000 * (int) $found['whole'];
		if ( null !== $found['places'] ) {
			// Four places and the one that rounds them, whatever was typed.
			$places          = $found['places'] . '0000';
			$tenThousandths += (int) substr( $places, 0, 4 ) + ( $places[4] >= '5' ? 1 : 0 );
		}
		if ( null !== $found['bottom'] ) {
			$bottom = (int) $found['bottom'];
			if ( 0 === $bottom ) {
				throw InvalidMeasure::zeroDenominator();
			}
			$tenThousandths += intdiv( 2 * 10000 * (int) $found['top'] + $bottom, 2 * $bottom );
		}
		$marked = null === $found['mark'] ? $unit : self::MARKS[ strtolower( $found['mark'] ) ];
		return Measure::of( $tenThousandths, $marked )->to( $unit );
	}
}
