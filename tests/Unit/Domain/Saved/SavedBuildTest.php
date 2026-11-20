<?php
/**
 * SavedBuild.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Saved;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Spec (configurator): A saved build that has changed (D-254), "What's kept": each part's price and its currency.
 */
final class SavedBuildTest extends TestCase {

	public function test_a_build_saved_again_takes_its_new_prices_and_one_renamed_keeps_its_own(): void {
		$then   = new DateTimeImmutable( '2026-11-01', new DateTimeZone( 'UTC' ) );
		$now    = new DateTimeImmutable( '2026-11-19', new DateTimeZone( 'UTC' ) );
		$prices = new SavedPrices( 'CAD', array( 'deck' => 6000 ), 6000 );
		$build  = new SavedBuild( 'a', 7, 9, array( 'deck' => 101 ), true, 'Mine', $then, $then, $prices );
		self::assertSame( $prices, $build->with( 9, array( 'deck' => 101 ), true, 'Renamed', $now )->prices, 'kept' );
		$again = new SavedPrices( 'CAD', array( 'deck' => 6500 ), 6500 );
		self::assertSame( $again, $build->with( 9, array( 'deck' => 102 ), true, 'Mine', $now, $again )->prices, 'saved again' );
		self::assertNull( ( new SavedBuild( 'b', 7, 9, array(), true, '', $then, $then ) )->prices, 'saved before 1.0.0' );
	}

	public function test_prices_are_kept_as_a_plain_array_and_read_back_only_when_whole(): void {
		$prices = new SavedPrices( 'CAD', array( 'deck' => 6000 ), 6900 );
		self::assertSame( array( 'currency' => 'CAD', 'lines' => array( 'deck' => 6000 ), 'total' => 6900 ), $prices->toArray() );
		self::assertEquals( $prices, SavedPrices::fromArray( $prices->toArray() ) );
		self::assertEquals( new SavedPrices( 'CAD', array(), 0 ), SavedPrices::fromArray( array( 'currency' => 'CAD', 'total' => 0 ) ), 'a build of no lines' );
		foreach ( array(
			'none'                 => null,
			'not an array'         => 'CAD',
			'no currency'          => array( 'lines' => array(), 'total' => 1 ),
			'an empty currency'    => array( 'currency' => '', 'lines' => array(), 'total' => 1 ),
			'a total in text'      => array( 'currency' => 'CAD', 'lines' => array(), 'total' => '1' ),
			'a line in text'       => array( 'currency' => 'CAD', 'lines' => array( 'deck' => '60' ), 'total' => 60 ),
			'a line by number'     => array( 'currency' => 'CAD', 'lines' => array( 6000 ), 'total' => 6000 ),
			'lines that are none'  => array( 'currency' => 'CAD', 'lines' => 'deck', 'total' => 6000 ),
		) as $why => $kept ) {
			self::assertNull( SavedPrices::fromArray( $kept ), $why );
		}
	}

	public function test_a_price_is_compared_only_in_the_currency_it_was_kept_in(): void {
		$prices = new SavedPrices( 'CAD', array( 'deck' => 6000 ), 6900 );
		self::assertSame( 6000, $prices->of( 'deck', 'CAD' ) );
		self::assertNull( $prices->of( 'deck', 'USD' ), 'another currency' );
		self::assertNull( $prices->of( 'trucks', 'CAD' ), 'a slot with none' );
		self::assertSame( 6900, $prices->total( 'CAD' ) );
		self::assertNull( $prices->total( 'EUR' ) );
	}
}
