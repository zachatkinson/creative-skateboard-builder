<?php
/**
 * Picks the size of a look the build takes.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BoltLengthRule;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function in_array;

use const PHP_INT_MAX;

/**
 * Of a look's parts, the one the build takes, among those the engine offers, a size in stock before one sold out
 * (SPEC-CONFIGURATOR, One card per look; D-240):
 *
 * - deck: the width asked, and no other;
 * - trucks: the axle nearest the deck's width;
 * - risers: the thinnest that covers the thickness the wheels ask for, or the thinnest when nothing asks;
 * - hardware: the length nearest the one R4 makes usual, 1 in plus the riser, the longer where two are as near;
 * - grip: the smallest sheet, narrowest and then shortest;
 * - anything else, where the shopper chooses: the size wanted, or the nearest to it.
 *
 * Where sizes are as near, the smaller comes first, and then the first given. It decides nothing about fit itself.
 */
final readonly class SizePicker {

	/**
	 * The slots whose size the build decides.
	 */
	private const array DECIDES = array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::RISERS, SlotKey::HARDWARE, SlotKey::GRIP );

	/**
	 * What the build asks of a slot.
	 *
	 * @var Asking
	 */
	private Asking $asking;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {
		$this->asking = new Asking( $engine );
	}

	/**
	 * Whether the build decides a slot's size, so that the shopper never chooses it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot The slot's key.
	 */
	public static function decides( string $slot ): bool {
		return in_array( $slot, self::DECIDES, true );
	}

	/**
	 * The option of a look's parts the build takes, or null when the engine offers none of them.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit          $kit    The kit.
	 * @param string       $slot   The slot's key.
	 * @param list<Part>   $parts  The look's parts, narrowed to the choices made.
	 * @param Picks        $build  The build so far.
	 * @param Measure|null $wanted For a deck, the width asked; where the shopper chooses, the size their chip or the
	 *                             recommendation wants.
	 */
	public function pick( Kit $kit, string $slot, array $parts, Picks $build, ?Measure $wanted = null ): ?Option {
		$spec   = SlotSize::spec( $slot );
		$target = $this->target( $kit, $slot, $build, $wanted );
		$picked = null;
		$best   = array();
		foreach ( $this->engine->options( $kit, $slot, $build, $parts )->offered as $option ) {
			$has = null === $spec ? null : $option->part->specs->measure( $spec );
			if ( SlotKey::DECK === $slot && ( null === $has || null === $wanted || ! $has->equals( $wanted ) ) ) {
				continue;
			}
			$rank = array(
				! $option->available(),
				SlotKey::RISERS === $slot && null !== $has && null !== $target && $has->lessThan( $target ),
				null === $has || null === $target ? PHP_INT_MAX : $has->gap( $target )->tenThousandths,
				SlotKey::HARDWARE === $slot && null !== $has && null !== $target && $has->lessThan( $target ),
				self::length( $has ),
				self::length( $option->part->specs->measure( SpecKey::GripLength ) ),
			);
			if ( null === $picked || ( $rank <=> $best ) < 0 ) {
				$picked = $option;
				$best   = $rank;
			}
		}
		return $picked;
	}

	/**
	 * The size a slot's parts are measured to, or null where the smallest is wanted.
	 *
	 * @param Kit          $kit    The kit.
	 * @param string       $slot   The slot's key.
	 * @param Picks        $build  The build so far.
	 * @param Measure|null $wanted The size wanted, if any.
	 */
	private function target( Kit $kit, string $slot, Picks $build, ?Measure $wanted ): ?Measure {
		$riser = $build->part( SlotKey::RISERS )?->specs->measure( SpecKey::RiserThickness );
		$usual = Measure::inches( BoltLengthRule::USUAL );
		return match ( $slot ) {
			SlotKey::TRUCKS   => $build->part( SlotKey::DECK )?->specs->measure( SpecKey::DeckWidth ),
			SlotKey::RISERS   => Asking::size( $this->asking->findings( $kit, $slot, $build ) )[0] ?? null,
			SlotKey::HARDWARE => null === $riser ? $usual : $usual->plus( $riser ),
			SlotKey::GRIP     => null,
			default           => $wanted,
		};
	}

	/**
	 * A length in ten-thousandths of an inch, to rank the smaller first; the longest when it is unknown.
	 *
	 * @param Measure|null $length The length.
	 */
	private static function length( ?Measure $length ): int {
		return null === $length ? PHP_INT_MAX : $length->to( Unit::Inch )->tenThousandths;
	}
}
