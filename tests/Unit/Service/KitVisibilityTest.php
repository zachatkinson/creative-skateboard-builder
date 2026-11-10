<?php
/**
 * Kits kept out of the sitemap and of product markup.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\KitVisibility;
use PHPUnit\Framework\TestCase;

final class KitVisibilityTest extends TestCase {

	private const NOT_KITS = array(
		'taxonomy' => 'product_type',
		'field'    => 'slug',
		'terms'    => array( 'cbuild_kit' ),
		'operator' => 'NOT IN',
	);

	public function test_the_products_sitemap_query_leaves_kits_out_and_keeps_what_it_asked(): void {
		$visibility = new KitVisibility( 'cbuild_kit' );
		self::assertSame(
			array(
				'orderby'   => 'ID',
				'tax_query' => array( self::NOT_KITS ),
			),
			$visibility->sitemapQuery( array( 'orderby' => 'ID' ), 'product' )
		);

		$asked = array(
			'taxonomy' => 'product_cat',
			'terms'    => array( 4 ),
		);
		self::assertSame(
			array( 'tax_query' => array( $asked, self::NOT_KITS ) ),
			$visibility->sitemapQuery( array( 'tax_query' => array( $asked ) ), 'product' ),
			'another plugin\'s condition stays'
		);
		self::assertSame(
			array( 'tax_query' => array( self::NOT_KITS ) ),
			$visibility->sitemapQuery( array( 'tax_query' => 'not a query' ), 'product' )
		);
	}

	public function test_another_post_types_sitemap_and_what_is_not_a_query_are_left_as_they_are(): void {
		$visibility = new KitVisibility( 'cbuild_kit' );
		$args       = array( 'orderby' => 'ID' );
		self::assertSame( $args, $visibility->sitemapQuery( $args, 'page' ) );
		self::assertSame( $args, $visibility->sitemapQuery( $args, 'product_variation' ) );
		self::assertSame( $args, $visibility->sitemapQuery( $args, null ) );
		self::assertSame( 'broken', $visibility->sitemapQuery( 'broken', 'product' ) );
		self::assertNull( $visibility->sitemapQuery( null, 'product' ) );
	}

	public function test_a_kit_has_no_markup_and_any_other_product_keeps_its_own(): void {
		$visibility = new KitVisibility( 'cbuild_kit' );
		$markup     = array( '@type' => 'Product' );
		self::assertSame( array(), $visibility->markup( $markup, 'cbuild_kit' ) );
		self::assertSame( $markup, $visibility->markup( $markup, 'simple' ) );
		self::assertSame( $markup, $visibility->markup( $markup, '' ) );
		self::assertSame( array(), ( new KitVisibility( 'other' ) )->markup( $markup, 'other' ), 'the type is the one it was given' );
	}
}
