<?php
/**
 * The state route's rate limit.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Service\RateLimit;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryTally;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Security: a rate limit per address, 120 a minute by default; the address is the connection's own unless the
 * shop names a trusted proxy header, and it is kept only as a salted hash for its minute.
 */
final class RateLimitTest extends TestCase {

	// A minute's first second.
	private const int START = 1_800_000_000;

	private InMemoryTally $tally;

	private FakeClock $clock;

	private InMemoryShopSettings $settings;

	private RateLimit $limit;

	protected function setUp(): void {
		$this->tally    = new InMemoryTally();
		$this->clock    = new FakeClock( self::START );
		$this->settings = new InMemoryShopSettings();
		$this->limit    = new RateLimit( $this->tally, $this->clock, $this->settings );
	}

	private function terms( int $rate, string $header = '' ): void {
		$this->settings->terms = BuildTerms::read(
			array(
				BuildTerms::RATE  => $rate,
				BuildTerms::PROXY => $header,
			)
		);
	}

	public function test_the_limit_is_the_shops_setting_and_the_request_after_it_waits_out_the_minute(): void {
		$this->terms( 10 );
		$this->clock->advance( 17 );
		for ( $request = 1; $request <= 10; $request++ ) {
			self::assertSame( 0, $this->limit->wait( '203.0.113.7' ), "Request {$request} is within the limit." );
		}
		self::assertSame( 43, $this->limit->wait( '203.0.113.7' ) );
		$this->clock->advance( 42 );
		self::assertSame( 1, $this->limit->wait( '203.0.113.7' ) );
	}

	public function test_the_default_is_120_a_minute(): void {
		for ( $request = 1; $request <= 120; $request++ ) {
			self::assertSame( 0, $this->limit->wait( '203.0.113.7' ) );
		}
		self::assertSame( 60, $this->limit->wait( '203.0.113.7' ) );
	}

	public function test_a_new_minute_starts_a_new_count(): void {
		$this->terms( 10 );
		for ( $request = 1; $request <= 11; $request++ ) {
			$this->limit->wait( '203.0.113.7' );
		}
		$this->clock->advance( 59 );
		self::assertSame( 1, $this->limit->wait( '203.0.113.7' ) );
		$this->clock->advance( 1 );
		self::assertSame( 0, $this->limit->wait( '203.0.113.7' ) );
		self::assertCount( 2, $this->tally->counts );
	}

	public function test_each_address_has_its_own_count(): void {
		$this->terms( 10 );
		for ( $request = 1; $request <= 11; $request++ ) {
			$this->limit->wait( '203.0.113.7' );
		}
		self::assertSame( 0, $this->limit->wait( '203.0.113.8' ) );
	}

	public function test_what_is_kept_is_a_salted_hash_for_two_minutes_and_never_the_address(): void {
		$this->limit->wait( '203.0.113.7' );
		$key = (string) array_key_first( $this->tally->counts );
		self::assertMatchesRegularExpression( '/\A[0-9a-f]{40}\z/', $key );
		self::assertStringNotContainsString( '203', $key );
		self::assertNotSame( substr( hash( 'sha256', intdiv( self::START, 60 ) . '|' . bin2hex( (string) inet_pton( '203.0.113.7' ) ) ), 0, 40 ), $key, 'A hash anyone could make is no secret.' );
		self::assertSame( substr( hash_hmac( 'sha256', intdiv( self::START, 60 ) . '|' . bin2hex( (string) inet_pton( '203.0.113.7' ) ), 'a secret of the site' ), 0, 40 ), $key );
		self::assertSame( array( $key => 120 ), $this->tally->kept );

		// Another site's secret makes another key.
		$this->tally->salt = 'another';
		$this->limit->wait( '203.0.113.7' );
		self::assertCount( 2, $this->tally->counts );
	}

	/**
	 * @return iterable<string, array{string, string, string, string, bool}>
	 */
	public static function addresses(): iterable {
		yield 'the same address' => array( '203.0.113.7', '', '203.0.113.7', '', true );
		yield 'the same address, spaced' => array( '203.0.113.7', '', ' 203.0.113.7 ', '', true );
		yield 'two IPv6 addresses of one /64' => array( '2001:db8:1:2::1', '', '2001:db8:1:2:ffff:ffff:ffff:ffff', '', true );
		yield 'two IPv6 addresses of two /64s' => array( '2001:db8:1:2::1', '', '2001:db8:1:3::1', '', false );
		yield 'an IPv4 address written as IPv6' => array( '::ffff:203.0.113.7', '', '203.0.113.7', '', true );
		yield 'two IPv4 addresses written as IPv6' => array( '::ffff:203.0.113.7', '', '::ffff:203.0.113.8', '', false );
		yield 'the header, when one is read' => array( '10.0.0.1', '198.51.100.4', '10.0.0.2', '198.51.100.4', true );
		yield 'the header over the connection' => array( '10.0.0.1', '198.51.100.4', '10.0.0.1', '198.51.100.5', false );
		yield 'the last of a list, which the proxy wrote' => array( '10.0.0.1', '192.0.2.99, 198.51.100.4', '10.0.0.1', '192.0.2.1,198.51.100.4', true );
		yield 'never the first of a list, which the visitor wrote' => array( '10.0.0.1', '192.0.2.99, 198.51.100.4', '10.0.0.1', '192.0.2.99, 198.51.100.5', false );
		yield 'a header that holds no address is the connection' => array( '10.0.0.1', 'unknown', '10.0.0.1', '', true );
		yield 'a header that ends on no address is the connection' => array( '10.0.0.1', '198.51.100.4,', '10.0.0.1', '', true );
		yield 'no address at all is one count' => array( '', '', 'not an address', '', true );
		yield 'an address is not no address' => array( '', '', '0.0.0.0', '', false );
	}

	#[DataProvider( 'addresses' )]
	public function test_what_counts_as_one_address( string $remote, string $forwarded, string $other, string $otherForwarded, bool $same ): void {
		$this->limit->wait( $remote, $forwarded );
		$this->limit->wait( $other, $otherForwarded );
		self::assertCount( $same ? 1 : 2, $this->tally->counts );
	}

	public function test_the_header_is_the_one_the_shop_names(): void {
		self::assertSame( '', $this->limit->header() );
		$this->terms( 120, 'CF-Connecting-IP' );
		self::assertSame( 'CF-Connecting-IP', $this->limit->header() );
	}
}
