<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\PartSpecs;

/**
 * Products in a list, with every write kept.
 */
final class InMemoryPartSpecs implements PartSpecs {

	/**
	 * The products, by ID.
	 *
	 * @var array<int, array{sku: string, name: string, fields: array<string, string>, table: int, size: string}>
	 */
	public array $held = array();

	/**
	 * The IDs written, in order.
	 *
	 * @var list<int>
	 */
	public array $written = array();

	/**
	 * When set, write() throws it for this product.
	 */
	public ?\Throwable $fails = null;

	public int $failsOn = 0;

	/**
	 * Add a product.
	 *
	 * @param array<string, string> $fields Its values.
	 */
	public function add( int $id, string $sku = '', array $fields = array(), int $table = 0, string $size = '', string $name = '' ): void {
		$this->held[ $id ] = compact( 'sku', 'name', 'fields', 'table', 'size' );
		ksort( $this->held );
	}

	public function page( int $page, int $size ): array {
		return array_slice( array_keys( $this->held ), ( $page - 1 ) * $size, $size );
	}

	/**
	 * What each read() asked for.
	 *
	 * @var list<array<int>>
	 */
	public array $reads = array();

	public function read( int ...$ids ): array {
		$this->reads[] = $ids;
		return array_intersect_key( $this->held, array_flip( $ids ) );
	}

	public function bySku( string ...$skus ): array {
		$found = array();
		foreach ( $this->held as $id => $part ) {
			if ( in_array( $part['sku'], $skus, true ) ) {
				$found[ $part['sku'] ] = $id;
			}
		}
		return $found;
	}

	public function write( int $id, array $fields, int $table, string $size ): void {
		if ( null !== $this->fails && $id === $this->failsOn ) {
			throw $this->fails;
		}
		$this->held[ $id ]['fields'] = $fields;
		$this->held[ $id ]['table']  = $table;
		$this->held[ $id ]['size']   = $size;
		$this->written[]             = $id;
	}
}
