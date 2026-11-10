<?php
/**
 * R1: deck width and truck size.
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
 * A deck fits a truck when its width is inside the range the truck's maker publishes, widened by the kit's tolerance
 * (0 unless the shop sets one). A truck with no published range goes by its axle: within the kit's first tolerance of
 * the deck's width it fits, within the second it warns, and beyond that it is blocked. Widths are compared, never size
 * numbers (spec: R1). A range is both ends; one end alone is not a range.
 */
final readonly class DeckWidthRule implements Rule {

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R1;
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
		$read  = new Reader( RuleId::R1, $picks );
		$slots = array( SlotKey::DECK, SlotKey::TRUCKS );
		$width = $read->measure( SlotKey::DECK, SpecKey::DeckWidth );
		$min   = $read->optional( SlotKey::TRUCKS, SpecKey::DeckMin );
		$max   = $read->optional( SlotKey::TRUCKS, SpecKey::DeckMax );
		if ( null !== $min && null !== $max ) {
			if ( null === $width ) {
				return $read->missing();
			}
			$values = array(
				'deck_width' => $width,
				'deck_min'   => $min,
				'deck_max'   => $max,
			);
			if ( $width->plus( $settings->rangeTolerance )->lessThan( $min ) ) {
				return array( new Finding( RuleId::R1, Severity::Block, MessageCode::DeckBelowRange, Source::Maker, $slots, $values ) );
			}
			if ( $width->moreThan( $max->plus( $settings->rangeTolerance ) ) ) {
				return array( new Finding( RuleId::R1, Severity::Block, MessageCode::DeckAboveRange, Source::Maker, $slots, $values ) );
			}
			return array();
		}
		$axle = $read->measure( SlotKey::TRUCKS, SpecKey::AxleWidth );
		if ( null === $width || null === $axle ) {
			return $read->missing();
		}
		$gap = $axle->gap( $width );
		if ( $gap->atMost( $settings->axleFit ) ) {
			return array();
		}
		return array(
			new Finding(
				RuleId::R1,
				$gap->atMost( $settings->axleWarn ) ? Severity::Warn : Severity::Block,
				$axle->lessThan( $width ) ? MessageCode::AxleNarrower : MessageCode::AxleWider,
				Source::Kit,
				$slots,
				array(
					'deck_width' => $width,
					'axle_width' => $axle,
					'gap'        => $gap,
				)
			),
		);
	}
}
