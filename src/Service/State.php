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
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
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
	 * The step after the last slot's: the review. No slot may take the word for its key (`Slots::KEPT`).
	 *
	 * @since 0.6.0
	 */
	public const string REVIEW = 'review';

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param int                $id         The kit product's ID.
	 * @param Kit                $kit        The kit.
	 * @param list<StateStep>    $steps      The steps, in step order.
	 * @param string             $step       The step the shopper is on: a slot's key, or the review.
	 * @param list<BuildProblem> $refused    The picks that were sent and not taken: for a slot the kit lacks, or a
	 *                                       part that is not one of its slot's. Nothing else is said of them.
	 * @param Validation         $validation What the validator finds of the build the steps count.
	 * @param ChangeList|null    $changes    What a changed pick does to the later ones, when it breaks any.
	 * @param Quote              $quote      What the build costs so far.
	 * @param int                $discount   The build discount the quote was made with, in basis points.
	 * @param list<PresetState>  $presets    Each of the kit's presets as a build, in the kit's order.
	 * @param Quote|null         $estimate   What the board costs with each slot's recommendation in it, while one fills
	 *                                       a slot and every slot the kit requires has a part (D-213).
	 */
	public function __construct(
		public int $id,
		public Kit $kit,
		public array $steps,
		public string $step,
		public array $refused,
		public Validation $validation,
		public ?ChangeList $changes,
		public Quote $quote,
		public int $discount,
		public array $presets = array(),
		public ?Quote $estimate = null
	) {}

	/**
	 * The same build, with the shopper on another step.
	 *
	 * @since 0.6.0
	 *
	 * @param string $step A slot's key, or the review.
	 */
	public function at( string $step ): self {
		return new self( $this->id, $this->kit, $this->steps, $step, $this->refused, $this->validation, $this->changes, $this->quote, $this->discount, $this->presets, $this->estimate );
	}

	/**
	 * Where each preset a shopper is offered stands among the kit's, from 0 (#104): none where the kit's Show ready
	 * builds is off, and none that a problem keeps from the cart, which the Catalogue check lists for the owner
	 * instead. A preset the state did not build has nothing keeping it from the cart. A link to any preset still
	 * opens it.
	 *
	 * @since 1.1.0
	 *
	 * @return list<int>
	 */
	public function shownPresets(): array {
		if ( ! $this->kit->layout->showPresets ) {
			return array();
		}
		$shown = array();
		foreach ( array_keys( $this->kit->presets ) as $at ) {
			$built = $this->presets[ $at ] ?? null;
			if ( null === $built || array() === $built->problems() ) {
				$shown[] = $at;
			}
		}
		return $shown;
	}

	/**
	 * Whether the build can go to the cart as it stands.
	 *
	 * @since 0.4.0
	 */
	public function ready(): bool {
		return $this->validation->isValid();
	}

	/**
	 * The part each step counts, which is its pick, by slot in step order.
	 *
	 * @since 0.7.0
	 * @since 0.8.0 The picks alone (D-213).
	 *
	 * @return array<string, Part>
	 */
	public function counted(): array {
		$counted = array();
		foreach ( $this->steps as $step ) {
			$counts = $step->counted();
			if ( null !== $counts ) {
				$counted[ $step->slot->key ] = $counts->part;
			}
		}
		return $counted;
	}
}
