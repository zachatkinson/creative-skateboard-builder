<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e Shop Kit template
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_template=shop-kit, a product's page is drawn with the markup of Kadence Shop Kit's product template, as Claude in Cowork's recheck of 0.6.0-rc.2 quotes it (finding 2). Shop Kit cannot run in CI. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( isset( $_COOKIE['cbuild_e2e_template'] ) && 'shop-kit' === $_COOKIE['cbuild_e2e_template'] ) {
	add_filter(
		'template_include',
		static function ( $template ) {
			return is_singular( 'product' ) ? WP_PLUGIN_DIR . '/creative-skateboard-builder/tests/e2e/fixtures/shop-kit-product.php' : $template;
		},
		99
	);
}
