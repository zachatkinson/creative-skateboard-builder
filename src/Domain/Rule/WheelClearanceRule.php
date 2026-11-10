<?php
/**
 * R3: wheels, truck height and risers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * Each truck has a limit: the largest wheel it takes without risers. The maker's figure comes first; without one the
 * truck's height class gives it, from the kit's settings, and the truck is flagged so the figure can be filled in. A
 * wheel over the limit warns and suggests a riser by how far over it is, from the kit's table; past the table it warns
 * that no riser covers it. The warning clears once a riser at least as thick as suggested is picked (spec: R3). It
 * never blocks.
 */
final readonly class WheelClearanceRule implements Rule {

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R3;
	}

	/**
	 * What the rule finds in these picks.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks        $picks    The picks.
	 * @param RuleSettings $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	public function check( Picks $picks, RuleSettings $settings ): array {
		$read     = new Reader( RuleId::R3, $picks );
		$diameter = $read->measure( SlotKey::WHEELS, SpecKey::WheelDiameter );
		$riser    = $read->measure( SlotKey::RISERS, SpecKey::RiserThickness );
		$findings = $read->missing();
		if ( ! $read->picked( SlotKey::TRUCKS ) ) {
			return $findings;
		}
		$source = Source::Maker;
		$limit  = $read->optional( SlotKey::TRUCKS, SpecKey::MaxWheel );
		if ( null === $limit ) {
			$class      = $read->optionalChoice( SlotKey::TRUCKS, SpecKey::HeightClass ) ?? RuleSettings::USUAL_CLASS;
			$source     = Source::Kit;
			$limit      = $settings->classLimit( $class );
			$findings[] = new Finding(
				RuleId::R3,
				Severity::Fits,
				MessageCode::LimitFromClass,
				Source::Kit,
				array( SlotKey::TRUCKS ),
				array(
					'height_class' => $class,
					'limit'        => $limit,
				)
			);
		}
		if ( null === $diameter || $diameter->atMost( $limit ) ) {
			return $findings;
		}
		$over   = $diameter->gap( $limit );
		$values = array(
			'wheel_diameter' => $diameter,
			'limit'          => $limit,
			'over'           => $over,
		);
		foreach ( $settings->riserSteps as $step ) {
			if ( $over->moreThan( $step->over ) ) {
				continue;
			}
			if ( null === $riser || $riser->lessThan( $step->riser ) ) {
				$findings[] = new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNeedsRiser, $source, array( SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS ), $values + array( 'riser' => $step->riser ) );
			}
			return $findings;
		}
		$findings[] = new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNoRiser, $source, array( SlotKey::TRUCKS, SlotKey::WHEELS ), $values );
		return $findings;
	}
}
