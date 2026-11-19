<?php
/**
 * Money written plainly.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Money;

/**
 * Dollars: "$12.50".
 */
final class FakeMoney implements Money {

	public string $currency = 'CAD';

	public function currency(): string {
		return $this->currency;
	}

	public function text( int $amount, int $decimals ): string {
		return '$' . number_format( $amount / 10 ** $decimals, $decimals, '.', ',' );
	}
}
