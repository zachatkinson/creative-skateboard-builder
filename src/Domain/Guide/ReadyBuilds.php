<?php
/**
 * The guided start's ready builds.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Asking;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BearingRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function array_keys;
use function array_map;
use function count;
use function implode;
use function in_array;
use function intdiv;
use function usort;

/**
 * Three ready builds from the guided start's answers (#134, D-401): budget, mid and premium. Each slot, in the kit's
 * order, takes the cheapest, the middle or the dearest of its parts that are in stock and that the engine offers
 * against the picks before it: the deck in the width asked, the wheels in the sizes asked, and the trucks, risers,
 * bolts and grip in the size the build takes (`SizePicker`). A slot the kit does not require is filled only where the
 * build asks for it, as wheels over a truck's limit ask for a riser, and a part that fits comes before one that only
 * fits with a warning. A build is offered only where the engine finds every part of the whole fits; two alike are
 * one. So a shop makes as many builds as it can, and never one that fails fit. It decides nothing about fit itself.
 *
 * @since 1.3.0
 */
final readonly class ReadyBuilds {

	/**
	 * The size the build decides for each slot whose size it decides, past the deck's, which the answers decide.
	 */
	private const array SIZES = array(
		SlotKey::TRUCKS   => SpecKey::AxleWidth,
		SlotKey::RISERS   => SpecKey::RiserThickness,
		SlotKey::HARDWARE => SpecKey::BoltLength,
		SlotKey::GRIP     => SpecKey::GripWidth,
	);

	/**
	 * What the build asks of a slot.
	 *
	 * @var Asking
	 */
	private Asking $asking;

	/**
	 * Picks the size the build takes.
	 *
	 * @var SizePicker
	 */
	private SizePicker $sizes;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {
		$this->asking = new Asking( $engine );
		$this->sizes  = new SizePicker( $engine );
	}

	/**
	 * The width the builds are made at, from a row of the width guide: of the decks in stock in the row, the width
	 * nearest its middle, or nearest its first width where it has no end; the narrower of two as near. Null where the
	 * shop has no deck in stock in the row.
	 *
	 * @since 1.3.0
	 *
	 * @param Span       $row   The row's widths.
	 * @param list<Part> $decks The kit's decks.
	 */
	public static function width( Span $row, array $decks ): ?Measure {
		$best = null;
		$rank = array();
		foreach ( $decks as $deck ) {
			$width = $deck->specs->measure( SpecKey::DeckWidth );
			if ( null === $width || ! $deck->covers( 1 ) || ! $row->holds( $width ) ) {
				continue;
			}
			$ranked = array( $row->fromMiddle( $width ), $width->to( Unit::Inch )->tenThousandths );
			if ( null === $best || ( $ranked <=> $rank ) < 0 ) {
				$best = $width;
				$rank = $ranked;
			}
		}
		return $best;
	}

	/**
	 * The ready builds, cheapest first: up to three, as many as the shop can make.
	 *
	 * @since 1.3.0
	 *
	 * @param Kit                       $kit        The kit.
	 * @param array<string, list<Part>> $candidates Each slot's parts, as the server found them, by slot key.
	 * @param Measure|null              $width      The deck's width, or null for any.
	 * @param Span|null                 $wheels     The wheels' sizes, or null for any.
	 * @return list<ReadyBuild>
	 */
	public function choose( Kit $kit, array $candidates, ?Measure $width, ?Span $wheels ): array {
		$found = array();
		$tiers = array();
		foreach ( array( Tier::Budget, Tier::Mid, Tier::Premium ) as $tier ) {
			$made = $this->build( $kit, $candidates, $width, $wheels, $tier );
			if ( null === $made ) {
				continue;
			}
			[ $picks, $key ] = $made;
			$found[ $key ]   = $picks;
			$tiers[ $key ][] = $tier;
		}
		$builds = array();
		foreach ( $found as $key => $picks ) {
			// Two alike are one, named for what it is: the cheapest where it is the budget build, the dearest where it
			// is the premium one.
			$named    = $tiers[ $key ];
			$builds[] = new ReadyBuild(
				match ( true ) {
					1 === count( $found ) => Tier::Only,
					in_array( Tier::Budget, $named, true ) => Tier::Budget,
					in_array( Tier::Premium, $named, true ) => Tier::Premium,
					default => Tier::Mid,
				},
				$picks
			);
		}
		return $builds;
	}

	/**
	 * The builds a budget covers, by their place among the builds: each whose total is no more than it, or, where it
	 * covers none, the cheapest alone, the first of two as cheap. Every build where there is no budget.
	 *
	 * @since 1.3.0
	 *
	 * @param list<int> $totals Each build's total, in minor units.
	 * @param int|null  $budget The budget, in minor units, or null for none.
	 * @return list<int>
	 */
	public static function affordable( array $totals, ?int $budget ): array {
		if ( null === $budget ) {
			return array_keys( $totals );
		}
		$kept     = array();
		$cheapest = array();
		foreach ( $totals as $at => $total ) {
			if ( $total <= $budget ) {
				$kept[] = $at;
			}
			if ( array() === $cheapest || $total < $totals[ $cheapest[0] ] ) {
				$cheapest = array( $at );
			}
		}
		return array() === $kept ? $cheapest : $kept;
	}

	/**
	 * One tier's build, slot by slot, with its key; or null where a slot the kit requires has nothing to take, or the
	 * engine does not find every part of the whole fits.
	 *
	 * @param Kit                       $kit        The kit.
	 * @param array<string, list<Part>> $candidates Each slot's parts.
	 * @param Measure|null              $width      The deck's width, or null for any.
	 * @param Span|null                 $wheels     The wheels' sizes, or null for any.
	 * @param Tier                      $tier       The tier.
	 * @return array{Picks, string}|null
	 */
	private function build( Kit $kit, array $candidates, ?Measure $width, ?Span $wheels, Tier $tier ): ?array {
		$picks = Picks::none();
		foreach ( $kit->slots->all() as $slot ) {
			$key = $slot->key;
			// Wheels sold with their bearings fill the Bearings slot (D-246), and a slot the kit does not require is
			// filled only where the build asks for it.
			if ( ( SlotKey::BEARINGS === $key && BearingRule::included( $picks ) ) || ( ! $slot->required && array() === $this->asking->findings( $kit, $key, $picks ) ) ) {
				continue;
			}
			$usable = $this->usable( $kit, $key, $picks, self::wanted( $key, $candidates[ $key ] ?? array(), $width, $wheels ) );
			if ( array() === $usable ) {
				if ( $slot->required ) {
					return null;
				}
				continue;
			}
			$picks = $picks->with( $key, self::tiered( $usable, $tier )->part );
		}
		$key = $this->key( $kit, $picks );
		return null === $key ? null : array( $picks, $key );
	}

	/**
	 * A slot's parts in the deck's width and the wheels' sizes asked; any other slot's parts as they are.
	 *
	 * @param string       $slot   The slot's key.
	 * @param list<Part>   $parts  Its parts.
	 * @param Measure|null $width  The deck's width, or null for any.
	 * @param Span|null    $wheels The wheels' sizes, or null for any.
	 * @return list<Part>
	 */
	private static function wanted( string $slot, array $parts, ?Measure $width, ?Span $wheels ): array {
		$wanted = array();
		foreach ( $parts as $part ) {
			$size = $part->specs->measure( SlotKey::DECK === $slot ? SpecKey::DeckWidth : SpecKey::WheelDiameter );
			$kept = match ( $slot ) {
				SlotKey::DECK   => null === $width || ( null !== $size && $size->equals( $width ) ),
				SlotKey::WHEELS => null === $wheels || ( null !== $size && $wheels->holds( $size ) ),
				default         => true,
			};
			if ( $kept ) {
				$wanted[] = $part;
			}
		}
		return $wanted;
	}

	/**
	 * The options a slot may take: those the engine offers against the picks before it, in stock in what the build
	 * takes, those that fit before those that only fit with a warning, and in the size the build takes where it
	 * decides one.
	 *
	 * @param Kit        $kit   The kit.
	 * @param string     $slot  The slot's key.
	 * @param Picks      $picks The picks before it.
	 * @param list<Part> $parts The slot's parts wanted.
	 * @return list<Option>
	 */
	private function usable( Kit $kit, string $slot, Picks $picks, array $parts ): array {
		$offered = array();
		$fitting = array();
		foreach ( $this->engine->options( $kit, $slot, $picks, $parts )->offered as $option ) {
			if ( ! $option->available() ) {
				continue;
			}
			$offered[] = $option;
			if ( Severity::Fits === $option->severity ) {
				$fitting[] = $option;
			}
		}
		$usable = array() === $fitting ? $offered : $fitting;
		$spec   = self::SIZES[ $slot ] ?? null;
		if ( null === $spec ) {
			return $usable;
		}
		// The size the build takes, of those it may: none where it may take none.
		$sized = $this->sizes->pick( $kit, $slot, array_map( static fn( Option $option ): Part => $option->part, $usable ), $picks );
		if ( null === $sized ) {
			return array();
		}
		$size = self::size( $sized, $spec );
		$same = array();
		foreach ( $usable as $option ) {
			if ( self::size( $option, $spec ) === $size ) {
				$same[] = $option;
			}
		}
		return $same;
	}

	/**
	 * An option's size, in ten-thousandths of a millimetre, or null where it gives none.
	 *
	 * @param Option  $option The option.
	 * @param SpecKey $spec   The size's spec.
	 */
	private static function size( Option $option, SpecKey $spec ): ?int {
		return $option->part->specs->measure( $spec )?->to( Unit::Millimetre )->tenThousandths;
	}

	/**
	 * A tier's option: the cheapest, the dearest, or the middle, the cheaper of two in the middle, by what the build
	 * pays for it, and of two as dear the lower ID first.
	 *
	 * @param non-empty-list<Option> $usable The options.
	 * @param Tier                   $tier   The tier.
	 */
	private static function tiered( array $usable, Tier $tier ): Option {
		usort( $usable, static fn( Option $one, Option $other ): int => array( self::cost( $one ), $one->part->id ) <=> array( self::cost( $other ), $other->part->id ) );
		$last = count( $usable ) - 1;
		return $usable[ match ( $tier ) {
			Tier::Budget  => 0,
			Tier::Premium => $last,
			default       => intdiv( $last, 2 ),
		} ];
	}

	/**
	 * What a build pays for an option: its price for as many as it takes.
	 *
	 * @param Option $option The option.
	 */
	private static function cost( Option $option ): int {
		// An offered part always has a quantity: one that gives no set size where the slot takes more is blocked (R7).
		return $option->part->price * (int) $option->quantity;
	}

	/**
	 * A whole build's parts as one key, their IDs in the kit's order, to tell two alike; or null where the engine does
	 * not find every part fits, a riser picked after the wheels clearing their warning as it does for the cart.
	 *
	 * @param Kit   $kit   The kit.
	 * @param Picks $picks The build.
	 */
	private function key( Kit $kit, Picks $picks ): ?string {
		$ids = array();
		foreach ( $this->engine->build( $kit, $picks ) as $option ) {
			if ( Severity::Fits !== $option->severity ) {
				return null;
			}
			$ids[] = $option->part->id;
		}
		return implode( ',', $ids );
	}
}
