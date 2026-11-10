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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For each line of the shop's mapping, the value the part has for that attribute, read in the line's unit: a
 * variation's own value, or a product's when it has one (spec: Data model, Part specs). A value is read with the
 * shop's decimal separator and, failing that, with a point, since a term's name may have been typed either way. A
 * product with several values for a mapped attribute has none that can be read, and the source says so.
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
		foreach ( $this->products->load( ...$partIds ) as $id => $product ) {
			foreach ( $mapping as $map ) {
				$text = trim( $product->get_attribute( $map->attribute ) );
				if ( '' === $text ) {
					continue;
				}
				$reading = Reading::from( $map->spec, $text, Origin::Attribute, $map->attribute, $separator, $map->unit );
				if ( ! $reading instanceof Reading && '.' !== $separator ) {
					$pointed = Reading::from( $map->spec, $text, Origin::Attribute, $map->attribute, '.', $map->unit );
					$reading = $pointed instanceof Reading ? $pointed : $reading;
				}
				$found[ $id ][] = $reading;
			}
		}
		return $found;
	}
}
