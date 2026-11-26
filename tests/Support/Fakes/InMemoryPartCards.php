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
	 * Each call's brands.
	 *
	 * @var list<list<string>>
	 */
	public array $brandsAsked = array();

	/**
	 * The parts forgotten.
	 *
	 * @var list<int>
	 */
	public array $forgotten = array();

	/**
	 * The ratings the shop shows, by part (1.3.0, #137); a part not here has none.
	 *
	 * @var array<int, array{average: float, count: int}>
	 */
	public array $rated = array();

	/**
	 * The parts whose card picture is their layer image, having no photo (D-282): a part here has no large photo.
	 *
	 * @var array<int, array{src: string, width: int, height: int, srcset: string, sizes: string}>
	 */
	public array $layered = array();

	public function cards( array $ids ): array {
		$this->asked[] = $ids;
		$cards         = array();
		foreach ( $ids as $id ) {
			if ( ! in_array( $id, $this->missing, true ) ) {
				$cards[ $id ]           = self::card( $id );
				$cards[ $id ]['rating'] = $this->rated[ $id ] ?? null;
				if ( isset( $this->layered[ $id ] ) ) {
					$cards[ $id ] = array(
						'image' => $this->layered[ $id ],
						'photo' => false,
						'large' => null,
					) + $cards[ $id ];
				}
			}
		}
		return $cards;
	}

	/**
	 * A card as the route lists it: without the large photo, which is the picture's alone (#82).
	 *
	 * @return array<string, mixed>
	 */
	public static function listed( int $id ): array {
		return array_diff_key( self::card( $id ), array( 'large' => true ) );
	}

	/**
	 * The card this fake gives a part.
	 *
	 * @return array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, photo: bool, large: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, rating: array{average: float, count: int}|null}
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
			'photo'       => 0 !== $id % 2,
			// The photo uncropped, at the shop's single-product size (1.1.0, #82).
			'large'       => 0 === $id % 2 ? null : array(
				'src'    => "https://shop.test/{$id}-large.jpg",
				'width'  => 600,
				'height' => 1200,
				'srcset' => "https://shop.test/{$id}-large.jpg 600w",
				'sizes'  => '(max-width: 600px) 100vw, 600px',
			),
			// The rating the shop shows, none unless a test sets one (1.3.0, #137).
			'rating'      => null,
		);
	}

	/**
	 * A name for every brand but `gone`: the slug with a capital.
	 */
	public function brands( array $slugs ): array {
		$this->brandsAsked[] = $slugs;
		$names               = array();
		foreach ( $slugs as $slug ) {
			if ( 'gone' !== $slug ) {
				$names[ $slug ] = ucfirst( $slug );
			}
		}
		return $names;
	}

	/**
	 * The media library's photos, by attachment ID.
	 *
	 * @var array<int, array{src: string, width: int, height: int, srcset: string, sizes: string}>
	 */
	public array $photos = array();

	public function photo( int $attachment ): ?array {
		return $this->photos[ $attachment ] ?? null;
	}

	public function forget( int ...$ids ): void {
		array_push( $this->forgotten, ...array_values( $ids ) );
	}
}
