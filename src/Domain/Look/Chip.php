<?php
/**
 * One filter chip.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * A facet's value, how many looks it leaves, and whether it is chosen (D-247).
 */
final readonly class Chip {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value  The value.
	 * @param int    $count  The looks it leaves, with the other groups' choices applied and its own group's not.
	 * @param bool   $chosen Whether it is chosen.
	 */
	public function __construct( public string $value, public int $count, public bool $chosen ) {}
}
