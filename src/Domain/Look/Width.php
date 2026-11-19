<?php
/**
 * A width the deck step's question offers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * A deck width the kit's decks come in, and how many of its looks are in stock in it (D-241).
 */
final readonly class Width {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Measure $width   The width.
	 * @param int     $inStock How many looks are in stock in it.
	 */
	public function __construct( public Measure $width, public int $inStock ) {}
}
