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

/**
 * When a pick changes, walks the slots after it in step order and asks the engine about each later pick against the
 * build as it now stands. A pick that still fits is kept. One that does not gets the closest part that does: the
 * nearest on the slot's own spec (for trucks, nearest the new deck's width), then the same brand, then the nearest
 * price, among the parts in stock (spec: How rules run; ClosestPart finds it, D-254). A swap made for one slot is what
 * the next slot is held against. It decides nothing about fit itself.
 */
final readonly class ChangePlanner {

	/**
	 * Finds the closest part that fits, for a pick that no longer does.
	 *
	 * @var ClosestPart
	 */
	private ClosestPart $closest;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {
		$this->closest = new ClosestPart( $engine );
	}

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
			$closest   = $this->closest->find( $kit, $later, $was, $proposed, $candidates[ $later ] ?? array() );
			$changes[] = new Change( $later, $was, $findings, $closest );
			if ( null !== $closest ) {
				$proposed = $proposed->with( $later, $closest->part );
			}
		}
		return new ChangeList( $before, $proposed, $kept, $changes );
	}
}
