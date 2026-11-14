<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e theme
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: for one request, shows the site in the theme the visual snapshots ask for by cookie, in its light or its dark colours. The site's own theme and settings are left as they are. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

$cbuild_e2e_theme  = isset( $_COOKIE['cbuild_e2e_theme'] ) ? (string) $_COOKIE['cbuild_e2e_theme'] : '';
$cbuild_e2e_scheme = isset( $_COOKIE['cbuild_e2e_scheme'] ) ? (string) $_COOKIE['cbuild_e2e_scheme'] : '';

if ( in_array( $cbuild_e2e_theme, array( 'twentytwentyfive', 'storefront', 'kadence' ), true ) ) {
	$cbuild_e2e_slug = static fn () => $cbuild_e2e_theme;
	add_filter( 'stylesheet', $cbuild_e2e_slug );
	add_filter( 'template', $cbuild_e2e_slug );
}

if ( 'dark' !== $cbuild_e2e_scheme ) {
	return;
}

// Twenty Twenty-Five: its own dark style, Evening, as a shop would pick it in the Site Editor.
add_filter(
	'wp_theme_json_data_user',
	static function ( $data ) {
		$file = get_theme_root() . '/twentytwentyfive/styles/01-evening.json';
		if ( 'twentytwentyfive' !== get_stylesheet() || ! is_readable( $file ) ) {
			return $data;
		}
		$evening = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $evening ) ? $data->update_with( $evening ) : $data;
	}
);

// Kadence: a dark palette, as its Customizer's palette sets it. Palette 3 is the strongest text, 9 the page.
add_filter(
	'kadence_palette_option',
	static function ( $value, $subkey ) {
		$dark = array(
			'palette1' => '#63b3ed',
			'palette2' => '#4299e1',
			'palette3' => '#f7fafc',
			'palette4' => '#edf2f7',
			'palette5' => '#cbd2d9',
			'palette6' => '#a0aec0',
			'palette7' => '#2d3748',
			'palette8' => '#252c39',
			'palette9' => '#1a202c',
		);
		return $dark[ $subkey ] ?? $value;
	},
	10,
	2
);

// Storefront: dark colours, as its Customizer's defaults. Storefront answers each `theme_mod_` itself with its
// defaults where the site set none, so the defaults are where to change them.
add_filter(
	'storefront_setting_default_values',
	static fn ( $defaults ) => array_merge(
		(array) $defaults,
		array(
			'background_color'                       => '1b1b1b',
			'storefront_heading_color'               => '#f0f0f0',
			'storefront_text_color'                  => '#d6d6d6',
			'storefront_accent_color'                => '#b79ce0',
			'storefront_header_background_color'     => '#111111',
			'storefront_header_text_color'           => '#d6d6d6',
			'storefront_header_link_color'           => '#f0f0f0',
			'storefront_footer_background_color'     => '#111111',
			'storefront_footer_heading_color'        => '#f0f0f0',
			'storefront_footer_text_color'           => '#d6d6d6',
			'storefront_footer_link_color'           => '#f0f0f0',
			'storefront_button_background_color'     => '#333333',
			'storefront_button_text_color'           => '#f0f0f0',
			'storefront_button_alt_background_color' => '#f0f0f0',
			'storefront_button_alt_text_color'       => '#1b1b1b',
		)
	)
);
