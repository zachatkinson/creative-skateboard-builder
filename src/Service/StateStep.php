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
 * A slot as the builder shows it: its parts held against the build before it, those offered and those hidden with
 * why, the part picked for it, and the part it starts on where nothing is picked.
 *
 * @since 0.4.0
 */
final readonly class StateStep {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Slot        $slot        The slot.
	 * @param SlotOptions $options     Its parts against the build before it.
	 * @param Option|null $picked      The part picked for it, as the slot holds it: offered, or hidden with its reason.
	 * @param Option|null $recommended The part it starts on, when none is picked and the build is ready for one.
	 */
	public function __construct( public Slot $slot, public SlotOptions $options, public ?Option $picked, public ?Option $recommended ) {}

	/**
	 * The part the build counts for the slot: the pick, or the recommendation.
	 *
	 * @since 0.4.0
	 */
	public function counted(): ?Option {
		return $this->picked ?? $this->recommended;
	}
}
