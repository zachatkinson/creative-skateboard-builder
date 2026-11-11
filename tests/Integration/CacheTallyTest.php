<?php
/**
 * The rate limit's counts, where the site has no persistent object cache.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\CacheTally;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * The auditor's should-fix 4, milestone 4: a transient for each address and minute, never read again, left two rows
 * of the options table for each until WordPress's daily clean. Addresses that keep changing filled the table.
 */
final class CacheTallyTest extends IntegrationTestCase {

	/**
	 * The rows the counts take in the options table.
	 */
	private static function rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_cbuild_rate_' ) . '%' ) );
	}

	public function test_each_key_is_counted_on_its_own_and_the_rows_are_bounded_however_many_keys(): void {
		if ( wp_using_ext_object_cache() ) {
			self::markTestSkipped( 'With a persistent object cache the counts are its entries, which it drops itself.' );
		}
		$clock = new FakeClock();
		$tally = new CacheTally( $clock );
		$key   = static fn( int $minute, int $address ): string => substr( hash_hmac( 'sha256', $minute . '|' . $address, 'salt' ), 0, 40 );

		self::assertSame( 1, $tally->add( $key( 1, 1 ), 120 ) );
		self::assertSame( 2, $tally->add( $key( 1, 1 ), 120 ) );
		self::assertSame( 1, $tally->add( $key( 1, 2 ), 120 ), 'another address starts at one' );
		self::assertSame( 3, $tally->add( $key( 1, 1 ), 120 ) );

		// 3,000 addresses over three minutes, none of them seen twice.
		foreach ( array( 1, 2, 3 ) as $minute ) {
			for ( $address = 10; $address < 1010; $address++ ) {
				self::assertSame( 1, $tally->add( $key( $minute, $address ), 120 ) );
			}
			$clock->advance( 60 );
		}
		self::assertLessThanOrEqual( CacheTally::BUCKETS, self::rows(), 'a fixed number of rows, whoever asks' );
		self::assertSame( 2, $tally->add( $key( 3, 500 ), 120 ), 'and each is still counted on its own' );

		// A count past its time is dropped when its row is next written, and starts again.
		$clock->advance( 121 );
		self::assertSame( 1, $tally->add( $key( 3, 500 ), 120 ) );
		$kept = get_transient( 'cbuild_rate_' . hexdec( substr( $key( 3, 500 ), 0, 4 ) ) % CacheTally::BUCKETS );
		self::assertIsArray( $kept );
		self::assertSame( array( $key( 3, 500 ) ), array_keys( $kept ), 'nothing of the minutes before is kept' );
	}
}
