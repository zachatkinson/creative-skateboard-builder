<?php
/**
 * BuildTerms.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shop-wide terms a build is sold on, from the settings (spec: Admin, Settings).
 */
final class BuildTermsTest extends TestCase {

	private const array NO_ASSEMBLY = array(
		'schema_version' => 1,
		'default_on'     => false,
		'fee'            => '0',
		'lead_time'      => '',
		'returns_notice' => '',
	);

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function nothing(): iterable {
		yield 'no settings' => array( null );
		yield 'not an array' => array( 'settings' );
		yield 'an empty array' => array( array() );
		yield 'every key wrong' => array(
			array(
				'build_discount'       => 5,
				'fixed_coupons'        => 'cart',
				'rate_limit'           => '120',
				'trusted_proxy_header' => 'X Forwarded For',
				'kit_defaults'         => 'none',
			),
		);
		yield 'defaults with no assembly' => array( array( 'kit_defaults' => array() ) );
		yield 'an assembly the domain does not read' => array( array( 'kit_defaults' => array( 'assembly' => array( 'schema_version' => 1 ) ) ) );
	}

	#[DataProvider( 'nothing' )]
	public function test_settings_that_hold_nothing_read_as_the_defaults( mixed $stored ): void {
		$terms = BuildTerms::read( $stored );
		self::assertSame( 0, $terms->discount );
		self::assertSame( 'kit', $terms->fixedCoupons );
		self::assertSame( 120, $terms->rateLimit );
		self::assertSame( '', $terms->proxyHeader );
		self::assertSame( self::NO_ASSEMBLY, $terms->assembly );
		self::assertSame(
			array(
				'build_discount'       => '0',
				'fixed_coupons'        => 'kit',
				'rate_limit'           => 120,
				'trusted_proxy_header' => '',
				'kit_defaults'         => array( 'assembly' => self::NO_ASSEMBLY ),
			),
			$terms->toArray()
		);
	}

	public function test_what_the_settings_hold_is_read_and_written_back_the_same(): void {
		$stored = array(
			'build_discount'       => '7.5',
			'fixed_coupons'        => 'parts',
			'rate_limit'           => 60,
			'trusted_proxy_header' => 'CF-Connecting-IP',
			'kit_defaults'         => array(
				'assembly' => array(
					'schema_version' => 1,
					'default_on'     => true,
					'fee'            => '15.5',
					'lead_time'      => 'Two working days',
					'returns_notice' => 'An assembled board cannot be returned.',
				),
			),
		);
		$terms  = BuildTerms::read( $stored + array( 'attribute_mapping' => array() ) );
		self::assertSame( 750, $terms->discount );
		self::assertSame( 'parts', $terms->fixedCoupons );
		self::assertSame( 60, $terms->rateLimit );
		self::assertSame( 'CF-Connecting-IP', $terms->proxyHeader );
		self::assertSame( $stored['kit_defaults']['assembly'], $terms->assembly );
		self::assertSame( $stored, $terms->toArray() );
	}

	public function test_one_wrong_key_leaves_the_others_as_they_are(): void {
		$terms = BuildTerms::read(
			array(
				'build_discount' => '100',
				'rate_limit'     => 30,
			)
		);
		self::assertSame( 0, $terms->discount );
		self::assertSame( 30, $terms->rateLimit );
	}

	/**
	 * @return iterable<string, array{mixed, ?int, string}>
	 */
	public static function discounts(): iterable {
		yield 'none' => array( '0', 0, '0' );
		yield 'whole' => array( '5', 500, '5' );
		yield 'one place' => array( '7.5', 750, '7.5' );
		yield 'two places' => array( '12.25', 1225, '12.25' );
		yield 'a small second place' => array( '0.05', 5, '0.05' );
		yield 'zeros it does not need' => array( '10.50', 1050, '10.5' );
		yield 'a zero whole written twice' => array( '07.00', 700, '7' );
		yield 'the most' => array( '99.99', 9999, '99.99' );
		yield 'all of it' => array( '100', null, '' );
		yield 'three places' => array( '5.125', null, '' );
		yield 'a comma' => array( '7,5', null, '' );
		yield 'a sign' => array( '-5', null, '' );
		yield 'no whole' => array( '.5', null, '' );
		yield 'no places' => array( '5.', null, '' );
		yield 'a line after' => array( "5\n", null, '' );
		yield 'words before' => array( 'about 5', null, '' );
		yield 'empty' => array( '', null, '' );
		yield 'a number' => array( 5, null, '' );
		yield 'a float' => array( 7.5, null, '' );
	}

	#[DataProvider( 'discounts' )]
	public function test_a_discount_is_a_percentage_below_100_kept_in_basis_points( mixed $value, ?int $points, string $written ): void {
		self::assertSame( $points, BuildTerms::discountFrom( $value ) );
		if ( null !== $points ) {
			self::assertSame( $written, BuildTerms::read( array( 'build_discount' => $value ) )->toArray()['build_discount'] );
		}
	}

	public function test_a_fixed_product_coupon_lands_on_the_kit_or_on_the_parts(): void {
		self::assertSame( 'kit', BuildTerms::couponsFrom( 'kit' ) );
		self::assertSame( 'parts', BuildTerms::couponsFrom( 'parts' ) );
		self::assertNull( BuildTerms::couponsFrom( 'Kit' ) );
		self::assertNull( BuildTerms::couponsFrom( '' ) );
		self::assertNull( BuildTerms::couponsFrom( null ) );
		self::assertNull( BuildTerms::couponsFrom( true ) );
	}

	public function test_the_rate_limit_is_a_whole_number_from_10_to_6000(): void {
		self::assertNull( BuildTerms::rateFrom( 9 ) );
		self::assertSame( 10, BuildTerms::rateFrom( 10 ) );
		self::assertSame( 120, BuildTerms::rateFrom( 120 ) );
		self::assertSame( 6000, BuildTerms::rateFrom( 6000 ) );
		self::assertNull( BuildTerms::rateFrom( 6001 ) );
		self::assertNull( BuildTerms::rateFrom( '120' ) );
		self::assertNull( BuildTerms::rateFrom( 120.0 ) );
		self::assertNull( BuildTerms::rateFrom( null ) );
	}

	public function test_the_proxy_header_is_a_headers_name_or_empty(): void {
		self::assertSame( '', BuildTerms::headerFrom( '' ) );
		self::assertSame( 'X-Forwarded-For', BuildTerms::headerFrom( 'X-Forwarded-For' ) );
		self::assertSame( 'a', BuildTerms::headerFrom( 'a' ) );
		self::assertSame( 'X' . str_repeat( '1', 63 ), BuildTerms::headerFrom( 'X' . str_repeat( '1', 63 ) ) );
		self::assertNull( BuildTerms::headerFrom( 'X' . str_repeat( '1', 64 ) ) );
		self::assertNull( BuildTerms::headerFrom( '1-Header' ) );
		self::assertNull( BuildTerms::headerFrom( '-Header' ) );
		self::assertNull( BuildTerms::headerFrom( 'HTTP_X_REAL_IP' ) );
		self::assertNull( BuildTerms::headerFrom( 'X-Real-IP: 1' ) );
		self::assertNull( BuildTerms::headerFrom( "X-Real-IP\n" ) );
		self::assertNull( BuildTerms::headerFrom( ' X-Real-IP' ) );
		self::assertNull( BuildTerms::headerFrom( null ) );
		self::assertNull( BuildTerms::headerFrom( 0 ) );
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function fees(): iterable {
		yield 'none' => array( '0', '0' );
		yield 'whole' => array( '15', '15' );
		yield 'cents' => array( '15.50', '15.5' );
		yield 'a small cent' => array( '15.05', '15.05' );
		yield 'four places' => array( '0.0001', '0.0001' );
		yield 'zeros in the middle' => array( '20.0100', '20.01' );
		yield 'the most' => array( '999999999.9999', '999999999.9999' );
	}

	#[DataProvider( 'fees' )]
	public function test_a_new_kits_assembly_is_an_assembly_document_with_its_fee_written_plainly( string $fee, string $written ): void {
		$document = array(
			'schema_version' => 1,
			'default_on'     => true,
			'fee'            => $fee,
			'lead_time'      => 'Two working days',
			'returns_notice' => '',
			'another'        => 'field',
		);
		self::assertSame(
			array(
				'schema_version' => 1,
				'default_on'     => true,
				'fee'            => $written,
				'lead_time'      => 'Two working days',
				'returns_notice' => '',
			),
			BuildTerms::assemblyFrom( $document )
		);
	}

	public function test_an_assembly_the_domain_refuses_is_no_default(): void {
		self::assertNull( BuildTerms::assemblyFrom( null ) );
		self::assertNull( BuildTerms::assemblyFrom( array( 'schema_version' => 2 ) + self::NO_ASSEMBLY ) );
		self::assertNull( BuildTerms::assemblyFrom( array( 'fee' => '15,50' ) + self::NO_ASSEMBLY ) );
		self::assertNull( BuildTerms::assemblyFrom( array( 'lead_time' => str_repeat( 'a', 201 ) ) + self::NO_ASSEMBLY ) );
		self::assertSame( self::NO_ASSEMBLY, BuildTerms::assemblyFrom( self::NO_ASSEMBLY ) );
	}
}
