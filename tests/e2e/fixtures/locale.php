<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e locale
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: switches the site's locale for one request when the browser suite's fr-ca or rtl project sends its header. Never shipped.
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
