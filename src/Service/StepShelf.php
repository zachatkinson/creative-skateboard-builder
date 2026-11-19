<?php
/**
 * A step's shelf, from the shop.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotSize;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfAsk;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelved;
use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;
use CreativeBranding\SkateboardBuilder\Port\Ranking;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\TextFolding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the shopper's step with the domain's shelf (`Domain\Look\Shelf`, D-264), from what the shop holds of each
 * part offered (D-263):
 *
 * - its product, photo and layer image, and its global attributes, but the one the shop mapped to the slot's size:
 *   that is the size, never a choice or a chip of its own;
 * - its words for the search: its brand's name, its product's name and its size; and its look's own, its values'
 *   names; each folded;
 * - for a query, Creative Search's ranking where it answers and the kit's Search setting allows it (D-248).
 *
 * @since 1.0.0
 */
final class StepShelf {

	/**
	 * The domain's shelf.
	 *
	 * @var Shelf
	 */
	private Shelf $shelf;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param PartLooks    $looks    What makes each part's look.
	 * @param PartCards    $cards    The brands' names.
	 * @param Ranking      $ranking  A search engine's ranking.
	 * @param TextFolding  $folding  How search folds text.
	 * @param ShopSettings $settings The attribute mapping.
	 */
	public function __construct( private PartLooks $looks, private PartCards $cards, private Ranking $ranking, private TextFolding $folding, private ShopSettings $settings ) {
		$this->shelf = new Shelf( new SizePicker( FitEngine::standard() ) );
	}

	/**
	 * The listing of the state's step and the names its values need, or null on the review, which lists no parts.
	 *
	 * @since 1.0.0
	 *
	 * @param State      $state The build's state.
	 * @param ShelfQuery $query What the request asks.
	 */
	public function of( State $state, ShelfQuery $query ): ?ShelfView {
		$current = null;
		$build   = Picks::none();
		foreach ( $state->steps as $step ) {
			if ( $step->slot->key === $state->step ) {
				$current = $step;
			}
			$counts = $step->counted();
			if ( null !== $counts ) {
				$build = $build->with( $step->slot->key, $counts->part );
			}
		}
		if ( null === $current ) {
			return null;
		}
		$slot    = $current->slot->key;
		$offered = $current->options->offered;
		$ids     = array_map( static fn( $option ): int => $option->part->id, $offered );
		$facts   = $this->looks->looks( $ids );

		$brands = array();
		foreach ( $offered as $option ) {
			if ( '' !== $option->part->brand ) {
				$brands[] = $option->part->brand;
			}
		}
		$brands = array() === $brands ? array() : $this->cards->brands( array_values( array_unique( $brands ) ) );

		// The attribute the shop mapped to the slot's size is the size, not a choice.
		$spec  = SlotSize::spec( $slot );
		$sized = array();
		foreach ( $this->settings->attributeMapping() as $map ) {
			if ( $map->spec === $spec ) {
				$sized[] = $map->attribute;
			}
		}

		$shelved = array();
		$names   = array();
		$labels  = array( Shelf::BRAND => $brands );
		$sizes   = array();
		foreach ( $offered as $option ) {
			$fact = $facts[ $option->part->id ] ?? null;
			if ( null === $fact ) {
				continue;
			}
			$attributes = array_diff_key( $fact->attributes, array_flip( $sized ) );
			$choices    = array();
			$own        = array();
			foreach ( $attributes as $taxonomy => $values ) {
				if ( 1 === count( $values ) ) {
					$choices[ $taxonomy ] = $values[0];
				}
				foreach ( $values as $value ) {
					$label                         = $fact->labels[ $taxonomy ][ $value ] ?? $value;
					$labels[ $taxonomy ][ $value ] = $label;
					$own[]                         = $this->folding->fold( $label );
				}
			}
			$brand = $option->part->brand;
			$words = array( $this->folding->fold( $brands[ $brand ] ?? $brand ), $this->folding->fold( $fact->name ) );
			$size  = null === $spec ? null : $option->part->specs->measure( $spec );
			if ( null !== $size ) {
				$words[]                        = Shelf::named( $size );
				$sizes[ Shelf::named( $size ) ] = $size;
			}
			$names[ $fact->product ]      = $fact->name;
			$shelved[ $option->part->id ] = new Shelved( $fact->product, $fact->photo, $fact->layer, $choices, $attributes, $words, $own, $fact->featured, $fact->sales, $fact->created, $fact->name );
		}

		$text      = mb_substr( trim( $query->query ), 0, ShelfQuery::QUERY );
		$ranked    = null;
		$corrected = null;
		if ( '' !== $text && $query->ranked ) {
			$ranking = $this->ranking->ranked( $text, $ids );
			if ( null !== $ranking ) {
				$ranked    = $ranking['ids'];
				$corrected = null === $ranking['corrected'] ? null : $this->folding->fold( $ranking['corrected'] );
			}
		}
		// A deck the build holds brings its width, as a preset, a link or a saved build does, and skips the question.
		$held    = SlotKey::DECK === $slot ? $current->picked?->part->specs->measure( SpecKey::DeckWidth ) : null;
		$ask     = new ShelfAsk(
			self::width( $query->width ) ?? $held,
			$query->chips,
			$this->folding->fold( $text ),
			$ranked,
			$corrected,
			$query->sort,
			max( 0, $query->from ),
			max( 1, $query->shown )
		);
		$width   = null === $ask->width ? '' : Shelf::named( $ask->width );
		$listing = $this->shelf->of( $state->kit, $slot, $build, $offered, $current->picked, $current->recommended, $shelved, $ask );
		$named   = array();
		foreach ( $listing->groups as $group ) {
			if ( isset( $labels[ $group->name ] ) && Shelf::BRAND !== $group->name ) {
				$named[] = $group->name;
			}
		}
		return new ShelfView( $listing, $labels, $sizes, $text, in_array( $query->sort, ShelfAsk::SORTS, true ) ? $query->sort : ShelfAsk::SORTS[0], $width, $this->looks->attributes( $named ), $names );
	}

	/**
	 * A width as a size is named, `8.25in` or `216mm`, or null when it is not one.
	 *
	 * @param string $named The width.
	 */
	private static function width( string $named ): ?Measure {
		if ( 1 !== preg_match( '/\A([0-9]{1,3}(?:\.[0-9]{1,4})?)(in|mm)\z/', $named, $parts ) ) {
			return null;
		}
		try {
			return MeasureParser::parse( $parts[1], Unit::from( $parts[2] ) );
		} catch ( InvalidMeasure ) {
			return null;
		}
	}
}
