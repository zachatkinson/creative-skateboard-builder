<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e flagged part
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: flags a part as Mature Content does, by a class through cbuild_part_classes, and blurs what that class reaches, about the 22 px Mature Content blurs a part with (D-309). The cookie cbuild_e2e_flag with a part's ID flags it as a whole, as `mcw-mature` does: every picture of it blurs. The cookie cbuild_e2e_flag_layer flags only its layer image, as `mcw-mature-layer` does: the pictures marked as the layer image (`…__photo--drawn`) and the board's layers blur, and a photo stays sharp (D-398). Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

$cbuild_e2e_flags = array(
	'cbuild-e2e-flagged'       => isset( $_COOKIE['cbuild_e2e_flag'] ) ? (int) $_COOKIE['cbuild_e2e_flag'] : 0,
	'cbuild-e2e-flagged-layer' => isset( $_COOKIE['cbuild_e2e_flag_layer'] ) ? (int) $_COOKIE['cbuild_e2e_flag_layer'] : 0,
);

if ( max( $cbuild_e2e_flags ) > 0 ) {
	add_filter(
		'cbuild_part_classes',
		static function ( $classes, $part ) use ( $cbuild_e2e_flags ) {
			foreach ( $cbuild_e2e_flags as $class => $flagged ) {
				if ( $flagged > 0 && $flagged === (int) $part ) {
					$classes = array_merge( (array) $classes, array( $class ) );
				}
			}
			return $classes;
		},
		10,
		2
	);
	add_action(
		'wp_head',
		static function () {
			echo '<style id="cbuild-e2e-flag">img.cbuild-e2e-flagged,.cbuild-e2e-flagged img,'
				. 'img.cbuild-e2e-flagged-layer[class*="__photo--drawn"],.cbuild-e2e-flagged-layer img[class*="__photo--drawn"],'
				. '.cbuild-board .cbuild-e2e-flagged-layer img,.cbuild-board img.cbuild-e2e-flagged-layer{filter:blur(22px)}</style>';
		},
		99
	);
}
