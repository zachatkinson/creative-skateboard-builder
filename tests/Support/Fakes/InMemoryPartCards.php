<?php
/**
 * Cards kept in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\PartCards;

/**
 * A card for every part but those named as missing, and a record of what was asked for.
 */
final class InMemoryPartCards implements PartCards {

	/**
	 * The parts with no card: a product deleted since the index read it.
	 *
	 * @var list<int>
	 */
	public array $missing = array();

	/**
	 * Each call's IDs.
	 *
	 * @var list<list<int>>
	 */
	public array $asked = array();

	/**
	 * The parts forgotten.
	 *
	 * @var list<int>
	 */
	public array $forgotten = array();

	public function cards( array $ids ): array {
		$this->asked[] = $ids;
		$cards         = array();
		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $this->missing, true ) ) {
				$cards[ $id ] = self::card( $id );
			}
		}
		return $cards;
	}

	/**
	 * The card this fake gives a part.
	 *
	 * @return array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null}
	 */
	public static function card( int $id ): array {
		return array(
			'name'        => "Part {$id}",
			'url'         => "https://shop.test/?p={$id}",
			'price'       => '$' . $id,
			'stock'       => 'In stock',
			'stock_class' => 'in-stock',
			'image'       => 0 === $id % 2 ? null : array(
				'src'    => "https://shop.test/{$id}.jpg",
				'width'  => 300,
				'height' => 300,
				'srcset' => '',
				'sizes'  => '',
			),
		);
	}

	public function forget( int ...$ids ): void {
		array_push( $this->forgotten, ...array_values( $ids ) );
	}
}
