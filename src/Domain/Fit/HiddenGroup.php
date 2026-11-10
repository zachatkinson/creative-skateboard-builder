<?php
/**
 * Hidden parts that share a reason.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;

use function count;

/**
 * The parts a slot hid for one rule and one reason: "9 trucks hidden: too narrow for 8.5 in" (spec: How rules run;
 * docs/UX-RESEARCH.md, P1). Each hidden part is in one group, the one for its first blocking finding, so the groups'
 * counts add up to the number hidden.
 */
final readonly class HiddenGroup {

	/**
	 * How many parts are in the group.
	 *
	 * @since 0.2.0
	 *
	 * @var int
	 */
	public int $count;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleId       $rule    The rule.
	 * @param MessageCode  $code    The reason.
	 * @param list<Option> $options The hidden options, each with its own findings and values.
	 * @param Finding      $example The first part's finding, for the values the group's heading names.
	 */
	public function __construct( public RuleId $rule, public MessageCode $code, public array $options, public Finding $example ) {
		$this->count = count( $options );
	}
}
