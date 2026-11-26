<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e veil
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: blurs the 3D view as a plugin such as Mature Content does (D-468). The cookie cbuild_e2e_veil with a part's ID adds `is-flagged` to that part's classes through cbuild_part_classes, blurs a canvas with it, and puts a veil of its own in `.cbuild-3d__frame`, a plugin's place in the 3D view. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

$cbuild_e2e_veil = isset( $_COOKIE['cbuild_e2e_veil'] ) ? (int) $_COOKIE['cbuild_e2e_veil'] : 0;

if ( $cbuild_e2e_veil > 0 ) {
	add_filter(
		'cbuild_part_classes',
		static function ( $classes, $part ) use ( $cbuild_e2e_veil ) {
			return $cbuild_e2e_veil === (int) $part ? array_merge( (array) $classes, array( 'is-flagged' ) ) : $classes;
		},
		10,
		2
	);
	add_action(
		'wp_footer',
		static function () {
			echo '<style id="cbuild-e2e-veil">.cbuild-3d__canvas.is-flagged{filter:blur(22px)}html.mcw-revealed .cbuild-3d__canvas.is-flagged{filter:none}'
				. '.cbuild-e2e-veil{position:absolute;inset:0;display:grid;place-items:center;pointer-events:none}'
				. '.cbuild-e2e-veil button{pointer-events:auto}</style>';
			echo '<script id="cbuild-e2e-veil-script">document.querySelectorAll(".cbuild-3d__frame").forEach(function(frame){'
				. 'var veil=document.createElement("div");veil.className="cbuild-e2e-veil";'
				. 'var reveal=document.createElement("button");reveal.type="button";reveal.textContent="Show";'
				. 'reveal.addEventListener("click",function(){document.documentElement.classList.add("mcw-revealed");});'
				. 'veil.appendChild(reveal);frame.appendChild(veil);});</script>';
		},
		99
	);
}
