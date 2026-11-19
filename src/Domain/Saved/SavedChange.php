<?php
/**
 * One pick of a saved build that changed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Saved;

/**
 * A pick a build was saved with, why it is not in the build as it reopens, and the closest part that fits in its
 * place, if any (D-254).
 *
 * @since 1.0.0
 */
final readonly class SavedChange {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $slot    The slot's key.
	 * @param int         $was     The part it was saved with.
	 * @param SavedReason $reason  Why it changed.
	 * @param int|null    $closest The closest part that fits in its place, or null where none does.
	 */
	public function __construct( public string $slot, public int $was, public SavedReason $reason, public ?int $closest ) {}
}
