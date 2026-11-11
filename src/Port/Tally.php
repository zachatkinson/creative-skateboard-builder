<?php
/**
 * Counts that last a short while.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A count kept under a key for a short while, and the site's own secret to make such a key with. The rate limit
 * counts a visitor's requests here (spec: Security): the key is a salted hash, so nothing kept names an address.
 *
 * @since 0.4.0
 */
interface Tally {

	/**
	 * A secret of this site's own, which no visitor knows.
	 *
	 * @since 0.4.0
	 */
	public function salt(): string;

	/**
	 * Adds one to a key's count and gives the count. A key not counted before starts at one.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key     The key: letters and digits.
	 * @param int    $seconds How long the count is kept from its first addition, in seconds.
	 */
	public function add( string $key, int $seconds ): int;
}
