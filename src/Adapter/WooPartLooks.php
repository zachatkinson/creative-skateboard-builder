<?php
/**
 * Parts' looks, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\LookFact;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
use WP_Post;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a part's look from the product through WooCommerce's CRUD (D-240, D-243):
 *
 * - its product, a variation's parent or itself;
 * - its photo (`WC_Product::get_image_id()`, which gives a variation with none its parent's) and its layer image,
 *   its own or its parent's;
 * - its global attributes' values: a variation's own, then those its parent holds and it does not vary;
 * - the product's star, sales count and date made, for the default order.
 *
 * Each part's look is kept in the object cache for an hour at most, against the same stamp as its card
 * (`WooPartCards::stamp()`): read again once the part or its parent is saved. A term renamed without a save shows
 * its old name until the hour ends. What forgets a part's card forgets its look (`WooPartCards::settle()`). The posts are primed in one query, the products that missed in one more, and
 * each taxonomy's terms in one.
 *
 * @since 1.0.0
 */
final class WooPartLooks implements PartLooks {

	/**
	 * The post types a part has.
	 */
	private const array TYPES = array( 'product', 'product_variation' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Products    $products Loads products in one query.
	 * @param ProductMeta $meta     Reads the products' meta in one query.
	 */
	public function __construct( private Products $products, private ProductMeta $meta ) {}

	/**
	 * The facts of these parts, by ID, in the order asked.
	 *
	 * @since 1.0.0
	 *
	 * @param list<int> $ids Parts' IDs.
	 * @return array<int, LookFact>
	 */
	public function looks( array $ids ): array {
		$ids = array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
		if ( array() === $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, false );
		$posts   = array();
		$parents = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && in_array( $post->post_type, self::TYPES, true ) ) {
				$posts[ $id ] = $post;
				if ( $post->post_parent > 0 ) {
					$parents[] = $post->post_parent;
				}
			}
		}
		_prime_post_caches( array_values( array_unique( $parents ) ), false, false );

		$facts  = array();
		$stamps = array();
		foreach ( $posts as $id => $post ) {
			$stamps[ $id ] = WooPartCards::stamp( $post );
			$entry         = wp_cache_get( self::key( $id ), Options::CACHE_GROUP );
			$kept          = is_array( $entry ) && ( $entry['stamp'] ?? null ) === $stamps[ $id ] ? self::read( $entry['look'] ?? null ) : null;
			if ( null !== $kept ) {
				$facts[ $id ] = $kept;
			}
		}

		$missed = array_values( array_diff( array_keys( $posts ), array_keys( $facts ) ) );
		if ( array() !== $missed ) {
			$products = $this->products->load( ...$missed );
			$owners   = $this->products->parentsOf( $products );
			// The layer image is the products' meta, which WooCommerce reads a product at a time unless it is primed.
			$this->meta->prime( ...array_merge( array_keys( $products ), array_keys( $owners ) ) );
			$read = array();
			foreach ( $products as $id => $product ) {
				$read[ $id ] = self::readPart( $product, $owners[ $product->get_parent_id() ] ?? null );
			}
			$labels = self::labels( $read );
			foreach ( $read as $id => $one ) {
				$one['labels'] = array();
				foreach ( $one['attributes'] as $taxonomy => $slugs ) {
					foreach ( $slugs as $slug ) {
						$one['labels'][ $taxonomy ][ $slug ] = $labels[ $taxonomy ][ $slug ] ?? $slug;
					}
				}
				$facts[ $id ] = self::fact( $one );
				wp_cache_set(
					self::key( $id ),
					array(
						'stamp' => $stamps[ $id ],
						'look'  => $one,
					),
					Options::CACHE_GROUP,
					HOUR_IN_SECONDS
				);
			}
		}

		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $facts[ $id ] ) ) {
				$ordered[ $id ] = $facts[ $id ];
			}
		}
		return $ordered;
	}

	/**
	 * A part's look, before its values are named.
	 *
	 * @param WC_Product      $product  The part.
	 * @param WC_Product|null $variable A variation's parent.
	 * @return array{product: int, photo: int, layer: int, name: string, attributes: array<string, list<string>>, labels: array<string, array<string, string>>, featured: bool, sales: int, created: int}
	 */
	private static function readPart( WC_Product $product, ?WC_Product $variable ): array {
		$owner      = $variable ?? $product;
		$attributes = array();
		if ( null !== $variable ) {
			foreach ( $product->get_attributes() as $taxonomy => $value ) {
				// "Any" is an empty value: the variation says nothing of it.
				if ( is_string( $taxonomy ) && str_starts_with( $taxonomy, 'pa_' ) && is_string( $value ) && '' !== $value ) {
					$attributes[ $taxonomy ] = array( $value );
				}
			}
		}
		foreach ( $owner->get_attributes() as $held ) {
			if ( ! $held instanceof \WC_Product_Attribute || ! $held->is_taxonomy() || isset( $attributes[ $held->get_name() ] ) ) {
				continue;
			}
			if ( null !== $variable && $held->get_variation() ) {
				continue;
			}
			$slugs = array();
			foreach ( $held->get_terms() ?? array() as $term ) {
				if ( $term instanceof WP_Term ) {
					$slugs[] = $term->slug;
				}
			}
			if ( array() !== $slugs ) {
				$attributes[ $held->get_name() ] = $slugs;
			}
		}
		$layer   = Cast::int( $product->get_meta( Options::META_LAYER, true ) );
		$created = $owner->get_date_created();
		return array(
			'product'    => $owner->get_id(),
			'photo'      => Cast::int( $product->get_image_id() ),
			'layer'      => $layer > 0 || null === $variable ? $layer : Cast::int( $variable->get_meta( Options::META_LAYER, true ) ),
			// WordPress stores a title with its entities written out; this is plain text, which the edge escapes.
			'name'       => html_entity_decode( wp_strip_all_tags( $owner->get_name() ), ENT_QUOTES, 'UTF-8' ),
			'attributes' => $attributes,
			'labels'     => array(),
			'featured'   => $owner->is_featured(),
			'sales'      => Cast::int( $owner->get_total_sales() ),
			'created'    => null === $created ? 0 : $created->getTimestamp(),
		);
	}

	/**
	 * The shop's names of these global attributes, from WooCommerce's cached list of them
	 * (`wc_get_attribute_taxonomy_labels()`, woocommerce/includes/wc-attribute-functions.php:121).
	 *
	 * @since 1.0.0
	 *
	 * @param list<string> $taxonomies Attributes' taxonomies, `pa_…`.
	 * @return array<string, string>
	 */
	public function attributes( array $taxonomies ): array {
		if ( array() === $taxonomies ) {
			return array();
		}
		$labels = wc_get_attribute_taxonomy_labels();
		$names  = array();
		foreach ( $taxonomies as $taxonomy ) {
			$label = str_starts_with( $taxonomy, 'pa_' ) ? ( $labels[ substr( $taxonomy, 3 ) ] ?? null ) : null;
			if ( is_string( $label ) ) {
				$names[ $taxonomy ] = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $names;
	}

	/**
	 * The names of the values the looks hold, by taxonomy and slug: one query a taxonomy.
	 *
	 * @param array<int, array{attributes: array<string, list<string>>}> $looks The looks read.
	 * @return array<string, array<string, string>>
	 */
	private static function labels( array $looks ): array {
		$asked = array();
		foreach ( $looks as $look ) {
			foreach ( $look['attributes'] as $taxonomy => $slugs ) {
				foreach ( $slugs as $slug ) {
					$asked[ $taxonomy ][ $slug ] = true;
				}
			}
		}
		$labels = array();
		foreach ( $asked as $taxonomy => $slugs ) {
			$terms = get_terms(
				array(
					'taxonomy'               => $taxonomy,
					'slug'                   => array_map( 'strval', array_keys( $slugs ) ),
					'hide_empty'             => false,
					'update_term_meta_cache' => false,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term instanceof WP_Term ) {
					$labels[ $taxonomy ][ $term->slug ] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
				}
			}
		}
		return $labels;
	}

	/**
	 * The entry's key for a part, which `WooPartCards::settle()` forgets with the part's card.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The part's ID.
	 */
	public static function key( int $id ): string {
		return 'look_' . $id;
	}

	/**
	 * A kept look, when it is one: a cache can hand back anything.
	 *
	 * @param mixed $kept What the entry holds.
	 */
	private static function read( mixed $kept ): ?LookFact {
		if ( ! is_array( $kept ) ) {
			return null;
		}
		$product    = $kept['product'] ?? null;
		$photo      = $kept['photo'] ?? null;
		$layer      = $kept['layer'] ?? null;
		$name       = $kept['name'] ?? null;
		$featured   = $kept['featured'] ?? null;
		$sales      = $kept['sales'] ?? null;
		$created    = $kept['created'] ?? null;
		$attributes = self::lists( $kept['attributes'] ?? null );
		$labels     = self::names( $kept['labels'] ?? null );
		if ( ! is_int( $product ) || ! is_int( $photo ) || ! is_int( $layer ) || ! is_string( $name ) || ! is_bool( $featured ) || ! is_int( $sales ) || ! is_int( $created ) || null === $attributes || null === $labels ) {
			return null;
		}
		return new LookFact( $product, $photo, $layer, $name, $attributes, $labels, $featured, $sales, $created );
	}

	/**
	 * A look read, as a fact.
	 *
	 * @param array{product: int, photo: int, layer: int, name: string, attributes: array<string, list<string>>, labels: array<string, array<string, string>>, featured: bool, sales: int, created: int} $look The look.
	 */
	private static function fact( array $look ): LookFact {
		return new LookFact( $look['product'], $look['photo'], $look['layer'], $look['name'], $look['attributes'], $look['labels'], $look['featured'], $look['sales'], $look['created'] );
	}

	/**
	 * Lists of strings by string key, or null when the value is not that.
	 *
	 * @param mixed $value The value.
	 * @return array<string, list<string>>|null
	 */
	private static function lists( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$lists = array();
		foreach ( $value as $key => $list ) {
			if ( ! is_string( $key ) || ! is_array( $list ) ) {
				return null;
			}
			foreach ( $list as $one ) {
				if ( ! is_string( $one ) ) {
					return null;
				}
				$lists[ $key ][] = $one;
			}
		}
		return $lists;
	}

	/**
	 * Names by slug, by taxonomy, or null when the value is not that.
	 *
	 * @param mixed $value The value.
	 * @return array<string, array<string, string>>|null
	 */
	private static function names( mixed $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$names = array();
		foreach ( $value as $key => $named ) {
			if ( ! is_string( $key ) || ! is_array( $named ) ) {
				return null;
			}
			foreach ( $named as $slug => $name ) {
				if ( ! is_string( $name ) ) {
					return null;
				}
				// PHP keeps a numeric slug, as a wheel's 52, as an integer key.
				$names[ $key ][ (string) $slug ] = $name;
			}
		}
		return $names;
	}
}
