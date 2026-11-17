<?php
/**
 * The upgrade's mixed request: the previous release's classes in memory, this release's files on disk.
 *
 * WordPress replaces a plugin's files in the middle of a request (the upload screen's "Replace current with uploaded",
 * Dashboard › Updates, WP-CLI) and the request goes on: hooks fire after the copy, among them `deleted_post` for the
 * uploaded zip's attachment. The previous release's callbacks still run, ask its container for a service, and get this
 * release's class, built from the previous release's bindings and factories (Cowork's M7 review, F3; D-208).
 *
 * Two passes, each run with WP-CLI and the copy skipped, so that nothing of it is loaded until this script loads it:
 *   1. `services`: with the previous release's files, every class of its `src/` that its own container builds, which
 *      is every service its callbacks can ask for, written to a file.
 *      wp --skip-plugins=<copy> eval-file tools/upgrade/graph.php services <the copy's folder> <the list>
 *   2. `check`: the previous release booted as one kind of request boots it (admin: the upload screen and Dashboard ›
 *      Updates; cron: automatic updates; cli: `wp plugin update`), the new files put in the copy's place as the
 *      upgrader does, and each service on the list asked of that same container. Each one it cannot build, and each public method that no longer takes what the previous release's callers pass, is named.
 *      wp --skip-plugins=<copy> eval-file tools/upgrade/graph.php check <the copy's folder> <the list> <the new build's folder> <admin|cron|cli>
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions -- WP-CLI's eval-file in wp-env, never the plugin.

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

$cbuild_args = $args ?? array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- eval-file's own.
$cbuild_mode = (string) ( $cbuild_args[0] ?? '' );
$cbuild_copy = (string) ( $cbuild_args[1] ?? '' );
$cbuild_list = (string) ( $cbuild_args[2] ?? '' );
$cbuild_new  = (string) ( $cbuild_args[3] ?? '' );
$cbuild_kind = (string) ( $cbuild_args[4] ?? '' );
if ( ! is_file( $cbuild_copy . '/creative-skateboard-builder.php' ) || '' === $cbuild_list || ! ( 'services' === $cbuild_mode || ( 'check' === $cbuild_mode && is_file( $cbuild_new . '/creative-skateboard-builder.php' ) && is_readable( $cbuild_list ) && in_array( $cbuild_kind, array( 'admin', 'cron', 'cli' ), true ) ) ) ) {
	WP_CLI::error( 'Usage: graph.php services <copy> <list>, or graph.php check <copy> <list> <new build> <admin|cron|cli>' );
}

$cbuild_plugin    = 'CreativeBranding\\SkateboardBuilder\\Plugin';
$cbuild_scope     = 'CreativeBranding\\SkateboardBuilder\\Scope';
$cbuild_container = 'CreativeBranding\\SkateboardBuilder\\Container';
if ( class_exists( $cbuild_container, false ) ) {
	WP_CLI::error( 'The plugin is already loaded: skip the copy with --skip-plugins.' );
}

/**
 * A class's public methods, each as the calls an earlier caller may make of it: how many arguments it needs, and the
 * type each takes.
 *
 * @param string $class The class.
 * @return array<string, array{needs: int, takes: list<string>}>
 */
function cbuild_upgrade_methods( string $class ): array {
	$methods = array();
	foreach ( ( new ReflectionClass( $class ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
		if ( $method->isConstructor() ) {
			continue;
		}
		$methods[ $method->getName() ] = array(
			'needs' => $method->getNumberOfRequiredParameters(),
			'takes' => array_map( static fn( ReflectionParameter $p ): string => (string) $p->getType(), $method->getParameters() ),
		);
	}
	return $methods;
}

if ( 'services' === $cbuild_mode ) {
	require $cbuild_copy . '/creative-skateboard-builder.php';
	$cbuild_services = array();
	$cbuild_files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_copy . '/src', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $cbuild_files as $cbuild_file ) {
		$cbuild_class = 'CreativeBranding\\SkateboardBuilder\\' . str_replace( '/', '\\', substr( $cbuild_file->getPathname(), strlen( $cbuild_copy . '/src/' ), -4 ) );
		try {
			// A fresh container each time, as a callback's first ask would find it.
			( new $cbuild_container() )->get( $cbuild_class );
			$cbuild_services[ $cbuild_class ] = cbuild_upgrade_methods( $cbuild_class );
		} catch ( Throwable ) {
			// A value, an interface with no binding, an enum: nothing the container hands out.
			continue;
		}
	}
	ksort( $cbuild_services );
	file_put_contents( $cbuild_list, (string) wp_json_encode( $cbuild_services, JSON_PRETTY_PRINT ) );
	WP_CLI::success( sprintf( '%d services the previous release builds, in %s.', count( $cbuild_services ), $cbuild_list ) );
	return;
}

// The previous release, booted as this kind of request boots it: its main file, then the Hookables of its scopes.
require $cbuild_copy . '/creative-skateboard-builder.php';
$cbuild_c = $cbuild_plugin::container();
foreach ( $cbuild_c->hookablesFor( ...$cbuild_scope::atBoot( 'admin' === $cbuild_kind, 'cli' === $cbuild_kind, 'cron' === $cbuild_kind ) ) as $cbuild_hookable ) {
	$cbuild_hookable->register();
}
$cbuild_loaded = count( array_filter( get_declared_classes(), static fn( string $c ): bool => str_starts_with( $c, 'CreativeBranding\\SkateboardBuilder\\' ) ) );

// The upgrader's way: the old folder emptied, the new files copied in (wp-admin/includes/class-wp-upgrader.php).
$cbuild_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_copy, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $cbuild_files as $cbuild_file ) {
	$cbuild_file->isDir() ? rmdir( $cbuild_file->getPathname() ) : unlink( $cbuild_file->getPathname() );
}
$cbuild_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_new, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
foreach ( $cbuild_files as $cbuild_file ) {
	$cbuild_to = $cbuild_copy . '/' . substr( $cbuild_file->getPathname(), strlen( $cbuild_new ) + 1 );
	$cbuild_file->isDir() ? mkdir( $cbuild_to ) : copy( $cbuild_file->getPathname(), $cbuild_to );
}

// Every service the previous release can be asked for: its lists' keys and every class it built from its own files.
$cbuild_known = (array) json_decode( (string) file_get_contents( $cbuild_list ), true );
$cbuild_ids   = array_keys( $cbuild_known );
foreach ( array( 'bindings', 'factories', 'hookableClasses' ) as $cbuild_lists ) {
	if ( method_exists( $cbuild_container, $cbuild_lists ) ) {
		$cbuild_ids = array_merge( $cbuild_ids, array_keys( (array) ( new ReflectionMethod( $cbuild_container, $cbuild_lists ) )->invoke( null ) ) );
	}
}
$cbuild_ids = array_values( array_unique( $cbuild_ids ) );

$cbuild_failed = array();
foreach ( $cbuild_ids as $cbuild_id ) {
	try {
		$cbuild_service = $cbuild_c->get( $cbuild_id );
	} catch ( Throwable $cbuild_e ) {
		$cbuild_failed[] = sprintf( '%s is not built: %s', $cbuild_id, $cbuild_e->getMessage() );
		continue;
	}
	// Each public method the previous release's callers may call takes what they pass: no more arguments needed, the
	// same type for each.
	$cbuild_now = cbuild_upgrade_methods( $cbuild_service::class );
	foreach ( (array) ( $cbuild_known[ $cbuild_id ] ?? array() ) as $cbuild_name => $cbuild_was ) {
		$cbuild_is = $cbuild_now[ $cbuild_name ] ?? null;
		if ( null === $cbuild_is ) {
			$cbuild_failed[] = sprintf( '%s::%s() is gone', $cbuild_id, $cbuild_name );
		} elseif ( $cbuild_is['needs'] > $cbuild_was['needs'] || array_slice( $cbuild_is['takes'], 0, count( $cbuild_was['takes'] ) ) !== array_slice( $cbuild_was['takes'], 0, count( $cbuild_is['takes'] ) ) ) {
			$cbuild_failed[] = sprintf( '%s::%s() took (%s) and takes (%s), needing %d', $cbuild_id, $cbuild_name, implode( ', ', $cbuild_was['takes'] ), implode( ', ', $cbuild_is['takes'] ), $cbuild_is['needs'] );
		}
	}
}

WP_CLI::log( sprintf( 'A %s request: %d of the previous release\'s classes loaded at boot, then %d services asked of its container.', $cbuild_kind, $cbuild_loaded, count( $cbuild_ids ) ) );
foreach ( $cbuild_failed as $cbuild_line ) {
	WP_CLI::log( '  ' . $cbuild_line );
}
if ( array() !== $cbuild_failed ) {
	WP_CLI::error( sprintf( '%d services or methods the previous release\'s callers use do not fit this release\'s files.', count( $cbuild_failed ) ) );
}
WP_CLI::success( 'Every one of them builds from this release\'s files, and takes the calls the previous release makes.' );
