<?php
/**
 * The specs each product holds itself.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Part specs panel shows for one product, for many at once: the export reads it and the import writes it.
 * A variable product is here beside its variations, since it names the chart they take their sizes from.
 *
 * @since 0.3.0
 */
interface PartSpecs {

	/**
	 * One page of every product and variation in the shop, by ID, whatever its status.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page The page, from 1.
	 * @param int $size How many a page holds.
	 * @return list<int> IDs. Fewer than a page's worth means it is the last.
	 */
	public function page( int $page, int $size ): array;

	/**
	 * What these products hold, by ID. An ID that is not a product is not in the answer.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 * @return array<int, array{sku: string, name: string, fields: array<string, string>, table: int, size: string}>
	 */
	public function read( int ...$ids ): array;

	/**
	 * The products with these SKUs, as SKU to ID. A SKU no product has is not in the answer.
	 *
	 * @since 0.3.0
	 *
	 * @param string ...$skus SKUs.
	 * @return array<string, int>
	 */
	public function bySku( string ...$skus ): array;

	/**
	 * Store what a product holds, in place of what it held.
	 *
	 * @since 0.3.0
	 *
	 * @param int                   $id     The product's or variation's ID.
	 * @param array<string, string> $fields Its own values, by spec key.
	 * @param int                   $table  The spec table it names, 0 for none.
	 * @param string                $size   The size it names, '' for none.
	 */
	public function write( int $id, array $fields, int $table, string $size ): void;
}
