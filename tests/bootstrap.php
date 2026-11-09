<?php
/**
 * Unit test bootstrap: no WordPress loaded.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/tests/Support/fake-abspath/' );
}
if ( ! defined( 'CBUILD_VERSION' ) ) {
	define( 'CBUILD_VERSION', '0.1.0' );
}

require_once __DIR__ . '/Support/wp-shims.php';
