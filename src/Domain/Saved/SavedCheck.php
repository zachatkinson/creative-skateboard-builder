<?php
/**
 * A saved build checked against the shop as it is now.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Saved;

use CreativeBranding\SkateboardBuilder\Domain\Fit\ClosestPart;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Recommender;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;

/**
 * What reopening a saved build finds (spec: A saved build that has changed, D-254). The picks are walked in step
 * order: one the kit no longer offers, one sold out or held in too few, and one that no longer fits the build before
 * it each change, to the closest part that fits (`ClosestPart`, against the build as it stands so far), which the
 * slots after it are then held against. Prices are compared by the service, which knows the currency.
 *
 * @since 1.0.0
 */
final readonly class SavedCheck {

	/**
	 * Constructor.
	 *
	 * @param list<SavedChange>  $changes The picks that changed, in step order.
	 * @param array<string, int> $picks  The build with each change's closest part in its place, in step order. A slot
	 *                                   whose part changed to nothing is left out.
	 */
	private function __construct( public array $changes, public array $picks ) {}

	/**
	 * The check of a saved build's picks.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit                       $kit        The kit.
	 * @param FitEngine                 $engine     The engine.
	 * @param array<string, int>        $picks      The picks saved.
	 * @param array<string, list<Part>> $candidates Each slot's parts now, as the server found them.
	 * @param array<string, Part>       $was        Each pick the kit no longer offers, as it reads now, by slot; one
	 *                                              that no longer reads is left out, and the slot's recommendation
	 *                                              takes its place.
	 */
	public static function of( Kit $kit, FitEngine $engine, array $picks, array $candidates, array $was ): self {
		$closest     = new ClosestPart( $engine );
		$recommender = new Recommender( $engine );
		$held        = Picks::none();
		$changes     = array();
		foreach ( $kit->slots->all() as $slot ) {
			$id = $picks[ $slot->key ] ?? null;
			if ( null === $id ) {
				continue;
			}
			$offered = $candidates[ $slot->key ] ?? array();
			$part    = null;
			foreach ( $offered as $candidate ) {
				if ( $candidate->id === $id ) {
					$part = $candidate;
				}
			}
			if ( null === $part ) {
				$reason = SavedReason::Gone;
			} elseif ( ! $part->covers( $slot->units ) ) {
				$reason = SavedReason::SoldOut;
			} elseif ( null === $engine->options( $kit, $slot->key, $held, $offered )->offer( $id ) ) {
				$reason = SavedReason::NoLongerFits;
			} else {
				$held = $held->with( $slot->key, $part );
				continue;
			}
			// Not the part itself, nor one the shop holds too few of either.
			$units = $slot->units;
			$pool  = array_values( array_filter( $offered, static fn( Part $candidate ): bool => $candidate->id !== $id && $candidate->covers( $units ) ) );
			$from  = $part ?? ( $was[ $slot->key ] ?? null );
			// A part that no longer reads has nothing to be near: the slot's recommendation stands in.
			$near = null === $from
				? $recommender->among( $kit, $slot->key, $held, $engine->options( $kit, $slot->key, $held, $pool ) )
				: $closest->find( $kit, $slot->key, $from, $held, $pool );
			if ( null !== $near ) {
				$held = $held->with( $slot->key, $near->part );
			}
			$changes[] = new SavedChange( $slot->key, $id, $reason, $near?->part->id );
		}
		$kept = array();
		foreach ( $kit->slots->keys() as $key ) {
			$part = $held->part( $key );
			if ( null !== $part ) {
				$kept[ $key ] = $part->id;
			}
		}
		return new self( $changes, $kept );
	}

	/**
	 * Whether any pick changed.
	 *
	 * @since 1.0.0
	 */
	public function changed(): bool {
		return array() !== $this->changes;
	}
}
