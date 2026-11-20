<?php
/**
 * Hides what needs a riser the kit can't supply.
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
use CreativeBranding\SkateboardBuilder\Domain\Rule\BoltLengthRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * R3 only warns that wheels over the trucks' limit need a riser; a step hides them when the kit can't supply one.
 * Wheels stay offered only when a riser in stock at least as thick as R3 asks has bolts in stock that R4 allows for
 * it, and a riser stays offered only when it has such bolts, so a shopper who takes one always has hardware to pick
 * (SPEC-CONFIGURATOR, Only what fits; Risers; D-245). A kit with no Hardware slot needs no bolts. It asks the engine
 * what fits and decides nothing about fit itself.
 */
final readonly class RiserClearance {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * The wheels' options with those no riser the kit can supply clears moved to the hidden, each with its reason
	 * after R3's warning.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit         $kit    The kit.
	 * @param Picks       $build  The build before the wheels.
	 * @param SlotOptions $wheels The wheels' options for that build.
	 * @param list<Part>  $risers The kit's risers.
	 * @param list<Part>  $bolts  The kit's bolts.
	 */
	public function wheels( Kit $kit, Picks $build, SlotOptions $wheels, array $risers, array $bolts ): SlotOptions {
		$offered = array();
		$hidden  = $wheels->hidden;
		// The risers are judged with the wheels by R3 alone, which reads their diameter against the trucks and says what
		// it read in its finding: wheels it says the same of get the same answer. Each is worked out once, not once a
		// wheel and a riser (D-289).
		$known  = array();
		$bolted = array();
		foreach ( $wheels->offered as $option ) {
			$asking = self::asking( $option );
			if ( null === $asking ) {
				$offered[] = $option;
				continue;
			}
			$key = self::said( $asking );
			if ( ! array_key_exists( $key, $known ) ) {
				$known[ $key ] = $this->blocked( $kit, $build->with( SlotKey::WHEELS, $option->part ), $risers, $bolts, $bolted );
			}
			$code = $known[ $key ];
			if ( null === $code ) {
				$offered[] = $option;
				continue;
			}
			$hidden[] = new Option( $option->part, array( ...$option->findings, new Finding( RuleId::R3, Severity::Block, $code, $asking->source, $asking->slots, $asking->values ) ), $option->quantity );
		}
		return new SlotOptions( $wheels->slot, $offered, $hidden, $wheels->against );
	}

	/**
	 * The risers' options with those no bolt in stock reaches through moved to the hidden, each with R4's reason.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit         $kit    The kit.
	 * @param Picks       $build  The build before the risers.
	 * @param SlotOptions $risers The risers' options for that build.
	 * @param list<Part>  $bolts  The kit's bolts.
	 */
	public function risers( Kit $kit, Picks $build, SlotOptions $risers, array $bolts ): SlotOptions {
		$offered = array();
		$hidden  = $risers->hidden;
		$bolted  = array();
		foreach ( $risers->offered as $option ) {
			$thickness = $option->part->specs->measure( SpecKey::RiserThickness );
			if ( null === $thickness || $this->bolted( $kit, $build, $option->part, $bolts, $bolted ) ) {
				$offered[] = $option;
				continue;
			}
			$values   = array(
				'riser_thickness' => $thickness,
				'shortest'        => Measure::inches( BoltLengthRule::SHORTEST )->plus( $thickness ),
			);
			$hidden[] = new Option( $option->part, array( ...$option->findings, new Finding( RuleId::R4, Severity::Block, MessageCode::RiserBoltsShort, Source::Standard, array( SlotKey::RISERS, SlotKey::HARDWARE ), $values ) ), $option->quantity );
		}
		return new SlotOptions( $risers->slot, $offered, $hidden, $risers->against );
	}

	/**
	 * R3's warning that the wheels need a riser, or a thicker one, if it has one.
	 *
	 * @param Option $option The wheels' or the riser's option.
	 */
	private static function asking( Option $option ): ?Finding {
		foreach ( $option->findings as $finding ) {
			if ( MessageCode::WheelNeedsRiser === $finding->code ) {
				return $finding;
			}
		}
		return null;
	}

	/**
	 * Why no riser the kit can supply clears the wheels, or null when one does.
	 *
	 * @param Kit                 $kit    The kit.
	 * @param Picks               $build  The build with the wheels.
	 * @param list<Part>          $risers The kit's risers.
	 * @param list<Part>          $bolts  The kit's bolts.
	 * @param array<string, bool> $bolted The bolts' answers so far, by the riser's thickness.
	 */
	private function blocked( Kit $kit, Picks $build, array $risers, array $bolts, array &$bolted ): ?MessageCode {
		if ( null === $kit->slots->get( SlotKey::RISERS ) ) {
			return MessageCode::RiserNoSlot;
		}
		$code = MessageCode::RiserNotInStock;
		// One riser at a time, as the engine judges each alone, so the first that clears ends the search. One out of
		// stock is never available.
		foreach ( $risers as $riser ) {
			if ( ! $riser->inStock ) {
				continue;
			}
			// Judged alone, a riser is one option or none.
			$option = $this->engine->options( $kit, SlotKey::RISERS, $build, array( $riser ) )->offered[0] ?? null;
			// R3 warns of a riser thinner than it asks for, so one offered without that warning clears the wheels.
			if ( null === $option || ! $option->available() || null !== self::asking( $option ) ) {
				continue;
			}
			if ( $this->bolted( $kit, $build, $option->part, $bolts, $bolted ) ) {
				return null;
			}
			$code = MessageCode::RiserNoBolts;
		}
		return $code;
	}

	/**
	 * Whether the kit has bolts in stock that R4 allows with a riser, or needs none.
	 *
	 * @param Kit                 $kit   The kit.
	 * @param Picks               $build The build before the riser.
	 * @param Part                $riser The riser.
	 * @param list<Part>          $bolts The kit's bolts.
	 * @param array<string, bool> $known The answers so far, by the riser's thickness.
	 */
	private function bolted( Kit $kit, Picks $build, Part $riser, array $bolts, array &$known ): bool {
		if ( null === $kit->slots->get( SlotKey::HARDWARE ) ) {
			return true;
		}
		// The bolts are judged with the risers by R4 alone, which reads their thickness: risers as thick get the same
		// answer (D-289).
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- The domain has no WordPress (STANDARDS.md); a memo key, never stored or sent.
		$key = json_encode( $riser->specs->measure( SpecKey::RiserThickness ), JSON_THROW_ON_ERROR );
		if ( array_key_exists( $key, $known ) ) {
			return $known[ $key ];
		}
		$known[ $key ] = false;
		$with          = $build->with( SlotKey::RISERS, $riser );
		// One bolt at a time, so the first in stock that R4 allows ends the search.
		foreach ( $bolts as $bolt ) {
			if ( ! $bolt->inStock ) {
				continue;
			}
			foreach ( $this->engine->options( $kit, SlotKey::HARDWARE, $with, array( $bolt ) )->offered as $option ) {
				if ( $option->available() ) {
					$known[ $key ] = true;
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * What R3 read of the wheels and the trucks, and the riser it asks for, from its finding.
	 *
	 * @param Finding $finding R3's finding on the wheels.
	 */
	private static function said( Finding $finding ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- The domain has no WordPress (STANDARDS.md); a memo key, never stored or sent.
		return json_encode( $finding->values, JSON_THROW_ON_ERROR );
	}
}
