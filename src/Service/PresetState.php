<?php
/**
 * A preset, worked out as a build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Validation;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Quote;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a preset's tile says (spec: Presets, "Each shows its picture, its total and a line on who it's for", and "A
 * preset holding a sold-out part says so on its tile"): its picks checked and priced by the engine that checks and
 * prices every build, with the kit's own assembly setting, from the parts its state has already read.
 *
 * @since 0.6.0
 */
final readonly class PresetState {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param Validation         $validation What the validator finds of the preset's build: a sold-out part among it.
	 * @param Quote              $quote      What it costs.
	 * @param list<BuildProblem> $refused    Its picks that are no longer one of their slot's parts.
	 */
	public function __construct( public Validation $validation, public Quote $quote, public array $refused ) {}

	/**
	 * What keeps it from the cart: its picks no longer their slot's parts first, then what the validator finds. A
	 * preset with any is broken (#104), and its tile is left out for shoppers.
	 *
	 * @since 1.1.0
	 *
	 * @return list<BuildProblem>
	 */
	public function problems(): array {
		return array( ...$this->refused, ...$this->validation->problems );
	}
}
