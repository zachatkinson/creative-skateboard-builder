<?php
/**
 * Picks the part a slot starts on.
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
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function abs;
use function count;
use function intdiv;
use function sort;

use const PHP_INT_MAX;

/**
 * After the deck, each slot starts on a recommendation: the kit's default part when it fits and is in stock,
 * otherwise the fitting, in-stock part nearest the middle of the slot's prices (spec: How rules run;
 * docs/UX-RESEARCH.md, P9). Where the build asks for a size, as wheels over a truck's limit ask for a riser of a
 * certain thickness, the part nearest that size that covers it comes before the middle price (P2). The deck is never picked for the shopper. A slot the kit does not require gets one only
 * when the build so far asks for it, as wheels over a truck's limit ask for a riser. A part that only fits with a
 * warning is never recommended. It asks the engine what fits and decides nothing about fit itself.
 */
final readonly class Recommender {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * The findings that ask for a part of a certain size, by code: the value that names the size, and the spec of the
	 * asked slot's parts it is measured on.
	 */
	private const array ASKED = array(
		MessageCode::WheelNeedsRiser->value => array( 'riser', SpecKey::RiserThickness ),
	);

	/**
	 * The option a slot starts on, or null when the shopper is left to pick.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit        $kit        The kit.
	 * @param string     $slot       The slot's key.
	 * @param Picks      $picks      The picks so far.
	 * @param list<Part> $candidates The slot's parts, as the server found them.
	 */
	public function recommend( Kit $kit, string $slot, Picks $picks, array $candidates ): ?Option {
		$about = $kit->slots->get( $slot );
		if ( null === $about || SlotKey::DECK === $slot ) {
			return null;
		}
		$asking = $this->asking( $kit, $slot, $picks );
		if ( ! $about->required && array() === $asking ) {
			return null;
		}
		$fitting = array();
		foreach ( $this->engine->options( $kit, $slot, $picks, $candidates )->offered as $option ) {
			if ( Severity::Fits === $option->severity && $option->part->inStock ) {
				if ( $about->defaultPart === $option->part->id ) {
					return $option;
				}
				$fitting[] = $option;
			}
		}
		$prices = array();
		foreach ( $candidates as $part ) {
			$prices[] = $part->price;
		}
		sort( $prices );
		$wanted  = self::wanted( $asking );
		$nearest = null;
		$best    = array();
		foreach ( $fitting as $option ) {
			// Twice the distance from the median, so the middle of an even number of prices needs no half.
			$middle = abs( 2 * $option->part->price - $prices[ intdiv( count( $prices ) - 1, 2 ) ] - $prices[ intdiv( count( $prices ), 2 ) ] );
			$rank   = array( $middle );
			if ( null !== $wanted ) {
				// A part that falls short of the size asked for comes after one that covers it, then the nearest to it.
				// A part with no figure is the furthest.
				$has  = $option->part->specs->measure( $wanted[1] );
				$rank = array( null === $has || $has->lessThan( $wanted[0] ), null === $has ? PHP_INT_MAX : $has->gap( $wanted[0] )->tenThousandths, $middle );
			}
			if ( null === $nearest || ( $rank <=> $best ) < 0 ) {
				$nearest = $option;
				$best    = $rank;
			}
		}
		return $nearest;
	}

	/**
	 * The size the build asks for, and the spec it is measured on: from the first finding that names one.
	 *
	 * @param list<Finding> $asking The findings that ask for the slot.
	 * @return array{Measure, SpecKey}|null
	 */
	private static function wanted( array $asking ): ?array {
		foreach ( $asking as $finding ) {
			$asked = self::ASKED[ $finding->code->value ] ?? null;
			if ( null === $asked ) {
				continue;
			}
			$size = $finding->values[ $asked[0] ] ?? null;
			if ( $size instanceof Measure ) {
				return array( $size, $asked[1] );
			}
		}
		return null;
	}

	/**
	 * The findings among the picks before a slot that read it: the build is asking for the slot to be filled.
	 *
	 * @param Kit    $kit   The kit.
	 * @param string $slot  The slot's key.
	 * @param Picks  $picks The picks so far.
	 * @return list<Finding>
	 */
	private function asking( Kit $kit, string $slot, Picks $picks ): array {
		$asking = array();
		foreach ( $this->engine->build( $kit, $picks->only( $kit->slots->before( $slot ) ) ) as $option ) {
			foreach ( $option->findings as $finding ) {
				if ( $finding->reads( $slot ) ) {
					$asking[] = $finding;
				}
			}
		}
		return $asking;
	}
}
