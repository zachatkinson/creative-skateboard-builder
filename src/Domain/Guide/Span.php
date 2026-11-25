<?php
/**
 * A range of sizes.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * The sizes a recommendation names (#134): from one size to another, both kept, or from one size and larger where the
 * range has no end, as the width guide's last row does.
 *
 * @since 1.3.0
 */
final readonly class Span {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Measure      $from The smallest.
	 * @param Measure|null $to   The largest, no smaller than the smallest, or null for "and larger".
	 */
	public function __construct( public Measure $from, public ?Measure $to ) {}

	/**
	 * Whether a size is in the range, both ends kept.
	 *
	 * @since 1.3.0
	 *
	 * @param Measure $size The size.
	 */
	public function holds( Measure $size ): bool {
		return $size->atLeast( $this->from ) && ( null === $this->to || $size->atMost( $this->to ) );
	}

	/**
	 * How far a size is from the range's middle, or from its smallest where it has no end, in its smallest's unit.
	 * Twice the distance, so a middle between two sizes needs no half.
	 *
	 * @since 1.3.0
	 *
	 * @param Measure $size The size.
	 */
	public function fromMiddle( Measure $size ): int {
		$size = $size->to( $this->from->unit )->tenThousandths;
		$from = $this->from->tenThousandths;
		$to   = null === $this->to ? $from : $this->to->to( $this->from->unit )->tenThousandths;
		return abs( 2 * $size - $from - $to );
	}
}
