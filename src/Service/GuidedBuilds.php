<?php
/**
 * The ready builds the guided start recommends.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Guide\ReadyBuilds;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Tier;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The guided start's use case (#134): the deck width a row of the width guide comes to among the decks the shop holds,
 * and the ready builds the shop can make at a width and wheel sizes (`ReadyBuilds`), each checked and priced as the
 * cart checks and prices a build, and kept within a budget. The builds come from the parts the state has already read,
 * so they cost no query of their own but the decks' cards. `Front\GuidedStart` reads the answers and the kit's guides,
 * and asks a plugin, around it.
 *
 * @since 1.3.0
 */
final readonly class GuidedBuilds {

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
	 * Each slot's parts at a build's start, by slot key: at the start the state holds every slot's parts against no
	 * pick, and offered or hidden, each is one.
	 *
	 * @since 1.3.0
	 *
	 * @param State $state The build's state, at its start.
	 * @return array<string, list<Part>>
	 */
	public static function candidates( State $state ): array {
		$candidates = array();
		foreach ( $state->steps as $step ) {
			$candidates[ $step->slot->key ] = array_map( static fn( Option $option ): Part => $option->part, array( ...$step->options->offered, ...$step->options->hidden ) );
		}
		return $candidates;
	}

	/**
	 * The deck width a row of the width guide comes to among the decks the shop holds, or null where none is in it.
	 *
	 * @since 1.3.0
	 *
	 * @param State $state The build's state, at its start.
	 * @param Span  $row   The row's widths.
	 */
	public static function width( State $state, Span $row ): ?Measure {
		return ReadyBuilds::width( $row, self::candidates( $state )[ SlotKey::DECK ] ?? array() );
	}

	/**
	 * The ready builds at a width and wheel sizes, within a budget where there is one, or the closest.
	 *
	 * @since 1.3.0
	 *
	 * @param State        $state  The build's state, at its start.
	 * @param Measure|null $width  The deck width, or null for any.
	 * @param Span|null    $wheels The wheel sizes, or null for any.
	 * @param int|null     $budget A whole amount of the shop's currency, or null for none.
	 * @return array{budget: int|null, over: bool, builds: list<array{tier: Tier, picks: array<string, int>, total: string, amount: int, over: string, image: mixed, drawn: bool, deck: int}>}
	 * @throws InvalidMoney When a build cannot be priced in the shop's currency settings.
	 */
	public function of( State $state, ?Measure $width, ?Span $wheels, ?int $budget ): array {
		$kit        = $state->kit;
		$candidates = self::candidates( $state );
		$engine     = FitEngine::standard();
		$validator  = new BuildValidator( $engine );
		$pricer     = new Pricer();
		$decimals   = $this->kits->decimals();
		$ids        = array_map( static fn( array $parts ): array => array_map( static fn( Part $part ): int => $part->id, $parts ), $candidates );
		$made       = array();
		$totals     = array();
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
				// The deck's card picture is its layer image where it has no photo (D-282): marked drawn, as the card's is.
				'drawn'  => null !== ( $cards[ $deck ]['image'] ?? null ) && false === ( $cards[ $deck ]['photo'] ?? true ),
				'deck'   => $deck,
			);
		}
		return array(
			'budget' => $budget,
			'over'   => $over,
			'builds' => $builds,
		);
	}
}
