<?php
/**
 * What a step's shelf knows of a part beside its fit.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

/**
 * A part as the shelf groups, filters, finds and orders it (D-240, D-243, D-247, D-248). Its fit is the engine's.
 */
final readonly class Shelved {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int                         $product  The product it belongs to.
	 * @param int                         $photo    Its photo, 0 for none.
	 * @param int                         $layer    Its layer image, 0 for none.
	 * @param array<string, string>       $choices  Its own values of the attributes other than the slot's size, by attribute.
	 * @param array<string, list<string>> $facets   Its attributes' values for the chips, by attribute, brand and size aside.
	 * @param list<string>                $words    Its brand, name and size, folded: in lower case without accents.
	 * @param list<string>                $own      Its look's own words (its values' names), folded, which tell it from its
	 *                                              product's other looks.
	 * @param bool                        $featured Whether the shop stars its product.
	 * @param int                         $sales    Its product's sales count.
	 * @param int                         $created  When its product was made, a timestamp.
	 * @param string                      $name     Its product's name, for the order.
	 */
	public function __construct(
		public int $product,
		public int $photo,
		public int $layer,
		public array $choices,
		public array $facets,
		public array $words,
		public array $own,
		public bool $featured,
		public int $sales,
		public int $created,
		public string $name
	) {}
}
