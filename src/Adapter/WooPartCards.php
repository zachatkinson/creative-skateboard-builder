<?php
/**
 * Parts' cards, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
use WC_Tax;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads what a card shows from the product: its name, its page, its price as the shop shows one
 * (`wc_get_price_to_display()` through `wc_price()`), its availability in the shop's own words
 * (`WC_Product::get_availability()`, which follows the shop's stock display format) and its photo at the catalogue's
 * size. A variation with no photo of its own shows its parent's. The photo has no words of its own: the card's name
 * stands beside it.
 *
 * Each part's card is kept in the object cache for an hour at most (docs/STANDARDS.md, Object cache). An entry holds
 * the last change of the part and of its parent, and a card for each price context it was asked in; it is read again
 * when either was saved since, when it holds no card for this context, and when a hook says the part's price or
 * stock moved (`Hooks\CardHooks`). The posts are primed in one query, and the products that missed in one more.
 *
 * @since 0.4.0
 */
final class WooPartCards implements PartCards {

	/**
	 * How many price contexts one entry holds; the oldest goes first.
	 */
	public const int CONTEXTS = 4;

	/**
	 * The most variations forgotten with their parents in one go. Past it, a variation's card is read again when its
	 * hour ends.
	 */
	private const int MOST_VARIATIONS = 1000;

	/**
	 * The size a card's photo is asked for in.
	 */
	private const string SIZE = 'woocommerce_thumbnail';

	/**
	 * A layer image's size on a card: WordPress's, which is never cropped, as the shop's thumbnail may be. A deck's
	 * cut-out is tall and narrow, and a square crop showed its middle alone (D-282).
	 */
	private const string LAYER_SIZE = 'medium';

	/**
	 * The post types a part has.
	 */
	private const array TYPES = array( 'product', 'product_variation' );

	/**
	 * The products whose cards are to be forgotten when the request ends, by ID.
	 *
	 * @var array<int, int>
	 */
	private array $moved = array();

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Products $products Loads products in one query.
	 */
	public function __construct( private Products $products ) {}

	/**
	 * The cards of these parts, by ID, in the order asked.
	 *
	 * @since 0.4.0
	 *
	 * @param list<int> $ids Parts' IDs.
	 * @return array<int, array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, photo: bool}>
	 */
	public function cards( array $ids ): array {
		$ids = array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
		if ( array() === $ids ) {
			return array();
		}
		$this->settle();
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

		$context = self::context();
		$cards   = array();
		$stamps  = array();
		$others  = array();
		foreach ( $posts as $id => $post ) {
			$stamps[ $id ] = self::stamp( $post );
			$entry         = wp_cache_get( self::key( $id ), Options::CACHE_GROUP );
			$kept          = is_array( $entry ) && ( $entry['stamp'] ?? null ) === $stamps[ $id ] && is_array( $entry['cards'] ?? null ) ? $entry['cards'] : array();
			$card          = self::read( $kept[ $context ] ?? null );
			if ( null === $card ) {
				unset( $kept[ $context ] );
				$others[ $id ] = $kept;
				continue;
			}
			$cards[ $id ] = $card;
		}

		if ( array() !== $others ) {
			$missed = $this->products->load( ...array_keys( $others ) );
			$images = array();
			foreach ( $missed as $product ) {
				// The photo, else the layer image the card falls back to (D-282), read with the rest in one query.
				$photo = Cast::int( $product->get_image_id() );
				$photo = $photo > 0 ? $photo : self::layer( $product );
				if ( $photo > 0 ) {
					$images[] = $photo;
				}
			}
			_prime_post_caches( array_values( array_unique( $images ) ), false, true );
			foreach ( $missed as $id => $product ) {
				$cards[ $id ] = self::card( $product );
				wp_cache_set(
					self::key( $id ),
					array(
						'stamp' => $stamps[ $id ],
						// This context's card first, then as many of the others as an entry holds.
						'cards' => array_slice( array( $context => $cards[ $id ] ) + $others[ $id ], 0, self::CONTEXTS, true ),
					),
					Options::CACHE_GROUP,
					HOUR_IN_SECONDS
				);
			}
		}

		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $cards[ $id ] ) ) {
				$ordered[ $id ] = $cards[ $id ];
			}
		}
		return $ordered;
	}

	/**
	 * The shop's names for these brands, by slug: one query, of WooCommerce's brand taxonomy, whose terms WordPress
	 * keeps.
	 *
	 * @since 0.6.0
	 *
	 * @param list<string> $slugs Brands' slugs.
	 * @return array<string, string>
	 */
	public function brands( array $slugs ): array {
		$slugs = array_values( array_unique( array_filter( $slugs, static fn( string $slug ): bool => '' !== $slug ) ) );
		if ( array() === $slugs ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'               => 'product_brand',
				'slug'                   => $slugs,
				'hide_empty'             => false,
				// The names alone are read: no order, and none of the terms' meta. Nor WooCommerce's filter of the
				// terms a shopper's page lists, which counts each term's products and writes a transient: the
				// argument is the one get_term_by() passes (wp-includes/taxonomy.php:1129, :1364).
				'orderby'                => 'none',
				'update_term_meta_cache' => false,
				'suppress_filter'        => true,
			)
		);
		$names = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof WP_Term ) {
				// A term's name is stored with its entities written out, as a title is.
				$names[ $term->slug ] = html_entity_decode( wp_strip_all_tags( $term->name ), ENT_QUOTES, 'UTF-8' );
			}
		}
		return $names;
	}

	/**
	 * Forget what is kept of these products and of their variations.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void {
		foreach ( $ids as $id ) {
			if ( $id > 0 ) {
				$this->moved[ $id ] = $id;
			}
		}
		if ( array() !== $this->moved && false === has_action( 'shutdown', array( $this, 'settle' ) ) ) {
			add_action( 'shutdown', array( $this, 'settle' ), 10, 0 );
		}
	}

	/**
	 * Forget the cards of what moved in this request, in one go: a product's save tells of it several times, and
	 * its variations are looked for once. Run when the request ends, and before this request reads a card.
	 *
	 * @since 0.4.0
	 */
	public function settle(): void {
		$ids         = array_values( $this->moved );
		$this->moved = array();
		if ( array() === $ids ) {
			return;
		}
		// A variation's card shows its parent's name, and its parent's photo and stock where it has none of its own.
		$children = new WP_Query(
			array(
				'post_parent__in' => $ids,
				'post_type'       => 'product_variation',
				'post_status'     => 'any',
				'fields'          => 'ids',
				'posts_per_page'  => self::MOST_VARIATIONS,
				'orderby'         => 'none',
				'no_found_rows'   => true,
			)
		);
		foreach ( array_merge( $ids, Cast::ints( $children->posts ) ) as $id ) {
			wp_cache_delete( self::key( $id ), Options::CACHE_GROUP );
			// Its look is kept beside its card, against the same stamp (D-263).
			wp_cache_delete( WooPartLooks::key( $id ), Options::CACHE_GROUP );
		}
	}

	/**
	 * The entry's key for a part.
	 *
	 * @param int $id The part's ID.
	 */
	private static function key( int $id ): string {
		return 'card_' . $id;
	}

	/**
	 * When the part and its parent were last saved: what a part's kept card, and its kept look, are checked against.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post The part's post.
	 */
	public static function stamp( WP_Post $post ): string {
		$parent = $post->post_parent > 0 ? get_post( $post->post_parent ) : null;
		return $post->post_modified_gmt . '|' . ( $parent instanceof WP_Post ? $parent->post_modified_gmt : '' );
	}

	/**
	 * Whether a price the shop shows can depend on where the shopper is: it is shown with tax, or it is entered
	 * with tax and a shop's code has said the shop's own rates are not what comes off it
	 * (`woocommerce_adjust_non_base_location_prices`, includes/wc-product-functions.php:1575). A shop that enters
	 * and shows prices without tax shows every shopper the same price, whatever its rates.
	 *
	 * @since 0.5.0
	 */
	public static function byAddress(): bool {
		if ( ! wc_tax_enabled() ) {
			return false;
		}
		if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
			return true;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, asked as it asks it.
		return wc_prices_include_tax() && true !== apply_filters( 'woocommerce_adjust_non_base_location_prices', true );
	}

	/**
	 * What a price and its words depend on beside the part: the currency and its decimals, the language, whether
	 * prices are shown with tax, and the tax rates a shown price is worked out with. Those are the rates where the
	 * visitor is only where a shown price can depend on that (`byAddress()`), and the shop's own otherwise, so a
	 * shop that shows prices without tax keeps one context however many rates it has. The rates stand in for where
	 * the visitor is, so no address is kept, and visitors charged the same rates share what is kept.
	 *
	 * @since 0.4.0
	 */
	public static function context(): string {
		$rates = array();
		if ( wc_tax_enabled() ) {
			$there = self::byAddress();
			foreach ( array_merge( array( '' ), WC_Tax::get_tax_class_slugs() ) as $class ) {
				$rates[ $class ] = $there ? WC_Tax::get_rates( $class ) : WC_Tax::get_base_tax_rates( $class );
			}
		}
		$context = array(
			get_woocommerce_currency(),
			wc_get_price_decimals(),
			determine_locale(),
			get_option( 'woocommerce_tax_display_shop' ),
			wc_prices_include_tax(),
			$rates,
		);
		/**
		 * Filter what a part's price depends on beside the part itself. A card is kept for each context a part is
		 * asked in, and so is each part's row of the parts table, so a plugin that shows some shoppers another price, a
		 * dealer's for one, adds what tells them apart: a role, a price list's ID. Add only what the price depends
		 * on, since each new value is another copy to keep, and never anything that names a person. An answer that
		 * is no array leaves the context as it is.
		 *
		 * @since 0.4.0
		 *
		 * @param array<mixed> $context The currency, its decimals, the language, the tax display, whether prices are
		 *                              entered with tax, and the tax rates that apply.
		 */
		$said = apply_filters( 'cbuild_price_context', $context );
		return md5( (string) wp_json_encode( is_array( $said ) ? $said : $context ) );
	}

	/**
	 * An image from the media library as a card's photo is, or null when it is none.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment The attachment's ID.
	 * @return array{src: string, width: int, height: int, srcset: string, sizes: string}|null
	 */
	public function photo( int $attachment ): ?array {
		return self::image( $attachment );
	}

	/**
	 * An attachment at the cards' size, or null.
	 *
	 * @param int    $photo The attachment's ID, or 0.
	 * @param string $size  The image size: the shop's thumbnail, or a layer image's.
	 * @return array{src: string, width: int, height: int, srcset: string, sizes: string}|null
	 */
	private static function image( int $photo, string $size = self::SIZE ): ?array {
		$source = $photo > 0 ? wp_get_attachment_image_src( $photo, $size ) : false;
		if ( ! is_array( $source ) ) {
			return null;
		}
		return array(
			'src'    => $source[0],
			'width'  => $source[1],
			'height' => $source[2],
			'srcset' => (string) wp_get_attachment_image_srcset( $photo, $size ),
			'sizes'  => (string) wp_get_attachment_image_sizes( $photo, $size ),
		);
	}

	/**
	 * A part's layer image, for a card with no photo (D-282): a variation's own, else its parent's, as the picture
	 * reads it.
	 *
	 * @param WC_Product $product The part.
	 */
	private static function layer( WC_Product $product ): int {
		$own = Cast::int( $product->get_meta( Options::META_LAYER, true ) );
		if ( $own > 0 || 0 === $product->get_parent_id() ) {
			return $own;
		}
		$parent = wc_get_product( $product->get_parent_id() );
		return $parent instanceof WC_Product ? Cast::int( $parent->get_meta( Options::META_LAYER, true ) ) : 0;
	}

	/**
	 * A product's card.
	 *
	 * @param WC_Product $product The part.
	 * @return array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, photo: bool}
	 */
	private static function card( WC_Product $product ): array {
		$availability = $product->get_availability();
		$photo        = self::image( Cast::int( $product->get_image_id() ) );
		$image        = $photo ?? self::image( self::layer( $product ), self::LAYER_SIZE );
		return array(
			// WordPress stores a title with its entities written out. A card's name is plain text, which the edge escapes.
			'name'        => html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' ),
			'url'         => $product->get_permalink(),
			'price'       => ShopMoney::plain( wc_price( Cast::float( wc_get_price_to_display( $product ) ) ) ),
			'stock'       => $availability['availability'],
			'stock_class' => $availability['class'],
			'image'       => $image,
			// Whether the image is the part's photo, not its layer image: a share card wants the photo (D-282).
			'photo'       => null !== $photo,
		);
	}

	/**
	 * A kept card, when it is one: a cache can hand back anything.
	 *
	 * @param mixed $kept What the entry holds for a context.
	 * @return array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null, photo: bool}|null
	 */
	private static function read( mixed $kept ): ?array {
		if ( ! is_array( $kept ) ) {
			return null;
		}
		$image = $kept['image'] ?? null;
		foreach ( array( 'name', 'url', 'price', 'stock', 'stock_class' ) as $text ) {
			if ( ! is_string( $kept[ $text ] ?? null ) ) {
				return null;
			}
		}
		// A card kept before 1.0.0 has no `photo`, and is read again with its layer image.
		if ( ! is_bool( $kept['photo'] ?? null ) ) {
			return null;
		}
		if ( null !== $image && ! ( is_array( $image ) && is_string( $image['src'] ?? null ) && is_int( $image['width'] ?? null ) && is_int( $image['height'] ?? null ) && is_string( $image['srcset'] ?? null ) && is_string( $image['sizes'] ?? null ) ) ) {
			return null;
		}
		return array(
			'name'        => $kept['name'],
			'url'         => $kept['url'],
			'price'       => $kept['price'],
			'stock'       => $kept['stock'],
			'stock_class' => $kept['stock_class'],
			'image'       => null === $image ? null : array(
				'src'    => $image['src'],
				'width'  => $image['width'],
				'height' => $image['height'],
				'srcset' => $image['srcset'],
				'sizes'  => $image['sizes'],
			),
			'photo'       => $kept['photo'],
		);
	}
}
