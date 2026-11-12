<?php
/**
 * What a save changed of the specs index's sources.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The index is read from a product's attributes, its spec documents and its parent. WooCommerce saves a product for
 * much else: an order writes the stock count itself and then saves the product with nothing changed
 * (`wc_update_product_stock()`, WooCommerce 11.2.2, includes/wc-stock-functions.php:58 to :64), and the hook that
 * follows a save says nothing of what it changed. This notes, within a request, what each save is about to change,
 * so that the save's job is queued only when a source moved (issue #21).
 *
 * It errs towards the job: a save it was told nothing of, and a change to any property it does not know to be
 * beside the point, both count as a source moved.
 *
 * @since 0.5.0
 */
final class SavedSources {

	/**
	 * The properties a save may change without moving a source: what selling a product changes.
	 */
	private const BESIDE = array( 'stock_quantity', 'stock_status', 'total_sales', 'date_modified' );

	/**
	 * The prefix of an attribute's taxonomy, as WooCommerce names them.
	 */
	private const ATTRIBUTE = 'pa_';

	/**
	 * The products being saved, each with whether its save changes a property that may be a source.
	 *
	 * @var array<int, bool>
	 */
	private array $saving = array();

	/**
	 * The products with a spec document written or an attribute's terms changed since they were last asked about.
	 *
	 * @var array<int, int>
	 */
	private array $written = array();

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param list<string> $documents The meta keys the index reads.
	 */
	public function __construct( private array $documents ) {}

	/**
	 * A product is about to be saved over what is stored.
	 *
	 * @since 0.5.0
	 *
	 * @param int          $id    Its ID; none for a product not yet made, whose first save always queues its job.
	 * @param list<string> $props The properties the save changes.
	 */
	public function saving( int $id, array $props ): void {
		if ( $id > 0 ) {
			$this->saving[ $id ] = array() !== array_diff( $props, self::BESIDE );
		}
	}

	/**
	 * A post's meta was added, changed or deleted.
	 *
	 * @since 0.5.0
	 *
	 * @param int    $id  The post's ID.
	 * @param string $key The meta key.
	 */
	public function meta( int $id, string $key ): void {
		if ( in_array( $key, $this->documents, true ) ) {
			$this->written[ $id ] = $id;
		}
	}

	/**
	 * A post's terms were set.
	 *
	 * @since 0.5.0
	 *
	 * @param int               $id       The post's ID.
	 * @param string            $taxonomy The taxonomy.
	 * @param array<int|string> $now      The term taxonomy IDs it has now, as numbers or as the database's strings.
	 * @param array<int|string> $before   Those it had.
	 */
	public function terms( int $id, string $taxonomy, array $now, array $before ): void {
		if ( ! str_starts_with( $taxonomy, self::ATTRIBUTE ) ) {
			return;
		}
		// array_diff compares as strings, so 3 and '3' are the same term.
		if ( array() !== array_diff( $now, $before ) || array() !== array_diff( $before, $now ) ) {
			$this->written[ $id ] = $id;
		}
	}

	/**
	 * Whether the save that just ended may have moved a source. What was noted of the product is forgotten.
	 *
	 * @since 0.5.0
	 *
	 * @param int $id The product's ID.
	 */
	public function moved( int $id ): bool {
		$moved = ( $this->saving[ $id ] ?? true ) || isset( $this->written[ $id ] );
		unset( $this->saving[ $id ], $this->written[ $id ] );
		return $moved;
	}
}
