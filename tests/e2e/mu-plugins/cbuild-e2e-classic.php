<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e classic cart
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_classic, the theme is Storefront and the shop's cart and checkout pages are the shortcode pages the shop fixture made. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( isset( $_COOKIE['cbuild_e2e_classic'] ) ) {
	$cbuild_e2e_theme = static fn() => 'storefront';
	add_filter( 'template', $cbuild_e2e_theme );
	add_filter( 'stylesheet', $cbuild_e2e_theme );
	foreach ( array( 'cart', 'checkout' ) as $cbuild_e2e_page ) {
		add_filter(
			'woocommerce_get_' . $cbuild_e2e_page . '_page_id',
			static function ( $id ) use ( $cbuild_e2e_page ) {
				$pages = (array) get_option( 'e2e_classic_pages_for_cbuild', array() );
				return isset( $pages[ $cbuild_e2e_page ] ) ? (int) $pages[ $cbuild_e2e_page ] : $id;
			}
		);
	}
}
