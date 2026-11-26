<?php
/**
 * Finds the swaps a build is offered.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;

use function array_keys;
use function array_map;
use function array_values;
use function uasort;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * Smart swaps (#135): for a part the shopper picked, the cheapest part in stock that fits the whole build, and the
 * next better one, by the shop's rank where any part of the slot has one and by price where none does; and the same
 * board for less, every slot but the deck at once. The deck is the shopper's look, so it is never swapped (D-394).
 *
 * Every swap is the build with the part in its place, checked by the validator as the cart checks a build: the part
 * fits the whole build with no warning, the shop holds what the build takes of it, and the build has no problem it did
 * not have before. A part that fails is passed over for the next, so a swap is offered only where one exists. It asks
 * the engine and decides nothing about fit itself, and it reads no price: the parts come from the server.
 */
final readonly class Swapper {

	/**
	 * Checks the build each swap makes.
	 *
	 * @var BuildValidator
	 */
	private BuildValidator $validator;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {
		$this->validator = new BuildValidator( $engine );
	}

	/**
	 * The swaps one slot's pick is offered: for less, then better, each where one exists. None for the deck or a slot
	 * with no pick.
	 *
	 * @since 1.3.0
	 *
	 * @param Kit                       $kit        The kit.
	 * @param Picks                     $build      The build as it is.
	 * @param string                    $slot       The slot's key.
	 * @param array<string, list<Part>> $candidates Each slot's parts, by slot key, as the server found them.
	 * @param array<int, int>           $ranks      The shop's rank of each part that has one, by ID: higher is better.
	 * @return list<Swap>
	 */
	public function slot( Kit $kit, Picks $build, string $slot, array $candidates, array $ranks ): array {
		$was = $build->part( $slot );
		if ( null === $was || SlotKey::DECK === $slot || null === $kit->slots->get( $slot ) ) {
			return array();
		}
		$parts  = $candidates[ $slot ] ?? array();
		$cost   = $this->cost( $kit, $slot, $was );
		$ranked = isset( $ranks[ $was->id ] );
		foreach ( $parts as $part ) {
			$ranked = $ranked || isset( $ranks[ $part->id ] );
		}
		$better = array();
		foreach ( $this->costs( $kit, $slot, $parts ) as $id => $price ) {
			$rank = $ranks[ $id ] ?? null;
			if ( ! $ranked && $price > ( $cost ?? PHP_INT_MAX ) ) {
				// By price where no part of the slot is ranked: the next dearer first.
				$better[ $id ] = array( $price, $id );
			}
			if ( null !== $rank && $rank > ( $ranks[ $was->id ] ?? PHP_INT_MIN ) ) {
				// By rank where any is: a part the shop ranks is better than one it does not, the next rank up first.
				$better[ $id ] = array( $rank, $price, $id );
			}
		}
		$ids    = self::ids( $candidates );
		$before = $this->validator->validate( $kit, $build, $ids );
		$swaps  = array();
		foreach ( array( array( SwapKind::Less, $this->cheaper( $kit, $slot, $parts, $cost ) ), array( SwapKind::Better, $better ) ) as [ $kind, $order ] ) {
			$after = $this->first( $kit, $build, $before, $slot, $parts, $order, $ids );
			if ( null !== $after ) {
				$swaps[ $after->build[ $slot ]->part->id ] = new Swap( $kind, array( $slot => $was ), array( $slot => $after->build[ $slot ] ), $after );
			}
		}
		// A better part that is also the cheapest is offered once, as better: its price difference says it costs less.
		return array_values( $swaps );
	}

	/**
	 * The same board for less: in step order, each picked slot but the deck takes the cheapest part that fits the
	 * board as it stands with the slots before it swapped, so the board they make together fits. Null where no slot
	 * has a part for less.
	 *
	 * @since 1.3.0
	 *
	 * @param Kit                       $kit        The kit.
	 * @param Picks                     $build      The build as it is.
	 * @param array<string, list<Part>> $candidates Each slot's parts, by slot key, as the server found them.
	 */
	public function board( Kit $kit, Picks $build, array $candidates ): ?Swap {
		$ids     = self::ids( $candidates );
		$board   = $build;
		$checked = $this->validator->validate( $kit, $build, $ids );
		$was     = array();
		$to      = array();
		foreach ( $kit->slots->keys() as $slot ) {
			$part = $build->part( $slot );
			if ( null === $part || SlotKey::DECK === $slot ) {
				continue;
			}
			$parts = $candidates[ $slot ] ?? array();
			$after = $this->first( $kit, $board, $checked, $slot, $parts, $this->cheaper( $kit, $slot, $parts, $this->cost( $kit, $slot, $part ) ), $ids );
			if ( null !== $after ) {
				$was[ $slot ] = $part;
				$board        = $board->with( $slot, $after->build[ $slot ]->part );
				$checked      = $after;
			}
		}
		foreach ( $was as $slot => $part ) {
			$to[ $slot ] = $checked->build[ $slot ];
		}
		return array() === $was ? null : new Swap( SwapKind::Board, $was, $to, $checked );
	}

	/**
	 * What a part costs a build in a slot: its price times as many as the build takes, or null for a part with no set
	 * size where the slot takes more than one, which the engine blocks and the price leaves out.
	 *
	 * @param Kit    $kit  The kit.
	 * @param string $slot The slot's key.
	 * @param Part   $part The part.
	 */
	private function cost( Kit $kit, string $slot, Part $part ): ?int {
		$quantity = $this->engine->quantity( $kit, $slot, $part );
		return null === $quantity ? null : $quantity * $part->price;
	}

	/**
	 * What each part with a price costs a build in a slot, by ID.
	 *
	 * @param Kit        $kit   The kit.
	 * @param string     $slot  The slot's key.
	 * @param list<Part> $parts The slot's parts.
	 * @return array<int, int>
	 */
	private function costs( Kit $kit, string $slot, array $parts ): array {
		$costs = array();
		foreach ( $parts as $part ) {
			$cost = $this->cost( $kit, $slot, $part );
			if ( null !== $cost ) {
				$costs[ $part->id ] = $cost;
			}
		}
		return $costs;
	}

	/**
	 * The parts that cost a build less than its pick, the cheapest first. None for a pick with no price.
	 *
	 * @param Kit        $kit   The kit.
	 * @param string     $slot  The slot's key.
	 * @param list<Part> $parts The slot's parts.
	 * @param int|null   $cost  What the pick costs the build.
	 * @return array<int, list<int>> What each is ordered by, by ID.
	 */
	private function cheaper( Kit $kit, string $slot, array $parts, ?int $cost ): array {
		$less = array();
		foreach ( $this->costs( $kit, $slot, $parts ) as $id => $price ) {
			if ( $price < ( $cost ?? PHP_INT_MIN ) ) {
				$less[ $id ] = array( $price, $id );
			}
		}
		return $less;
	}

	/**
	 * The build with the first part, in order, that passes: the build checked as a whole, with the part in the slot.
	 * Null where none does.
	 *
	 * @param Kit                      $kit    The kit.
	 * @param Picks                    $build  The build the part goes into.
	 * @param Validation               $before The build as the validator found it before.
	 * @param string                   $slot   The slot's key.
	 * @param list<Part>               $parts  The slot's parts.
	 * @param array<int, list<int>>    $order  What each part to try is ordered by, the least first, by ID. Each ends in
	 *                                         the ID, so no two tie.
	 * @param array<string, list<int>> $ids    The IDs of each slot's parts.
	 */
	private function first( Kit $kit, Picks $build, Validation $before, string $slot, array $parts, array $order, array $ids ): ?Validation {
		uasort( $order, static fn( array $one, array $other ): int => $one <=> $other );
		$found = array();
		foreach ( $parts as $part ) {
			$found[ $part->id ] = $part;
		}
		foreach ( array_keys( $order ) as $id ) {
			$after = $this->validator->validate( $kit, $build->with( $slot, $found[ $id ] ), $ids );
			if ( Severity::Fits === $after->build[ $slot ]->severity && self::within( $after, $before ) ) {
				return $after;
			}
		}
		return null;
	}

	/**
	 * Whether a build has no problem another did not: none in a slot that had none of its kind.
	 *
	 * @param Validation $after  The build after a swap.
	 * @param Validation $before The build before it.
	 */
	private static function within( Validation $after, Validation $before ): bool {
		foreach ( $after->problems as $problem ) {
			$had = false;
			foreach ( $before->problems as $was ) {
				$had = $had || ( $was->code === $problem->code && $was->slot === $problem->slot );
			}
			if ( ! $had ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The IDs of each slot's parts, for the validator.
	 *
	 * @param array<string, list<Part>> $candidates Each slot's parts.
	 * @return array<string, list<int>>
	 */
	private static function ids( array $candidates ): array {
		return array_map( static fn( array $parts ): array => array_map( static fn( Part $part ): int => $part->id, $parts ), $candidates );
	}
}
