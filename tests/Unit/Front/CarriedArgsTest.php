<?php
/**
 * The arguments a kit's page carries to the Builder page.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Front\CarriedArgs;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * M14's audit, F10: the rule that picks what is carried (F38, #161) read WordPress's request from a global inside a
 * hook class. It is a service now, given the request, so a fake one tests it.
 */
final class CarriedArgsTest extends TestCase {

	private static function request( string ...$vars ): stdClass {
		$request             = new stdClass();
		$request->query_vars = array_fill_keys( $vars, 'complete' );
		return $request;
	}

	public function test_every_other_argument_is_carried_and_the_pages_own_query_variables_are_not(): void {
		self::assertSame(
			array(
				'utm_source' => 'newsletter',
				'gclid'      => 'Cj0KCQ',
			),
			CarriedArgs::of(
				array(
					'utm_source' => 'newsletter',
					'product'    => 'complete',
					'gclid'      => 'Cj0KCQ',
				),
				self::request( 'product', 'post_type' )
			)
		);
		self::assertSame( array( 'product' => 'complete' ), CarriedArgs::of( array( 'product' => 'complete' ), self::request() ), 'a request that found nothing by it' );
		self::assertSame( array( 'product' => 'complete' ), CarriedArgs::of( array( 'product' => 'complete' ), null ), 'no request' );
		self::assertSame( array( 'product' => 'complete' ), CarriedArgs::of( array( 'product' => 'complete' ), new stdClass() ), 'a request with no query variables' );
		$odd             = new stdClass();
		$odd->query_vars = 'product';
		self::assertSame( array( 'product' => 'complete' ), CarriedArgs::of( array( 'product' => 'complete' ), $odd ), 'query variables that are no list' );
	}

	public function test_the_build_a_name_that_is_no_tags_and_a_value_that_is_no_text_are_left(): void {
		self::assertSame(
			array(
				'utm_campaign' => rawurlencode( 'spring sale' ),
				'a.b-c_9'      => 'x',
			),
			CarriedArgs::of(
				array(
					'cbuild'       => 'deck',
					'utm_campaign' => 'spring <b>sale</b>',
					'odd'          => array( 'a' => 'b' ),
					'bad name'     => 'x',
					'a.b-c_9'      => 'x',
					7              => 'x',
					str_repeat( 'a', 65 ) => 'x',
					str_repeat( 'b', 64 ) . "\n" => 'x',
				),
				self::request()
			)
		);
		self::assertArrayHasKey( str_repeat( 'a', 64 ), CarriedArgs::of( array( str_repeat( 'a', 64 ) => 'x' ), self::request() ), 'a name of 64 characters' );
		self::assertSame( array( 'q' => 'a%20b%26c' ), CarriedArgs::of( array( 'q' => 'a b&c' ), self::request() ), 'encoded for the address' );
	}
}
