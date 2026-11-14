<?php
/**
 * Constants the main file defines at runtime, for PHPStan.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

define( 'CBUILD_VERSION', '0.6.0' );
define( 'CBUILD_FILE', __DIR__ . '/../creative-skateboard-builder.php' );
define( 'CBUILD_PATH', __DIR__ . '/../' );
define( 'CBUILD_URL', 'https://example.com/wp-content/plugins/creative-skateboard-builder/' );
define( 'COOKIEHASH', 'cookiehash' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant, for WooCommerce's session cookie's name.
define( 'WC_VERSION', '11.2.2' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WooCommerce's own constant, for the version check.
