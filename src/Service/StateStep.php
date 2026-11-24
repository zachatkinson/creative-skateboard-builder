<?php
/**
 * One step of a build's state.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotOptions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A slot as the builder shows it: its parts held against the picks before it, those offered and those hidden with
 * why, the part picked for it, the part it recommends where nothing is picked, and the part its cards are listed under.
 *
 * @since 0.4.0
 */
final readonly class StateStep {

	/**
	 * The part the step's cards are listed under first: the recommendation it makes, or would make were nothing
	 * picked in it, so a pick never moves its cards (D-340).
	 *
	 * @since 1.1.0
	 *
	 * @var Option|null
	 */
	public ?Option $leads;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 * @since 1.1.0 Takes the part its cards are listed under.
	 *
	 * @param Slot        $slot        The slot.
	 * @param SlotOptions $options     Its parts against the picks before it.
	 * @param Option|null $picked      The part picked for it, as the slot holds it: offered, or hidden with its reason.
	 * @param Option|null $recommended The part it recommends, when none is picked and the build is ready for one: the
	 *                                 card it offers, labelled and never picked (D-213).
	 * @param Option|null $leads       The part it would recommend were nothing picked in it, which its cards are listed
	 *                                 under first; null for the recommendation (D-340).
	 */
	public function __construct( public Slot $slot, public SlotOptions $options, public ?Option $picked, public ?Option $recommended, ?Option $leads = null ) {
		$this->leads = $leads ?? $recommended;
	}

	/**
	 * The part the build counts for the slot: the pick. A recommendation is never counted (D-213).
	 *
	 * @since 0.4.0
	 * @since 0.8.0 The pick alone; before, the recommendation where nothing was picked.
	 */
	public function counted(): ?Option {
		return $this->picked;
	}
}
