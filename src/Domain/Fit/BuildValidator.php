<?php
/**
 * Checks a whole build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;

use function array_filter;
use function array_values;
use function in_array;

/**
 * What the cart, the checkout and a build link ask before a build is sold: is every pick for a slot the kit has, is
 * every required slot filled, is each part one of its slot's parts, in stock, and does the whole fit. Fit is the
 * engine's answer about the whole build, nothing else is decided here, and nothing the browser said is taken on
 * trust: the picks are parts the server looked up, and the slots' parts are the server's too (spec: Security).
 */
final readonly class BuildValidator {

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * Check a build.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit                      $kit   The kit.
	 * @param Picks                    $picks The build's picks.
	 * @param array<string, list<int>> $parts The ids of each slot's parts, by slot key, as the server found them.
	 */
	public function validate( Kit $kit, Picks $picks, array $parts ): Validation {
		$slots    = $kit->slots->keys();
		$build    = $this->engine->build( $kit, $picks );
		$problems = array();
		foreach ( $picks->slots() as $slot ) {
			if ( ! in_array( $slot, $slots, true ) ) {
				$problems[] = new BuildProblem( Problem::SlotUnknown, $slot, $picks->part( $slot )?->id );
			}
		}
		foreach ( $kit->slots->all() as $slot ) {
			$option = $build[ $slot->key ] ?? null;
			if ( null === $option ) {
				if ( $slot->required ) {
					$problems[] = new BuildProblem( Problem::SlotEmpty, $slot->key );
				}
				continue;
			}
			if ( ! in_array( $option->part->id, $parts[ $slot->key ] ?? array(), true ) ) {
				$problems[] = new BuildProblem( Problem::PartNotInSlot, $slot->key, $option->part->id );
			}
			if ( ! $option->part->inStock ) {
				$problems[] = new BuildProblem( Problem::PartSoldOut, $slot->key, $option->part->id );
			}
			if ( Severity::Block === $option->severity ) {
				$problems[] = new BuildProblem(
					Problem::PartBlocked,
					$slot->key,
					$option->part->id,
					array_values( array_filter( $option->findings, static fn( Finding $finding ): bool => Severity::Block === $finding->severity ) )
				);
			}
		}
		return new Validation( $problems, $build );
	}
}
