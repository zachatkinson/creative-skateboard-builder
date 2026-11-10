<?php
/**
 * A kit's assembly settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Rounding;

use function max;

/**
 * The shop putting the build together (spec: Vocabulary, Assembly; Data model, `_cbuild_assembly`): whether a build
 * starts with it on, the fee, and the owner's lead time and returns notice.
 */
final readonly class Assembly {

	/**
	 * The decimal places the fee is kept to. A currency with fewer has the fee rounded to its minor unit.
	 */
	public const int MAX_DECIMALS = 4;

	/**
	 * The most decimal places a shop's prices may have here.
	 */
	public const int MOST_DECIMALS = 8;

	/**
	 * The most characters the lead time and the returns notice may have, as the schema says.
	 */
	public const int MAX_LEAD_TIME = 200;

	public const int MAX_RETURNS_NOTICE = 1000;

	/**
	 * How many ten-thousandths one minor unit is, by the currency's decimal places.
	 */
	private const array STEP = array( 10000, 1000, 100, 10, 1 );

	/**
	 * How many minor units one ten-thousandth is, for a currency with more than four decimal places.
	 */
	private const array FINER = array(
		5 => 10,
		6 => 100,
		7 => 1000,
		8 => 10000,
	);

	/**
	 * Constructor.
	 *
	 * @param bool   $defaultOn     Whether a build starts with assembly on.
	 * @param int    $fee           The fee in ten-thousandths of the currency's unit.
	 * @param string $leadTime      The lead time, in the owner's words, or empty.
	 * @param string $returnsNotice The returns notice shown before checkout, or empty.
	 */
	private function __construct( public bool $defaultOn, private int $fee, public string $leadTime, public string $returnsNotice ) {}

	/**
	 * From the assembly document.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $document The decoded `_cbuild_assembly` document.
	 *
	 * @throws InvalidDocument When the document or a field is not valid.
	 */
	public static function fromDocument( mixed $document ): self {
		$root = Fields::document( $document );
		return new self( $root->flag( 'default_on' ), $root->amount( 'fee' ), $root->textOrEmpty( 'lead_time', self::MAX_LEAD_TIME ), $root->textOrEmpty( 'returns_notice', self::MAX_RETURNS_NOTICE ) );
	}

	/**
	 * The fee in the currency's minor units, rounded half up: 1500 for $15.00, 15 for ¥15.
	 *
	 * @since 0.2.0
	 *
	 * @param int $decimals The shop's price decimals, 0 to 8, as the parts' prices have. Fewer than none reads as none.
	 *
	 * @throws InvalidMoney When there are more than eight.
	 */
	public function fee( int $decimals ): int {
		if ( $decimals > self::MAX_DECIMALS ) {
			// The parts' prices are in the shop's own minor units, so the fee is scaled up to them and never clamped.
			if ( ! isset( self::FINER[ $decimals ] ) ) {
				$refused = InvalidMoney::because( MoneyError::TooManyDecimals );
				throw $refused;
			}
			return $this->fee * self::FINER[ $decimals ];
		}
		$step = self::STEP[ max( 0, $decimals ) ];
		return Rounding::halfUp( $this->fee, $step );
	}
}
