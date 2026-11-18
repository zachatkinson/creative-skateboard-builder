<?php
/**
 * Answers what a build's state is.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangeList;
use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangePlanner;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Recommender;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotOptions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a build's state is put together (spec: How it fits; Quality bar, DRY and SOLID): the state route and
 * both abilities ask here, so they cannot disagree. It looks the picks up among each slot's own parts, as the server
 * found them, and asks the domain for everything else: fit, the hidden counts, the recommendations, the change list,
 * the validation and the price. It decides nothing about fit or price itself, and takes nothing the caller says on
 * trust: a pick is an ID, and an ID that is not one of its slot's parts is refused and says nothing of the product
 * it names (spec: Security).
 *
 * @since 0.4.0
 */
final class BuildState {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Kits         $kits     The shop's kits and each slot's parts.
	 * @param ShopSettings $settings The shop's settings, for the build discount.
	 * @param Logger       $logger   Where a kit that does not read, and a build that cannot be priced, are written.
	 */
	public function __construct( private Kits $kits, private ShopSettings $settings, private Logger $logger ) {}

	/**
	 * The state of a build of a kit, or null when the shop has no such kit.
	 *
	 * @since 0.4.0
	 *
	 * @param int               $id       The kit product's ID.
	 * @param array<mixed>      $picks    The picks: slot key to part ID.
	 * @param array<mixed>|null $previous The picks as they were before the last one changed, for the change list.
	 * @param string|null       $step     The step the shopper is on: a slot's key, or the review. Null, or a slot the
	 *                                    kit lacks, is the first step with nothing picked.
	 * @param bool|null         $assembly Whether the shop assembles the build; null is the kit's own default.
	 * @param list<string>      $left     The slots the shopper chose to leave empty, by key. One the kit does not
	 *                                    require starts on no part; one it requires starts as any other does.
	 *
	 * @throws InvalidMoney When the build cannot be priced in the shop's currency settings. It is logged first.
	 */
	public function of( int $id, array $picks, ?array $previous = null, ?string $step = null, ?bool $assembly = null, array $left = array() ): ?State {
		$kit = $this->kits->kit( $id );
		if ( null === $kit ) {
			// The callers ask only for a kit a visitor may see, so this one is published and its settings do not
			// read: no shopper can open it, and nothing else tells the owner.
			$this->logger->hourly( 'kit_unread_' . $id, Logger::WARNING, 'A kit\'s settings do not read, so the builder cannot open it. Save its Build tab; the Catalogue check lists it.', array( 'kit' => $id ) );
			return null;
		}
		$engine     = FitEngine::standard();
		$candidates = array();
		$parts      = array();
		foreach ( $kit->slots->all() as $slot ) {
			// From what is kept of each part: reading them from the shop loads every product (D-116).
			$candidates[ $slot->key ] = $this->kits->offered( $id, $slot );
			$parts[ $slot->key ]      = array();
			foreach ( $candidates[ $slot->key ] as $part ) {
				$parts[ $slot->key ][ $part->id ] = $part;
			}
		}
		$refused = array();
		$picked  = self::found( $picks, $parts, $refused );

		// Two builds go through the steps (D-213). The one counted holds the picks alone: each slot's parts are held
		// against it, and it is what is validated and priced. The one estimated adds each slot's recommendation, made
		// against the picks and the recommendations before it, so the board they make together fits.
		$recommender = new Recommender( $engine );
		$counted     = Picks::none();
		$estimated   = Picks::none();
		$recommends  = false;
		$steps       = array();
		$started     = true;
		$first       = null;
		foreach ( $kit->slots->all() as $slot ) {
			$options     = $engine->options( $kit, $slot->key, $counted, $candidates[ $slot->key ] );
			$part        = $picked->part( $slot->key );
			$starts      = null === $part && $started && ( $slot->required || ! in_array( $slot->key, $left, true ) );
			$recommended = null;
			if ( $starts ) {
				// Held against the recommendations before it only where there are any: otherwise the options are the same.
				$against     = $recommends ? $engine->options( $kit, $slot->key, $estimated, $candidates[ $slot->key ] ) : $options;
				$recommended = $recommender->among( $kit, $slot->key, $estimated, $against );
				// A part that fits the board the estimate counts fits the picks alone: it is the card the step offers.
				$recommended = null === $recommended ? null : ( $options->offer( $recommended->part->id ) ?? $recommended );
			}
			$steps[] = new StateStep( $slot, $options, null === $part ? null : self::option( $options, $part->id ), $recommended );
			if ( null !== $part ) {
				$counted   = $counted->with( $slot->key, $part );
				$estimated = $estimated->with( $slot->key, $part );
			} elseif ( null !== $recommended ) {
				$estimated  = $estimated->with( $slot->key, $recommended->part );
				$recommends = true;
			}
			if ( null === $part ) {
				$first ??= $slot->key;
			}
			// A later slot recommends a part once every slot the kit requires before it has one, picked or recommended.
			$started = $started && ( null !== $part || null !== $recommended || ! $slot->required );
		}

		$validator  = new BuildValidator( $engine );
		$ids        = array_map( 'array_keys', $parts );
		$validation = $validator->validate( $kit, $counted, $ids );
		$discount   = $this->settings->terms()->discount;
		$decimals   = $this->kits->decimals();
		$pricer     = new Pricer();
		$presets    = array();
		try {
			$assembled = $assembly ?? $kit->assembly->defaultOn;
			$quote     = $pricer->quote( $kit, $validation->build, $assembled, $discount, $decimals );
			// The estimate, while a recommendation fills a slot and the board it makes is whole: a board that cannot
			// be finished from what the shop has is given no price.
			$estimate = null;
			if ( $recommends && $started ) {
				$whole    = $validator->validate( $kit, $estimated, $ids );
				$estimate = $pricer->quote( $kit, $whole->build, $assembled, $discount, $decimals );
			}
			// Each preset as the build it starts: its tile's total and what keeps it from the cart.
			foreach ( $kit->presets as $preset ) {
				$left      = array();
				$checked   = $validator->validate( $kit, self::found( $preset->picks, $parts, $left ), $ids );
				$presets[] = new PresetState( $checked, $pricer->quote( $kit, $checked->build, $kit->assembly->defaultOn, $discount, $decimals ), $left );
			}
		} catch ( InvalidMoney $unpriced ) {
			$this->logger->hourly( 'no_price_' . $id, Logger::ERROR, 'A build could not be priced in the shop\'s currency settings.', array( 'kit' => $id ) );
			throw $unpriced;
		}
		$keys = $kit->slots->keys();
		return new State(
			$id,
			$kit,
			$steps,
			null !== $step && ( State::REVIEW === $step || null !== $kit->slots->get( $step ) ) ? $step : ( $first ?? $keys[ count( $keys ) - 1 ] ),
			$refused,
			$validation,
			null === $previous ? null : self::changes( $engine, $kit, $picked, self::found( $previous, $parts ), $candidates ),
			$quote,
			$discount,
			$presets,
			$estimate
		);
	}

	/**
	 * The picks that are parts of their slots, as the server found those parts. One for a slot the kit lacks, or
	 * that is not one of its slot's parts, is refused.
	 *
	 * @param array<mixed>                    $picks   Slot key to part ID, as sent.
	 * @param array<string, array<int, Part>> $parts   Each of the kit's slots' parts, by ID.
	 * @param list<BuildProblem>              $refused The picks not taken, added to.
	 */
	private static function found( array $picks, array $parts, array &$refused = array() ): Picks {
		$found = Picks::none();
		foreach ( $picks as $slot => $id ) {
			$slot = (string) $slot;
			$id   = is_int( $id ) && $id > 0 ? $id : null;
			$part = null === $id ? null : ( $parts[ $slot ][ $id ] ?? null );
			if ( null === $part ) {
				$refused[] = new BuildProblem( isset( $parts[ $slot ] ) ? Problem::PartNotInSlot : Problem::SlotUnknown, $slot, $id );
				continue;
			}
			$found = $found->with( $slot, $part );
		}
		return $found;
	}

	/**
	 * A part as its slot holds it: among those offered, or among those hidden.
	 *
	 * @param SlotOptions $options The slot's options.
	 * @param int         $id      The part's ID, which is one of the slot's.
	 */
	private static function option( SlotOptions $options, int $id ): ?Option {
		$offered = $options->offer( $id );
		if ( null !== $offered ) {
			return $offered;
		}
		foreach ( $options->hidden as $hidden ) {
			if ( $id === $hidden->part->id ) {
				return $hidden;
			}
		}
		return null;
	}

	/**
	 * What the first pick that changed does to the picks after it, or null when it breaks none of them.
	 *
	 * @param FitEngine                 $engine     The engine.
	 * @param Kit                       $kit        The kit.
	 * @param Picks                     $now        The picks as they are.
	 * @param Picks                     $before     The picks as they were.
	 * @param array<string, list<Part>> $candidates Each slot's parts.
	 */
	private static function changes( FitEngine $engine, Kit $kit, Picks $now, Picks $before, array $candidates ): ?ChangeList {
		foreach ( $kit->slots->keys() as $slot ) {
			$part = $now->part( $slot );
			if ( null !== $part && $part->id !== $before->part( $slot )?->id ) {
				$list = ( new ChangePlanner( $engine ) )->plan( $kit, $before, $slot, $part, $candidates );
				return $list->isClean() ? null : $list;
			}
		}
		return null;
	}
}
