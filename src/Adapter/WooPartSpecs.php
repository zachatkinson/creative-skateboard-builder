<?php
/**
 * What each product holds, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Port\PartSpecs;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Products and variations through WooCommerce's CRUD. A write saves the product, which queues its index job as any
 * other save does (Hooks\IndexHooks).
 *
 * @since 0.3.0
 */
final class WooPartSpecs implements PartSpecs {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Products $products Loads a batch.
	 */
	public function __construct( private Products $products ) {}

	/**
	 * One page of every product and variation, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param int $page The page, from 1.
	 * @param int $size How many a page holds.
	 * @return list<int>
	 */
	public function page( int $page, int $size ): array {
		$found = wc_get_products(
			array(
				'type'    => array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) ),
				'status'  => array_keys( get_post_stati() ),
				'limit'   => $size,
				'page'    => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);
		return array_values( Cast::ints( $found ) );
	}

	/**
	 * What these products hold, by ID.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 * @return array<int, array{sku: string, name: string, fields: array<string, string>, table: int, size: string}>
	 */
	public function read( int ...$ids ): array {
		$held = array();
		foreach ( $this->products->load( ...$ids ) as $id => $product ) {
			$named       = StoredSpecs::named( $product->get_meta( Options::META_SPEC_TABLE, true ) );
			$held[ $id ] = array(
				'sku'    => $product->get_sku( 'edit' ),
				'name'   => $product->get_name(),
				'fields' => StoredSpecs::fields( $product->get_meta( Options::META_SPECS, true ) ),
				'table'  => $named['table'],
				'size'   => $named['size'],
			);
		}
		return $held;
	}

	/**
	 * The products with these SKUs.
	 *
	 * @since 0.3.0
	 *
	 * @param string ...$skus SKUs.
	 * @return array<string, int>
	 */
	public function bySku( string ...$skus ): array {
		$found = array();
		foreach ( $skus as $sku ) {
			$id = wc_get_product_id_by_sku( $sku );
			if ( $id > 0 ) {
				$found[ $sku ] = $id;
			}
		}
		return $found;
	}

	/**
	 * Store what a product holds.
	 *
	 * @since 0.3.0
	 *
	 * @param int                   $id     The product's or variation's ID.
	 * @param array<string, string> $fields Its own values.
	 * @param int                   $table  The spec table it names.
	 * @param string                $size   The size it names.
	 */
	public function write( int $id, array $fields, int $table, string $size ): void {
		$product = $this->products->load( $id )[ $id ] ?? null;
		if ( null === $product ) {
			return;
		}
		$kept = StoredSpecs::kept( $fields, $table, $size );
		foreach ( array(
			Options::META_SPECS      => $kept['fields'],
			Options::META_SPEC_TABLE => $kept['named'],
		) as $key => $value ) {
			if ( null === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
		$product->save();
	}
}
