<?php
/**
 * Reads the picks for a rule.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * A rule reads its specs through this. An empty slot reads as null and is nothing to remark on. A picked part that
 * lacks a spec the rule needs reads as null too, and is recorded: the part is not offered, so nothing is sold on a
 * guess (spec: Compatibility rules, How rules run). Every rule therefore treats a missing spec the same way, and
 * says so even before the other slot is picked.
 */
final class Reader {

	/**
	 * A finding for each needed spec a picked part lacked, in the order they were read.
	 *
	 * @var list<Finding>
	 */
	private array $missing = array();

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleId $rule  The rule that is reading.
	 * @param Picks  $picks The picks.
	 */
	public function __construct( private readonly RuleId $rule, private readonly Picks $picks ) {}

	/**
	 * Whether a slot is filled.
	 *
	 * @since 0.2.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function picked( string $slot ): bool {
		return null !== $this->picks->part( $slot );
	}

	/**
	 * A measure the rule needs.
	 *
	 * @since 0.2.0
	 *
	 * @param string  $slot The slot's key.
	 * @param SpecKey $key  The spec.
	 */
	public function measure( string $slot, SpecKey $key ): ?Measure {
		$value = $this->optional( $slot, $key );
		if ( null === $value ) {
			$this->miss( $slot, $key );
		}
		return $value;
	}

	/**
	 * A measure the rule can do without.
	 *
	 * @since 0.2.0
	 *
	 * @param string  $slot The slot's key.
	 * @param SpecKey $key  The spec.
	 */
	public function optional( string $slot, SpecKey $key ): ?Measure {
		return $this->picks->part( $slot )?->specs->measure( $key );
	}

	/**
	 * A choice the rule needs.
	 *
	 * @since 0.2.0
	 *
	 * @param string  $slot The slot's key.
	 * @param SpecKey $key  The spec.
	 */
	public function choice( string $slot, SpecKey $key ): ?string {
		$value = $this->optionalChoice( $slot, $key );
		if ( null === $value ) {
			$this->miss( $slot, $key );
		}
		return $value;
	}

	/**
	 * A choice the rule can do without.
	 *
	 * @since 0.2.0
	 *
	 * @param string  $slot The slot's key.
	 * @param SpecKey $key  The spec.
	 */
	public function optionalChoice( string $slot, SpecKey $key ): ?string {
		return $this->picks->part( $slot )?->specs->choice( $key );
	}

	/**
	 * Record a needed spec as missing, if its slot is filled.
	 *
	 * @param string  $slot The slot's key.
	 * @param SpecKey $key  The spec.
	 */
	private function miss( string $slot, SpecKey $key ): void {
		if ( $this->picked( $slot ) ) {
			$this->missing[] = Finding::missing( $this->rule, $slot, $key );
		}
	}

	/**
	 * The missing specs recorded so far.
	 *
	 * @since 0.2.0
	 *
	 * @return list<Finding>
	 */
	public function missing(): array {
		return $this->missing;
	}
}
