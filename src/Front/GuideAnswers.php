<?php
/**
 * The guided start's answers.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Guide\RidingStyle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a shopper answered "Help me choose" with (#134), as the build's link carries it under `cbuild[_guide]`, and
 * nowhere else (D-402): `shoe`, the row of the kit's width guide, counted from 0; `style`, where they skate; `budget`,
 * a whole amount of the shop's currency; and `asked`, `1` once the form is sent, so an answer of every question left
 * blank still shows the builds. Each answer may be left out. What is not one is left out too.
 *
 * @since 1.3.0
 */
final readonly class GuideAnswers {

	/**
	 * The most rows a width guide has (`WidthGuide`), so the most a shoe answer counts to.
	 *
	 * @since 1.3.0
	 */
	public const int ROWS = 12;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param int|null         $shoe   The width guide's row, counted from 0, or null where not answered.
	 * @param RidingStyle|null $style  Where they skate, or null.
	 * @param int|null         $budget The budget, a whole amount of the shop's currency above nothing, or null.
	 * @param bool             $asked  Whether the form was sent.
	 */
	public function __construct( public ?int $shoe = null, public ?RidingStyle $style = null, public ?int $budget = null, public bool $asked = false ) {}

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
		$budget = self::whole( $sent['budget'] ?? null, 6 );
		$style  = $sent['style'] ?? null;
		return new self(
			null !== $shoe && $shoe < self::ROWS ? $shoe : null,
			is_string( $style ) ? RidingStyle::tryFrom( $style ) : null,
			0 === $budget ? null : $budget,
			isset( $sent['asked'] ) && '1' === $sent['asked']
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
	 * A whole number of at most so many digits, written as PHP writes one, or null.
	 *
	 * @param mixed $value  What was sent.
	 * @param int   $digits The most digits.
	 */
	private static function whole( mixed $value, int $digits ): ?int {
		return is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]{0,' . ( $digits - 1 ) . '})\z/', $value ) ? (int) $value : null;
	}
}
