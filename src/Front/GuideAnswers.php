<?php
/**
 * The guided start's answers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Guide\RidingStyle;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SizeGuide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a shopper answered "Help me choose" with (#134), as the build's link carries it under `cbuild[_guide]`, and
 * nowhere else (D-402): `shoe`, the row of the kit's width guide, counted from 0; `style`, where they skate; `budget`,
 * a whole amount of the shop's currency, read from what was typed as forgivingly as it can be (D-433); and `asked`,
 * `1` once the form is sent, so an answer of every question left blank still shows the builds. Each answer may be
 * left out. What is not one is left out too, and a budget left out so is said to be.
 *
 * @since 1.3.0
 */
final readonly class GuideAnswers {

	/**
	 * The most rows a width guide has (`WidthGuide`), so the most a shoe answer counts to: the kit's own limit.
	 *
	 * @since 1.3.0
	 */
	public const int ROWS = SizeGuide::MAX_ROWS;

	/**
	 * The largest budget, six digits.
	 */
	private const int MOST_BUDGET = 999999;

	/**
	 * The most characters of a budget read: room for a sign, separators, decimals and spaces around six digits.
	 */
	private const int TYPED = 20;

	/**
	 * Where they skate, or null.
	 *
	 * @since 1.3.0
	 *
	 * @var RidingStyle|null
	 */
	public ?RidingStyle $style;

	/**
	 * Constructor. Where they skate comes as the style's value, not the enum: an earlier release's container builds the
	 * build's link, and this with it, by building each class its constructor names, and an enum can't be (D-425).
	 *
	 * @since 1.3.0
	 *
	 * @param int|null    $shoe   The width guide's row, counted from 0, or null where not answered.
	 * @param string|null $style  Where they skate, a `RidingStyle` value, or null. Any other is no answer.
	 * @param int|null    $budget The budget, a whole amount of the shop's currency above nothing, or null.
	 * @param bool        $asked  Whether the form was sent.
	 * @param bool        $budgetLeftOut Whether a budget was typed that is no amount, so it was left out (D-433).
	 */
	public function __construct( public ?int $shoe = null, ?string $style = null, public ?int $budget = null, public bool $asked = false, public bool $budgetLeftOut = false ) {
		$this->style = null === $style ? null : RidingStyle::tryFrom( $style );
	}

	/**
	 * Read what a request sent under `cbuild[_guide]`.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed $sent The parameter's value, unslashed.
	 */
	public static function read( mixed $sent ): self {
		$sent   = is_array( $sent ) ? $sent : array();
		$shoe   = self::whole( $sent['shoe'] ?? null, 2 );
		$typed  = $sent['budget'] ?? null;
		$budget = is_string( $typed ) ? self::amount( $typed ) : null;
		$style  = $sent['style'] ?? null;
		return new self(
			null !== $shoe && $shoe < self::ROWS ? $shoe : null,
			is_string( $style ) ? $style : null,
			0 === $budget ? null : $budget,
			isset( $sent['asked'] ) && '1' === $sent['asked'],
			// Something typed that is no amount: left out, and said so where the builds are shown.
			is_string( $typed ) && null === $budget
		);
	}

	/**
	 * Whether nothing was answered or sent.
	 *
	 * @since 1.3.0
	 */
	public function isEmpty(): bool {
		return null === $this->shoe && null === $this->style && null === $this->budget && ! $this->asked;
	}

	/**
	 * The answers as query parameters under `cbuild[_guide]`, in one order.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string>
	 */
	public function query(): array {
		$query = array();
		if ( null !== $this->shoe ) {
			$query['shoe'] = (string) $this->shoe;
		}
		if ( null !== $this->style ) {
			$query['style'] = $this->style->value;
		}
		if ( null !== $this->budget ) {
			$query['budget'] = (string) $this->budget;
		}
		if ( $this->asked ) {
			$query['asked'] = '1';
		}
		return $query;
	}

	/**
	 * A budget as a shopper types one, forgiven (D-433): any currency sign and any space are taken out, and so are
	 * thousands separators, a comma or a point before each three digits; a comma or a point before one or two digits at
	 * the end, the other one where thousands are separated, is the decimals, and the amount is rounded to a whole one,
	 * half up. So "$150", "150.50", "1,000" and "1 000,50 $" all read. 0 where nothing is left, which is no budget; null
	 * where what is left is no amount of at most six digits.
	 *
	 * @param string $typed What was typed.
	 */
	private static function amount( string $typed ): ?int {
		if ( strlen( $typed ) > self::TYPED ) {
			return null;
		}
		$plain = (string) preg_replace( '/[\p{Sc}\p{Z}\s]+/u', '', $typed );
		if ( '' === $plain && 1 !== preg_match( '/\p{Sc}/u', $typed ) ) {
			return 0;
		}
		foreach ( array( '/\A([0-9]+)(?:[.,]([0-9]{1,2}))?\z/', '/\A([0-9]{1,3}(?:,[0-9]{3})+)(?:\.([0-9]{1,2}))?\z/', '/\A([0-9]{1,3}(?:\.[0-9]{3})+)(?:,([0-9]{1,2}))?\z/' ) as $pattern ) {
			if ( 1 === preg_match( $pattern, $plain, $parts ) ) {
				$whole = (int) str_replace( array( ',', '.' ), '', $parts[1] ) + ( (int) str_pad( $parts[2] ?? '', 2, '0' ) >= 50 ? 1 : 0 );
				return $whole <= self::MOST_BUDGET ? $whole : null;
			}
		}
		return null;
	}

	/**
	 * A whole number of at most so many digits, written as PHP writes one, or null.
	 *
	 * @param mixed $value  What was sent.
	 * @param int   $digits The most digits.
	 */
	private static function whole( mixed $value, int $digits ): ?int {
		return is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]{0,' . ( $digits - 1 ) . '})\z/', $value ) ? (int) $value : null;
	}
}
