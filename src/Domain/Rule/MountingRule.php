<?php
/**
 * R2: mounting holes.
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
 * A truck fits a deck when its baseplate takes the pattern the deck is drilled for: the same pattern, or either one
 * taking both, as six-hole baseplates and decks drilled twice do. A deck that gives no pattern counts as new school,
 * and is flagged so the Catalogue check can list it (spec: R2).
 */
final readonly class MountingRule implements Rule {

	/**
	 * The pattern a deck is taken to have when it gives none.
	 */
	private const string USUAL = 'new_school';

	/**
	 * A deck drilled for both patterns, or a baseplate that takes both.
	 */
	private const string BOTH = 'both';

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R2;
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
		$read      = new Reader( RuleId::R2, $picks );
		$baseplate = $read->choice( SlotKey::TRUCKS, SpecKey::Baseplate );
		$findings  = $read->missing();
		$pattern   = $read->optionalChoice( SlotKey::DECK, SpecKey::HolePattern );
		if ( null === $pattern && $read->picked( SlotKey::DECK ) ) {
			$pattern    = self::USUAL;
			$findings[] = new Finding( RuleId::R2, Severity::Fits, MessageCode::PatternAssumed, Source::Standard, array( SlotKey::DECK ), array( 'hole_pattern' => $pattern ) );
		}
		if ( null === $pattern || null === $baseplate || self::BOTH === $baseplate || self::BOTH === $pattern || $baseplate === $pattern ) {
			return $findings;
		}
		$findings[] = new Finding(
			RuleId::R2,
			Severity::Block,
			MessageCode::PatternMismatch,
			Source::Maker,
			array( SlotKey::DECK, SlotKey::TRUCKS ),
			array(
				'hole_pattern' => $pattern,
				'baseplate'    => $baseplate,
			)
		);
		return $findings;
	}
}
