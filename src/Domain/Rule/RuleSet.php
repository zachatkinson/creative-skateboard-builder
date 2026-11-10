<?php
/**
 * The fit rules, run together.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;

/**
 * Every fit rule behind one question: what do the rules a kit has switched on find in these picks. The engine, the
 * change list, the recommender and the cart's validator all ask it here, so there is one place that decides fit
 * (docs/STANDARDS.md, One engine).
 */
final readonly class RuleSet {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param list<Rule> $rules The rules, in the order their findings are wanted.
	 */
	public function __construct( private array $rules ) {}

	/**
	 * R1 to R6.
	 *
	 * @since 0.2.0
	 */
	public static function standard(): self {
		return new self( array( new DeckWidthRule(), new MountingRule(), new WheelClearanceRule(), new BoltLengthRule(), new BearingRule(), new GripRule() ) );
	}

	/**
	 * What the kit's rules find in these picks, rule by rule. A rule the kit has switched off finds nothing.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks        $picks    The picks.
	 * @param RuleSettings $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	public function check( Picks $picks, RuleSettings $settings ): array {
		$findings = array();
		foreach ( $this->rules as $rule ) {
			if ( $settings->enabled( $rule->id() ) ) {
				$findings = array( ...$findings, ...$rule->check( $picks, $settings ) );
			}
		}
		return $findings;
	}
}
