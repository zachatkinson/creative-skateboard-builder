<?php
/**
 * The delivery line the builder words itself from the shop's shipping zones.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Front\ShippingWords;
use CreativeBranding\SkateboardBuilder\Port\ShippingRates;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Spec: The builder, The total. "With no answer, the builder words the line itself from WooCommerce's shipping zones":
 * the shipping to the shopper's province, or how far the build is from free shipping (D-166).
 */
#[CoversClass( ShippingWords::class )]
final class ShippingWordsTest extends TestCase {

	private static function line( ?ShippingRates $rates, int $total ): string {
		return ( new ShippingWords( new FakeMoney() ) )->line( $rates, $total );
	}

	public function test_how_far_the_build_is_from_free_shipping(): void {
		self::assertSame(
			'Spend $18.00 more for free shipping to Ontario.',
			self::line( new ShippingRates( 'Ontario', false, 20000, 1200, 2 ), 18200 )
		);
		self::assertSame(
			'Spend $0.01 more for free shipping to Ontario.',
			self::line( new ShippingRates( 'Ontario', false, 20000, null, 2 ), 19999 )
		);
	}

	public function test_free_shipping_once_the_build_reaches_it_or_where_it_needs_nothing(): void {
		self::assertSame( 'Free shipping to Ontario.', self::line( new ShippingRates( 'Ontario', false, 20000, 1200, 2 ), 20000 ) );
		self::assertSame( 'Free shipping to Ontario.', self::line( new ShippingRates( 'Ontario', true, null, 1200, 2 ), 100 ) );
		self::assertSame( 'Free shipping to Quebec.', self::line( new ShippingRates( 'Quebec', false, null, 0, 2 ), 100 ), 'a flat rate of nothing' );
	}

	public function test_the_flat_rate_where_there_is_no_free_shipping(): void {
		self::assertSame( 'Shipping to Alberta: $12.00.', self::line( new ShippingRates( 'Alberta', false, null, 1200, 2 ), 18200 ) );
		self::assertSame( 'Shipping to Japan: ¥900.', ( new ShippingWords( new class() implements \CreativeBranding\SkateboardBuilder\Port\Money {
			public function text( int $amount, int $decimals ): string {
				return '¥' . $amount;
			}

			public function currency(): string {
				return 'JPY';
			}

			public function settings(): array {
				return array(
					'code'               => 'JPY',
					'symbol'             => '¥',
					'decimals'           => 0,
					'decimal_separator'  => '.',
					'thousand_separator' => ',',
					'format'             => '%1$s%2$s',
				);
			}
		} ) )->line( new ShippingRates( 'Japan', false, null, 900, 0 ), 5000 ), 'the shop\'s own decimals' );
	}

	public function test_nothing_where_no_address_is_held_or_the_zone_gives_nothing_to_say(): void {
		self::assertSame( '', self::line( null, 18200 ) );
		self::assertSame( '', self::line( new ShippingRates( 'Ontario', false, null, null, 2 ), 18200 ) );
		self::assertSame( '', self::line( new ShippingRates( '', true, null, null, 2 ), 18200 ), 'no place to name' );
	}
}
