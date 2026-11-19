<?php
/**
 * A build's state, as the state route answers it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SizePicker;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfLook;
use CreativeBranding\SkateboardBuilder\Domain\Look\Width;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Service\Reopened;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
use CreativeBranding\SkateboardBuilder\Service\ShelfView;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StateStep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the state the service worked out into the document `schema/state.json` describes: the codes with their
 * sentences beside them, the amounts with the shop's way of writing them, and a card for each part on show. It
 * decides nothing of fit or price. What it does decide is what is on show:
 *
 * - **One step's options.** Every step says how many parts it offers and hides and why; only the step the shopper is
 *   on lists them.
 * - **24 at a time** (spec: Performance), of those offered and of those hidden, and 24 more each time the address
 *   asks. Parts the shop can supply come before those it cannot, each in the engine's own order, and the part the
 *   step counts is always among them.
 * - **The step's filters** (spec: A step): the brands and the sizes among the parts offered, and those alone listed
 *   where the address names one.
 * - **The owner's own words through their filters:** `cbuild_slot_label`, `cbuild_preset_label` and `cbuild_kit_text`.
 * - **Cards for those and no others,** asked for in one call: the options listed, the parts the total counts, and the
 *   parts a change list names.
 * - **What is in the box** (spec: The picture): each part the total counts, labelled, and the build in a sentence.
 * - **What others add under the total:** the delivery line's two filters, and `cbuild_summary_notes`.
 *
 * @since 0.4.0
 */
final class StateAnswer {

	/**
	 * How many options a step lists.
	 *
	 * @since 0.4.0
	 */
	public const int SHOWN = 24;

	/**
	 * How many lines other plugins may put under the total.
	 *
	 * @since 0.6.0
	 */
	public const int NOTES = 6;

	/**
	 * The spec a slot's parts are sized by, as a shopper names a size: a deck by its width, wheels by their
	 * diameter. A slot with a key of the owner's own has none.
	 */
	private const array SIZES = array(
		SlotKey::DECK     => SpecKey::DeckWidth,
		SlotKey::TRUCKS   => SpecKey::AxleWidth,
		SlotKey::WHEELS   => SpecKey::WheelDiameter,
		SlotKey::RISERS   => SpecKey::RiserThickness,
		SlotKey::HARDWARE => SpecKey::BoltLength,
	);

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 * @since 0.6.0 Takes what the shop says of delivery.
	 *
	 * @param PartCards    $cards    What a card shows of a part.
	 * @param Money        $money    Writes an amount as the shop does.
	 * @param Kits         $kits     The shop's kits, for the currency's decimals.
	 * @param Delivery     $delivery What the shop says of a build's delivery.
	 * @param PageRenderer $renderer Draws the layout for the page.
	 */
	public function __construct(
		private PartCards $cards,
		private Money $money,
		private Kits $kits,
		private Delivery $delivery,
		private PageRenderer $renderer
	) {}

	/**
	 * The drawn parts' layer images, since 0.7.0, given by the container through `withArtwork()`. An answer that an
	 * earlier release's container builds, in the request that replaces that release's files with these, has none, and
	 * draws the board with no layers and each part's labelled photo beside it (D-208).
	 *
	 * @var LayerArtwork|null
	 */
	private ?LayerArtwork $artwork = null;

	/**
	 * The step's shelf, since 1.0.0, given by the container through `withShelf()`. An answer an earlier release's
	 * container builds has none, and says null for it, as on the review.
	 *
	 * @var StepShelf|null
	 */
	private ?StepShelf $shelf = null;

	/**
	 * A preset's tile picture by kit and picks, since 1.0.0, given by the container through `withPictures()`: its
	 * address once made, or null. An answer an earlier release's container builds has none, and shows each preset's
	 * first part's photo (D-208, D-252).
	 *
	 * @var (Closure(int, array<string, int>): ?string)|null
	 */
	private ?Closure $pictures = null;

	/**
	 * The same answer, with the presets' tile pictures (D-252).
	 *
	 * @since 1.0.0
	 *
	 * @param Closure(int, array<string, int>): ?string $pictures A preset's picture's address, or null until made.
	 */
	public function withPictures( Closure $pictures ): self {
		$answer           = clone $this;
		$answer->pictures = $pictures;
		return $answer;
	}

	/**
	 * The same answer, with the step's shelf (D-264).
	 *
	 * @since 1.0.0
	 *
	 * @param StepShelf $shelf The step's shelf.
	 */
	public function withShelf( StepShelf $shelf ): self {
		$answer        = clone $this;
		$answer->shelf = $shelf;
		return $answer;
	}

	/**
	 * The same answer, drawing the parts from their layer images.
	 *
	 * @since 0.7.0
	 *
	 * @param LayerArtwork $artwork The drawn parts' layer images.
	 */
	public function withArtwork( LayerArtwork $artwork ): self {
		$answer          = clone $this;
		$answer->artwork = $artwork;
		return $answer;
	}

	/**
	 * The answer.
	 *
	 * @since 0.4.0
	 * @since 0.6.0 Takes the address, for the step's filters and how many options it lists.
	 *
	 * @since 1.0.0 Takes what the route asks of the shelf, which comes before the link's.
	 *
	 * @param State           $state The build's state.
	 * @param BuildLink|null  $link  The address the build was asked at, or null for one that says nothing.
	 * @param ShelfQuery|null $query What the route asks of the step's shelf, or null for the link's.
	 * @return array<string, mixed>
	 */
	public function of( State $state, ?BuildLink $link = null, ?ShelfQuery $query = null ): array {
		$link  ??= new BuildLink();
		$view    = $this->shelf?->of( $state, $query ?? self::page( $link ) );
		$labels  = array();
		$current = null;
		$counted = $state->counted();
		foreach ( $state->steps as $step ) {
			$labels[ $step->slot->key ] = self::label( $step->slot->label, $step->slot->key, $state->id );
			if ( $step->slot->key === $state->step ) {
				$current = $step;
			}
		}
		$page     = max( self::SHOWN, $link->shown );
		$all      = null === $current ? array() : $current->options->offered;
		$matching = array();
		foreach ( $all as $shown ) {
			$size = self::size( $state->step, $shown->part )['value'] ?? null;
			if ( in_array( $link->brand, array( '', $shown->part->brand ), true ) && in_array( $link->size, array( '', $size ), true ) ) {
				$matching[] = $shown;
			}
		}
		// The part the step counts is one of those offered, or a pick that is hidden: it is listed with its own kind.
		$counts  = $current?->counted();
		$kept    = null === $current || null === $counts ? null : $current->options->offer( $counts->part->id );
		$offered = self::shown( $matching, $kept, $page );
		$hidden  = null === $current ? array() : self::shown( $current->options->hidden, null === $kept ? $counts : null, $page );
		$more    = count( $matching ) > $page && $page < BuildLink::MOST ? min( BuildLink::MOST, $page + self::SHOWN ) : null;

		// The brands' names, in one call: those of the step's parts, for its filter, and of the parts the total counts.
		$slugs = array();
		foreach ( $all as $shown ) {
			$slugs[] = $shown->part->brand;
		}
		foreach ( $counted as $part ) {
			$slugs[] = $part->brand;
		}
		$slugs  = array_values( array_diff( array_unique( $slugs ), array( '' ) ) );
		$brands = array() === $slugs ? array() : $this->cards->brands( $slugs );

		// Every part the answer names, for one call to the cards.
		$ids = array();
		foreach ( array( $offered, $hidden ) as $list ) {
			foreach ( $list as $option ) {
				$ids[] = $option->part->id;
			}
		}
		foreach ( $state->quote->lines as $line ) {
			$ids[] = $line->part;
		}
		foreach ( null === $view ? array() : array_merge( $view->listing->looks, $view->listing->soldOut ) as $look ) {
			$ids[] = $look->option->part->id;
		}
		foreach ( $state->kit->presets as $preset ) {
			// Its first slot's part, for the tile's picture.
			$first = array_values( $preset->picks )[0] ?? null;
			if ( null !== $first ) {
				$ids[] = $first;
			}
		}
		foreach ( $state->presets as $preset ) {
			foreach ( array_merge( $preset->refused, $preset->validation->problems ) as $kept ) {
				if ( null !== $kept->part ) {
					$ids[] = $kept->part;
				}
			}
		}
		foreach ( $state->changes->changes ?? array() as $change ) {
			$ids[] = $change->was->id;
			if ( null !== $change->closest ) {
				$ids[] = $change->closest->part->id;
			}
		}
		$cards    = $this->cards->cards( array_values( array_unique( $ids ) ) );
		$decimals = $this->kits->decimals();
		$money    = fn( int $amount ): array => array(
			'amount' => $amount,
			'text'   => $this->money->text( $amount, $decimals ),
		);
		$delta    = function ( Option $option, ?StateStep $step ) use ( $state, $decimals ): array {
			$change = Pricer::change( $option, $step?->counted(), $state->discount );
			$amount = $change ?? 0;
			$kind   = 'none';
			if ( null !== $change ) {
				$kind = $amount < 0 ? 'less' : ( 0 === $amount ? 'free' : 'more' );
				if ( $option->part->id === $step?->counted()?->part->id ) {
					$kind = 'included';
				}
			}
			$text = $this->money->text( abs( $amount ), $decimals );
			return array(
				'kind'   => $kind,
				'amount' => $amount,
				'text'   => Words::delta( $kind, $text ),
				// Under the card's full price, once the step has a pick (D-249).
				'change' => null === $step?->counted() ? '' : Words::change( $kind, $text ),
			);
		};
		$option   = static fn( Option $option, string $slot, ?StateStep $step ): array => array(
			'id'          => $option->part->id,
			'brand'       => $option->part->brand,
			'size'        => self::size( $slot, $option->part ),
			'delta'       => $delta( $option, $step ),
			'fit'         => $option->severity->value,
			'in_stock'    => $option->part->inStock,
			'available'   => $option->available(),
			'quantity'    => $option->quantity,
			'picked'      => $option->part->id === $step?->picked?->part->id,
			'recommended' => $option->part->id === $step?->recommended?->part->id,
			'messages'    => self::messages( $option->findings ),
			'card'        => $cards[ $option->part->id ] ?? null,
		);
		$problem  = static fn( BuildProblem $problem ): array => array(
			'code'     => $problem->code->value,
			'slot'     => $problem->slot,
			'part'     => $problem->part,
			'message'  => Words::problem( $problem->code, $labels[ $problem->slot ] ?? $problem->slot ),
			'messages' => self::messages( $problem->findings ),
		);

		$steps = array();
		foreach ( $state->steps as $step ) {
			$groups = array();
			foreach ( $step->options->hiddenGroups() as $group ) {
				$groups[] = array(
					'rule'    => $group->rule->value,
					'code'    => $group->code->value,
					'count'   => $group->count,
					'message' => Words::hidden( $group->code, $group->count ),
				);
			}
			$steps[] = array(
				'slot'        => $step->slot->key,
				'label'       => $labels[ $step->slot->key ],
				'help'        => self::text( $step->slot->help, 'slot_help', $state->id, $step->slot->key ),
				'required'    => $step->slot->required,
				'units'       => $step->slot->units,
				'picked'      => $step->picked?->part->id,
				'recommended' => $step->recommended?->part->id,
				'offered'     => count( $step->options->offered ),
				'hidden'      => count( $step->options->hidden ),
				'groups'      => $groups,
				// The picks before the step its options were checked against; null where no rule reads the slot.
				'against'     => $step->options->against,
			);
		}

		$lines = array();
		foreach ( $state->quote->lines as $line ) {
			$lines[] = array(
				'slot'     => $line->slot,
				'part'     => $line->part,
				'name'     => $cards[ $line->part ]['name'] ?? '',
				'quantity' => $line->quantity,
				'price'    => $money( $line->price ),
				'charged'  => $money( $line->charged ),
				'total'    => $money( $line->total ),
			);
		}

		$changes = null;
		if ( null !== $state->changes ) {
			$changes = array(
				'kept'     => $state->changes->kept,
				'proposed' => array(),
				'changes'  => array(),
			);
			foreach ( $state->steps as $step ) {
				$part = $state->changes->proposed->part( $step->slot->key );
				if ( null !== $part ) {
					$changes['proposed'][] = array(
						'slot' => $step->slot->key,
						'part' => $part->id,
					);
				}
			}
			foreach ( $state->changes->changes as $change ) {
				$changes['changes'][] = array(
					'slot'     => $change->slot,
					'label'    => $labels[ $change->slot ] ?? $change->slot,
					'was'      => $change->was->id,
					'name'     => $cards[ $change->was->id ]['name'] ?? '',
					'messages' => self::messages( $change->findings ),
					'closest'  => null === $change->closest ? null : $option( $change->closest, $change->slot, null ),
				);
			}
		}

		$presets = array();
		// The tiles show at the start alone, so only there are their pictures looked for (D-252).
		$begun = ( $state->kit->slots->keys()[0] ?? '' ) !== $state->step;
		foreach ( $state->steps as $step ) {
			$begun = $begun || null !== $step->counted();
		}
		foreach ( $state->kit->presets as $at => $preset ) {
			$picks = array();
			foreach ( $preset->picks as $slot => $part ) {
				$picks[] = compact( 'slot', 'part' );
			}
			$built     = $state->presets[ $at ] ?? null;
			$kept      = null === $built ? array() : array_merge( $built->refused, $built->validation->problems );
			$presets[] = array(
				'name'        => self::preset( $preset->name, $at, $state->id ),
				'description' => self::text( $preset->description, 'preset_description', $state->id, (string) $at ),
				'picks'       => $picks,
				'image'       => $this->tile( $state->id, $preset->picks, $preset->image, $begun ) ?? $cards[ array_values( $preset->picks )[0] ?? 0 ]['image'] ?? null,
				'total'       => $money( $built->quote->total ?? 0 ),
				'ready'       => array() === $kept,
				// What keeps it from the cart, each part named for its tile (spec: Presets).
				'problems'    => array_map( static fn( BuildProblem $kept ): array => $problem( $kept ) + array( 'name' => $cards[ $kept->part ?? 0 ]['name'] ?? '' ), $kept ),
			);
		}

		return array(
			'kit'      => $state->id,
			'step'     => $state->step,
			'ready'    => $state->ready(),
			'texts'    => array(
				'lead_time'      => self::text( $state->kit->assembly->leadTime, 'lead_time', $state->id ),
				'returns_notice' => self::text( $state->kit->assembly->returnsNotice, 'returns_notice', $state->id ),
			),
			'presets'  => $presets,
			'steps'    => $steps,
			'options'  => array_map( static fn( Option $shown ): array => $option( $shown, $state->step, $current ), $offered ),
			'hidden'   => array_map( static fn( Option $shown ): array => $option( $shown, $state->step, $current ), $hidden ),
			'filters'  => self::filters( $state->step, $all, $brands, $link ),
			'matching' => count( $matching ),
			'more'     => $more,
			'refused'  => array_map( $problem, $state->refused ),
			'problems' => array_map( $problem, $state->validation->problems ),
			'changes'  => $changes,
			'total'    => array(
				'lines'    => $lines,
				'parts'    => $money( $state->quote->parts ),
				'saved'    => $money( $state->quote->saved ),
				'assembly' => $state->quote->assembly,
				'fee'      => $money( $state->quote->fee ),
				'total'    => $money( $state->quote->total ),
				// The board with each recommendation in it, while one fills a slot (D-213).
				'estimate' => null === $state->estimate ? null : $money( $state->estimate->total ),
			),
			'picture'  => $this->picture( $state, $counted, $labels, $brands, $cards ),
			'delivery' => $this->delivery->said( $state->id, $state->quote->assembly, $state->quote->total, $decimals ),
			'notes'    => self::notes( $state, $decimals ),
			'shelf'    => null === $view ? null : self::shelf( $view, $state->step, static fn( Option $shown ): array => $option( $shown, $state->step, $current ), $current ),
		);
	}

	/**
	 * The sizes of the look the step's pick is of, where nothing in the build decides its size: each the part of
	 * that size with the same choices as the pick, smallest first, for the radio group after the picked card (spec:
	 * One card per look; D-279). Nothing for any other look, or a look in one size.
	 *
	 * @param ShelfLook                                             $shelved The card.
	 * @param string                                                $slot    The step's slot.
	 * @param StateStep|null                                        $current The step.
	 * @param callable(Measure): array{value: string, text: string} $size    A size as the answer names it.
	 * @return array{sized?: list<array{part: int, value: string, text: string, in_stock: bool, picked: bool}>}
	 */
	private static function sizesOf( ShelfLook $shelved, string $slot, ?StateStep $current, callable $size ): array {
		$spec   = self::SIZES[ $slot ] ?? null;
		$picked = $current?->picked?->part;
		if ( null === $spec || null === $picked || null === $current || SizePicker::decides( $slot ) ) {
			return array();
		}
		$held = null;
		foreach ( $shelved->look->variants as $variant ) {
			$held = $variant->part->id === $picked->id ? $variant : $held;
		}
		if ( null === $held ) {
			return array();
		}
		$sized = array();
		foreach ( $shelved->look->variants as $variant ) {
			$measure = $variant->part->specs->measure( $spec );
			$offered = $current->options->offer( $variant->part->id );
			if ( null === $measure || null === $offered || $variant->choices !== $held->choices ) {
				continue;
			}
			$named = Shelf::named( $measure );
			// One radio a size: the part the build holds, else the first in stock.
			if ( isset( $sized[ $named ] ) && ( $sized[ $named ]['picked'] || $sized[ $named ]['in_stock'] ) && $variant !== $held ) {
				continue;
			}
			$sized[ $named ] = array( 'part' => $variant->part->id ) + $size( $measure ) + array(
				'in_stock' => $offered->available(),
				'picked'   => $variant === $held,
				'measure'  => $measure,
			);
		}
		if ( count( $sized ) < 2 ) {
			return array();
		}
		usort( $sized, static fn( array $one, array $other ): int => $one['measure']->compare( $other['measure'] ) );
		return array(
			'sized' => array_map(
				static fn( array $one ): array => array(
					'part'     => $one['part'],
					'value'    => $one['value'],
					'text'     => $one['text'],
					'in_stock' => $one['in_stock'],
					'picked'   => $one['picked'],
				),
				$sized
			),
		);
	}

	/**
	 * What a link asks of the step's shelf: its width, chips, search and sort, and as many looks as it lists, a
	 * page at least.
	 *
	 * @param BuildLink $link The link.
	 */
	private static function page( BuildLink $link ): ShelfQuery {
		return new ShelfQuery( $link->shelf->width, $link->shelf->chips, $link->shelf->query, $link->shelf->sort, 0, max( Shelf::SHOWN, $link->shown ) );
	}

	/**
	 * The shelf as the answer carries it.
	 *
	 * @param ShelfView                      $view   The shelf and its names.
	 * @param string                         $slot   The step's slot.
	 * @param callable(Option): array<mixed> $option  An option as the answer carries it.
	 * @param StateStep|null                 $current The step, for the sizes of the look its pick is of.
	 * @return array<string, mixed>
	 */
	private static function shelf( ShelfView $view, string $slot, callable $option, ?StateStep $current = null ): array {
		$fraction = in_array( self::SIZES[ $slot ] ?? null, array( SpecKey::RiserThickness, SpecKey::BoltLength ), true );
		$size     = static fn( Measure $measure ): array => array(
			'value' => Shelf::named( $measure ),
			'text'  => Words::measure( $measure, $fraction ),
		);
		$look     = static function ( ShelfLook $shelved ) use ( $view, $option, $size, $slot, $current ): array {
			$choices = array();
			foreach ( $shelved->look->choices() as $name => $values ) {
				$choices[ $name ] = array_map(
					static fn( string $value ): array => array(
						'value' => $value,
						'label' => $view->labels[ $name ][ $value ] ?? $value,
					),
					$values
				);
			}
			$brand = $shelved->option->part->brand;
			$name  = array();
			foreach ( $shelved->name as $attribute => $value ) {
				$name[] = $view->labels[ $attribute ][ $value ] ?? $value;
			}
			return array(
				'key'     => $shelved->look->key,
				'product' => $shelved->look->product,
				'name'    => $view->names[ $shelved->look->product ] ?? '',
				'brand'   => '' === $brand ? '' : ( $view->labels[ Shelf::BRAND ][ $brand ] ?? '' ),
				'look'    => $name,
				'part'    => $option( $shelved->option ),
				'sizes'   => array_map( $size, $shelved->sizes ),
				'choices' => (object) $choices,
			) + self::sizesOf( $shelved, $slot, $current, $size );
		};
		$sized    = self::SIZES[ $slot ]->value ?? null;
		$groups   = array();
		foreach ( $view->listing->groups as $group ) {
			$chips = array();
			foreach ( $group->chips as $chip ) {
				$measure = $group->name === $sized ? ( $view->sizes[ $chip->value ] ?? null ) : null;
				$chips[] = array(
					'value'    => $chip->value,
					'label'    => null === $measure ? ( $view->labels[ $group->name ][ $chip->value ] ?? $chip->value ) : $size( $measure )['text'],
					'count'    => $chip->count,
					'selected' => $chip->chosen,
				);
			}
			$groups[] = array(
				'name'  => $group->name,
				'label' => match ( $group->name ) {
					Shelf::BRAND => __( 'Brand', 'creative-skateboard-builder' ),
					$sized       => self::sizeName( $slot ),
					default      => $view->groups[ $group->name ] ?? $group->name,
				},
				'chips' => $chips,
			);
		}
		return array(
			'looks'           => array_map( $look, $view->listing->looks ),
			'sold_out'        => array_map( $look, $view->listing->soldOut ),
			'chips'           => $groups,
			'widths'          => array_map(
				static fn( Width $width ): array => $size( $width->width ) + array(
					'in_stock' => $width->inStock,
					'selected' => Shelf::named( $width->width ) === $view->width,
				),
				$view->listing->widths
			),
			'offered'         => $view->listing->offered,
			'matching'        => $view->listing->matching,
			'more'            => $view->listing->more,
			'query'           => $view->query,
			'sort'            => $view->sort,
			'elsewhere'       => array_map( static fn( Width $width ): array => $size( $width->width ), $view->listing->elsewhere ),
			'hidden_matching' => $view->hidden,
		);
	}

	/**
	 * A saved build as it reopens, for "Your build has changed" (D-254): each pick that changed with its reason and
	 * the closest part in its place, each part kept whose price changed, then and now, and the totals.
	 *
	 * @since 1.0.0
	 *
	 * @param Reopened $reopened What changed.
	 * @return array{changes: list<array{slot: string, label: string, was: int, name: string, reason: string, closest: array{id: int, name: string}|null}>, prices: list<array{slot: string, label: string, part: int, name: string, then: string, now: string}>, then: string|null, now: string, picks: array<string, int>, first: string}
	 */
	public function reopened( Reopened $reopened ): array {
		$state = $reopened->state;
		$ids   = array_column( $reopened->prices, 'part' );
		foreach ( $reopened->check->changes as $change ) {
			$ids[] = $change->was;
			if ( null !== $change->closest ) {
				$ids[] = $change->closest;
			}
		}
		$cards    = $this->cards->cards( array_values( array_unique( $ids ) ) );
		$decimals = $this->kits->decimals();
		$label    = static fn( string $slot ): string => self::label( $state->kit->slots->get( $slot )->label ?? $slot, $slot, $state->id );
		$changes  = array();
		foreach ( $reopened->check->changes as $change ) {
			$changes[] = array(
				'slot'    => $change->slot,
				'label'   => $label( $change->slot ),
				'was'     => $change->was,
				'name'    => $cards[ $change->was ]['name'] ?? '',
				'reason'  => $change->reason->value,
				'closest' => null === $change->closest ? null : array(
					'id'   => $change->closest,
					'name' => $cards[ $change->closest ]['name'] ?? '',
				),
			);
		}
		$prices = array();
		foreach ( $reopened->prices as $price ) {
			$prices[] = array(
				'slot'  => $price['slot'],
				'label' => $label( $price['slot'] ),
				'part'  => $price['part'],
				'name'  => $cards[ $price['part'] ]['name'] ?? '',
				'then'  => $this->money->text( $price['then'], $decimals ),
				'now'   => $this->money->text( $price['now'], $decimals ),
			);
		}
		return array(
			'changes' => $changes,
			'prices'  => $prices,
			'then'    => null === $reopened->then ? null : $this->money->text( $reopened->then, $decimals ),
			'now'     => $this->money->text( $state->quote->total, $decimals ),
			'picks'   => $reopened->check->picks,
			'first'   => $reopened->check->changes[0]->slot ?? ( $reopened->prices[0]['slot'] ?? State::REVIEW ),
		);
	}

	/**
	 * A preset's tile photo: the rider photo it names, else the build's composed picture once made; null for the
	 * first part's photo meanwhile, and on a build begun, which shows no tiles (D-252).
	 *
	 * @param int                $kit   The kit product's ID.
	 * @param array<string, int> $picks The preset's picks.
	 * @param int|null           $image The rider photo it names, or null.
	 * @param bool               $begun Whether the build is begun.
	 * @return array{src: string, width: int, height: int, srcset: string, sizes: string}|null
	 */
	private function tile( int $kit, array $picks, ?int $image, bool $begun ): ?array {
		if ( $begun ) {
			return null;
		}
		$photo = null === $image ? null : $this->cards->photo( $image );
		if ( null !== $photo ) {
			return $photo;
		}
		$url = null === $this->pictures ? null : ( $this->pictures )( $kit, $picks );
		return null === $url ? null : array(
			'src'    => $url,
			'width'  => ComposedPictures::CANVASES[ ComposedPictures::CART ][0],
			'height' => ComposedPictures::CANVASES[ ComposedPictures::CART ][1],
			'srcset' => '',
			'sizes'  => '',
		);
	}

	/**
	 * What a slot's size is called: a deck's width, wheels' diameter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot The slot's key.
	 */
	public static function sizeName( string $slot ): string {
		return match ( $slot ) {
			SlotKey::DECK     => __( 'Width', 'creative-skateboard-builder' ),
			SlotKey::TRUCKS   => __( 'Axle width', 'creative-skateboard-builder' ),
			SlotKey::WHEELS   => __( 'Diameter', 'creative-skateboard-builder' ),
			SlotKey::RISERS   => __( 'Thickness', 'creative-skateboard-builder' ),
			SlotKey::HARDWARE => __( 'Length', 'creative-skateboard-builder' ),
			default           => __( 'Size', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * A part's size as its slot's parts are sized, or null where the slot has no such spec or the part does not
	 * give it.
	 *
	 * @param string $slot The slot's key.
	 * @param Part   $part The part.
	 * @return array{value: string, text: string, measure: Measure}|null `value` is how the address names the size, as
	 *         `8.25in` or `54mm`; `text` is how a person reads it.
	 */
	private static function sized( string $slot, Part $part ): ?array {
		$key     = self::SIZES[ $slot ] ?? null;
		$measure = null === $key ? null : $part->specs->measure( $key );
		if ( null === $measure ) {
			return null;
		}
		return array(
			'value'   => rtrim( rtrim( $measure->decimal(), '0' ), '.' ) . $measure->unit->value,
			// Bolts and risers are said in fractions (spec: Numbers and units).
			'text'    => Words::measure( $measure, SpecKey::RiserThickness === $key || SpecKey::BoltLength === $key ),
			'measure' => $measure,
		);
	}

	/**
	 * A part's size, as the answer carries it, and as a share card names it (D-200).
	 *
	 * @since 0.7.0
	 *
	 * @param string $slot The slot's key.
	 * @param Part   $part The part.
	 * @return array{value: string, text: string}|null
	 */
	public static function size( string $slot, Part $part ): ?array {
		$sized = self::sized( $slot, $part );
		return null === $sized ? null : array(
			'value' => $sized['value'],
			'text'  => $sized['text'],
		);
	}

	/**
	 * The step's filters: the brands and the sizes among the parts it offers, each with how many parts have it. A
	 * list of one says nothing a shopper could choose by, so it is empty.
	 *
	 * @param string                $slot    The step's slot.
	 * @param list<Option>          $offered Every part the step offers.
	 * @param array<string, string> $names   Brands' names, by slug.
	 * @param BuildLink             $link    The address, for the filters set.
	 * @return array{brands: list<array{value: string, label: string, count: int, selected: bool}>, sizes: list<array{value: string, label: string, count: int, selected: bool}>}
	 */
	private static function filters( string $slot, array $offered, array $names, BuildLink $link ): array {
		$brands   = array();
		$sizes    = array();
		$measures = array();
		foreach ( $offered as $option ) {
			$brand = $option->part->brand;
			if ( '' !== $brand ) {
				$brands[ $brand ] ??= array(
					'value'    => $brand,
					'label'    => $names[ $brand ] ?? $brand,
					'count'    => 0,
					'selected' => $brand === $link->brand,
				);
				++$brands[ $brand ]['count'];
			}
			$sized = self::sized( $slot, $option->part );
			if ( null !== $sized ) {
				$sizes[ $sized['value'] ] ??= array(
					'value'    => $sized['value'],
					'label'    => $sized['text'],
					'count'    => 0,
					'selected' => $sized['value'] === $link->size,
				);
				++$sizes[ $sized['value'] ]['count'];
				$measures[ $sized['value'] ] = $sized['measure'];
			}
		}
		// A sort numbers a list again from nothing.
		usort( $brands, static fn( array $one, array $other ): int => strnatcasecmp( $one['label'], $other['label'] ) );
		usort( $sizes, static fn( array $one, array $other ): int => $measures[ $one['value'] ]->compare( $measures[ $other['value'] ] ) );
		return array(
			'brands' => count( $brands ) > 1 ? $brands : array(),
			'sizes'  => count( $sizes ) > 1 ? $sizes : array(),
		);
	}

	/**
	 * What is in the box: each part the total counts, labelled with its slot, its brand and its size, with its photo,
	 * and the build in one sentence. From 0.7.0 the board as well: the layout of the parts counted, with the kit's
	 * picture settings, drawn for the page from the parts with a layer image, each of which the box then leaves out
	 * (spec: The picture; D-195). From 0.8.0 the board's top too, from the same layout (#46).
	 *
	 * @param State                                                                                                                                                                                   $state   The build's state.
	 * @param array<string, Part>                                                                                                                                                                     $counted The part each slot counts, by slot.
	 * @param array<string, string>                                                                                                                                                                   $labels  The slots' labels.
	 * @param array<string, string>                                                                                                                                                                   $brands  Brands' names, by slug.
	 * @param array<int, array{name: string, url: string, price: string, stock: string, stock_class: string, image: array{src: string, width: int, height: int, srcset: string, sizes: string}|null}> $cards The cards.
	 * @return array{items: list<array<string, mixed>>, description: string, width: int, height: int, layers: list<array<string, mixed>>, top: list<array<string, mixed>>}
	 */
	private function picture( State $state, array $counted, array $labels, array $brands, array $cards ): array {
		$picks = Picks::none();
		foreach ( $counted as $slot => $part ) {
			$picks = $picks->with( $slot, $part );
		}
		$layout = Layout::of( $picks, $state->kit->picture );
		$board  = $this->renderer->render( $layout, null === $this->artwork ? array() : $this->artwork->of( $layout, $counted ) );
		// A part either side draws: the grip and the hardware are drawn on the top alone (#46).
		$drawn = array_column( $board['top'], 'part', 'slot' ) + array_column( $board['layers'], 'part', 'slot' );
		$items = array();
		$names = array();
		foreach ( $state->quote->lines as $line ) {
			$part    = $counted[ $line->slot ];
			$name    = $cards[ $line->part ]['name'] ?? '';
			$size    = self::size( $line->slot, $part )['text'] ?? '';
			$items[] = array(
				'slot'  => $line->slot,
				'part'  => $line->part,
				'label' => Words::boxed( $labels[ $line->slot ], $brands[ $part->brand ] ?? '', $size ),
				'size'  => $size,
				'name'  => $name,
				'image' => $cards[ $line->part ]['image'] ?? null,
				'drawn' => ( $drawn[ $line->slot ] ?? null ) === $line->part,
			);
			if ( '' !== $name ) {
				$names[] = array( $labels[ $line->slot ], $name );
			}
		}
		return array(
			'items'       => $items,
			'description' => Words::built( $names ),
			'width'       => $board['width'],
			'height'      => $board['height'],
			'layers'      => $board['layers'],
			'top'         => $board['top'],
		);
	}

	/**
	 * The lines other plugins put under the total (spec: The total), as plain text.
	 *
	 * @param State $state    The build's state.
	 * @param int   $decimals How many decimals the minor unit is.
	 * @return list<string>
	 */
	private static function notes( State $state, int $decimals ): array {
		$parts = array();
		foreach ( $state->quote->lines as $line ) {
			$parts[ $line->slot ] = $line->part;
		}
		/**
		 * Filter the lines shown under a build's total in the builder: the points a build earns, or a word to a
		 * signed-in dealer. Each is a sentence in the language of the request, shown as plain text, and the builder
		 * shows the first six. The answer is not cached, and it is given on every pick, so it has to be quick.
		 *
		 * @since 0.6.0
		 *
		 * @param list<string>                                                                          $notes   The lines so far: none.
		 * @param array{kit: int, parts: array<string, int>, assembly: bool, total: int, decimals: int} $context The kit product's ID; the parts the total counts, slot key to part ID; whether the shop assembles the build; and the build's total in whole minor units, with how many decimals that is.
		 */
		$said  = apply_filters(
			'cbuild_summary_notes',
			array(),
			array(
				'kit'      => $state->id,
				'parts'    => $parts,
				'assembly' => $state->quote->assembly,
				'total'    => $state->quote->total,
				'decimals' => $decimals,
			)
		);
		$notes = array();
		foreach ( is_array( $said ) ? $said : array() as $note ) {
			// WordPress trims what it strips.
			$note = is_string( $note ) ? wp_strip_all_tags( $note ) : '';
			if ( '' !== $note ) {
				$notes[] = $note;
			}
		}
		return array_slice( $notes, 0, self::NOTES );
	}

	/**
	 * A slot's label, as another plugin would have it said (spec: French and other languages). An answer that is no
	 * text leaves the owner's words as they are, here and in the two methods below.
	 *
	 * @since 0.5.0 Public, for the cart's sentences.
	 *
	 * @param string $label The owner's label.
	 * @param string $slot  The slot's key.
	 * @param int    $kit   The kit product's ID.
	 */
	public static function label( string $label, string $slot, int $kit ): string {
		/**
		 * Filter a slot's label as a shopper reads it. The label is the shop owner's own words, so no translation file
		 * holds it: a translation plugin answers here.
		 *
		 * @since 0.4.0
		 *
		 * @param string $label The label the owner typed.
		 * @param string $slot  The slot's key, such as trucks.
		 * @param int    $kit   The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_slot_label', $label, $slot, $kit );
		return is_string( $said ) ? $said : $label;
	}

	/**
	 * A preset's name, as another plugin would have it said.
	 *
	 * @param string $name     The owner's name for it.
	 * @param int    $position Where the preset stands among the kit's, from 0.
	 * @param int    $kit      The kit product's ID.
	 */
	private static function preset( string $name, int $position, int $kit ): string {
		/**
		 * Filter a preset's name as a shopper reads it.
		 *
		 * @since 0.4.0
		 *
		 * @param string $name     The name the owner typed.
		 * @param int    $position Where the preset stands among the kit's, from 0.
		 * @param int    $kit      The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_preset_label', $name, $position, $kit );
		return is_string( $said ) ? $said : $name;
	}

	/**
	 * Another of the owner's texts, as another plugin would have it said.
	 *
	 * @param string $text  The owner's words.
	 * @param string $field Which text it is.
	 * @param int    $kit   The kit product's ID.
	 * @param string $of    The slot's key or the preset's position, for a text that belongs to one.
	 */
	public static function text( string $text, string $field, int $kit, string $of = '' ): string {
		/**
		 * Filter one of a kit's texts as a shopper reads it: a slot's help, a preset's description, the lead time or
		 * the returns notice.
		 *
		 * @since 0.4.0
		 *
		 * @param string $text  The text the owner typed.
		 * @param string $field Which text: `slot_help`, `preset_description`, `lead_time` or `returns_notice`.
		 * @param int    $kit   The kit product's ID.
		 * @param string $of    A slot's key for `slot_help`, a preset's position from 0 for `preset_description`, and
		 *                      an empty string otherwise.
		 */
		$said = apply_filters( 'cbuild_kit_text', $text, $field, $kit, $of );
		return is_string( $said ) ? $said : $text;
	}

	/**
	 * The options a list shows: those the shop can supply before those it cannot, the first page of them, and the
	 * one the step counts where the page does not hold it: it stands later, or a filter leaves it out.
	 *
	 * @param list<Option> $options The list, in the engine's order.
	 * @param Option|null  $counted The option the step counts, when it is one of the list's kind.
	 * @param int          $page    How many a page is.
	 * @return list<Option>
	 */
	private static function shown( array $options, ?Option $counted, int $page ): array {
		$here = array();
		$gone = array();
		foreach ( $options as $option ) {
			if ( $option->available() ) {
				$here[] = $option;
			} else {
				$gone[] = $option;
			}
		}
		$all   = array_merge( $here, $gone );
		$shown = array_slice( $all, 0, $page );
		if ( null !== $counted && ! in_array( $counted->part->id, array_map( static fn( Option $option ): int => $option->part->id, $shown ), true ) ) {
			$shown[] = $counted;
		}
		return $shown;
	}

	/**
	 * Findings as the answer carries them: the rule, the code, how bad, and the sentence.
	 *
	 * @param list<Finding> $findings The findings.
	 * @return list<array{rule: string, code: string, severity: string, text: string}>
	 */
	private static function messages( array $findings ): array {
		$messages = array();
		foreach ( $findings as $finding ) {
			$messages[] = array(
				'rule'     => $finding->rule->value,
				'code'     => $finding->code->value,
				'severity' => $finding->severity->value,
				'text'     => Words::finding( $finding ),
				'short'    => Words::short( $finding ),
			);
		}
		return $messages;
	}
}
