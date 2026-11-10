<?php
/**
 * Prices a build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Pricing;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use InvalidArgumentException;

use function intdiv;

/**
 * Each part at the price the server read for it, times the quantity the build takes; the optional build discount, a
 * percentage off each part's price; and the assembly fee on the parent (spec: Pricing). Money is whole minor units of
 * the shop's price decimals, so no float is ever added up. The domain reads no price itself and trusts none from the
 * browser: the parts come from the server.
 *
 * The discount sets each part's price for one, rounded half up to the minor unit, and the line is that price times
 * the quantity, which is how the cart will charge it: one price per cart line (D-058).
 */
final readonly class Pricer {

	/**
	 * A discount of 100 %, in basis points. The build discount is always less: a part is never free because it is in
	 * a build.
	 */
	public const int WHOLE = 10000;

	/**
	 * What a build costs.
	 *
	 * @since 0.2.0
	 *
	 * @param Kit                   $kit      The kit, for the assembly fee.
	 * @param array<string, Option> $build    The build's picks as the engine answers for them, by slot, in step order.
	 * @param bool                  $assembly Whether the shop assembles the build.
	 * @param int                   $discount The build discount in basis points: 500 is 5 %. 0 is none.
	 * @param int                   $decimals The shop's price decimals, for the fee.
	 *
	 * @throws InvalidArgumentException When the discount is below 0 or is 100 % or more.
	 */
	public function quote( Kit $kit, array $build, bool $assembly, int $discount, int $decimals ): Quote {
		if ( $discount < 0 || $discount >= self::WHOLE ) {
			throw new InvalidArgumentException( 'discount_out_of_range' );
		}
		$lines = array();
		foreach ( $build as $slot => $option ) {
			// A part with no set size has no quantity. The engine blocks it, so it is never charged, and it adds nothing
			// to a running total.
			if ( null !== $option->quantity ) {
				$lines[] = new Line( $slot, $option->part->id, $option->quantity, $option->part->price, self::discounted( $option->part->price, $discount ) );
			}
		}
		return new Quote( $lines, $assembly, $assembly ? $kit->assembly->fee( $decimals ) : 0 );
	}

	/**
	 * A price with a discount off, rounded half up to the minor unit.
	 *
	 * @since 0.2.0
	 *
	 * @param int $price    The price, in minor units.
	 * @param int $discount The discount in basis points.
	 */
	public static function discounted( int $price, int $discount ): int {
		return intdiv( 2 * $price * ( self::WHOLE - $discount ) + self::WHOLE, 2 * self::WHOLE );
	}
}
