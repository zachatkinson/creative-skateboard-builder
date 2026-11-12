<?php
/**
 * The shop's kits, from WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotSources;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PartFact;
use CreativeBranding\SkateboardBuilder\Port\PartFacts;
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
	 * The products whose rows are to be deleted when the request ends, by ID.
	 *
	 * @var array<int, int>
	 */
	private array $moved = array();

	/**
	 * Whether every row is to be deleted when the request ends.
	 *
	 * @var bool
	 */
	private bool $every = false;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Products        $products Loads products in batches.
	 * @param DocumentSchemas $schemas  The documents' schemas.
	 * @param SpecIndex       $index    The parts' specs.
	 * @param PartFacts       $facts    What is kept of each part.
	 * @param Clock           $clock    The time.
	 */
	public function __construct( private Products $products, private DocumentSchemas $schemas, private SpecIndex $index, private PartFacts $facts, private Clock $clock ) {}

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
	 * One kit, or null when the product is no kit or its documents do not read.
	 *
	 * @since 0.4.0
	 *
	 * @param int $id The kit product's ID.
	 */
	public function kit( int $id ): ?Kit {
		$product = $this->products->load( $id )[ $id ] ?? null;
		return null === $product ? null : $this->read( $product );
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
	 * The parts these sources find, read from the shop now: every product loaded. For the owner's own screens and
	 * checks, which must show what is there.
	 *
	 * @since 0.4.0
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<Part>
	 */
	public function parts( SlotSources $sources ): array {
		$found = $this->found( $sources );
		return $this->chosen( $sources, $found, $this->shopSays( $this->clock->now()->getTimestamp(), ...$found, ...$sources->products ) );
	}

	/**
	 * A slot's parts, from the parts table (D-116): a product is loaded only when the table holds
	 * no row for it in this visitor's price context, or the row is past its hour. Which products the sources find
	 * is asked of the shop each time, in a query that loads none.
	 *
	 * @since 0.4.0
	 *
	 * @param int  $kit  The kit product's ID.
	 * @param Slot $slot One of its slots.
	 * @return list<Part>
	 */
	public function offered( int $kit, Slot $slot ): array {
		unset( $kit );
		$sources = $slot->sources;
		$found   = $this->found( $sources );
		$ids     = array_values( array_unique( array( ...$found, ...$sources->products ) ) );
		if ( array() === $ids ) {
			return array();
		}
		$this->settle();
		$context = WooPartCards::context();
		$now     = $this->clock->now()->getTimestamp();
		$kept    = $this->facts->of( $context, ...$ids );
		$missing = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $kept[ $id ] ) || self::stale( $kept[ $id ], $now ) ) {
				$missing[ $id ] = $id;
			}
		}
		if ( array() !== $missing ) {
			$read = $this->shopSays( $now, ...array_values( $missing ) );
			$this->facts->replace( $context, array_values( $missing ), array_values( $read ) );
			// What was kept of them and of their variations gives way to what was read.
			$kept = array_filter( $kept, static fn( PartFact $fact ): bool => ! isset( $missing[ $fact->id ] ) && ! isset( $missing[ $fact->of ] ) ) + $read;
		}
		return $this->chosen( $sources, $found, $kept );
	}

	/**
	 * How long a row is believed: an hour, and up to an hour more by the part's ID, so that rows read together do
	 * not all go out of date together. What WooCommerce is told of is forgotten at once (`Hooks\CardHooks`); this
	 * is for what it is not told of.
	 *
	 * @param PartFact $fact A row.
	 * @param int      $now  The time, a UTC timestamp.
	 */
	private static function stale( PartFact $fact, int $now ): bool {
		return $now - $fact->readAt >= HOUR_IN_SECONDS + ( $fact->id % 60 ) * MINUTE_IN_SECONDS;
	}

	/**
	 * A build's parts as the shop has them now, read from WooCommerce and not from what is kept (issue #26), in
	 * one reading of the shop for them all (D-134).
	 *
	 * @since 0.5.0
	 *
	 * @param array<int|string, array{Slot, int}> $asked By any key: one of a kit's slots, and a product's or
	 *                                               variation's ID.
	 * @return array<int|string, Part> By the same keys; a key is left out when the ID is not one of its slot's parts.
	 */
	public function picked( array $asked ): array {
		$ids   = array_values( array_unique( array_column( $asked, 1 ) ) );
		$read  = array() === $ids ? array() : $this->shopSays( $this->clock->now()->getTimestamp(), ...$ids );
		$parts = array();
		foreach ( $asked as $key => [ $slot, $id ] ) {
			// The one product alone, with the slot's own rule for which of the shop's products are its parts.
			foreach ( $this->chosen( $slot->sources, $this->found( $slot->sources ), array( $id => $read[ $id ] ) ) as $part ) {
				if ( $id === $part->id ) {
					$parts[ $key ] = $part;
				}
			}
		}
		return $parts;
	}

	/**
	 * Forget what is kept of these products, or of every product when none is named.
	 *
	 * @since 0.4.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function forget( int ...$ids ): void {
		if ( array() === $ids ) {
			$this->every = true;
		}
		foreach ( $ids as $id ) {
			$this->moved[ $id ] = $id;
			// A variation the table has not met yet still changes what its parent's row answers for.
			$parent = Cast::int( wp_get_post_parent_id( $id ) );
			if ( $parent > 0 ) {
				$this->moved[ $parent ] = $parent;
			}
		}
		if ( false === has_action( 'shutdown', array( $this, 'settle' ) ) ) {
			add_action( 'shutdown', array( $this, 'settle' ), 10, 0 );
		}
	}

	/**
	 * Read these from the shop and keep their rows, in the price context of the request this runs in: a background
	 * job's, which has no shopper, so the shop's own language and the prices a visitor nothing is known of is shown
	 * (issue #28). A row is a product's, with its variations', so a variation stands for its parent. Only what
	 * some kit's slot could offer is read. What moved in this request is deleted first, for every context, so the
	 * rows written here are not deleted with it when the request ends.
	 *
	 * @since 0.6.0
	 *
	 * @param int ...$ids Products' and variations' IDs.
	 */
	public function warm( int ...$ids ): void {
		$owners = array();
		foreach ( $ids as $id ) {
			$parent                                = Cast::int( wp_get_post_parent_id( $id ) );
			$owners[ $parent > 0 ? $parent : $id ] = true;
		}
		$wanted = array();
		foreach ( $this->all() as $read ) {
			foreach ( $read['kit']->slots->all() as $slot ) {
				foreach ( array( ...$this->found( $slot->sources ), ...$slot->sources->products ) as $id ) {
					if ( isset( $owners[ $id ] ) ) {
						$wanted[ $id ] = $id;
					}
				}
			}
		}
		if ( array() === $wanted ) {
			return;
		}
		$this->settle();
		$wanted = array_values( $wanted );
		$this->facts->replace( WooPartCards::context(), $wanted, array_values( $this->shopSays( $this->clock->now()->getTimestamp(), ...$wanted ) ) );
	}

	/**
	 * Delete the rows of what moved in this request, in one go: a product's save tells of the same product a dozen
	 * times over, once for each taxonomy it sets, and a row is deleted once. Run when the request ends, and before
	 * this request reads a row.
	 *
	 * @since 0.4.0
	 */
	public function settle(): void {
		$moved       = array_values( $this->moved );
		$every       = $this->every;
		$this->moved = array();
		$this->every = false;
		if ( $every ) {
			$this->facts->forgetAll();
		} elseif ( array() !== $moved ) {
			$this->facts->forget( ...$moved );
		}
	}

	/**
	 * A term was edited or deleted. A brand's slug is in each row and a variation's attribute values are too;
	 * which products a category holds is asked of the shop each time, and needs nothing forgotten.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy The term's taxonomy.
	 */
	public function termMoved( string $taxonomy ): void {
		if ( self::BRAND === $taxonomy || str_starts_with( $taxonomy, 'pa_' ) ) {
			$this->forget();
		}
	}

	/**
	 * What the shop says now of these products and, for a variable product, of each of its variations: every one
	 * loaded. A product that is not there is said to be no part, so that it is not looked for on every request.
	 *
	 * @param int $now    The time, a UTC timestamp.
	 * @param int ...$ids Products' and variations' IDs.
	 * @return array<int, PartFact> By ID.
	 */
	private function shopSays( int $now, int ...$ids ): array {
		$products = $this->products->load( ...$ids );
		$children = array();
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$children = array_merge( $children, Cast::ints( $product->get_children() ) );
			}
		}
		$all     = $products + $this->products->load( ...$children );
		$parents = $this->products->parentsOf( $all ) + $all;
		$owners  = array();
		foreach ( $all as $id => $part ) {
			$owners[ $id ] = $part->is_type( 'variation' ) ? $part->get_parent_id() : $id;
		}
		$brands = $this->brands( ...array_values( $owners ) );
		$specs  = array() === $all ? array() : $this->index->specsOf( ...array_keys( $all ) );
		$facts  = array();
		foreach ( $ids as $id ) {
			$facts[ $id ] = new PartFact( $id, 0, false, '', 0, false, null, array(), array(), $now );
		}
		foreach ( $all as $id => $part ) {
			$variation = $part->is_type( 'variation' );
			$price     = self::minor( $part );
			$own       = array();
			foreach ( $variation ? Cast::map( $part->get_attributes() ) : array() as $taxonomy => $value ) {
				$own[ (string) $taxonomy ] = Cast::str( $value );
			}
			$facts[ $id ] = new PartFact(
				$id,
				$variation ? $part->get_parent_id() : 0,
				// A price below nothing, which a filter can give: no part.
				$price >= 0 && self::sellable( $part, $variation ? ( $parents[ $part->get_parent_id() ] ?? null ) : $part ),
				$brands[ $owners[ $id ] ] ?? '',
				max( 0, $price ),
				$part->is_in_stock(),
				self::stock( $part ),
				$own,
				isset( $specs[ $id ] ) ? $specs[ $id ]->toIndex() : array(),
				$now
			);
		}
		return $facts;
	}

	/**
	 * The parts among what is known of the products the sources find and name: each variable product's variations
	 * in its place, those of a found product held to the attribute values asked for, and only what a shopper
	 * could be shown and could buy.
	 *
	 * @param SlotSources          $sources The slot's sources.
	 * @param list<int>            $found   The products its categories, brands and attributes find.
	 * @param array<int, PartFact> $facts   What is known of them, of the products named and of their variations.
	 * @return list<Part>
	 */
	private function chosen( SlotSources $sources, array $found, array $facts ): array {
		$variations = array();
		foreach ( $facts as $fact ) {
			if ( $fact->of > 0 ) {
				$variations[ $fact->of ][] = $fact;
			}
		}
		$chosen = array();
		// What the sources find, and then what they name: a named product is not narrowed.
		foreach ( array( array( $found, $sources->attributes ), array( $sources->products, array() ) ) as [ $ids, $attributes ] ) {
			foreach ( $ids as $id ) {
				foreach ( $variations[ $id ] ?? array() as $variation ) {
					if ( self::holdsTo( $variation->attributes, $attributes ) ) {
						$chosen[ $variation->id ] = $variation;
					}
				}
				if ( isset( $facts[ $id ] ) ) {
					$chosen[ $id ] = $facts[ $id ];
				}
			}
		}
		$chosen = array_filter( $chosen, static fn( PartFact $fact ): bool => $fact->sellable );
		ksort( $chosen );
		$chosen = array_slice( $chosen, 0, self::MAX_PARTS, true );
		$parts  = array();
		foreach ( $chosen as $id => $fact ) {
			$parts[] = new Part( $id, $fact->brand, Specs::fromIndex( $fact->specs ), $fact->price, $fact->inStock, $fact->stock );
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
	 * The products in the sources' categories, of their brands and with their attribute values, whatever their
	 * status: whether one may be sold is said of each product itself. A category holds what its sub-categories
	 * hold. One read of the term relationships for each taxonomy, which loads no product and no post (D-116).
	 *
	 * @param SlotSources $sources A slot's sources.
	 * @return list<int>
	 */
	private function found( SlotSources $sources ): array {
		$found = null;
		foreach ( self::narrowing( $sources ) as $one ) {
			if ( ! taxonomy_exists( $one['taxonomy'] ) ) {
				return array();
			}
			$terms = $one['terms'];
			if ( is_taxonomy_hierarchical( $one['taxonomy'] ) ) {
				foreach ( $one['terms'] as $term ) {
					$terms = array_merge( $terms, Cast::ints( get_term_children( $term, $one['taxonomy'] ) ) );
				}
			}
			$in    = Cast::ints( get_objects_in_term( array_values( array_unique( $terms ) ), $one['taxonomy'] ) );
			$found = null === $found ? $in : array_intersect( $found, $in );
		}
		$found = array_values( array_unique( $found ?? array() ) );
		sort( $found );
		return array_slice( $found, 0, self::MAX_PARTS );
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
		$own   = array();
		foreach ( $part->is_type( 'variation' ) ? Cast::map( $part->get_attributes() ) : array() as $taxonomy => $value ) {
			$own[ (string) $taxonomy ] = Cast::str( $value );
		}
		$terms = array();
		$keys  = array();
		foreach ( $kits as $kit ) {
			foreach ( $kit->slots->all() as $slot ) {
				$sources = $slot->sources;
				$narrow  = self::narrowing( $sources );
				$takes   = array() !== $narrow && self::holdsTo( $own, $sources->attributes );
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
	 * Whether a variation's own values are among those asked for. One that takes any value of an attribute is, and
	 * so is a product that is no variation, which has no values of its own.
	 *
	 * @param array<string, string>                           $own        The variation's own values, by taxonomy.
	 * @param list<array{taxonomy: string, terms: list<int>}> $attributes The attribute values asked for.
	 */
	private static function holdsTo( array $own, array $attributes ): bool {
		foreach ( $attributes as $attribute ) {
			$slug = $own[ $attribute['taxonomy'] ] ?? '';
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
	private static function sellable( WC_Product $part, ?WC_Product $owner ): bool {
		return null !== $owner
			&& ! in_array( $part->get_type(), array( 'variable', Options::PRODUCT_TYPE_KIT ), true )
			&& 'publish' === $part->get_status()
			&& 'publish' === $owner->get_status()
			&& 'hidden' !== $owner->get_catalog_visibility()
			&& '' === $owner->get_post_password()
			&& $part->is_purchasable()
			&& self::available( $part );
	}

	/**
	 * Whether no plugin rules the part out (spec: Pricing, stock, assembly and returns).
	 *
	 * @param WC_Product $part A part a shopper could be shown and could buy.
	 */
	private static function available( WC_Product $part ): bool {
		/**
		 * Filter whether a product or a variation may be a part of a build. A plugin that knows something of a part
		 * WooCommerce does not, such as stock held elsewhere, rules it out here, and the builder then neither offers
		 * nor sells it in any kit. It is asked only of a part a shopper could be shown and could buy, and anything but
		 * false leaves the part in.
		 *
		 * @since 0.4.0
		 *
		 * @param bool       $available Whether the part may be used. Default true.
		 * @param int        $id        The product's or the variation's ID.
		 * @param WC_Product $part      The product or the variation.
		 */
		return false !== apply_filters( 'cbuild_part_available', true, $part->get_id(), $part );
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
