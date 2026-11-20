<?php
/**
 * What the build asks of a slot.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * The findings among the picks before a slot that read it, as wheels over a truck's limit ask for a riser, and the
 * size they ask for. The recommendation and the size the build picks both ask it (D-240).
 */
final readonly class Asking {

	/**
	 * The findings that ask for a part of a certain size, by code: the value that names the size, and the spec of the
	 * asked slot's parts it is measured on.
	 */
	private const array ASKED = array(
		MessageCode::WheelNeedsRiser->value => array( 'riser', SpecKey::RiserThickness ),
	);

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FitEngine $engine The engine.
	 */
	public function __construct( private FitEngine $engine ) {}

	/**
	 * The findings among the picks before a slot that read it: the build is asking for the slot to be filled.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit    $kit   The kit.
	 * @param string $slot  The slot's key.
	 * @param Picks  $picks The picks so far.
	 * @return list<Finding>
	 */
	public function findings( Kit $kit, string $slot, Picks $picks ): array {
		$asking = array();
		foreach ( $this->engine->build( $kit, $picks->only( $kit->slots->before( $slot ) ) ) as $option ) {
			foreach ( $option->findings as $finding ) {
				if ( $finding->reads( $slot ) ) {
					$asking[] = $finding;
				}
			}
		}
		return $asking;
	}

	/**
	 * The size asked for, and the spec it is measured on: from the first finding that names one.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Finding> $asking The findings that ask for the slot.
	 * @return array{Measure, SpecKey}|null
	 */
	public static function size( array $asking ): ?array {
		foreach ( $asking as $finding ) {
			$asked = self::ASKED[ $finding->code->value ] ?? null;
			if ( null === $asked ) {
				continue;
			}
			$size = $finding->values[ $asked[0] ] ?? null;
			if ( $size instanceof Measure ) {
				return array( $size, $asked[1] );
			}
		}
		return null;
	}
}
