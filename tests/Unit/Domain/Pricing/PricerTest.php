<?php
/**
 * Pricer.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Pricing;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Line;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Quote;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\MoneyError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Parts at the prices passed in, the build discount per line and the assembly fee (spec: Pricing).
 */
final class PricerTest extends TestCase {

	/**
	 * A kit with this assembly fee.
	 */
	private static function kit( string $fee = '0' ): Kit {
		return KitDocuments::kit( null, null, null, array( 'fee' => $fee ) + KitDocuments::assembly() );
	}

	/**
	 * A complete: a deck, trucks sold singly, wheels in fours, bearings, bolts and grip, at these prices.
	 *
	 * @param Kit $kit The kit.
	 * @return array<string, Option>
	 */
	private static function build( Kit $kit ): array {
		return FitEngine::standard()->build(
			$kit,
			Parts::picks(
				array(
					SlotKey::DECK     => Parts::of( array( 'deck_width' => '8.5', 'deck_length' => '32', 'hole_pattern' => 'new_school', 'set_size' => 1 ), 100, 7999 ),
					SlotKey::TRUCKS   => Parts::of( array( 'axle_width' => '8.5', 'baseplate' => 'new_school', 'max_wheel' => '56', 'axle_diameter' => '8', 'set_size' => 1 ), 200, 2995 ),
					SlotKey::WHEELS   => Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 300, 4500 ),
					SlotKey::BEARINGS => Parts::of( array( 'bearing_bore' => '8', 'set_size' => 8 ), 500, 2250 ),
					SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '1', 'set_size' => 8 ), 600, 595 ),
					SlotKey::GRIP     => Parts::of( array( 'grip_width' => '9', 'grip_length' => '33', 'set_size' => 1 ), 700, 1005 ),
				)
			)
		);
	}

	public function test_each_part_at_its_own_price_times_the_quantity_the_build_takes(): void {
		$kit   = self::kit();
		$quote = ( new Pricer() )->quote( $kit, self::build( $kit ), true, 0, 2 );
		self::assertSame(
			array(
				array( SlotKey::DECK, 100, 1, 7999, 7999, 7999, 0 ),
				array( SlotKey::TRUCKS, 200, 2, 2995, 2995, 5990, 0 ),
				array( SlotKey::WHEELS, 300, 1, 4500, 4500, 4500, 0 ),
				array( SlotKey::BEARINGS, 500, 1, 2250, 2250, 2250, 0 ),
				array( SlotKey::HARDWARE, 600, 1, 595, 595, 595, 0 ),
				array( SlotKey::GRIP, 700, 1, 1005, 1005, 1005, 0 ),
			),
			array_map( static fn( Line $line ): array => array( $line->slot, $line->part, $line->quantity, $line->price, $line->charged, $line->total, $line->saved ), $quote->lines )
		);
		self::assertSame( 22339, $quote->parts );
		self::assertSame( 0, $quote->saved );
		self::assertSame( 0, $quote->fee );
		self::assertSame( 22339, $quote->total );
		self::assertTrue( $quote->assembly );
	}

	public function test_the_build_discount_sets_each_parts_price_rounded_half_up_and_the_line_is_that_times_the_quantity(): void {
		$kit   = self::kit( '15.00' );
		$quote = ( new Pricer() )->quote( $kit, self::build( $kit ), true, 1000, 2 );
		self::assertSame(
			array(
				// 10 % off 79.99 is 71.991.
				array( SlotKey::DECK, 7999, 7199, 7199, 800 ),
				// 10 % off 29.95 is 26.955: half up to 26.96, twice.
				array( SlotKey::TRUCKS, 2995, 2696, 5392, 598 ),
				array( SlotKey::WHEELS, 4500, 4050, 4050, 450 ),
				array( SlotKey::BEARINGS, 2250, 2025, 2025, 225 ),
				// 10 % off 5.95 is 5.355: half up to 5.36.
				array( SlotKey::HARDWARE, 595, 536, 536, 59 ),
				// 10 % off 10.05 is 9.045: half up to 9.05.
				array( SlotKey::GRIP, 1005, 905, 905, 100 ),
			),
			array_map( static fn( Line $line ): array => array( $line->slot, $line->price, $line->charged, $line->total, $line->saved ), $quote->lines )
		);
		self::assertSame( 20107, $quote->parts );
		self::assertSame( 2232, $quote->saved );
		self::assertSame( 1500, $quote->fee, 'the fee is not discounted' );
		self::assertSame( 21607, $quote->total );
		self::assertSame( 22339, $quote->parts + $quote->saved, 'what is charged and what is saved are the parts\' own prices' );
	}

	/**
	 * @return array<string, array{int, int, int}>
	 */
	public static function discounts(): array {
		return array(
			'none'                               => array( 7999, 0, 7999 ),
			'5 % of 79.99, 75.9905'              => array( 7999, 500, 7599 ),
			'a hundredth of a percent'           => array( 10000, 1, 9999 ),
			'12.5 % of 0.04, 0.035, half up'     => array( 4, 1250, 4 ),
			'50 % of 0.01, half a cent, half up' => array( 1, 5000, 1 ),
			'50 % of 0.03, 0.015'                => array( 3, 5000, 2 ),
			'just under a half'                  => array( 1000, 1234, 877 ),
			'33.33 % of 9.99, 6.660333'          => array( 999, 3333, 666 ),
			'99.99 % of 100.00, one cent'        => array( 10000, 9999, 1 ),
			'99.99 % of 0.49, under half a cent' => array( 49, 9999, 0 ),
			'a free part stays free'             => array( 0, 2500, 0 ),
			'a large price'                      => array( 99999999, 1500, 84999999 ),
		);
	}

	#[DataProvider( 'discounts' )]
	public function test_a_discounted_price( int $price, int $discount, int $charged ): void {
		self::assertSame( $charged, Pricer::discounted( $price, $discount ) );
	}

	public function test_a_currency_without_decimals(): void {
		// Yen: prices are whole yen, and so is the fee.
		$kit   = self::kit( '500' );
		$build = FitEngine::standard()->build(
			$kit,
			Parts::picks(
				array(
					SlotKey::DECK   => Parts::of( array( 'deck_width' => '8.5', 'set_size' => 1 ), 100, 12500 ),
					SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 2 ), 300, 3333 ),
				)
			)
		);
		$quote = ( new Pricer() )->quote( $kit, $build, true, 1500, 0 );
		self::assertSame( array( 10625, 2833 ), array_column( $quote->lines, 'charged' ), '15 % off 3333 is 2833.05' );
		self::assertSame( array( 10625, 5666 ), array_column( $quote->lines, 'total' ) );
		self::assertSame( 500, $quote->fee );
		self::assertSame( 16791, $quote->total );
		self::assertSame( 50000, ( new Pricer() )->quote( $kit, array(), true, 0, 2 )->fee, 'the same fee where the shop shows two decimals' );
	}

	public function test_the_parent_carries_the_fee_or_nothing(): void {
		$free  = self::kit( '0' );
		$paid  = self::kit( '15.50' );
		$price = static fn( Kit $kit, bool $assembly ): Quote => ( new Pricer() )->quote( $kit, self::build( $kit ), $assembly, 0, 2 );
		self::assertSame( 0, $price( $free, true )->fee, 'free assembly: the one line that is 0' );
		self::assertSame( 1550, $price( $paid, true )->fee );
		self::assertSame( 23889, $price( $paid, true )->total );
		self::assertSame( 0, $price( $paid, false )->fee, 'assembly off: no fee' );
		self::assertFalse( $price( $paid, false )->assembly );
		self::assertSame( 22339, $price( $paid, false )->total );
		foreach ( $price( $free, true )->lines as $line ) {
			self::assertGreaterThan( 0, $line->total, $line->slot . ': no part line is 0' );
		}
	}

	public function test_an_empty_build_is_the_fee_alone(): void {
		$quote = ( new Pricer() )->quote( self::kit( '15.00' ), array(), true, 1000, 2 );
		self::assertSame( array(), $quote->lines );
		self::assertSame( 0, $quote->parts );
		self::assertSame( 0, $quote->saved );
		self::assertSame( 1500, $quote->total );
	}

	public function test_a_part_with_no_set_size_has_no_line(): void {
		$kit   = self::kit();
		$build = FitEngine::standard()->build(
			$kit,
			Parts::picks(
				array(
					SlotKey::DECK   => Parts::of( array( 'deck_width' => '8.5', 'set_size' => 1 ), 100, 7999 ),
					SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ), 300, 4500 ),
				)
			)
		);
		$quote = ( new Pricer() )->quote( $kit, $build, false, 0, 2 );
		self::assertSame( array( SlotKey::DECK ), array_column( $quote->lines, 'slot' ) );
		self::assertSame( 7999, $quote->total );
	}

	/**
	 * @return array<string, array{int, bool}>
	 */
	public static function ranges(): array {
		return array(
			'none'             => array( 0, true ),
			'just under whole' => array( 9999, true ),
			'below none'       => array( -1, false ),
			'the whole price'  => array( 10000, false ),
			'more than whole'  => array( 10001, false ),
		);
	}

	#[DataProvider( 'ranges' )]
	public function test_the_discount_is_from_none_to_just_under_the_whole_price( int $discount, bool $accepted ): void {
		$kit = self::kit();
		try {
			( new Pricer() )->quote( $kit, self::build( $kit ), true, $discount, 2 );
			self::assertTrue( $accepted );
		} catch ( InvalidMoney $e ) {
			self::assertFalse( $accepted );
			self::assertSame( MoneyError::DiscountOutOfRange, $e->reason );
			self::assertSame( 'discount_out_of_range', $e->getMessage() );
		}
	}

	public function test_a_line_and_a_quote_add_themselves_up(): void {
		$line = new Line( SlotKey::TRUCKS, 200, 2, 2995, 2696 );
		self::assertSame( 5392, $line->total );
		self::assertSame( 598, $line->saved );
		$quote = new Quote( array( $line, new Line( SlotKey::DECK, 100, 1, 7999, 7199 ) ), true, 1500 );
		self::assertSame( 12591, $quote->parts );
		self::assertSame( 1398, $quote->saved );
		self::assertSame( 14091, $quote->total );
	}
}
