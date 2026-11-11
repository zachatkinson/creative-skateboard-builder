<?php
/**
 * The shop's kits, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidPart;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kits are products of the kit type (D-099), read through WooCommerce's CRUD. A slot's sources become parts as
 * D-101 sets out:
 *
 * - categories, brands and attribute values each narrow: a product is found when it is in one of the categories,
 *   and of one of the brands, and has one of the values of each attribute, for whichever of the three are given;
 * - a named product is added to those, whatever it is in;
 * - a variable product stands for its variations, and a variation that sets an attribute to one value is held to
 *   that value;
 * - a part is only what a shopper could be shown and could buy: published, not hidden from the catalogue, without
 *   a password, with a price, and no kit. Who is asking changes nothing. A part that is sold out is still a part.
 *
 * @since 0.4.0
 */
final class WooKits implements Kits {

	/**
	 * The most kits a shop's list holds.
	 *
	 * @since 0.4.0
	 */
	public const int MAX_KITS = 200;

	/**
	 * The most products a slot's categories, brands and attributes find, and the most parts a slot holds.
	 *
	 * @since 0.4.0
	 */
	public const int MAX_PARTS = 2000;

	/**
	 * WooCommerce's brand taxonomy.
	 */
	private const string BRAND = 'product_brand';

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Products        $products Loads products in batches.
	 * @param DocumentSchemas $schemas  The documents' schemas.
	 * @param SpecIndex       $index    The parts' specs.
	 */
	public function __construct( private Products $products, private DocumentSchemas $schemas, private SpecIndex $index ) {}

	/**
	 * Every kit whose documents read, by the kit product's ID.
	 *
	 * @since 0.4.0
	 *
	 * @return array<int, array{name: string, kit: Kit}>
	 */
	public function all(): array {
		$kits = array();
		foreach ( $this->products->load( ...$this->ids() ) as $id => $product ) {
			$kit = $this->read( $product );
			if ( null !== $kit ) {
				$kits[ $id ] = array(
					'name' => $product->get_name(),
					'kit'  => $kit,
				);
			}
		}
		ksort( $kits );
		return $kits;
	}

	/**
	 * Every kit product's ID: all but those in the bin and those WordPress made and nobody saved.
	 *
	 * @since 0.4.0
	 *
	 * @return list<int>
	 */
	public function ids(): array {
		$found = wc_get_products(
			array(
				'type'    => Options::PRODUCT_TYPE_KIT,
				'status'  => array_values( array_diff( array_keys( get_post_stati() ), array( 'trash', 'auto-draft' ) ) ),
				'limit'   => self::MAX_KITS,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);
		return array_values( Cast::ints( $found ) );
	}

	/**
	 * The parts that can fill one of a kit's slots.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $kit  The kit product's ID.
	 * @param string $slot The slot's key.
	 * @return list<Part>
	 */
	public function candidates( int $kit, string $slot ): array {
		$product = $this->products->load( $kit )[ $kit ] ?? null;
		$sources = null === $product ? null : $this->read( $product )?->slots->get( $slot )?->sources;
		return null === $sources ? array() : $this->parts( $sources );
	}

	/**
	 * The parts these sources find.
	 *
	 * @since 0.4.0
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<Part>
	 */
	public function parts( SlotSources $sources ): array {
		// What the sources find, and what they name: a named product is not narrowed.
		$found  = $this->products->load( ...$this->found( $sources ) );
		$named  = $this->products->load( ...$sources->products );
		$chosen = array();
		foreach ( array( $this->variationsOf( $found, $sources->attributes ), $this->variationsOf( $named, array() ) ) as $group ) {
			foreach ( $group as $id => $part ) {
				$chosen[ $id ] = $part;
			}
		}
		$parents = $this->products->parentsOf( $chosen ) + $found + $named;
		$chosen  = array_filter(
			$chosen,
			static fn( WC_Product $part ): bool => self::offered( $part, $part->is_type( 'variation' ) ? ( $parents[ $part->get_parent_id() ] ?? null ) : $part )
		);
		ksort( $chosen );
		$chosen = array_slice( $chosen, 0, self::MAX_PARTS, true );
		if ( array() === $chosen ) {
			return array();
		}

		$specs  = $this->index->specsOf( ...array_keys( $chosen ) );
		$owners = array();
		foreach ( $chosen as $id => $part ) {
			$owners[ $id ] = $part->is_type( 'variation' ) ? $part->get_parent_id() : $id;
		}
		$brands = $this->brands( ...array_values( $owners ) );
		$parts  = array();
		foreach ( $chosen as $id => $part ) {
			try {
				$parts[] = new Part(
					$id,
					$brands[ $owners[ $id ] ] ?? '',
					$specs[ $id ] ?? Specs::none(),
					self::minor( $part ),
					$part->is_in_stock(),
					self::stock( $part )
				);
			} catch ( InvalidPart ) {
				// A price below nothing, which a filter can give: no part.
				continue;
			}
		}
		return $parts;
	}

	/**
	 * Whether a visitor may see this kit: it is a kit, published, and has no password.
	 *
	 * @since 0.4.0
	 *
	 * @param int $kit A product's ID.
	 */
	public function isPublic( int $kit ): bool {
		$product = $this->products->load( $kit )[ $kit ] ?? null;
		return $product instanceof KitProduct && 'publish' === $product->get_status() && '' === $product->get_post_password();
	}

	/**
	 * A kit product's kit, or null when it is no kit, a document is refused by its schema, or the four disagree.
	 *
	 * @param WC_Product $product A product.
	 */
	private function read( WC_Product $product ): ?Kit {
		if ( ! $product instanceof KitProduct ) {
			return null;
		}
		$documents = $product->documents();
		foreach ( Options::kitDocumentNames() as $name => $key ) {
			if ( null !== $this->schemas->refusal( $name, $documents[ $key ] ) ) {
				return null;
			}
		}
		try {
			return Kit::fromDocuments(
				$documents[ Options::META_KIT_SLOTS ],
				$documents[ Options::META_KIT_RULES ],
				$documents[ Options::META_KIT_PRESETS ],
				$documents[ Options::META_KIT_ASSEMBLY ]
			);
		} catch ( InvalidDocument ) {
			return null;
		}
	}

	/**
	 * The published products in the sources' categories, of their brands and with their attribute values.
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<int>
	 */
	private function found( SlotSources $sources ): array {
		$narrow = self::narrowing( $sources );
		if ( array() === $narrow ) {
			return array();
		}
		$query = array( 'relation' => 'AND' );
		foreach ( $narrow as $one ) {
			if ( ! taxonomy_exists( $one['taxonomy'] ) ) {
				return array();
			}
			$query[] = array(
				'taxonomy' => $one['taxonomy'],
				'field'    => 'term_id',
				'terms'    => $one['terms'],
				'operator' => 'IN',
			);
		}
		$found = wc_get_products(
			array(
				'status'    => 'publish',
				'limit'     => self::MAX_PARTS,
				'orderby'   => 'ID',
				'order'     => 'ASC',
				'return'    => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- A slot's sources are terms; the answer is cached with the kit's card data.
				'tax_query' => array( $query ),
			)
		);
		return array_values( Cast::ints( $found ) );
	}

	/**
	 * What narrows a slot's products: its categories, its brands and each of its attributes, for those it gives.
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<array{taxonomy: string, terms: list<int>}>
	 */
	private static function narrowing( SlotSources $sources ): array {
		$narrow = array();
		foreach ( array(
			'product_cat' => $sources->categories,
			self::BRAND   => $sources->brands,
		) as $taxonomy => $terms ) {
			if ( array() !== $terms ) {
				$narrow[] = compact( 'taxonomy', 'terms' );
			}
		}
		foreach ( $sources->attributes as $attribute ) {
			if ( array() !== $attribute['terms'] ) {
				$narrow[] = $attribute;
			}
		}
		return $narrow;
	}

	/**
	 * The keys of the slots, in these kits, whose sources take a product or variation: named by a slot, or in what
	 * the slot's categories, brands and attributes find. A category holds what its sub-categories hold, as the
	 * query that finds a slot's parts has it.
	 *
	 * @since 0.4.0
	 *
	 * @param int $product The product's or variation's ID.
	 * @param Kit ...$kits The kits.
	 * @return list<string>
	 */
	public function slotsOf( int $product, Kit ...$kits ): array {
		$part = array() === $kits ? null : ( $this->products->load( $product )[ $product ] ?? null );
		if ( null === $part ) {
			return array();
		}
		$owner = $part->is_type( 'variation' ) ? $part->get_parent_id() : $product;
		$terms = array();
		$keys  = array();
		foreach ( $kits as $kit ) {
			foreach ( $kit->slots->all() as $slot ) {
				$sources = $slot->sources;
				$narrow  = self::narrowing( $sources );
				$takes   = array() !== $narrow && self::holdsTo( $part, $sources->attributes );
				foreach ( $narrow as $one ) {
					$terms[ $one['taxonomy'] ] ??= self::termsOf( $owner, $one['taxonomy'] );
					$takes                       = $takes && array() !== array_intersect( $one['terms'], $terms[ $one['taxonomy'] ] );
				}
				if ( $takes || in_array( $product, $sources->products, true ) || in_array( $owner, $sources->products, true ) ) {
					$keys[ $slot->key ] = $slot->key;
				}
			}
		}
		return array_values( $keys );
	}

	/**
	 * A product's terms in a taxonomy, with each term's ancestors.
	 *
	 * @param int    $product  The product's ID.
	 * @param string $taxonomy The taxonomy.
	 * @return list<int>
	 */
	private static function termsOf( int $product, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$ids = Cast::ints( wp_get_object_terms( $product, $taxonomy, array( 'fields' => 'ids' ) ) );
		foreach ( $ids as $id ) {
			$ids = array_merge( $ids, Cast::ints( get_ancestors( $id, $taxonomy, 'taxonomy' ) ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * These products as parts: each variable product's variations in its place, a variation that names one value
	 * of an attribute kept only when the value is among the terms asked for.
	 *
	 * @param array<int, WC_Product>                          $products   Products, by ID.
	 * @param list<array{taxonomy: string, terms: list<int>}> $attributes The attribute values asked for.
	 * @return array<int, WC_Product>
	 */
	private function variationsOf( array $products, array $attributes ): array {
		$parts    = array();
		$children = array();
		foreach ( $products as $id => $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$children = array_merge( $children, Cast::ints( $product->get_children() ) );
			} else {
				$parts[ $id ] = $product;
			}
		}
		foreach ( $this->products->load( ...$children ) as $id => $variation ) {
			if ( self::holdsTo( $variation, $attributes ) ) {
				$parts[ $id ] = $variation;
			}
		}
		return $parts;
	}

	/**
	 * Whether a variation's own values are among those asked for. One that takes any value of an attribute is.
	 *
	 * @param WC_Product                                      $variation  A variation.
	 * @param list<array{taxonomy: string, terms: list<int>}> $attributes The attribute values asked for.
	 */
	private static function holdsTo( WC_Product $variation, array $attributes ): bool {
		if ( ! $variation->is_type( 'variation' ) ) {
			return true;
		}
		$own = Cast::map( $variation->get_attributes() );
		foreach ( $attributes as $attribute ) {
			$slug = Cast::str( $own[ $attribute['taxonomy'] ] ?? '' );
			if ( '' === $slug || array() === $attribute['terms'] ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $attribute['taxonomy'] );
			if ( ! $term instanceof \WP_Term || ! in_array( $term->term_id, $attribute['terms'], true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a shopper could be shown this part and could buy it. WooCommerce's own answer lets a person who may
	 * edit a product buy it unpublished; this one does not ask who is looking.
	 *
	 * @param WC_Product      $part  The part.
	 * @param WC_Product|null $owner The part itself, or a variation's parent.
	 */
	private static function offered( WC_Product $part, ?WC_Product $owner ): bool {
		return null !== $owner
			&& ! in_array( $part->get_type(), array( 'variable', Options::PRODUCT_TYPE_KIT ), true )
			&& 'publish' === $part->get_status()
			&& 'publish' === $owner->get_status()
			&& 'hidden' !== $owner->get_catalog_visibility()
			&& '' === $owner->get_post_password()
			&& $part->is_purchasable();
	}

	/**
	 * Each product's brand: the slug of its brand, the first by name when it has several.
	 *
	 * @param int ...$ids Products' IDs.
	 * @return array<int, string>
	 */
	private function brands( int ...$ids ): array {
		if ( ! taxonomy_exists( self::BRAND ) ) {
			return array();
		}
		$terms  = wp_get_object_terms(
			array_values( array_unique( $ids ) ),
			self::BRAND,
			array(
				'fields'  => 'all_with_object_id',
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
		$brands = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			// The product's ID is a property WordPress adds to the term for this query alone.
			$brands[ Cast::int( get_object_vars( $term )['object_id'] ?? 0 ) ] ??= $term->slug;
		}
		return $brands;
	}

	/**
	 * The price of one as the shop shows it, in the currency's minor units.
	 *
	 * @param WC_Product $part The part.
	 */
	private static function minor( WC_Product $part ): int {
		return (int) round( Cast::float( wc_get_price_to_display( $part ) ) * 10 ** wc_get_price_decimals() );
	}

	/**
	 * How many the shop holds, when it counts them and sells no more than it holds.
	 *
	 * @param WC_Product $part The part.
	 */
	private static function stock( WC_Product $part ): ?int {
		if ( ! $part->managing_stock() || $part->backorders_allowed() ) {
			return null;
		}
		return max( 0, Cast::int( $part->get_stock_quantity() ) );
	}

	/**
	 * How many decimals the shop's prices have.
	 *
	 * @since 0.4.0
	 */
	public function decimals(): int {
		return wc_get_price_decimals();
	}
}
