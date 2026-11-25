<?php
/**
 * Products' meta, read in one query into WooCommerce's cache.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Repository;

use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WC_Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A read into WooCommerce's own cache, which is the caching.

/**
 * The rows WooCommerce 11.3.0's `WC_Data_Store_WP::read_meta()` reads for one product
 * (includes/data-stores/class-wc-data-store-wp.php:83), read for several in one prepared query, and handed to
 * `WC_Data::prime_raw_meta_data_cache()` (includes/abstracts/abstract-wc-data.php:664) under the products' cache
 * group, `products` (includes/abstracts/abstract-wc-product.php:57), as WooCommerce primes orders' meta itself.
 * `read_meta_data()` filters what it finds there as it filters what it reads (D-202).
 *
 * @since 0.7.0
 */
final class ProductMetaRepository implements ProductMeta {

	/**
	 * The products' cache group.
	 */
	private const GROUP = 'products';

	/**
	 * Read these products' meta into WooCommerce's cache.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$products Product or variation IDs.
	 */
	public function prime( int ...$products ): void {
		$asked = self::cold( $products );
		if ( count( $asked ) < 2 ) {
			// One product costs one query either way, and WooCommerce's own read keeps its order.
			return;
		}
		$this->read( $asked, false );
	}

	/**
	 * Read these products' meta, and their products' where they are variations, into WooCommerce's cache.
	 *
	 * @since 1.2.0
	 *
	 * @param int ...$products Product or variation IDs.
	 */
	public function primeWithParents( int ...$products ): void {
		$asked = self::cold( $products );
		if ( array() !== $asked ) {
			$this->read( $asked, true );
		}
	}

	/**
	 * The products whose meta WooCommerce's cache does not hold yet.
	 *
	 * @param array<int> $products Product or variation IDs.
	 * @return list<int>
	 */
	private static function cold( array $products ): array {
		$asked = array();
		foreach ( array_unique( $products ) as $product ) {
			if ( $product > 0 && ! is_array( wp_cache_get( WC_Data::generate_meta_cache_key( $product, self::GROUP ), self::GROUP ) ) ) {
				$asked[] = $product;
			}
		}
		return $asked;
	}

	/**
	 * Read the products' meta, and with $parents their variations' products' too, in one query.
	 *
	 * @param list<int> $asked   Product or variation IDs not in the cache.
	 * @param bool      $parents Whether to read each variation's product as well.
	 */
	private function read( array $asked, bool $parents ): void {
		global $wpdb;
		$list = implode( ', ', array_fill( 0, count( $asked ), '%d' ) );
		if ( $parents ) {
			// Two selects, each found by an index: the products' rows by post_id, and their parents' through the posts'
			// primary key. Under one OR, MySQL read every row of postmeta instead (the auditor's M12 should-fix 3). UNION
			// drops only a row read twice, a parent also asked for, since each row brings its meta_id.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, post_id, meta_key, meta_value FROM %i WHERE post_id IN (' . $list . ') UNION SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value FROM %i AS m INNER JOIN %i AS p ON m.post_id = p.post_parent WHERE p.ID IN (' . $list . ') AND p.post_type = %s AND p.post_parent > 0 ORDER BY meta_id', array( $wpdb->postmeta, ...$asked, ...array( $wpdb->postmeta, $wpdb->posts ), ...$asked, 'product_variation' ) ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, post_id, meta_key, meta_value FROM %i WHERE post_id IN (' . $list . ') ORDER BY meta_id', array( $wpdb->postmeta, ...$asked ) ) );
		}
		$found = array_fill_keys( $asked, array() );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_object( $row ) ) {
				$found[ Cast::int( $row->post_id ?? null ) ][] = (object) array(
					'meta_id'    => Cast::str( $row->meta_id ?? null ),
					'meta_key'   => Cast::str( $row->meta_key ?? null ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A row read, not a query.
					'meta_value' => Cast::str( $row->meta_value ?? null ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- A row read, not a query.
				);
			}
		}
		// A product already in the cache is left as WooCommerce holds it.
		$found = array_filter( $found, static fn( int $id ): bool => in_array( $id, $asked, true ) || ! is_array( wp_cache_get( WC_Data::generate_meta_cache_key( $id, self::GROUP ), self::GROUP ) ), ARRAY_FILTER_USE_KEY );
		WC_Data::prime_raw_meta_data_cache( $found, self::GROUP );
	}
}
