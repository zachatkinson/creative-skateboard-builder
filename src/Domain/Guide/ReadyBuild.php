<?php
/**
 * A guided start's ready build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;

/**
 * One of the guided start's ready builds (#134): what it is, and a part in each slot it fills, every one in stock and
 * the whole passing the fit engine.
 *
 * @since 1.3.0
 */
final readonly class ReadyBuild {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Tier  $tier  What it is.
	 * @param Picks $picks Its parts, by slot.
	 */
	public function __construct( public Tier $tier, public Picks $picks ) {}
}
