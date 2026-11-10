<?php
/**
 * Which products may fill a slot.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * The owner's choice of a slot's products, by category, brand, product and attribute value, as ids (spec: Data model,
 * `_cbuild_slots`). The catalogue turns them into candidate parts; the domain only carries them.
 */
final readonly class SlotSources {

	/**
	 * The most attributes a slot filters by.
	 */
	public const int MAX_ATTRIBUTES = 20;

	/**
	 * Constructor.
	 *
	 * @param list<int>                                       $categories Product category ids.
	 * @param list<int>                                       $brands     Brand ids.
	 * @param list<int>                                       $products   Product ids.
	 * @param list<array{taxonomy: string, terms: list<int>}> $attributes Attribute taxonomies with the term ids allowed in each.
	 */
	private function __construct( public array $categories, public array $brands, public array $products, public array $attributes ) {}

	/**
	 * From a slot's `sources` object.
	 *
	 * @since 0.2.0
	 *
	 * @param Fields $fields The object.
	 *
	 * @throws InvalidDocument When a list holds something that is not an id, or an attribute has no taxonomy.
	 */
	public static function fromFields( Fields $fields ): self {
		$attributes = array();
		foreach ( $fields->rows( 'attributes', self::MAX_ATTRIBUTES ) as $row ) {
			$attributes[] = array(
				'taxonomy' => $row->text( 'taxonomy' ),
				'terms'    => $row->ids( 'terms' ),
			);
		}
		return new self( $fields->ids( 'categories' ), $fields->ids( 'brands' ), $fields->ids( 'products' ), $attributes );
	}
}
