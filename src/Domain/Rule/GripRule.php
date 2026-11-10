<?php
/**
 * R6: grip.
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
 * A sheet of grip fits a deck when it is at least as wide and as long as the deck. A deck that gives no length skips
 * the length check, and is flagged so the Catalogue check can list it; a kit can switch the length check off
 * altogether. A sheet needs its length only where there is a deck's length to hold it to (spec: R6).
 */
final readonly class GripRule implements Rule {

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R6;
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
		$read      = new Reader( RuleId::R6, $picks );
		$deckWidth = $read->measure( SlotKey::DECK, SpecKey::DeckWidth );
		$gripWidth = $read->measure( SlotKey::GRIP, SpecKey::GripWidth );
		$slots     = array( SlotKey::DECK, SlotKey::GRIP );
		$notes     = array();
		$blocks    = array();
		if ( null !== $deckWidth && null !== $gripWidth && $gripWidth->lessThan( $deckWidth ) ) {
			$blocks[] = new Finding(
				RuleId::R6,
				Severity::Block,
				MessageCode::GripTooNarrow,
				Source::Maker,
				$slots,
				array(
					'grip_width' => $gripWidth,
					'deck_width' => $deckWidth,
				)
			);
		}
		if ( $settings->checkLength && $read->picked( SlotKey::DECK ) ) {
			$deckLength = $read->optional( SlotKey::DECK, SpecKey::DeckLength );
			if ( null === $deckLength ) {
				$notes[] = new Finding( RuleId::R6, Severity::Fits, MessageCode::LengthUnchecked, Source::Standard, array( SlotKey::DECK ), array() );
			} else {
				$gripLength = $read->measure( SlotKey::GRIP, SpecKey::GripLength );
				if ( null !== $gripLength && $gripLength->lessThan( $deckLength ) ) {
					$blocks[] = new Finding(
						RuleId::R6,
						Severity::Block,
						MessageCode::GripTooShort,
						Source::Maker,
						$slots,
						array(
							'grip_length' => $gripLength,
							'deck_length' => $deckLength,
						)
					);
				}
			}
		}
		return array( ...$read->missing(), ...$notes, ...$blocks );
	}
}
