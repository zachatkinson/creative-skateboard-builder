<?php
/**
 * One thing a rule found.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function array_diff;
use function array_keys;
use function in_array;

/**
 * A rule's finding about the picks: which rule, how bad, why (a code and the values behind it), where the figure came
 * from and which slots it read. A pair that fits has no finding, so a finding of `Fits` is a note: a default taken
 * for a spec the part does not give, which the Catalogue check lists. Enough is carried for the page to say why, never
 * only that (docs/UX-RESEARCH.md, P1).
 */
final readonly class Finding {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleId                        $rule     The rule.
	 * @param Severity                      $severity How bad.
	 * @param MessageCode                   $code     Why.
	 * @param Source                        $source   Where the figure came from.
	 * @param list<string>                  $slots    The slots it read, in the rule's own order.
	 * @param array<string, Measure|string> $values   The figures behind it, by name: only names its code gives.
	 *
	 * @throws InvalidFinding When a value has a name the code does not give.
	 */
	public function __construct(
		public RuleId $rule,
		public Severity $severity,
		public MessageCode $code,
		public Source $source,
		public array $slots,
		public array $values
	) {
		if ( array() !== array_diff( array_keys( $values ), $code->values() ) ) {
			throw InvalidFinding::valueNotNamed();
		}
	}

	/**
	 * A part lacks a spec the rule needs: a block on that part's slot alone, whatever else is picked.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleId  $rule The rule.
	 * @param string  $slot The part's slot.
	 * @param SpecKey $spec The spec it lacks.
	 */
	public static function missing( RuleId $rule, string $slot, SpecKey $spec ): self {
		return new self( $rule, Severity::Block, MessageCode::SpecMissing, Source::Maker, array( $slot ), array( 'spec' => $spec->value ) );
	}

	/**
	 * Whether the finding read this slot.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function reads( string $slot ): bool {
		return in_array( $slot, $this->slots, true );
	}

	/**
	 * The worst severity among some findings: fits when there are none.
	 *
	 * @since 0.2.0
	 *
	 * @param list<self> $findings The findings.
	 */
	public static function worst( array $findings ): Severity {
		$worst = Severity::Fits;
		foreach ( $findings as $finding ) {
			$worst = $worst->worst( $finding->severity );
		}
		return $worst;
	}

	/**
	 * The findings among some that block, in the order given: why a part is hidden, refused or swapped. The one place
	 * that says which findings those are.
	 *
	 * @since 0.2.0
	 *
	 * @param list<self> $findings The findings.
	 * @return list<self>
	 */
	public static function blocking( array $findings ): array {
		$blocking = array();
		foreach ( $findings as $finding ) {
			if ( Severity::Block === $finding->severity ) {
				$blocking[] = $finding;
			}
		}
		return $blocking;
	}
}
