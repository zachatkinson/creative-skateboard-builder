<?php
/**
 * Works out a change list.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function abs;

use const PHP_INT_MAX;

/**
 * When a pick changes, walks the slots after it in step order and asks the engine about each later pick against the
 * build as it now stands. A pick that still fits is kept. One that does not gets the closest part that does: the
 * nearest on the slot's own spec (for trucks, nearest the new deck's width), then the same brand, then the nearest
 * price, among the parts in stock (spec: How rules run). A swap made for one slot is what the next slot is held against. It decides nothing about fit itself.
 */
final readonly class ChangePlanner {

	/**
	 * The spec "nearest" is measured on, by slot: the one a shopper picks that part by.
	 */
	private const SPECS = array(
		SlotKey::DECK     => SpecKey::DeckWidth,
		SlotKey::TRUCKS   => SpecKey::AxleWidth,
		SlotKey::WHEELS   => SpecKey::WheelDiameter,
		SlotKey::RISERS   => SpecKey::RiserThickness,
		SlotKey::BEARINGS => SpecKey::BearingBore,
		SlotKey::HARDWARE => SpecKey::BoltLength,
		SlotKey::GRIP     => SpecKey::GripWidth,
	);

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
	 * @since 0.2.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * The change list for one pick changed.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit                       $kit        The kit.
	 * @param Picks                     $before     The build as it is.
	 * @param string                    $slot       The slot being changed.
	 * @param Part                      $part       The part it changes to.
	 * @param array<string, list<Part>> $candidates Each later slot's parts, by slot key, as the server found them.
	 */
	public function plan( Kit $kit, Picks $before, string $slot, Part $part, array $candidates ): ChangeList {
		if ( null === $kit->slots->get( $slot ) ) {
			// A slot the kit lacks has nothing before or after it: the build stands as it is.
			return new ChangeList( $before, $before, $before->slots(), array() );
		}
		$earlier  = $kit->slots->before( $slot );
		$proposed = $before->only( $earlier )->with( $slot, $part );
		$kept     = array();
		$changes  = array();
		foreach ( $earlier as $key ) {
			if ( null !== $before->part( $key ) ) {
				$kept[] = $key;
			}
		}
		foreach ( $kit->slots->after( $slot ) as $later ) {
			$was = $before->part( $later );
			if ( null === $was ) {
				continue;
			}
			$findings = Finding::blocking( $this->engine->judge( $kit, $later, $was, $proposed ) );
			if ( array() === $findings ) {
				$kept[]   = $later;
				$proposed = $proposed->with( $later, $was );
				continue;
			}
			$closest   = $this->closest( $later, $was, $proposed, $this->engine->options( $kit, $later, $proposed, $candidates[ $later ] ?? array() ) );
			$changes[] = new Change( $later, $was, $findings, $closest );
			if ( null !== $closest ) {
				$proposed = $proposed->with( $later, $closest->part );
			}
		}
		return new ChangeList( $before, $proposed, $kept, $changes );
	}

	/**
	 * The offered, in-stock option closest to the part that was picked: nearest spec (to the pick the slot is measured
	 * to, where the build has it, and otherwise to the part's own), then the same brand, then the nearest price; the
	 * first given where all three tie.
	 *
	 * @param string      $slot     The slot's key.
	 * @param Part        $was      The part that was picked.
	 * @param Picks       $proposed The build as it now stands, up to this slot.
	 * @param SlotOptions $options  The slot's options against that build.
	 */
	private function closest( string $slot, Part $was, Picks $proposed, SlotOptions $options ): ?Option {
		$spec    = self::SPECS[ $slot ] ?? null;
		$wanted  = null === $spec ? null : $was->specs->measure( $spec );
		$target  = self::TARGETS[ $slot ] ?? null;
		$wanted  = ( null === $target ? null : $proposed->part( $target[0] )?->specs->measure( $target[1] ) ) ?? $wanted;
		$closest = null;
		$best    = array();
		foreach ( $options->offered as $option ) {
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
