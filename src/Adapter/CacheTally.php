<?php
/**
 * Short-lived counts, in the object cache or a transient.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Tally;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a count where the site keeps short-lived things (D-112). With a persistent object cache it is an entry of
 * the plugin's group, added once with its expiry and then incremented, which the cache does in one step. Without
 * one it is a transient: a row of the options table, read and written, so two requests at the same instant may
 * count as one. The limit this serves is a brake, not a meter, and is none the worse for it.
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
		$name  = Options::TRANSIENT_PREFIX . self::PREFIX . $key;
		$count = Cast::int( get_transient( $name ) ) + 1;
		set_transient( $name, $count, $seconds );
		return $count;
	}
}
