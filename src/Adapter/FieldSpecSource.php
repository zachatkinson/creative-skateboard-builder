<?php
/**
 * A part's own specs, from its meta.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What was filled in on the part: `_cbuild_specs`, a value per spec key, each as the index stores it. A variation
 * left blank for a spec takes its parent's value, and says so in the reading's reference (spec: Admin, Part specs).
 * A key this version does not know is passed over: a newer version may have written it.
 *
 * @since 0.3.0
 */
final class FieldSpecSource implements SpecSource {

	/**
	 * A reading's reference when a variation takes its parent's value.
	 *
	 * @since 0.3.0
	 */
	public const PARENT = 'parent';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Products $products Loads the batch.
	 */
	public function __construct( private Products $products ) {}

	/**
	 * What each part's fields say.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, list<Reading|\CreativeBranding\SkateboardBuilder\Domain\Spec\Unread>>
	 */
	public function read( int ...$partIds ): array {
		$products = $this->products->load( ...$partIds );
		$parents  = $this->products->parentsOf( $products );
		$found    = array_fill_keys( $partIds, array() );
		foreach ( $products as $id => $product ) {
			$own       = self::fields( $product );
			$parent    = $parents[ $product->get_parent_id() ] ?? null;
			$inherited = null === $parent ? array() : self::fields( $parent );
			foreach ( SpecKey::cases() as $key ) {
				if ( isset( $own[ $key->value ] ) ) {
					$found[ $id ][] = Reading::from( $key, $own[ $key->value ], Origin::Field );
				} elseif ( isset( $inherited[ $key->value ] ) ) {
					$found[ $id ][] = Reading::from( $key, $inherited[ $key->value ], Origin::Field, self::PARENT );
				}
			}
		}
		return $found;
	}

	/**
	 * A product's own fields: the values that are text and not blank, by key.
	 *
	 * @param WC_Product $product The product.
	 * @return array<string, string>
	 */
	private static function fields( WC_Product $product ): array {
		$stored = $product->get_meta( Options::META_SPECS, true );
		$fields = array();
		foreach ( is_array( $stored ) ? $stored : array() as $key => $value ) {
			if ( is_string( $key ) && is_string( $value ) && '' !== trim( $value ) ) {
				$fields[ $key ] = $value;
			}
		}
		return $fields;
	}
}
