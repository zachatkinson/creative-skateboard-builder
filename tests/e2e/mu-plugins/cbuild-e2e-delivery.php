<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e delivery
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_delivery, the builder's delivery line and arrival line say something, as another plugin's answers to cbuild_delivery_line and cbuild_arrival_estimate do on the demo, so a test sees the bar's two lines (Cowork's review of 1.2.0, F33). Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( ! isset( $_COOKIE['cbuild_e2e_delivery'] ) ) {
	return;
}

add_filter( 'cbuild_delivery_line', static fn() => 'This build ships free to Ontario.' );
add_filter( 'cbuild_arrival_estimate', static fn() => 'Arrives Tue, Dec 1' );
