<?php
/**
 * A slot's options.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;

/**
 * What a slot offers against the picks before it, and what it hid and why. Nothing is dropped: every candidate is in
 * one list or the other, in the order it was given.
 */
final readonly class SlotOptions {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string       $slot    The slot's key.
	 * @param list<Option> $offered The options that fit, with or without a warning.
	 * @param list<Option> $hidden  The options that are blocked.
	 */
	public function __construct( public string $slot, public array $offered, public array $hidden ) {}

	/**
	 * The hidden options grouped by rule and then by reason, each in the order it is first met. An option with nothing
	 * blocking it has no reason to be hidden and is in no group.
	 *
	 * @since 0.2.0
	 *
	 * @return list<HiddenGroup>
	 */
	public function hiddenGroups(): array {
		/** @var array<string, array<string, Finding>> $examples */
		$examples = array();
		/** @var array<string, array<string, list<Option>>> $options */
		$options = array();
		foreach ( $this->hidden as $option ) {
			$reason = $option->reason();
			if ( null !== $reason ) {
				$examples[ $reason->rule->value ][ $reason->code->value ] ??= $reason;
				$options[ $reason->rule->value ][ $reason->code->value ][] = $option;
			}
		}
		$groups = array();
		foreach ( $examples as $rule => $reasons ) {
			foreach ( $reasons as $code => $example ) {
				$groups[] = new HiddenGroup( $example->rule, $example->code, $options[ $rule ][ $code ], $example );
			}
		}
		return $groups;
	}

	/**
	 * The offered option for a part, or null when the part is hidden or was never a candidate.
	 *
	 * @since 0.2.0
	 *
	 * @param int $id The part's id.
	 */
	public function offer( int $id ): ?Option {
		foreach ( $this->offered as $option ) {
			if ( $id === $option->part->id ) {
				return $option;
			}
		}
		return null;
	}
}
