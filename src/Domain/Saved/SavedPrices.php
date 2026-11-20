<?php
/**
 * The prices a build was saved at.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Saved;

use function is_array;
use function is_int;
use function is_string;

/**
 * What a build cost when it was saved, from 1.0.0: each slot's line and the total, in minor units, and the currency
 * they were in (spec: A saved build that has changed, D-254). The shop's prices, not personal data. They are compared
 * only in the same currency, so a currency switcher does not make every price look changed.
 *
 * @since 1.0.0
 */
final readonly class SavedPrices {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $currency The currency, as its ISO 4217 code.
	 * @param array<string, int> $lines    Each slot's line, by slot key.
	 * @param int                $total    The total.
	 */
	public function __construct( public string $currency, public array $lines, public int $total ) {}

	/**
	 * As a plain array, as the picks document and the session keep it (D-278).
	 *
	 * @since 1.0.0
	 *
	 * @return array{currency: string, lines: array<string, int>, total: int}
	 */
	public function toArray(): array {
		return array(
			'currency' => $this->currency,
			'lines'    => $this->lines,
			'total'    => $this->total,
		);
	}

	/**
	 * From what `toArray()` gave, or null where it is not that whole: a currency, whole amounts by slot, and a total.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $kept What was kept.
	 */
	public static function fromArray( mixed $kept ): ?self {
		if ( ! is_array( $kept ) || ! is_string( $kept['currency'] ?? null ) || '' === $kept['currency'] || ! is_int( $kept['total'] ?? null ) ) {
			return null;
		}
		$sent  = $kept['lines'] ?? array();
		$lines = array();
		if ( ! is_array( $sent ) ) {
			return null;
		}
		foreach ( $sent as $slot => $amount ) {
			if ( ! is_string( $slot ) || ! is_int( $amount ) ) {
				return null;
			}
			$lines[ $slot ] = $amount;
		}
		return new self( $kept['currency'], $lines, $kept['total'] );
	}

	/**
	 * A slot's line, or null where it has none or the currency is another.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot     The slot's key.
	 * @param string $currency The currency prices are in now.
	 */
	public function of( string $slot, string $currency ): ?int {
		return $currency === $this->currency ? $this->lines[ $slot ] ?? null : null;
	}

	/**
	 * The total, or null where the currency is another.
	 *
	 * @since 1.0.0
	 *
	 * @param string $currency The currency prices are in now.
	 */
	public function total( string $currency ): ?int {
		return $currency === $this->currency ? $this->total : null;
	}
}
