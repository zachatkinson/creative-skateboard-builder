<?php
/**
 * Short-lived counts, in the object cache or a transient.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Tally;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a count where the site keeps short-lived things (D-112). With a persistent object cache it is an entry of
 * the plugin's group, added once with its expiry and then incremented, which the cache does in one step. Without
 * one it is a transient, one of 1,024 at most: a row of the options table that holds the counts of the keys that
 * fall to it, read and written, so two requests at the same instant may count as one. The limit this serves is a brake, not a meter, and is none the worse for it.
 *
 * A cache that drops the entry early starts the count again; nothing else is lost with it.
 *
 * @since 0.4.0
 */
final class CacheTally implements Tally {

	/**
	 * What every key starts with.
	 */
	private const string PREFIX = 'rate_';

	/**
	 * How many rows the counts take at most, where they are rows.
	 *
	 * @since 0.4.0
	 */
	public const int BUCKETS = 1024;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Clock $clock The time, for when a count ends.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * The site's secret.
	 *
	 * @since 0.4.0
	 */
	public function salt(): string {
		return wp_salt( 'nonce' );
	}

	/**
	 * Adds one to a key's count and gives the count.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key     The key.
	 * @param int    $seconds How long the count is kept from its first addition.
	 */
	public function add( string $key, int $seconds ): int {
		if ( wp_using_ext_object_cache() ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- the caller's two minutes: a count that outlived its minute by longer would be an address's trace kept for nothing.
			wp_cache_add( self::PREFIX . $key, 0, Options::CACHE_GROUP, $seconds );
			$count = wp_cache_incr( self::PREFIX . $key, 1, Options::CACHE_GROUP );
			return false === $count ? 1 : $count;
		}
		// One of a fixed number of rows, each holding the counts of the keys that fall to it and when each ends.
		// A row for each key would be two rows of the options table for every address and minute, read once and
		// left until WordPress's daily clean: addresses that keep changing would fill the table.
		$name  = Options::TRANSIENT_PREFIX . self::PREFIX . ( hexdec( substr( $key, 0, 4 ) ) % self::BUCKETS );
		$now   = $this->clock->now()->getTimestamp();
		$kept  = get_transient( $name );
		$live  = array();
		$count = 0;
		foreach ( is_array( $kept ) ? $kept : array() as $other => $entry ) {
			if ( ! is_array( $entry ) || Cast::int( $entry[1] ?? 0 ) <= $now ) {
				continue;
			}
			if ( (string) $other === $key ) {
				$count = Cast::int( $entry[0] ?? 0 );
			}
			$live[ (string) $other ] = array( Cast::int( $entry[0] ?? 0 ), Cast::int( $entry[1] ?? 0 ) );
		}
		++$count;
		$live[ $key ] = array( $count, $live[ $key ][1] ?? $now + $seconds );
		// The row lasts as long as its last count and no longer, so no key outlives its own time in it.
		set_transient( $name, $live, max( array_column( $live, 1 ) ) - $now );
		return $count;
	}
}
