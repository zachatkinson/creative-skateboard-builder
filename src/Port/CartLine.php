<?php
/**
 * A line of the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A line of the shopper's cart, as much of it as a build needs: what it is, how many, and the build's keys it
 * carries (spec: Data model, Cart items). The keys are as the session stored them, so they are read before they
 * are believed.
 *
 * @since 0.5.0
 */
final readonly class CartLine {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param string       $key      The line's cart key.
	 * @param int          $part     What it sells: the variation's ID where it is one, the product's otherwise.
	 * @param int          $quantity How many.
	 * @param mixed        $build    The key `cbuild` as stored, on a kit's line: the build. Null where there is none.
	 * @param list<string> $children The key `cbuild_children`, on a kit's line: its parts' cart keys.
	 * @param string       $kit      The key `cbuild_parent`, on a part's line: its kit's cart key. Empty for none.
	 * @param string       $slot     The key `cbuild_slot`, on a part's line: the slot it fills. Empty for none.
	 */
	public function __construct(
		public string $key,
		public int $part,
		public int $quantity,
		public mixed $build = null,
		public array $children = array(),
		public string $kit = '',
		public string $slot = ''
	) {}
}
