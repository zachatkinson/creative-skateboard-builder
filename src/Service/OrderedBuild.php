<?php
/**
 * A build on an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A build as the workshop reads it: its kit's line, how many boards, whether the shop assembles it, and each slot's
 * part as sold. Read from the snapshot where it reads, and from the order's lines where it does not.
 *
 * @since 0.8.0
 */
final readonly class OrderedBuild {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param string                     $id       The build's `_cbuild_id`.
	 * @param int                        $item     The kit's order item, 0 where it was removed.
	 * @param int                        $kit      The kit product's ID, 0 where the snapshot does not say.
	 * @param int                        $boards   How many boards of it the order holds.
	 * @param bool                       $assembly Whether the shop assembles it.
	 * @param array<string, OrderedPick> $picks    Each slot's part, by its key, in step order.
	 * @param bool                       $read     Whether it was read from its snapshot.
	 */
	public function __construct(
		public string $id,
		public int $item,
		public int $kit,
		public int $boards,
		public bool $assembly,
		public array $picks,
		public bool $read
	) {}
}
