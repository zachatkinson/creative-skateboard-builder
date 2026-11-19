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
		foreach ( $wheels->offered as $option ) {
			$asking = self::asking( $option );
			$code   = null === $asking ? null : $this->blocked( $kit, $build->with( SlotKey::WHEELS, $option->part ), $risers, $bolts );
			if ( null === $asking || null === $code ) {
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
		foreach ( $risers->offered as $option ) {
			$thickness = $option->part->specs->measure( SpecKey::RiserThickness );
			if ( null === $thickness || $this->bolted( $kit, $build->with( SlotKey::RISERS, $option->part ), $bolts ) ) {
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
	 * @param Kit        $kit    The kit.
	 * @param Picks      $build  The build with the wheels.
	 * @param list<Part> $risers The kit's risers.
	 * @param list<Part> $bolts  The kit's bolts.
	 */
	private function blocked( Kit $kit, Picks $build, array $risers, array $bolts ): ?MessageCode {
		if ( null === $kit->slots->get( SlotKey::RISERS ) ) {
			return MessageCode::RiserNoSlot;
		}
		$code = MessageCode::RiserNotInStock;
		foreach ( $this->engine->options( $kit, SlotKey::RISERS, $build, $risers )->offered as $option ) {
			// R3 warns of a riser thinner than it asks for, so one offered without that warning clears the wheels.
			if ( ! $option->available() || null !== self::asking( $option ) ) {
				continue;
			}
			if ( $this->bolted( $kit, $build->with( SlotKey::RISERS, $option->part ), $bolts ) ) {
				return null;
			}
			$code = MessageCode::RiserNoBolts;
		}
		return $code;
	}

	/**
	 * Whether the kit has bolts in stock that R4 allows for the build's riser, or needs none.
	 *
	 * @param Kit        $kit   The kit.
	 * @param Picks      $build The build with the riser.
	 * @param list<Part> $bolts The kit's bolts.
	 */
	private function bolted( Kit $kit, Picks $build, array $bolts ): bool {
		if ( null === $kit->slots->get( SlotKey::HARDWARE ) ) {
			return true;
		}
		foreach ( $this->engine->options( $kit, SlotKey::HARDWARE, $build, $bolts )->offered as $option ) {
			if ( $option->available() ) {
				return true;
			}
		}
		return false;
	}
}
