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
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function abs;
use function array_filter;
use function array_values;

use const PHP_INT_MAX;

/**
 * When a pick changes, walks the slots after it in step order and asks the engine about each later pick against the
 * build as it now stands. A pick that still fits is kept. One that does not gets the closest part that does: the
 * nearest on the slot's own spec, then the same brand, then the nearest price, among the parts in stock (spec: How
 * rules run). A swap made for one slot is what the next slot is held against. It decides nothing about fit itself.
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
			$findings = array_values(
				array_filter(
					$this->engine->judge( $kit, $later, $was, $proposed ),
					static fn( Finding $finding ): bool => Severity::Block === $finding->severity
				)
			);
			if ( array() === $findings ) {
				$kept[]   = $later;
				$proposed = $proposed->with( $later, $was );
				continue;
			}
			$closest   = $this->closest( $later, $was, $this->engine->options( $kit, $later, $proposed, $candidates[ $later ] ?? array() ) );
			$changes[] = new Change( $later, $was, $findings, $closest );
			if ( null !== $closest ) {
				$proposed = $proposed->with( $later, $closest->part );
			}
		}
		return new ChangeList( $before, $proposed, $kept, $changes );
	}

	/**
	 * The offered, in-stock option closest to the part that was picked: nearest spec, then the same brand, then the
	 * nearest price; the first given where all three tie.
	 *
	 * @param string      $slot    The slot's key.
	 * @param Part        $was     The part that was picked.
	 * @param SlotOptions $options The slot's options against the build as it now stands.
	 */
	private function closest( string $slot, Part $was, SlotOptions $options ): ?Option {
		$spec    = self::SPECS[ $slot ] ?? null;
		$wanted  = null === $spec ? null : $was->specs->measure( $spec );
		$closest = null;
		$best    = array();
		foreach ( $options->offered as $option ) {
			if ( ! $option->part->inStock ) {
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
