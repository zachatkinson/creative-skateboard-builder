<?php
/**
 * A look as a step's shelf shows it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

/**
 * A card: the look, the part of it the card shows and the build would take, and the sizes it comes in.
 */
final readonly class ShelfLook {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Look                  $look   The look.
	 * @param Option                $option The part the card shows: the pick where it is in the look, else the size the build
	 *                                      takes or the shopper wants.
	 * @param list<Measure>         $sizes  The sizes the look comes in on the slot's size spec, smallest first.
	 * @param array<string, string> $name   What tells it from its product's other looks in the step, whatever size
	 *                                      is asked, as attribute slug to value slug (D-273).
	 */
	public function __construct( public Look $look, public Option $option, public array $sizes, public array $name = array() ) {}
}
