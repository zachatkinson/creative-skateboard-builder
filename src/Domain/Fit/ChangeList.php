<?php
/**
 * What a change to an earlier pick does to the later ones.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;

/**
 * A proposal, never an act (spec: A change that breaks later picks; docs/UX-RESEARCH.md, P8): the picks that are kept,
 * each pick that no longer fits with the closest part that does, the build as it would be if the shopper says "Use
 * these", and the build as it was for "Undo". Nothing here changes a build.
 */
final readonly class ChangeList {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks        $before   The build as it was, for the undo.
	 * @param Picks        $proposed The build with the change made, each pick that no longer fits swapped for its
	 *                               closest fit, and a slot with no fit left empty.
	 * @param list<string> $kept     The slots whose picks stay as they are, in step order, the changed slot aside.
	 * @param list<Change> $changes  The picks that no longer fit, in step order.
	 */
	public function __construct( public Picks $before, public Picks $proposed, public array $kept, public array $changes ) {}

	/**
	 * Whether the change leaves every later pick fitting, so there is nothing to ask.
	 *
	 * @since 0.2.0
	 */
	public function isClean(): bool {
		return array() === $this->changes;
	}
}
