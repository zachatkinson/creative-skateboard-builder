<?php
/**
 * The guided start.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Guide\ReadyBuilds;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Tier;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The guided start's recommendations from a shopper's answers (#134): the deck width from the kit's width guide, the
 * wheel sizes from its wheel guide, a plugin's answer to `cbuild_guided_start` after both, and the ready builds the
 * shop can make at them (`ReadyBuilds`), each checked and priced as the cart checks and prices a build. The builds come
 * from the parts the state has already read, so the answers cost no query of their own.
 *
 * @since 1.3.0
 */
final readonly class GuidedStart {

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Kits      $kits  The kits, for the shop's decimals.
	 * @param PartCards $cards The parts' cards, for a build's picture.
	 * @param Money     $money Writes an amount as the shop does.
	 */
	public function __construct( private Kits $kits, private PartCards $cards, private Money $money ) {}

	/**
	 * What the answers recommend at a build's start.
	 *
	 * @since 1.3.0
	 *
	 * @param State        $state   The build's state, at its start.
	 * @param GuideAnswers $answers The answers.
	 * @return array{shoe: string, width: Measure|null, noDeck: bool, wheels: Span|null, budget: int|null, over: bool, builds: list<array{tier: Tier, picks: array<string, int>, total: string, amount: int, over: string, image: mixed, deck: int}>}
	 * @throws InvalidMoney When a build cannot be priced in the shop's currency settings.
	 */
	public function of( State $state, GuideAnswers $answers ): array {
		$kit        = $state->kit;
		$candidates = array();
		foreach ( $state->steps as $step ) {
			// At the start the state holds every slot's parts against no pick: offered or hidden, each is one.
			$candidates[ $step->slot->key ] = array_map( static fn( Option $option ): Part => $option->part, array( ...$step->options->offered, ...$step->options->hidden ) );
		}
		$rows   = WidthGuide::structured( $state->id, $kit->settings->guide );
		$row    = null === $answers->shoe ? null : ( $rows[ $answers->shoe ] ?? null );
		$width  = null === $row ? null : ReadyBuilds::width( new Span( $row['from'], $row['to'] ), $candidates[ SlotKey::DECK ] ?? array() );
		$wheels = null === $answers->style ? null : $kit->settings->wheels->span( $answers->style );
		/**
		 * Filters the guided start's recommendation from a shopper's answers, after the kit's width guide and wheel
		 * guide have made it (#134): the deck width the ready builds are made at, and the wheel sizes. Each size is
		 * written as the build link writes a width, a number and its unit, such as `8.25in` or `54mm`. An empty width is
		 * any width, and an empty `wheels.from` any wheel. A size that does not read is left out, so the builds take any.
		 *
		 * @since 1.3.0
		 *
		 * @param array{width: string, wheels: array{from: string, to: string}} $recommended The recommendation.
		 * @param array{shoe: int|null, style: string|null, budget: int|null}   $answers     The shopper's answers: the
		 *                                                                                   width guide's row, counted
		 *                                                                                   from 0, where they skate,
		 *                                                                                   and a whole budget.
		 * @param int                                                           $kit         The kit product's ID.
		 */
		$said   = Cast::map(
			apply_filters(
				'cbuild_guided_start',
				array(
					'width'  => null === $width ? '' : Shelf::named( $width ),
					'wheels' => array(
						'from' => null === $wheels ? '' : Shelf::named( $wheels->from ),
						'to'   => null === $wheels?->to ? '' : Shelf::named( $wheels->to ),
					),
				),
				array(
					'shoe'   => $answers->shoe,
					'style'  => $answers->style?->value,
					'budget' => $answers->budget,
				),
				$state->id
			)
		);
		$width  = self::size( $said['width'] ?? null );
		$sizes  = Cast::map( $said['wheels'] ?? null );
		$from   = self::size( $sizes['from'] ?? null );
		$wheels = null === $from ? null : new Span( $from, self::size( $sizes['to'] ?? null ) );

		$engine    = FitEngine::standard();
		$validator = new BuildValidator( $engine );
		$pricer    = new Pricer();
		$decimals  = $this->kits->decimals();
		$ids       = array_map( static fn( array $parts ): array => array_map( static fn( Part $part ): int => $part->id, $parts ), $candidates );
		$made      = array();
		$totals    = array();
		foreach ( ( new ReadyBuilds( $engine ) )->choose( $kit, $candidates, $width, $wheels ) as $build ) {
			$checked  = $validator->validate( $kit, $build->picks, $ids );
			$totals[] = $pricer->quote( $kit, $checked->build, $kit->assembly->defaultOn, $state->discount, $decimals )->total;
			$picks    = array();
			foreach ( $kit->slots->keys() as $slot ) {
				$part = $build->picks->part( $slot );
				if ( null !== $part ) {
					$picks[ $slot ] = $part->id;
				}
			}
			$made[] = array( $build->tier, $picks );
		}
		// The budget is a whole amount; the totals are in the currency's smallest units.
		$budget = $answers->budget;
		for ( $place = 0; null !== $budget && $place < $decimals; $place++ ) {
			$budget *= 10;
		}
		$kept  = ReadyBuilds::affordable( $totals, $budget );
		$over  = null !== $budget && array() !== $kept && $totals[ $kept[0] ] > $budget;
		$decks = array();
		foreach ( $kept as $at ) {
			$decks[] = $made[ $at ][1][ SlotKey::DECK ] ?? 0;
		}
		$cards  = array() === $decks ? array() : $this->cards->cards( array_values( array_unique( $decks ) ) );
		$builds = array();
		foreach ( $kept as $at ) {
			[ $tier, $picks ] = $made[ $at ];
			$deck             = $picks[ SlotKey::DECK ] ?? 0;
			$builds[]         = array(
				'tier'   => $tier,
				'picks'  => $picks,
				'total'  => $this->money->text( $totals[ $at ], $decimals ),
				'amount' => $totals[ $at ],
				'over'   => $over ? $this->money->text( $totals[ $at ] - (int) $budget, $decimals ) : '',
				'image'  => $cards[ $deck ]['image'] ?? null,
				'deck'   => $deck,
			);
		}
		return array(
			'shoe'   => null === $row ? '' : $row['shoe'],
			'width'  => $width,
			'noDeck' => null !== $row && null === $width,
			'wheels' => $wheels,
			'budget' => $budget,
			'over'   => $over,
			'builds' => $builds,
		);
	}

	/**
	 * A size as the build link writes a width, or null where it does not read.
	 *
	 * @param mixed $named The size, such as `8.25in` or `54mm`.
	 */
	private static function size( mixed $named ): ?Measure {
		if ( ! is_string( $named ) || 1 !== preg_match( '/\A([0-9]{1,3}(?:\.[0-9]{1,4})?)(in|mm)\z/', $named, $parts ) ) {
			return null;
		}
		try {
			return MeasureParser::parse( $named, Unit::from( $parts[2] ) );
		} catch ( InvalidMeasure ) {
			return null;
		}
	}
}
