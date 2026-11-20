<?php
/**
 * The domain's test bootstrap: the autoloader and nothing else. No WordPress, no shim and no constant of the plugin's,
 * so a domain class that reaches for any of them fails here (docs/ROADMAP.md, milestone 2: Done when).
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// The path-coverage shards (tools/coverage.sh) load every domain class first, in one order. A branch's ID is its opcode's
// offset, and PHP compiles a file a little differently when a class it names is already loaded, so shards that load the
// domain in different orders number the same branch differently, and their merge counts it twice. Loaded alike, every
// shard compiles the domain alike.
if ( '1' === getenv( 'CBUILD_COVERAGE_PRELOAD' ) ) {
	$cbuild_root  = dirname( __DIR__ ) . '/src/Domain/';
	$cbuild_files = array();
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_root, FilesystemIterator::SKIP_DOTS ) ) as $cbuild_file ) {
		if ( $cbuild_file instanceof SplFileInfo && 'php' === $cbuild_file->getExtension() ) {
			$cbuild_files[] = substr( $cbuild_file->getPathname(), strlen( $cbuild_root ), -4 );
		}
	}
	sort( $cbuild_files );
	foreach ( $cbuild_files as $cbuild_relative ) {
		$cbuild_type = 'CreativeBranding\\SkateboardBuilder\\Domain\\' . str_replace( '/', '\\', $cbuild_relative );
		class_exists( $cbuild_type ) || interface_exists( $cbuild_type ) || enum_exists( $cbuild_type ) || trait_exists( $cbuild_type );
	}
	unset( $cbuild_root, $cbuild_files, $cbuild_file, $cbuild_relative, $cbuild_type );
}
