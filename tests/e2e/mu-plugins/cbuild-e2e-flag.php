<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e flagged part
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_flag with a part's ID, flags that part as Mature Content does, by a class through cbuild_part_classes, and blurs the images that class reaches, about the 22 px Mature Content blurs a part with (D-309). Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

$cbuild_e2e_flag = isset( $_COOKIE['cbuild_e2e_flag'] ) ? (int) $_COOKIE['cbuild_e2e_flag'] : 0;

if ( $cbuild_e2e_flag > 0 ) {
	add_filter(
		'cbuild_part_classes',
		static fn( $classes, $part ) => $cbuild_e2e_flag === (int) $part ? array_merge( (array) $classes, array( 'cbuild-e2e-flagged' ) ) : $classes,
		10,
		2
	);
	add_action(
		'wp_head',
		static function () {
			echo '<style id="cbuild-e2e-flag">img.cbuild-e2e-flagged,.cbuild-e2e-flagged img{filter:blur(22px)}</style>';
		},
		99
	);
}
