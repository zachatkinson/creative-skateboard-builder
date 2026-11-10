<?php
/**
 * One thing wrong with a build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;

/**
 * A problem, the slot it is in, the part where there is one, and for a part that does not fit, the findings that
 * block it, so the cart can say why and the Edit build link can open on the right step.
 */
final readonly class BuildProblem {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Problem       $code     The problem.
	 * @param string        $slot     The slot's key.
	 * @param int|null      $part     The part's id, or null for an empty slot.
	 * @param list<Finding> $findings The blocking findings, for a part that does not fit.
	 */
	public function __construct( public Problem $code, public string $slot, public ?int $part = null, public array $findings = array() ) {}
}
