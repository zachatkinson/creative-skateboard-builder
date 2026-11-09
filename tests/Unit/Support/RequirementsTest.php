<?php
/**
 * The floors.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Support;

use CreativeBranding\SkateboardBuilder\Support\Requirements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The checks the main file makes before the autoloader (D-023), with the versions injected.
 */
final class RequirementsTest extends TestCase {

	/**
	 * @return iterable<string, array{string, string, ?string}>
	 */
	public static function platforms(): iterable {
		yield 'both at the floor' => array( '8.5.0', '7.0', null );
		yield 'both newer' => array( '8.6.1', '7.1.4', null );
		yield 'WordPress alpha above the floor' => array( '8.5.12', '7.2-alpha-61000', null );
		yield 'PHP below' => array( '8.4.14', '7.1.4', Requirements::SHORT_PHP );
		yield 'PHP far below' => array( '7.4.33', '7.1.4', Requirements::SHORT_PHP );
		yield 'WordPress below' => array( '8.5.0', '6.9.1', Requirements::SHORT_WORDPRESS );
		yield 'PHP wins when both are below' => array( '8.2.0', '6.8', Requirements::SHORT_PHP );
	}

	#[DataProvider( 'platforms' )]
	public function test_platform_shortfall( string $php, string $wordpress, ?string $expected ): void {
		self::assertSame( $expected, Requirements::platformShortfall( $php, $wordpress ) );
	}

	/**
	 * @return iterable<string, array{?string, ?string}>
	 */
	public static function woocommerce(): iterable {
		yield 'absent' => array( null, Requirements::SHORT_WOOCOMMERCE );
		yield 'too old' => array( '11.1.9', Requirements::SHORT_WOOCOMMERCE );
		yield 'the floor' => array( '11.2.0', null );
		yield 'newer' => array( '11.3.0', null );
	}

	#[DataProvider( 'woocommerce' )]
	public function test_woocommerce_shortfall( ?string $version, ?string $expected ): void {
		self::assertSame( $expected, Requirements::woocommerceShortfall( $version ) );
	}

	public function test_the_floors_are_the_decided_ones(): void {
		self::assertSame( '8.5', Requirements::PHP );
		self::assertSame( '7.0', Requirements::WORDPRESS );
		self::assertSame( '11.2', Requirements::WOOCOMMERCE );
	}

	public function test_the_notice_names_every_floor_in_one_sentence(): void {
		$notice = Requirements::notice();
		self::assertStringContainsString( 'PHP 8.5', $notice );
		self::assertStringContainsString( 'WordPress 7.0', $notice );
		self::assertStringContainsString( 'WooCommerce 11.2', $notice );
		self::assertStringNotContainsString( '%', $notice );
	}

	public function test_the_file_parses_on_php_7_4(): void {
		// The main file requires it before the autoloader (D-023): no syntax PHP 7.4 refuses.
		$code = php_strip_whitespace( dirname( __DIR__, 3 ) . '/src/Support/Requirements.php' );
		self::assertDoesNotMatchRegularExpression( '/\b(readonly|enum|match|never|mixed)\b/', $code );
		self::assertDoesNotMatchRegularExpression( '/\?->|:\s*(int|string|bool)\|/', $code );
	}
	public function test_the_notice_can_be_printed_where_wordpress_has_no_admin_notice_function(): void {
		// wp_admin_notice() arrived in WordPress 6.4, and this notice is for a WordPress below the floor.
		self::assertSame( '<div class="notice notice-error"><p>Needs more.</p></div>', Requirements::noticeMarkup( 'Needs more.' ) );
	}
}
