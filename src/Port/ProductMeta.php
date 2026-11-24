<?php
/**
 * Products' meta, read in one go.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce reads a product's meta with a query of its own the first time it is asked for in a request. Priming
 * reads several products' meta in one query into WooCommerce's own cache, so their objects then read it from there,
 * still through WooCommerce's CRUD (D-202).
 *
 * @since 0.7.0
 */
interface ProductMeta {

	/**
	 * Read these products' and variations' meta into WooCommerce's cache, in one query. Those already there, as far
	 * as this request knows, are not read again.
	 *
	 * @since 0.7.0
	 *
	 * @param int ...$products Product or variation IDs.
	 */
	public function prime( int ...$products ): void;

	/**
	 * Read these products' and variations' meta, and the meta of each variation's product, into WooCommerce's cache
	 * in one query, where the caller does not know the products yet: a page of saved builds (#116, D-351).
	 *
	 * @since 1.2.0
	 *
	 * @param int ...$products Product or variation IDs.
	 */
	public function primeWithParents( int ...$products ): void;
}
