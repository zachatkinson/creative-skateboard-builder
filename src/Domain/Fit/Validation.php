<?php
/**
 * The validator's answer.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

/**
 * Whether a build can go to the cart, what is wrong with it if not, and the build as the engine answers for it, which
 * is what gets priced.
 */
final readonly class Validation {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param list<BuildProblem>    $problems What is wrong, unknown slots first and then in step order.
	 * @param array<string, Option> $build    The picks' options by slot key, in step order, for the slots the kit has.
	 */
	public function __construct( public array $problems, public array $build ) {}

	/**
	 * Whether the build can go to the cart.
	 *
	 * @since 0.2.0
	 */
	public function isValid(): bool {
		return array() === $this->problems;
	}
}
