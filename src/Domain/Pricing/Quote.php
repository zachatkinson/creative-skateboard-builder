<?php
/**
 * A build's price.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Pricing;

/**
 * What a build costs: a line per part, and the parent's line, which carries the assembly fee or nothing (spec:
 * Pricing). The revenue sits on the parts; the parent is credited only its fee.
 */
final readonly class Quote {

	/**
	 * The parts' lines together, after the build discount.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $parts;

	/**
	 * What the build discount takes off altogether.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $saved;

	/**
	 * The parts and the fee.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $total;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param list<Line> $lines    The parts' lines, in step order.
	 * @param bool       $assembly Whether the shop assembles the build.
	 * @param int        $fee      The parent's line: the assembly fee, or 0 when assembly is off or free.
	 */
	public function __construct( public array $lines, public bool $assembly, public int $fee ) {
		$parts = 0;
		$saved = 0;
		foreach ( $lines as $line ) {
			$parts += $line->total;
			$saved += $line->saved;
		}
		$this->parts = $parts;
		$this->saved = $saved;
		$this->total = $parts + $fee;
	}
}
