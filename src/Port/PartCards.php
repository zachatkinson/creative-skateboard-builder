<?php
/**
 * What a part's card shows.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An option is a part as the builder shows it in a slot (spec: Vocabulary, Option): its name, its photo, its price
 * and its stock, each as the shop shows it. Fit is the engine's, and is not here.
 *
 * @since 0.4.0
 */
interface PartCards {

	/**
	 * The cards of these parts, by ID, in the order asked. An ID that is no product or variation has none. The
	 * caller asks only for parts a shopper may be shown: a card is given for any product it names.
	 *
	 * @since 0.4.0
	 *
	 * @param list<int> $ids Parts' IDs.
	 * @return array<int, array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, photo: bool, large?: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, rating?: array{average: float, count: int}|null}>
	 *         `name` is plain text; `price` is the price of one as the shop formats it, in plain text; `stock` is the shop's own words for the
	 *         part's availability, empty where the shop says nothing of a part in stock.
	 *         `large`, from 1.1.0, is the photo uncropped at the shop's single-product size, for the picture's deck
	 *         where it has no layer image; null, or absent, where there is no photo.
	 *         `rating`, from 1.3.0, is the part's average rating, 0 to 5, and its review count, a variation's being its
	 *         product's; null, or absent, where it has no reviews or the shop shows no ratings (#137).
	 */
	public function cards( array $ids ): array;

	/**
	 * The shop's names for these brands, by slug, in plain text. A slug the shop has no brand for has none.
	 *
	 * @since 0.6.0
	 *
	 * @param list<string> $slugs Brands' slugs, as a part holds its brand.
	 * @return array<string, string>
	 */
	public function brands( array $slugs ): array;

	/**
	 * Forget what is kept of these products and of their variations, so their cards are read again.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void;

	/**
	 * An image from the media library as a card's photo is, or null when it is none.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment The attachment's ID.
	 * @return array{src: string, width: int, height: int, srcset: string, sizes: string}|null
	 */
	public function photo( int $attachment ): ?array;
}
