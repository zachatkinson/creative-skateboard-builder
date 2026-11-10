<?php
/**
 * The shop's parts, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;

/**
 * Products by ID: each is itself a part, or stands for the variations listed.
 */
final class InMemoryPartCatalogue implements PartCatalogue {

	/**
	 * Each product's parts: itself for a simple product or a variation, its variations for a variable one.
	 *
	 * @var array<int, list<int>>
	 */
	public array $products = array();

	/**
	 * The pages asked for.
	 *
	 * @var list<int>
	 */
	public array $pages = array();

	/**
	 * Thrown by the next page() call, once.
	 */
	public ?\Throwable $fails = null;

	public function page( int $page, int $size ): array {
		$this->pages[] = $page;
		if ( null !== $this->fails ) {
			$failure     = $this->fails;
			$this->fails = null;
			throw $failure;
		}
		$parts = array();
		foreach ( $this->products as $id => $stands ) {
			if ( array( $id ) === $stands ) {
				$parts[] = $id;
			}
		}
		sort( $parts );
		return array_slice( $parts, ( $page - 1 ) * $size, $size );
	}

	public function partsOf( int $id ): array {
		return $this->products[ $id ] ?? array();
	}

	public function holds( string $postType ): bool {
		return 'product' === $postType || 'product_variation' === $postType;
	}
}
