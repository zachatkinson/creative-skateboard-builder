<?php
/**
 * A length.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Measure;

use function abs;
use function intdiv;
use function sprintf;

/**
 * A length as a whole number of ten-thousandths of its unit, the precision the specs index keeps (`DECIMAL(10,4)`),
 * so no rule compares floats. Two measures compare exactly whatever their units; a conversion rounds half up at the
 * fourth place (D-052).
 */
final readonly class Measure {

	/**
	 * Constructor.
	 *
	 * @param int  $tenThousandths The length in ten-thousandths of the unit, zero or more.
	 * @param Unit $unit           The unit.
	 *
	 * @throws InvalidMeasure When the length is negative.
	 */
	private function __construct( public int $tenThousandths, public Unit $unit ) {
		if ( $tenThousandths < 0 ) {
			throw InvalidMeasure::negative();
		}
	}

	/**
	 * A length in inches.
	 *
	 * @since 0.2.0
	 *
	 * @param int $tenThousandths Ten-thousandths of an inch: 82500 is 8.25 in.
	 */
	public static function inches( int $tenThousandths ): self {
		return new self( $tenThousandths, Unit::Inch );
	}

	/**
	 * A length in millimetres.
	 *
	 * @since 0.2.0
	 *
	 * @param int $tenThousandths Ten-thousandths of a millimetre: 540000 is 54 mm.
	 */
	public static function millimetres( int $tenThousandths ): self {
		return new self( $tenThousandths, Unit::Millimetre );
	}

	/**
	 * A length in a unit.
	 *
	 * @since 0.2.0
	 *
	 * @param int  $tenThousandths Ten-thousandths of the unit.
	 * @param Unit $unit           The unit.
	 */
	public static function of( int $tenThousandths, Unit $unit ): self {
		return new self( $tenThousandths, $unit );
	}

	/**
	 * The same length in another unit, rounded half up at the fourth place.
	 *
	 * @since 0.2.0
	 *
	 * @param Unit $unit The unit to read it in.
	 */
	public function to( Unit $unit ): self {
		if ( $unit === $this->unit ) {
			return $this;
		}
		return new self( Rounding::halfUp( $this->base(), $unit->base() ), $unit );
	}

	/**
	 * Less than, equal to or more than another length: -1, 0 or 1.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length, in any unit.
	 */
	public function compare( self $other ): int {
		return $this->base() <=> $other->base();
	}

	/**
	 * Whether two lengths are the same.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function equals( self $other ): bool {
		return 0 === $this->compare( $other );
	}

	/**
	 * Whether this length is the other or longer.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function atLeast( self $other ): bool {
		return $this->compare( $other ) >= 0;
	}

	/**
	 * Whether this length is the other or shorter.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function atMost( self $other ): bool {
		return $this->compare( $other ) <= 0;
	}

	/**
	 * Whether this length is longer than the other.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function moreThan( self $other ): bool {
		return $this->compare( $other ) > 0;
	}

	/**
	 * Whether this length is shorter than the other.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function lessThan( self $other ): bool {
		return $this->compare( $other ) < 0;
	}

	/**
	 * The two lengths together, in this one's unit.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The length to add.
	 */
	public function plus( self $other ): self {
		return new self( $this->tenThousandths + $other->to( $this->unit )->tenThousandths, $this->unit );
	}

	/**
	 * How far apart the two lengths are, in this one's unit.
	 *
	 * @since 0.2.0
	 *
	 * @param self $other The other length.
	 */
	public function gap( self $other ): self {
		return new self( abs( $this->tenThousandths - $other->to( $this->unit )->tenThousandths ), $this->unit );
	}

	/**
	 * The length as a decimal with four places, as the index stores it: `8.2500`.
	 *
	 * @since 0.2.0
	 */
	public function decimal(): string {
		return sprintf( '%d.%04d', intdiv( $this->tenThousandths, 10000 ), $this->tenThousandths % 10000 );
	}

	/**
	 * The length in base steps, which both units divide into evenly.
	 */
	private function base(): int {
		return $this->tenThousandths * $this->unit->base();
	}
}
