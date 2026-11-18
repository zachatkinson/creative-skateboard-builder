<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e locale
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: switches the site's locale for one request when the browser suite's fr-ca or rtl project sends its header, and keeps the plugin's own strings in English unless the request asks for them translated (D-234). Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

add_filter(
	'locale',
	static function ( $locale ) {
		$wanted = isset( $_SERVER['HTTP_X_CBUILD_E2E_LOCALE'] ) ? (string) $_SERVER['HTTP_X_CBUILD_E2E_LOCALE'] : '';
		return in_array( $wanted, array( 'fr_CA', 'he_IL' ), true ) ? $wanted : $locale;
	}
);

// The fr-ca project runs every spec, which finds the builder by its English words: the site is in French, the plugin's
// own strings stay English. french.spec.ts asks for the plugin's French with a second header (D-234).
$cbuild_e2e_english = static fn(): bool => ! isset( $_SERVER['HTTP_X_CBUILD_E2E_TRANSLATED'] );
add_filter(
	'pre_load_textdomain',
	static fn( $loaded, $domain ) => 'creative-skateboard-builder' === $domain && $cbuild_e2e_english() ? false : $loaded,
	10,
	2
);
add_filter(
	'pre_load_script_translations',
	static fn( $translations, $file, $handle, $domain ) => 'creative-skateboard-builder' === $domain && $cbuild_e2e_english() ? '{"locale_data":{"messages":{"":{}}}}' : $translations,
	10,
	4
);
