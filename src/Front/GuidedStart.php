<?php
/**
 * The guided start.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Guide\Span;
use CreativeBranding\SkateboardBuilder\Domain\Guide\Tier;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Measure\InvalidMeasure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Service\GuidedBuilds;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The guided start's recommendations from a shopper's answers (#134): the deck width from the kit's width guide, the
 * wheel sizes from its wheel guide, a plugin's answer to `cbuild_guided_start` after both, and the ready builds the
 * shop can make at them, which `Service\GuidedBuilds` makes, checks and prices as the cart checks and prices a build.
 * This class reads the answers and the guides and asks the plugin; the use case is the service's.
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
	 * @return array{shoe: string, width: Measure|null, noDeck: bool, wheels: Span|null, budget: int|null, over: bool, builds: list<array{tier: Tier, picks: array<string, int>, total: string, amount: int, over: string, image: mixed, drawn: bool, deck: int}>}
	 * @throws InvalidMoney When a build cannot be priced in the shop's currency settings.
	 */
	public function of( State $state, GuideAnswers $answers ): array {
		$kit    = $state->kit;
		$rows   = WidthGuide::structured( $state->id, $kit->settings->guide );
		$row    = null === $answers->shoe ? null : ( $rows[ $answers->shoe ] ?? null );
		$width  = null === $row ? null : GuidedBuilds::width( $state, new Span( $row['from'], $row['to'] ) );
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

		$made = ( new GuidedBuilds( $this->kits, $this->cards, $this->money ) )->of( $state, $width, $wheels, $answers->budget );
		return array(
			'shoe'   => null === $row ? '' : $row['shoe'],
			'width'  => $width,
			'noDeck' => null !== $row && null === $width,
			'wheels' => $wheels,
		) + $made;
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
