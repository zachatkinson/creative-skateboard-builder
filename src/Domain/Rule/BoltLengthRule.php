<?php
/**
 * R4: bolts and risers.
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
 * The shortest bolt allowed is 7/8 in plus the riser, and the usual length is 1 in plus the riser; with no riser
 * picked the riser is 0. A shorter bolt is blocked, and one more than 1/4 in longer than the usual length warns that
 * the bolts will stand proud (spec: R4). The figures are the trade's published table, not a kit setting.
 */
final readonly class BoltLengthRule implements Rule {

	/**
	 * The shortest bolt with no riser, in ten-thousandths of an inch: 7/8 in.
	 */
	public const int SHORTEST = 8750;

	/**
	 * The usual bolt with no riser: 1 in.
	 */
	public const int USUAL = 10000;

	/**
	 * How much longer than the usual length a bolt can be before it stands proud: 1/4 in.
	 */
	public const int SLACK = 2500;

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId {
		return RuleId::R4;
	}

	/**
	 * The slots the rule reads.
	 *
	 * @since 0.6.0
	 *
	 * @return list<string>
	 */
	public function slots(): array {
		return array( SlotKey::HARDWARE, SlotKey::RISERS );
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
		$read      = new Reader( RuleId::R4, $picks );
		$bolt      = $read->measure( SlotKey::HARDWARE, SpecKey::BoltLength );
		$thickness = $read->measure( SlotKey::RISERS, SpecKey::RiserThickness );
		$slots     = array( SlotKey::RISERS, SlotKey::HARDWARE );
		if ( ! $read->picked( SlotKey::RISERS ) ) {
			$thickness = Measure::inches( 0 );
			$slots     = array( SlotKey::HARDWARE );
		}
		if ( null === $bolt || null === $thickness ) {
			return $read->missing();
		}
		$shortest = Measure::inches( self::SHORTEST )->plus( $thickness );
		$usual    = Measure::inches( self::USUAL )->plus( $thickness );
		$values   = array(
			'bolt_length'     => $bolt,
			'shortest'        => $shortest,
			'usual'           => $usual,
			'riser_thickness' => $thickness,
		);
		if ( $bolt->lessThan( $shortest ) ) {
			return array( new Finding( RuleId::R4, Severity::Block, MessageCode::BoltsTooShort, Source::Standard, $slots, $values ) );
		}
		if ( $bolt->moreThan( $usual->plus( Measure::inches( self::SLACK ) ) ) ) {
			return array( new Finding( RuleId::R4, Severity::Warn, MessageCode::BoltsTooLong, Source::Standard, $slots, $values ) );
		}
		return array();
	}
}
