<?php
/**
 * R5: bearings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * A bearing fits a truck when its bore equals the axle's diameter. An axle that gives no diameter counts as 8 mm, and
 * is flagged so the Catalogue check can list it. Bearings with their spacers built in block a separate spacers slot,
 * where a kit has one (spec: R5).
 */
final readonly class BearingRule implements Rule {

	/**
	 * The axle a truck is taken to have when it gives none, in ten-thousandths of a millimetre: 8 mm.
	 */
	public const int USUAL_AXLE = 80000;

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R5;
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
		$read     = new Reader( RuleId::R5, $picks );
		$bore     = $read->measure( SlotKey::BEARINGS, SpecKey::BearingBore );
		$findings = $read->missing();
		if ( $read->picked( SlotKey::TRUCKS ) ) {
			$source = Source::Maker;
			$axle   = $read->optional( SlotKey::TRUCKS, SpecKey::AxleDiameter );
			if ( null === $axle ) {
				$source     = Source::Standard;
				$axle       = Measure::millimetres( self::USUAL_AXLE );
				$findings[] = new Finding( RuleId::R5, Severity::Fits, MessageCode::AxleAssumed, Source::Standard, array( SlotKey::TRUCKS ), array( 'axle_diameter' => $axle ) );
			}
			if ( null !== $bore && ! $bore->equals( $axle ) ) {
				$findings[] = new Finding(
					RuleId::R5,
					Severity::Block,
					MessageCode::BoreMismatch,
					$source,
					array( SlotKey::TRUCKS, SlotKey::BEARINGS ),
					array(
						'bearing_bore'  => $bore,
						'axle_diameter' => $axle,
					)
				);
			}
		}
		if ( $read->picked( SlotKey::SPACERS ) && true === $picks->part( SlotKey::BEARINGS )?->specs->flag( SpecKey::SpacersBuiltIn ) ) {
			$findings[] = new Finding( RuleId::R5, Severity::Block, MessageCode::SpacersBuiltIn, Source::Maker, array( SlotKey::BEARINGS, SlotKey::SPACERS ), array() );
		}
		return $findings;
	}
}
