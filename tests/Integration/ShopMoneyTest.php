<?php
/**
 * The shop's currency as the state route sends it.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * The auditor's 1.0.0 minor 4 (D-290): the route's `currency` is plain text, its format as its symbol.
 */
final class ShopMoneyTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'woocommerce_currency_pos' );
		delete_option( 'woocommerce_currency' );
		delete_option( 'woocommerce_price_decimal_sep' );
		delete_option( 'woocommerce_price_thousand_sep' );
		parent::tear_down();
	}

	public function test_the_format_and_the_symbol_are_plain_text_with_no_entity(): void {
		update_option( 'woocommerce_currency', 'EUR' );
		foreach ( array(
			'left'        => '%1$s%2$s',
			'right'       => '%2$s%1$s',
			'left_space'  => "%1\$s\u{00A0}%2\$s",
			'right_space' => "%2\$s\u{00A0}%1\$s",
		) as $position => $format ) {
			update_option( 'woocommerce_currency_pos', $position );
			$settings = $this->service( Money::class )->settings();
			self::assertSame( $format, $settings['format'], $position );
			self::assertSame( '€', $settings['symbol'] );
		}
	}

	/**
	 * #87: WooCommerce keeps a separator as the shop typed it, entities and all (its OptionSanitizer decodes one only to
	 * look for digits, `src/Internal/Settings/OptionSanitizer.php:80`), so the route decodes it as it does the format.
	 */
	public function test_the_separators_are_plain_text_with_no_entity(): void {
		foreach ( array(
			'&nbsp;'  => "\u{00A0}",
			'&#8239;' => "\u{202F}",
			'&amp;'   => '&',
			','       => ',',
		) as $stored => $plain ) {
			update_option( 'woocommerce_price_decimal_sep', $stored );
			update_option( 'woocommerce_price_thousand_sep', $stored );
			$settings = $this->service( Money::class )->settings();
			self::assertSame( $plain, $settings['decimal_separator'], $stored );
			self::assertSame( $plain, $settings['thousand_separator'], $stored );
		}
	}
}
