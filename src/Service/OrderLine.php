<?php
/**
 * One line of an order, as the order's build is read from it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What an order item says, handed over by the code that reads WooCommerce's order: its ID, its product, how many,
 * the plugin's keys on it, and whether its product is a kit. Nothing here reads the order (D-138).
 *
 * @since 0.8.0
 */
final readonly class OrderLine {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $id       The order item's ID.
	 * @param int    $product  Its product's ID, the variation's where it is one.
	 * @param int    $quantity How many.
	 * @param string $name     The item's name, as the order keeps it.
	 * @param string $build    Its `_cbuild_id`, empty where it has none.
	 * @param string $role     Its `_cbuild_role`: `kit`, `part`, or empty.
	 * @param string $slot     Its `_cbuild_slot`, empty where it has none.
	 * @param bool   $kit      Whether its product is a kit.
	 * @param mixed  $snapshot Its `_cbuild_snapshot` as stored, null where it has none.
	 */
	public function __construct(
		public int $id,
		public int $product,
		public int $quantity,
		public string $name,
		public string $build = '',
		public string $role = '',
		public string $slot = '',
		public bool $kit = false,
		public mixed $snapshot = null
	) {}
}
