<?php
/**
 * The height classes a truck is sold in.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * A truck's height class, which gives its wheel limit when the maker publishes none (spec: R3). The values are what the
 * spec `height_class` holds and the keys of the kit's limits, so they are public API and never renamed in place.
 */
enum HeightClass: string {

	case Low  = 'low';
	case Mid  = 'mid';
	case High = 'high';

	/**
	 * The class a truck is taken to have when it gives none: the one that hides the fewest wheels.
	 */
	public const USUAL = self::High;

	/**
	 * The class a spec's value names, and the usual one when the truck gives none.
	 *
	 * @since 0.2.0
	 *
	 * @param string|null $value The truck's `height_class`.
	 */
	public static function of( ?string $value ): HeightClass {
		return self::tryFrom( $value ?? self::USUAL->value ) ?? self::USUAL;
	}
}
