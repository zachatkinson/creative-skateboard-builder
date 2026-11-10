<?php
/**
 * A part's specs from the attributes the shop mapped.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;
use WC_Product;
use WC_Product_Attribute;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For each line of the shop's mapping, the value the part has for that attribute, read in the line's unit: a
 * variation's own value, or a product's when it has one (spec: Data model, Part specs). A value is read with the
 * shop's decimal separator and, failing that, with a point, since a term's name may have been typed either way. A
 * product with several values for a mapped attribute has none that can be read, and the source says so.
 *
 * A variation with no value of its own takes its parent's, when the parent does not vary by that attribute: a
 * shop sets a deck's length once, on the product, and WooCommerce answers a variation from its own attributes alone
 * (11.2.2, includes/class-wc-product-variation.php:140). A variation left at "Any" of an attribute the parent does
 * vary by has no one value, and takes none.
 *
 * A line reads the attribute it names and no other: `pa_length` is the taxonomy, `Length` a product's own
 * attribute of that name. WooCommerce's own lookup tries the name and then `pa_` and the name
 * (includes/abstracts/abstract-wc-product.php:2278), which would hand a line meant for one to the other (D-089).
 *
 * @since 0.3.0
 */
final class AttributeSpecSource implements SpecSource {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Products     $products Loads the batch.
	 * @param ShopSettings $settings The mapping.
	 */
	public function __construct( private Products $products, private ShopSettings $settings ) {}

	/**
	 * What each part's mapped attributes say.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, list<Reading|\CreativeBranding\SkateboardBuilder\Domain\Spec\Unread>>
	 */
	public function read( int ...$partIds ): array {
		$found   = array_fill_keys( $partIds, array() );
		$mapping = $this->settings->attributeMapping();
		if ( array() === $mapping ) {
			return $found;
		}
		$separator = wc_get_price_decimal_separator();
		$products  = $this->products->load( ...$partIds );
		$parents   = $this->products->parentsOf( $products );
		foreach ( $products as $id => $product ) {
			$parent = $parents[ $product->get_parent_id() ] ?? null;
			foreach ( $mapping as $map ) {
				$text = self::own( $product, $map->attribute );
				$ref  = $map->attribute;
				if ( '' === $text && null !== $parent ) {
					$text = self::inherited( $parent, $map->attribute );
					$ref  = self::PARENT_ATTRIBUTE . $map->attribute;
				}
				if ( '' === $text ) {
					continue;
				}
				$found[ $id ][] = Reading::typed( $map->spec, $text, Origin::Attribute, $ref, $separator, $map->unit );
			}
		}
		return $found;
	}

	/**
	 * A product's or a variation's own value for the attribute a line names, and '' when it has none.
	 *
	 * @param WC_Product $product   The product or variation.
	 * @param string     $attribute The name a mapping line holds.
	 */
	private static function own( WC_Product $product, string $attribute ): string {
		// Both keep their attributes by the name's slug, so a name that is there is the one WooCommerce answers with.
		return isset( $product->get_attributes()[ sanitize_title( $attribute ) ] ) ? trim( $product->get_attribute( $attribute ) ) : '';
	}

	/**
	 * A parent's value for the attribute, for its variations: '' when the parent varies by it, or has none.
	 *
	 * @param WC_Product $parent_product The variable product.
	 * @param string     $attribute      The name a mapping line holds.
	 */
	private static function inherited( WC_Product $parent_product, string $attribute ): string {
		$held = $parent_product->get_attributes()[ sanitize_title( $attribute ) ] ?? null;
		return $held instanceof WC_Product_Attribute && ! $held->get_variation() ? trim( $parent_product->get_attribute( $attribute ) ) : '';
	}
}
