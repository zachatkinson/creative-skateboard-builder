<?php
/**
 * Finds the closest part that fits.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function abs;

use const PHP_INT_MAX;

/**
 * For a pick that no longer fits, or is sold out or gone, the offered part closest to it: the nearest on the slot's
 * own spec (for trucks, nearest the deck's width), then the same brand, then the nearest price, among the parts the
 * shop holds enough of. The change list and a saved build's sold-out parts both ask it (spec: How rules run;
 * SPEC-CONFIGURATOR, Saved builds; D-254). It decides nothing about fit itself: the engine says what is offered.
 */
final readonly class ClosestPart {

	/**
	 * Where a slot's spec is measured to another pick's, when the build has one: a truck is chosen for the deck it
	 * goes under, so the swap is the axle nearest the new deck's width, not the one nearest the old truck's.
	 */
	private const array TARGETS = array(
		SlotKey::TRUCKS => array( SlotKey::DECK, SpecKey::DeckWidth ),
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * The offered, available option closest to the part that was picked: nearest spec (to the pick the slot is
	 * measured to, where the build has it, and otherwise to the part's own), then the same brand, then the nearest
	 * price; the first given where all three tie. Null when nothing offered is available.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit        $kit        The kit.
	 * @param string     $slot       The slot's key.
	 * @param Part       $was        The part that was picked.
	 * @param Picks      $build      The build it is held against.
	 * @param list<Part> $candidates The slot's parts, as the server found them.
	 */
	public function find( Kit $kit, string $slot, Part $was, Picks $build, array $candidates ): ?Option {
		$spec    = SlotSize::spec( $slot );
		$wanted  = null === $spec ? null : $was->specs->measure( $spec );
		$target  = self::TARGETS[ $slot ] ?? null;
		$wanted  = ( null === $target ? null : $build->part( $target[0] )?->specs->measure( $target[1] ) ) ?? $wanted;
		$closest = null;
		$best    = array();
		foreach ( $this->engine->options( $kit, $slot, $build, $candidates )->offered as $option ) {
			if ( ! $option->available() ) {
				continue;
			}
			$has  = null === $spec ? null : $option->part->specs->measure( $spec );
			$rank = array(
				null === $wanted || null === $has ? PHP_INT_MAX : $has->gap( $wanted )->tenThousandths,
				$option->part->brand !== $was->brand,
				abs( $option->part->price - $was->price ),
			);
			if ( null === $closest || ( $rank <=> $best ) < 0 ) {
				$closest = $option;
				$best    = $rank;
			}
		}
		return $closest;
	}
}
