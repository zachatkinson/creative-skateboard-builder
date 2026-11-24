<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e slow end
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_slow_end, the Store API's add-item ends a second late, after its answer has gone out and before WooCommerce saves the session at shutdown, as a loaded server's request can (issues #51 and #108). Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( isset( $_COOKIE['cbuild_e2e_slow_end'] ) && str_contains( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/wc/store/v1/cart/add-item' ) ) {
	// Before WooCommerce's session handlers save, at 20 (includes/class-wc-session-handler.php:105).
	add_action(
		'shutdown',
		static function () {
			usleep( 1000000 );
		},
		19
	);
}
