<?php
/**
 * A build's parts in one shipping package.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\BuildPackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( BuildPackages::class )]
final class BuildPackagesTest extends TestCase {

	/**
	 * A package of lines, each costing what is given.
	 *
	 * @param array<string, int|float|string> $lines Cart key to line total.
	 * @return array{contents: array<string, array{line_total: int|float|string}>, contents_cost: float, destination: string}
	 */
	private static function package( array $lines ): array {
		$contents = array();
		foreach ( $lines as $key => $total ) {
			$contents[ $key ] = array( 'line_total' => $total );
		}
		return array(
			'contents'      => $contents,
			'contents_cost' => (float) array_sum( $lines ),
			'destination'   => 'home',
		);
	}

	public function test_a_cart_in_one_package_or_with_no_build_to_keep_comes_back_as_it_went(): void {
		$one = array( self::package( array( 'deck' => 70, 'trucks' => 54 ) ) );
		self::assertSame( $one, BuildPackages::together( $one, array( array( 'deck', 'trucks' ) ) ) );

		$split = array( self::package( array( 'deck' => 70 ) ), self::package( array( 'trucks' => 54 ) ) );
		self::assertSame( $split, BuildPackages::together( $split, array() ) );
		self::assertSame( $split, BuildPackages::together( $split, array( array() ) ) );
		self::assertSame( $split, BuildPackages::together( $split, array( array( 'gone', 'deck' ) ) ), 'one part in a package is already together' );
	}

	public function test_a_split_builds_parts_go_to_the_package_of_its_first_part(): void {
		$packages = array(
			'a' => self::package( array( 'wax' => 3, 'trucks' => 54 ) ),
			'b' => self::package( array( 'deck' => 70 ) ),
			'c' => self::package( array( 'wheels' => '32.50' ) ),
		);
		$out      = BuildPackages::together( $packages, array( array( 'deck', 'trucks', 'wheels' ) ) );

		self::assertCount( 2, $out, 'the package a move emptied is gone' );
		self::assertSame( array( 0, 1 ), array_keys( $out ) );
		self::assertSame( array( 'wax' ), array_keys( $out[0]['contents'] ) );
		self::assertSame( 3.0, $out[0]['contents_cost'] );
		self::assertSame( array( 'deck', 'trucks', 'wheels' ), array_keys( $out[1]['contents'] ) );
		self::assertSame( 156.5, $out[1]['contents_cost'] );
		self::assertSame( 'home', $out[1]['destination'], 'the rest of a package is left as it was' );
		self::assertSame( array( 'line_total' => 54 ), $out[1]['contents']['trucks'] );
	}

	public function test_two_builds_are_each_kept_together_and_apart_from_each_other(): void {
		$packages = array(
			self::package( array( 'deck1' => 70, 'deck2' => 60 ) ),
			self::package( array( 'trucks1' => 54, 'trucks2' => 50 ) ),
		);
		$out      = BuildPackages::together( $packages, array( array( 'deck1', 'trucks1' ), array( 'trucks2', 'deck2' ) ) );
		self::assertSame( array( 'deck1', 'trucks1' ), array_keys( $out[0]['contents'] ) );
		self::assertSame( 124.0, $out[0]['contents_cost'] );
		self::assertSame( array( 'trucks2', 'deck2' ), array_keys( $out[1]['contents'] ) );
		self::assertSame( 110.0, $out[1]['contents_cost'] );
	}

	public function test_packages_under_a_shops_own_keys_keep_them_where_nothing_moves(): void {
		$split = array(
			'north' => self::package( array( 'deck' => 70 ) ),
			'south' => self::package( array( 'wax' => 3 ) ),
		);
		self::assertSame( $split, BuildPackages::together( $split, array( array( 'deck' ) ) ) );
	}

	public function test_a_part_no_package_holds_does_not_stop_the_rest_being_gathered(): void {
		$out = BuildPackages::together(
			array( self::package( array( 'trucks' => 54 ) ), self::package( array( 'deck' => 70 ) ), self::package( array( 'wax' => 3 ) ) ),
			array( array( 'deck', 'gone', 'trucks' ) )
		);
		self::assertCount( 2, $out, 'the emptied package is dropped and the one after it kept' );
		self::assertSame( array( 'deck', 'trucks' ), array_keys( $out[0]['contents'] ) );
		self::assertSame( array( 'wax' ), array_keys( $out[1]['contents'] ) );
	}

	public function test_a_package_no_move_touched_keeps_the_cost_it_came_with(): void {
		$other                  = self::package( array( 'wax' => 3 ) );
		$other['contents_cost'] = 99.0;
		$out                    = BuildPackages::together(
			array( $other, self::package( array( 'deck' => 70 ) ), self::package( array( 'trucks' => 54 ) ) ),
			array( array( 'deck', 'trucks' ) )
		);
		self::assertCount( 2, $out );
		self::assertSame( $other, $out[0] );
		self::assertSame( 124.0, $out[1]['contents_cost'] );
	}

	public function test_a_package_or_a_line_that_is_not_woocommerces_shape_is_passed_by(): void {
		$packages = array(
			'not a package',
			array( 'contents' => 'not lines' ),
			self::package( array( 'deck' => 70 ) ),
			array(
				'contents' => array(
					'trucks' => 'not a line',
					'grip'   => array( 'line_total' => 'not a number' ),
					'wax'    => array(),
				),
			),
		);
		$out      = BuildPackages::together( $packages, array( array( 'deck', 'trucks' ) ) );
		self::assertSame( 'not a package', $out[0] );
		self::assertSame( array( 'contents' => 'not lines' ), $out[1] );
		self::assertSame( array( 'deck', 'trucks' ), array_keys( $out[2]['contents'] ) );
		self::assertSame( 70.0, $out[2]['contents_cost'] );
		self::assertSame( array( 'grip', 'wax' ), array_keys( $out[3]['contents'] ) );
		self::assertSame( 0.0, $out[3]['contents_cost'] );
	}
}
