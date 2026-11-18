<?php
/**
 * One slot of a build on an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A slot's part as it was sold, from the kit's snapshot, and the order's line that holds it now.
 *
 * @since 0.8.0
 */
final readonly class OrderedPick {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param string                $slot     The slot's key.
	 * @param string                $label    The slot's label as the shopper read it; its key where none was kept.
	 * @param string                $name     The part's name when it was sold; empty where none was kept.
	 * @param string                $sku      The part's SKU when it was sold; empty where it had none.
	 * @param int                   $part     The part's product or variation ID.
	 * @param int                   $quantity How many of it one board takes.
	 * @param array<string, string> $specs    Its specs when it was sold, spec key to value as the index held them.
	 * @param int                   $item     The order item that holds it now, 0 where none does.
	 */
	public function __construct(
		public string $slot,
		public string $label,
		public string $name,
		public string $sku,
		public int $part,
		public int $quantity,
		public array $specs,
		public int $item
	) {}
}
