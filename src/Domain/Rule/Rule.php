<?php
/**
 * A fit rule.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;

/**
 * One fit test between slots (spec: Compatibility rules). A rule reads the picks by slot key and the kit's settings,
 * and nothing else: no stock, no price, no clock. It answers for whatever is picked: nothing for slots still empty, a
 * missing spec for a picked part that lacks one, and its verdict once every slot it compares is filled.
 */
interface Rule {

	/**
	 * Which rule this is.
	 *
	 * @since 0.2.0
	 */
	public function id(): RuleId;

	/**
	 * What the rule finds in these picks. No finding means they fit as far as this rule goes.
	 *
	 * @since 0.2.0
	 *
	 * @param Picks        $picks    The picks.
	 * @param RuleSettings $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	public function check( Picks $picks, RuleSettings $settings ): array;

	/**
	 * The slots the rule reads, so that a step can say what its parts were checked against (Cowork's review,
	 * should-fix 5).
	 *
	 * @since 0.6.0
	 *
	 * @return list<string>
	 */
	public function slots(): array;
}
