<?php
/**
 * A build's state, as the state route answers it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

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
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
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
	 * @param State          $state The build's state.
	 * @param BuildLink|null $link  The address the build was asked at, or null for one that says nothing.
	 * @return array<string, mixed>
	 */
	public function of( State $state, ?BuildLink $link = null ): array {
		$link  ??= new BuildLink();
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
			return array(
				'kind'   => $kind,
				'amount' => $amount,
				'text'   => Words::delta( $kind, $this->money->text( abs( $amount ), $decimals ) ),
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
				'image'       => $cards[ array_values( $preset->picks )[0] ?? 0 ]['image'] ?? null,
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
		);
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
			);
		}
		return $messages;
	}
}
