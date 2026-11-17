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
		global $wpdb;
		$asked = array();
		foreach ( array_unique( $products ) as $product ) {
			if ( $product > 0 && ! is_array( wp_cache_get( WC_Data::generate_meta_cache_key( $product, self::GROUP ), self::GROUP ) ) ) {
				$asked[] = $product;
			}
		}
		if ( count( $asked ) < 2 ) {
			// One product costs one query either way, and WooCommerce's own read keeps its order.
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only placeholders are joined in: one %d per product.
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, post_id, meta_key, meta_value FROM %i WHERE post_id IN (' . implode( ', ', array_fill( 0, count( $asked ), '%d' ) ) . ') ORDER BY meta_id', array( $wpdb->postmeta, ...$asked ) ) );
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
		WC_Data::prime_raw_meta_data_cache( $found, self::GROUP );
	}
}
