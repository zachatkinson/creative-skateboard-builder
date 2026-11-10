<?php
/**
 * The domain's rounding.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

use function intdiv;
use function substr;

/**
 * The one place the domain rounds. Measures and money are whole numbers, so a division or a decimal with more places
 * than are kept rounds half up here, and nowhere works it out for itself.
 */
final class Rounding {

	/**
	 * A whole number divided by another, rounded half up. Both are zero or more, and the divisor is not zero.
	 *
	 * @since 0.2.0
	 *
	 * @param int $of What is divided.
	 * @param int $by What it is divided by.
	 */
	public static function halfUp( int $of, int $by ): int {
		return intdiv( 2 * $of + $by, 2 * $by );
	}

	/**
	 * A decimal's digits as ten-thousandths: four places kept, the fifth rounding them half up.
	 *
	 * @since 0.2.0
	 *
	 * @param string $whole  The digits before the separator.
	 * @param string $places The digits after it, or none.
	 */
	public static function decimal( string $whole, string $places ): int {
		$places .= '00000';
		return 10000 * (int) $whole + (int) substr( $places, 0, 4 ) + ( $places[4] >= '5' ? 1 : 0 );
	}
}
