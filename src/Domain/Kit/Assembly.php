<?php
/**
 * A kit's assembly settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use function intdiv;
use function max;
use function min;

/**
 * The shop putting the build together (spec: Vocabulary, Assembly; Data model, `_cbuild_assembly`): whether a build
 * starts with it on, the fee, and the owner's lead time and returns notice.
 */
final readonly class Assembly {

	/**
	 * The most decimal places a currency has here: the fee is kept to four.
	 */
	public const int MAX_DECIMALS = 4;

	/**
	 * How many ten-thousandths one minor unit is, by the currency's decimal places.
	 */
	private const array STEP = array( 10000, 1000, 100, 10, 1 );

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
		return new self( $root->flag( 'default_on' ), $root->amount( 'fee' ), $root->textOrEmpty( 'lead_time' ), $root->textOrEmpty( 'returns_notice' ) );
	}

	/**
	 * The fee in the currency's minor units, rounded half up: 1500 for $15.00, 15 for ¥15.
	 *
	 * @since 0.2.0
	 *
	 * @param int $decimals The currency's decimal places, 0 to 4; anything outside reads as the nearest of the two.
	 */
	public function fee( int $decimals ): int {
		$step = self::STEP[ max( 0, min( self::MAX_DECIMALS, $decimals ) ) ];
		return intdiv( 2 * $this->fee + $step, 2 * $step );
	}
}
