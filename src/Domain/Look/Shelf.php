<?php
/**
 * A step's shelf.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotSize;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

use function array_filter;
use function array_flip;
use function array_keys;
use function array_map;
use function array_values;
use function array_slice;
use function count;
use function in_array;
use function rtrim;
use function strnatcasecmp;
use function usort;

/**
 * What a step lists, from the parts the engine offers it (SPEC-CONFIGURATOR, Finding a part):
 *
 * - one card per look (D-240), showing the pick where it is in the look, else the size the build takes, else the
 *   size the shopper's one size chip or the recommendation wants;
 * - on decks, the width question first, then the decks in the width asked (D-241);
 * - the search (D-248), then the chips (D-247), whose groups and counts follow the search;
 * - in stock first, the sold out in a fold at the end;
 * - in the order asked (D-243): by default the recommendation, then the shop's featured, then best sellers, then by
 *   name, or Creative Search's order for a query it ranked; or by the card's price, or newest;
 * - a page at a time (D-244).
 *
 * A part the shelf knows nothing of, as one whose product has gone since the index was read, is left off.
 */
final class Shelf {

	/**
	 * How many looks a page shows (D-244).
	 */
	public const int SHOWN = 30;

	/**
	 * The most looks a step lists, page by page: search and filters narrow past it (spec: 30 at a time). The build
	 * link's `_shown` stops at the same number (`BuildLink::MOST`), and the state route's `from` before it.
	 */
	public const int MOST = 500;

	/**
	 * The group the brand chips are in.
	 */
	public const string BRAND = 'brand';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param SizePicker $sizes Picks a look's size where the build decides it.
	 */
	public function __construct( private SizePicker $sizes ) {}

	/**
	 * A size as the chips and the route name it: `8.25in`, `54mm`.
	 *
	 * @since 1.0.0
	 *
	 * @param Measure $size The size.
	 */
	public static function named( Measure $size ): string {
		return rtrim( rtrim( $size->decimal(), '0' ), '.' ) . $size->unit->value;
	}

	/**
	 * The step's listing.
	 *
	 * @since 1.0.0
	 *
	 * @param Kit                 $kit         The kit.
	 * @param string              $slot        The step's slot.
	 * @param Picks               $build       The build so far.
	 * @param list<Option>        $offered     The parts the engine offers the step.
	 * @param Option|null         $picked      The step's pick, if it is offered.
	 * @param Option|null         $recommended The step's recommendation, if any.
	 * @param array<int, Shelved> $shelved     What the shelf knows of the parts, by ID.
	 * @param ShelfAsk            $ask         What the shopper asks.
	 */
	public function of( Kit $kit, string $slot, Picks $build, array $offered, ?Option $picked, ?Option $recommended, array $shelved, ShelfAsk $ask ): Listing {
		$spec     = SlotSize::spec( $slot );
		$variants = array();
		foreach ( $offered as $option ) {
			$known = $shelved[ $option->part->id ] ?? null;
			if ( null !== $known ) {
				$variants[] = new Variant( $option->part, $known->product, $known->photo, $known->layer, $known->choices );
			}
		}
		$looks = Looks::of( $variants );
		// Named against every look the step offers, so a look's name is the same whatever width is asked (D-273).
		$names = Looks::names( $looks );

		// The size is a chip only where the shopper picks it (D-247).
		$chip   = SizePicker::decides( $slot ) ? null : $spec;
		$always = null === $chip ? array( self::BRAND ) : array( self::BRAND, $chip->value );
		$facets = array();
		$words  = array();
		$own    = array();
		$first  = array();
		foreach ( $looks as $look ) {
			$held = array();
			// What orders a look is its product's, so any of its parts says it.
			$first[ $look->key ] = $shelved[ $look->variants[0]->part->id ];
			foreach ( $look->variants as $variant ) {
				$known  = $shelved[ $variant->part->id ];
				$values = $known->facets;
				if ( '' !== $variant->part->brand ) {
					$values[ self::BRAND ] = array( $variant->part->brand );
				}
				$size = null === $chip ? null : $variant->part->specs->measure( $chip );
				if ( null !== $size ) {
					$values[ $chip->value ] = array( self::named( $size ) );
				}
				foreach ( $values as $name => $list ) {
					foreach ( $list as $value ) {
						$held[ $name ][ $value ] = $value;
					}
				}
				$words[ $look->key ]                 = array( ...$words[ $look->key ] ?? array(), ...$known->words, ...$known->own );
				$own[ $look->product ][ $look->key ] = array( ...$own[ $look->product ][ $look->key ] ?? array(), ...$known->own );
			}
			$facets[ $look->key ] = new Facets( $look->key, array_map( array_values( ... ), $held ) );
		}

		// The search, then the chips on what it found.
		$keys   = array_keys( $facets );
		$ranked = null;
		if ( '' !== $ask->query ) {
			if ( null !== $ask->ranked ) {
				$keys   = Query::narrow( $ask->ranked, $own, $ask->query, $ask->corrected );
				$ranked = array_flip( $keys );
			} else {
				$keys = array_filter( $keys, static fn( string $key ): bool => Query::matches( $ask->query, $words[ $key ] ) );
			}
		}
		$found = array();
		foreach ( $keys as $key ) {
			$found[] = $facets[ $key ];
		}
		// A deck in another width is not on the step, so no chip counts it ("how many looks it leaves"). The search
		// still reads it, for "Rascals comes in 8.5″".
		$width   = SlotKey::DECK === $slot ? $ask->width : null;
		$counted = array_flip( array_map( static fn( Look $look ): string => $look->key, array_filter( $looks, static fn( Look $look ): bool => null === $width || in_array( self::named( $width ), array_map( self::named( ... ), $look->sizes( SpecKey::DeckWidth ) ), true ) ) ) );
		$groups  = Chips::groups( array_values( array_filter( $found, static fn( Facets $look ): bool => isset( $counted[ $look->key ] ) ) ), $always, $ask->chosen );
		$left    = array_flip( array_map( static fn( Facets $look ): string => $look->key, Chips::filter( $found, $ask->chosen ) ) );

		$wanted  = $this->wanted( $slot, $looks, $chip, $recommended, $ask );
		$inStock = array();
		$soldOut = array();
		$index   = array_flip( array_keys( $facets ) );
		foreach ( $looks as $look ) {
			if ( ! isset( $left[ $look->key ] ) ) {
				continue;
			}
			$held = null !== $picked && in_array( $picked->part, $look->parts(), true ) ? $picked : null;
			// A deck picked in another width than the one asked shows its look in that width, for the builder to move the
			// pick to; a look not made in it keeps the pick, so its card shows what the build holds (#81).
			$option = null !== $held && ( null === $width || true === $held->part->specs->measure( SpecKey::DeckWidth )?->equals( $width ) )
				? $held
				: $this->sizes->pick( $kit, $slot, $look->parts(), $build, $wanted ) ?? $held;
			if ( null === $option ) {
				continue;
			}
			$card = new ShelfLook( $look, $option, null === $spec ? array() : $look->sizes( $spec ), $names[ $look->key ] );
			if ( $option->available() ) {
				$inStock[] = $card;
			} else {
				$soldOut[] = $card;
			}
		}
		$order = $this->order( $ask, $ranked, $index, $first, $recommended );
		usort( $inStock, $order );
		usort( $soldOut, $order );

		// No page goes past the cap, so a reload, which keeps at most that many, shows what was paged.
		$capped    = array_slice( $inStock, 0, self::MOST );
		$next      = $ask->from + $ask->shown;
		$elsewhere = array();
		if ( SlotKey::DECK === $slot && null !== $spec && '' !== $ask->query && array() === $inStock ) {
			// Nothing in stock in the width asked: the widths its matches are in stock in, for "Rascals comes in 8.5″".
			$matched   = array_values( array_filter( $looks, static fn( Look $look ): bool => isset( $left[ $look->key ] ) ) );
			$elsewhere = array_values( array_filter( self::widths( $matched, $spec ), static fn( Width $width ): bool => $width->inStock > 0 ) );
		}
		return new Listing(
			array_slice( $capped, $ask->from, $ask->shown ),
			$soldOut,
			$groups,
			SlotKey::DECK === $slot && null !== $spec ? self::widths( $looks, $spec ) : array(),
			count( $looks ),
			count( $inStock ),
			$next < count( $capped ) ? $next : null,
			$elsewhere
		);
	}

	/**
	 * The size the cards show where the build doesn't decide it: on decks the width asked; elsewhere the one size
	 * chip chosen, else the recommendation's size.
	 *
	 * @param string       $slot        The slot.
	 * @param list<Look>   $looks       The looks.
	 * @param SpecKey|null $spec      The slot's size spec.
	 * @param Option|null  $recommended The recommendation.
	 * @param ShelfAsk     $ask         What the shopper asks.
	 */
	private function wanted( string $slot, array $looks, ?SpecKey $spec, ?Option $recommended, ShelfAsk $ask ): ?Measure {
		if ( SlotKey::DECK === $slot ) {
			return $ask->width;
		}
		if ( null === $spec ) {
			return null;
		}
		$chosen = $ask->chosen[ $spec->value ] ?? array();
		if ( 1 === count( $chosen ) ) {
			foreach ( $looks as $look ) {
				foreach ( $look->sizes( $spec ) as $size ) {
					if ( self::named( $size ) === $chosen[0] ) {
						return $size;
					}
				}
			}
		}
		return $recommended?->part->specs->measure( $spec );
	}

	/**
	 * How the cards are ordered.
	 *
	 * @param ShelfAsk                $ask         What the shopper asks.
	 * @param array<string, int>|null $ranked      Each look's place in Creative Search's order, for a query it ranked.
	 * @param array<string, int>      $index       Each look's place as the looks came.
	 * @param array<string, Shelved>  $first       What the shelf knows of each look's first part.
	 * @param Option|null             $recommended The recommendation.
	 * @return \Closure(ShelfLook, ShelfLook): int
	 */
	private function order( ShelfAsk $ask, ?array $ranked, array $index, array $first, ?Option $recommended ): \Closure {
		// Names in natural order, once, so that every place below is a number.
		$names = array_keys( $first );
		usort( $names, static fn( string $a, string $b ): int => strnatcasecmp( $first[ $a ]->name, $first[ $b ]->name ) );
		$named = array_flip( $names );
		$rank  = static function ( ShelfLook $card ) use ( $ask, $ranked, $index, $first, $named, $recommended ): array {
			$key   = $card->look->key;
			$known = $first[ $key ];
			$place = $ranked[ $key ] ?? $index[ $key ];
			return match ( $ask->sort ) {
				'price-asc'  => array( $card->option->part->price, $place ),
				'price-desc' => array( -$card->option->part->price, $place ),
				'newest'     => array( -$known->created, $place ),
				default      => null !== $ranked ? array( $place ) : array(
					null === $recommended || ! in_array( $recommended->part, $card->look->parts(), true ),
					! $known->featured,
					-$known->sales,
					$named[ $key ],
					$place,
				),
			};
		};
		return static fn( ShelfLook $one, ShelfLook $other ): int => $rank( $one ) <=> $rank( $other );
	}


	/**
	 * The deck widths the kit's decks come in, smallest first, each with how many looks are in stock in it.
	 *
	 * @param list<Look> $looks The looks.
	 * @param SpecKey    $spec  The deck's width.
	 * @return list<Width>
	 */
	private static function widths( array $looks, SpecKey $spec ): array {
		$sizes = array();
		$count = array();
		foreach ( $looks as $look ) {
			foreach ( $look->sizes( $spec ) as $size ) {
				$sizes[ self::named( $size ) ]   = $size;
				$count[ self::named( $size ) ] ??= 0;
			}
			// The widths it is in stock in, each once.
			$stocked = array();
			foreach ( $look->variants as $variant ) {
				if ( $variant->part->inStock ) {
					$stocked[] = $variant;
				}
			}
			$inStock = new Look( $look->key, $look->product, $stocked );
			foreach ( $inStock->sizes( $spec ) as $size ) {
				++$count[ self::named( $size ) ];
			}
		}
		$widths = array();
		foreach ( $sizes as $name => $size ) {
			$widths[] = new Width( $size, $count[ $name ] );
		}
		usort( $widths, static fn( Width $a, Width $b ): int => $a->width->compare( $b->width ) );
		return $widths;
	}
}
