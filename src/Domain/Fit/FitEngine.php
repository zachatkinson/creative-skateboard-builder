<?php
/**
 * The fit engine.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function intdiv;

/**
 * The one place fit is decided (docs/STANDARDS.md, One engine). It checks every option in a slot against the picks
 * made before it, in step order, and says what it hid and why; it checks a whole build the same way for the cart. The
 * change list, the recommender and the validator ask it and decide nothing themselves. It reads the kit, the parts it
 * is handed and nothing else: the server finds the candidates, their prices and their stock.
 */
final readonly class FitEngine {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleSet $rules The fit rules.
	 */
	public function __construct( private RuleSet $rules ) {}

	/**
	 * The engine with R1 to R6.
	 *
	 * @since 0.2.0
	 */
	public static function standard(): self {
		return new self( RuleSet::standard() );
	}

	/**
	 * How many of a part cover a slot (R7): as many sets as it takes, so 4 wheels from sets of 4 is 1 and 2 trucks
	 * sold singly is 2. A slot that takes one unit takes one of any part, whatever it holds, so a deck or a sheet of
	 * grip needs no set size. Null when a slot that takes more is given a part with no set size, or the kit has no such
	 * slot.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit    $kit  The kit.
	 * @param string $slot The slot's key.
	 * @param Part   $part The part.
	 */
	public function quantity( Kit $kit, string $slot, Part $part ): ?int {
		$units = $kit->slots->get( $slot )?->units;
		if ( 1 === $units ) {
			return 1;
		}
		$size = $part->specs->count( SpecKey::SetSize );
		if ( null === $units || null === $size ) {
			return null;
		}
		return intdiv( $units + $size - 1, $size );
	}

	/**
	 * What the rules find about one part in one slot beside other picks: every finding that reads the slot, and a
	 * missing set size where the slot takes more than one unit.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit    $kit    The kit.
	 * @param string $slot   The slot's key.
	 * @param Part   $part   The part.
	 * @param Picks  $others The picks it is held against. Its own slot's pick, if any, is replaced.
	 * @return list<Finding>
	 */
	public function judge( Kit $kit, string $slot, Part $part, Picks $others ): array {
		$findings = array();
		foreach ( $this->rules->check( $others->with( $slot, $part ), $kit->rules ) as $finding ) {
			if ( $finding->reads( $slot ) ) {
				$findings[] = $finding;
			}
		}
		if ( 1 !== $kit->slots->get( $slot )?->units && null === $part->specs->count( SpecKey::SetSize ) ) {
			$findings[] = Finding::missing( RuleId::R7, $slot, SpecKey::SetSize );
		}
		return $findings;
	}

	/**
	 * A slot's options: each candidate against the picks made before the slot, in step order.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit        $kit        The kit.
	 * @param string     $slot       The slot's key.
	 * @param Picks      $picks      The picks so far. Picks in this slot and after it are not read.
	 * @param list<Part> $candidates The slot's parts, as the server found them.
	 */
	public function options( Kit $kit, string $slot, Picks $picks, array $candidates ): SlotOptions {
		$earlier = $picks->only( $kit->slots->before( $slot ) );
		$offered = array();
		$hidden  = array();
		foreach ( $candidates as $part ) {
			$option = new Option( $part, $this->judge( $kit, $slot, $part, $earlier ), $this->quantity( $kit, $slot, $part ) );
			if ( Severity::Block === $option->severity ) {
				$hidden[] = $option;
			} else {
				$offered[] = $option;
			}
		}
		return new SlotOptions( $slot, $offered, $hidden );
	}

	/**
	 * A whole build: each pick against every other pick, in step order. A pair that does not fit is found on both its
	 * slots, and a riser picked after the wheels clears their warning, which the wheels step alone could not know.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit   $kit   The kit.
	 * @param Picks $picks The build's picks. A pick for a slot the kit lacks is not read.
	 * @return array<string, Option> The picks' options by slot key.
	 */
	public function build( Kit $kit, Picks $picks ): array {
		$slots   = $kit->slots->keys();
		$picks   = $picks->only( $slots );
		$options = array();
		foreach ( $slots as $slot ) {
			$part = $picks->part( $slot );
			if ( null !== $part ) {
				$options[ $slot ] = new Option( $part, $this->judge( $kit, $slot, $part, $picks ), $this->quantity( $kit, $slot, $part ) );
			}
		}
		return $options;
	}
}
