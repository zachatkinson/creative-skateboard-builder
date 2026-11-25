<?php
/**
 * The swaps a build is offered, priced.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Swap;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Swapper;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SwapKind;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Port\PartRanks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Smart swaps for a build's state (#135): the domain's swapper asked about the parts the shopper picked, against the
 * parts each slot offers and hides, and each swap priced as the total is. Its difference is the total after the swap
 * less the total now, so the difference shown is the total's change. It decides nothing about fit or price itself.
 *
 * Where they are asked for (D-396): on the review, every picked slot's and the same board for less, first; on a step,
 * the step's own pick's, for its card. Nowhere else, so a step's cards cost nothing more.
 *
 * @since 1.3.0
 */
final class BuildSwaps {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param PartRanks $ranks The shop's ranks of its parts.
	 */
	public function __construct( private PartRanks $ranks ) {}

	/**
	 * The slots whose picks are asked about, in step order: on the review every picked slot's but the deck's, on a
	 * step its own where it is picked and not the deck. None elsewhere, so nothing is looked up for them.
	 *
	 * @since 1.3.0
	 *
	 * @param State $state The build's state, on the step shown.
	 * @return list<string>
	 */
	public static function slots( State $state ): array {
		$slots = array();
		foreach ( $state->steps as $step ) {
			$key = $step->slot->key;
			if ( null !== $step->picked && SlotKey::DECK !== $key && ( State::REVIEW === $state->step || $key === $state->step ) ) {
				$slots[] = $key;
			}
		}
		return $slots;
	}

	/**
	 * The swaps offered, each with what it does to the total in minor units: below nothing where it takes off.
	 *
	 * @since 1.3.0
	 *
	 * @param State $state    The build's state, on the step shown.
	 * @param int   $decimals The shop's price decimals, for the assembly fee.
	 * @return list<array{swap: Swap, change: int}>
	 *
	 * @throws InvalidMoney When the build cannot be priced in the shop's currency settings, as the state's own total.
	 */
	public function of( State $state, int $decimals ): array {
		$slots = self::slots( $state );
		if ( array() === $slots ) {
			return array();
		}
		$build      = Picks::none();
		$candidates = array();
		foreach ( $state->steps as $step ) {
			$key                = $step->slot->key;
			$candidates[ $key ] = array();
			foreach ( array_merge( $step->options->offered, $step->options->hidden ) as $option ) {
				$candidates[ $key ][] = $option->part;
			}
			if ( null !== $step->picked ) {
				$build = $build->with( $key, $step->picked->part );
			}
		}
		$review = State::REVIEW === $state->step;
		$ids    = array();
		foreach ( $slots as $slot ) {
			foreach ( $candidates[ $slot ] as $part ) {
				$ids[] = $part->id;
			}
		}
		$ranks   = $this->ranks->ranks( ...$ids );
		$swapper = new Swapper( FitEngine::standard() );
		$board   = $review ? $swapper->board( $state->kit, $build, $candidates ) : null;
		$swaps   = null === $board ? array() : array( $board );
		foreach ( $slots as $slot ) {
			array_push( $swaps, ...$swapper->slot( $state->kit, $build, $slot, $candidates, $ranks ) );
		}
		$pricer  = new Pricer();
		$offered = array();
		foreach ( $swaps as $swap ) {
			$change = $pricer->quote( $state->kit, $swap->after->build, $state->quote->assembly, $state->discount, $decimals )->total - $state->quote->total;
			// One for less that the total, rounded, does not show as less is no saving to offer.
			if ( SwapKind::Better === $swap->kind || $change < 0 ) {
				$offered[] = array(
					'swap'   => $swap,
					'change' => $change,
				);
			}
		}
		return $offered;
	}
}
