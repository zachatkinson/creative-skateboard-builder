<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e hostile theme
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for a browser that sends the cookie cbuild_e2e_hostile, the page gets a theme's aggressive rules, printed after every stylesheet as a page builder's per-block styles are, so the builder's own rules are tested against a theme's that outrank its resets (Cowork's review of 1.2.0, F32 and F33, D-416). Off without the cookie. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( ! isset( $_COOKIE['cbuild_e2e_hostile'] ) ) {
	return;
}

// The container a page builder's row draws, here around a product's add-to-cart form and what prints after it, as
// Kadence Blocks' row did around the demo's on the deck page: a dark row whose links are cream.
add_action(
	'woocommerce_before_add_to_cart_form',
	static function () {
		echo '<div class="cbuild-e2e-row">';
	},
	1
);
add_action(
	'woocommerce_after_add_to_cart_form',
	static function () {
		echo '</div>';
	},
	PHP_INT_MAX
);

// The content's container, as a classic theme's `.single-content` on the body's every page.
add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'cbuild-e2e-hostile';
		return $classes;
	}
);

add_action(
	'wp_head',
	static function () {
		$rules = array(
			// A theme's link colour for its content: blue that reads on white, not on a dark fill.
			'.cbuild-e2e-hostile .wp-site-blocks a,.cbuild-e2e-hostile .site a{color:rgb(0,102,204)}',
			// Kadence Blocks' `.kb-row-layout-id…_pdp-b a`: a row's links in its own colour, cream on the row's dark ground,
			// after the content's and at its weight, as a row's styles print after the theme's.
			'.cbuild-e2e-row{background:#121212;color:#f4f1ea;padding:16px}',
			'.cbuild-e2e-hostile .cbuild-e2e-row a{color:rgb(244,241,234)}',
			// Kadence's `.single-content p`: a paragraph's margin at its block's end, a class and an element, over a
			// `:where()` reset of no weight.
			'.cbuild-e2e-hostile p{margin:0 0 32px}',
			// A heading's margins, as Kadence's `.single-content h2` and Storefront's.
			'.cbuild-e2e-hostile h1,.cbuild-e2e-hostile h2,.cbuild-e2e-hostile h3,.cbuild-e2e-hostile h4{margin:1.5em 0 .75em}',
			// Every button the theme's own, Add to cart's yellow with dark words, as the demo's.
			'button,.button,a.button,.wp-element-button{background:rgb(232,228,0);color:rgb(18,18,18);border:0;border-radius:6px;padding:.6em 1.4em;font-weight:600}',
		);
		echo '<style id="cbuild-e2e-hostile">' . implode( '', $rules ) . '</style>';
	},
	PHP_INT_MAX
);
