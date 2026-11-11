<?php
/**
 * The state route's rate limit.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\Tally;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * How many requests one address may make of the state route in a minute (spec: Security). The address is the
 * connection's own unless the shop names a trusted proxy header, and it is never kept: what is counted is a hash of
 * it, the minute and the site's secret, which lasts two minutes at most and differs from one minute to the next.
 *
 * - A proxy's header may list several addresses. The last is the one the shop's own proxy wrote; the ones before it
 *   are whatever the visitor sent, so they are not read.
 * - An IPv6 address counts as its /64, which is what one visitor is given: a limit per single address would be no
 *   limit to anyone holding a /64.
 * - A header that holds no address falls back to the connection's own, and a request with no address at all shares
 *   one count with every other such request.
 *
 * @since 0.4.0
 */
final class RateLimit {

	/**
	 * The length of a window, in seconds.
	 *
	 * @since 0.4.0
	 */
	public const int WINDOW = 60;

	/**
	 * What an IPv4 address written as IPv6 starts with: ten empty bytes and two full ones.
	 */
	private const string MAPPED = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Tally        $tally    Keeps the counts.
	 * @param Clock        $clock    The time.
	 * @param ShopSettings $settings The shop's settings, for the limit.
	 */
	public function __construct( private Tally $tally, private Clock $clock, private ShopSettings $settings ) {}

	/**
	 * The name of the header the address is read from, or an empty string for the connection's own.
	 *
	 * @since 0.4.0
	 */
	public function header(): string {
		return $this->settings->terms()->proxyHeader;
	}

	/**
	 * Counts a request, and says how long its sender must wait: no seconds when it is within the limit, or the
	 * seconds left of this minute when it is over.
	 *
	 * @since 0.4.0
	 *
	 * @param string $remote    The connection's own address.
	 * @param string $forwarded What the trusted proxy header holds, or an empty string when the shop names none.
	 */
	public function wait( string $remote, string $forwarded = '' ): int {
		$now    = $this->clock->now()->getTimestamp();
		$minute = intdiv( $now, self::WINDOW );
		$who    = self::address( $forwarded ) ?? self::address( $remote ) ?? '';
		$key    = substr( hash_hmac( 'sha256', $minute . '|' . $who, $this->tally->salt() ), 0, 40 );
		$count  = $this->tally->add( $key, 2 * self::WINDOW );
		return $count > $this->settings->terms()->rateLimit ? self::WINDOW - $now % self::WINDOW : 0;
	}

	/**
	 * What an address is counted as: an IPv4 address whole, an IPv6 address by its /64, or null when the text holds
	 * no address. Of a list, the last entry is read.
	 *
	 * @param string $text An address, or a proxy's list of them.
	 */
	private static function address( string $text ): ?string {
		$parts  = explode( ',', $text );
		$last   = trim( $parts[ count( $parts ) - 1 ] );
		$packed = false === filter_var( $last, FILTER_VALIDATE_IP ) ? false : inet_pton( $last );
		if ( false === $packed ) {
			return null;
		}
		// An IPv4 address written as IPv6 (::ffff:192.0.2.1) is that IPv4 address. Otherwise the first eight bytes: an
		// IPv4 address has four, so it is whole.
		return str_starts_with( $packed, self::MAPPED ) ? bin2hex( substr( $packed, 12 ) ) : bin2hex( substr( $packed, 0, 8 ) );
	}
}
