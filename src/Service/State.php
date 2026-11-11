<?php
/**
 * A build's state.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangeList;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Validation;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Quote;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the builder shows of a build (spec: How it fits): each step with its options, the hidden counts, the
 * picks and the recommendations; what keeps the build from the cart; the change list when a pick was changed; and
 * what it costs. It holds codes, ids and amounts in minor units, and no words: the edge adds those.
 *
 * @since 0.4.0
 */
final readonly class State {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param int                $id         The kit product's ID.
	 * @param Kit                $kit        The kit.
	 * @param list<StateStep>    $steps      The steps, in step order.
	 * @param string             $step       The key of the step the shopper is on.
	 * @param list<BuildProblem> $refused    The picks that were sent and not taken: for a slot the kit lacks, or a
	 *                                       part that is not one of its slot's. Nothing else is said of them.
	 * @param Validation         $validation What the validator finds of the build the steps count.
	 * @param ChangeList|null    $changes    What a changed pick does to the later ones, when it breaks any.
	 * @param Quote              $quote      What the build costs so far.
	 */
	public function __construct(
		public int $id,
		public Kit $kit,
		public array $steps,
		public string $step,
		public array $refused,
		public Validation $validation,
		public ?ChangeList $changes,
		public Quote $quote
	) {}

	/**
	 * Whether the build can go to the cart as it stands.
	 *
	 * @since 0.4.0
	 */
	public function ready(): bool {
		return $this->validation->isValid();
	}
}
