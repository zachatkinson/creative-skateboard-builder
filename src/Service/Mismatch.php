<?php
/**
 * Where an order's lines no longer match its build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A code and what it is about, never words: the Build box and the ticket add those (spec: Admin, On orders).
 *
 * @since 0.8.0
 */
final readonly class Mismatch {

	/**
	 * A slot's part line is gone from the order.
	 *
	 * @since 0.8.0
	 */
	public const string PART_REMOVED = 'part_removed';

	/**
	 * A part's line holds a different number from what the build's boards need.
	 *
	 * @since 0.8.0
	 */
	public const string QUANTITY_CHANGED = 'quantity_changed';

	/**
	 * A slot's line is gone, and a line of the same part without the build's keys is in its place.
	 *
	 * @since 0.8.0
	 */
	public const string COPIED_WITHOUT_KEYS = 'copied_without_keys';

	/**
	 * A kit's line with no `_cbuild_id`: a kit added outside the builder (D-139).
	 *
	 * @since 0.8.0
	 */
	public const string KIT_WITHOUT_BUILD = 'kit_without_build';

	/**
	 * A build's parts are on the order and its kit's line is not.
	 *
	 * @since 0.8.0
	 */
	public const string KIT_REMOVED = 'kit_removed';

	/**
	 * The kit's `_cbuild_snapshot` is missing or does not read.
	 *
	 * @since 0.8.0
	 */
	public const string SNAPSHOT_UNREAD = 'snapshot_unread';

	/**
	 * A line tied to the build fills a slot the build was not sold with.
	 *
	 * @since 0.8.0
	 */
	public const string PART_NOT_IN_BUILD = 'part_not_in_build';

	/**
	 * A slot's line, or the kit's, is on the order twice.
	 *
	 * @since 0.8.0
	 */
	public const string PART_TWICE = 'part_twice';

	/**
	 * A slot's line is of another product than the one sold.
	 *
	 * @since 0.8.0
	 */
	public const string PART_CHANGED = 'part_changed';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param string $code     One of the constants above.
	 * @param string $build    The build's `_cbuild_id`, empty for a line of no build.
	 * @param string $slot     The slot's key, empty where it is not about a slot.
	 * @param int    $item     The order item it is about, 0 where there is none.
	 * @param int    $expected What the build needs: a quantity, or a product's ID for PART_CHANGED; 0 otherwise.
	 * @param int    $found    What the line holds, in the same terms; 0 otherwise.
	 * @param string $name     The line's name, as the order keeps it, where it is about a line.
	 */
	public function __construct(
		public string $code,
		public string $build,
		public string $slot = '',
		public int $item = 0,
		public int $expected = 0,
		public int $found = 0,
		public string $name = ''
	) {}
}
