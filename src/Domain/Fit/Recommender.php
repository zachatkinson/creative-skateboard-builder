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
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;

use function abs;
use function count;
use function intdiv;
use function sort;

/**
 * After the deck, each slot starts on a recommendation: the kit's default part when it fits and is in stock,
 * otherwise the fitting, in-stock part nearest the middle of the slot's prices (spec: How rules run;
 * docs/UX-RESEARCH.md, P9). The deck is never picked for the shopper. A slot the kit does not require gets one only
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
		if ( ! $about->required && ! $this->askedFor( $kit, $slot, $picks ) ) {
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
		$nearest = null;
		$least   = null;
		foreach ( $fitting as $option ) {
			// Twice the distance from the median, so the middle of an even number of prices needs no half.
			$distance = abs( 2 * $option->part->price - $prices[ intdiv( count( $prices ) - 1, 2 ) ] - $prices[ intdiv( count( $prices ), 2 ) ] );
			if ( null === $least || $distance < $least ) {
				$nearest = $option;
				$least   = $distance;
			}
		}
		return $nearest;
	}

	/**
	 * Whether the picks before a slot hold a finding that reads it: the build is asking for the slot to be filled.
	 *
	 * @param Kit    $kit   The kit.
	 * @param string $slot  The slot's key.
	 * @param Picks  $picks The picks so far.
	 */
	private function askedFor( Kit $kit, string $slot, Picks $picks ): bool {
		foreach ( $this->engine->build( $kit, $picks->only( $kit->slots->before( $slot ) ) ) as $option ) {
			foreach ( $option->findings as $finding ) {
				if ( $finding->reads( $slot ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
