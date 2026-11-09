<?php
/**
 * The public API, read from the code (docs/AUDIT.md: Contracts). `--write` rewrites tests/Fixtures/api-surface.json;
 * `--check` fails when the code's API differs from the snapshot, or when an item is missing from docs/HOOKS.md.
 * A change to the API is therefore three edits in one commit: the code, the snapshot and HOOKS.md (CLAUDE.md).
 *
 * What it reads, and from where:
 *   filters and actions   apply_filters()/do_action() of a cbuild_* hook, anywhere the plugin ships;
 *   constants             CBUILD_* constants: those a site may define (checked with defined()) and those the plugin
 *                         defines for others to read;
 *   options, jobs, meta   Support\Options;
 *   tables                Support\Tables;
 *   site transients       Support\Options::siteTransients();
 *   REST routes           register_rest_route( 'cbuild/v1', ... );
 *   abilities             wp_register_ability();
 *   WP-CLI commands       WP_CLI::add_command();
 *   blocks                block.json under assets/src;
 *   DOM events            'cbuild:*' strings under assets/src.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- A build script, run outside WordPress.

$cbuild_root = dirname( __DIR__ );
$cbuild_mode = $argv[1] ?? '--check';
if ( ! in_array( $cbuild_mode, array( '--check', '--write' ), true ) ) {
	fwrite( STDERR, "Usage: php tools/api-surface.php --check|--write\n" );
	exit( 1 );
}

/**
 * Every PHP file the plugin ships.
 *
 * @param string $root The repository.
 * @return string[]
 */
function cbuild_api_php_files( string $root ): array {
	$files = array( $root . '/creative-skateboard-builder.php', $root . '/uninstall.php' );
	if ( is_dir( $root . '/src' ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( str_ends_with( (string) $file, '.php' ) ) {
				$files[] = (string) $file;
			}
		}
	}
	return array_values( array_filter( $files, 'is_file' ) );
}

/**
 * Every file under a directory with a suffix.
 *
 * @param string $dir    Directory.
 * @param string $suffix File suffix.
 * @return string[]
 */
function cbuild_api_files( string $dir, string $suffix ): array {
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$out      = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( str_ends_with( (string) $file, $suffix ) ) {
			$out[] = (string) $file;
		}
	}
	return $out;
}

/**
 * Every match of a pattern's first group across files, sorted and unique.
 *
 * @param string[] $files   Files.
 * @param string   $pattern Pattern with one group.
 * @return string[]
 */
function cbuild_api_matches( array $files, string $pattern ): array {
	$out = array();
	foreach ( $files as $file ) {
		$code = (string) file_get_contents( $file );
		if ( preg_match_all( $pattern, $code, $m ) ) {
			$out = array_merge( $out, $m[1] );
		}
	}
	$out = array_values( array_unique( $out ) );
	sort( $out );
	return $out;
}

/**
 * A class's constants and list methods, without WordPress: the file is required with ABSPATH defined.
 *
 * @param string $root  The repository.
 * @param string $class Class name.
 * @return ReflectionClass<object>|null
 */
function cbuild_api_class( string $root, string $class ): ?ReflectionClass {
	$file = $root . '/src/' . str_replace( array( 'CreativeBranding\\SkateboardBuilder\\', '\\' ), array( '', '/' ), $class ) . '.php';
	if ( ! is_file( $file ) ) {
		return null;
	}
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $file;
	return class_exists( $class ) ? new ReflectionClass( $class ) : null;
}

/**
 * A static list method's answer, or an empty list.
 *
 * @param ReflectionClass<object>|null $class  Class.
 * @param string                       $method Method.
 * @return string[]
 */
function cbuild_api_list( ?ReflectionClass $class, string $method ): array {
	if ( null === $class || ! $class->hasMethod( $method ) ) {
		return array();
	}
	$out = $class->getMethod( $method )->invoke( null );
	$out = array_values( array_map( 'strval', is_array( $out ) ? $out : array() ) );
	sort( $out );
	return $out;
}

/**
 * A class constant's string value, or ''.
 *
 * @param ReflectionClass<object>|null $class Class.
 * @param string                       $name  Constant.
 */
function cbuild_api_const( ?ReflectionClass $class, string $name ): string {
	if ( null === $class || ! $class->hasConstant( $name ) ) {
		return '';
	}
	$value = $class->getConstant( $name );
	return is_scalar( $value ) ? (string) $value : '';
}

$cbuild_php     = cbuild_api_php_files( $cbuild_root );
$cbuild_options = cbuild_api_class( $cbuild_root, 'CreativeBranding\\SkateboardBuilder\\Support\\Options' );
$cbuild_tables  = cbuild_api_class( $cbuild_root, 'CreativeBranding\\SkateboardBuilder\\Support\\Tables' );
$cbuild_assets  = array_merge( cbuild_api_files( $cbuild_root . '/assets/src', '.ts' ), cbuild_api_files( $cbuild_root . '/assets/src', '.js' ) );
$cbuild_blocks  = array();
foreach ( cbuild_api_files( $cbuild_root . '/assets/src', 'block.json' ) as $cbuild_block_file ) {
	$cbuild_block = json_decode( (string) file_get_contents( $cbuild_block_file ), true );
	if ( is_array( $cbuild_block ) && isset( $cbuild_block['name'] ) && is_string( $cbuild_block['name'] ) ) {
		$cbuild_blocks[] = $cbuild_block['name'];
	}
}
sort( $cbuild_blocks );

$cbuild_tables_list = array_map( static fn( string $t ): string => cbuild_api_const( $cbuild_tables, 'PREFIX' ) . $t, cbuild_api_list( $cbuild_tables, 'all' ) );
sort( $cbuild_tables_list );

$cbuild_surface = array(
	'filters'           => cbuild_api_matches( $cbuild_php, "/apply_filters(?:_deprecated|_ref_array)?\\(\\s*'(cbuild_[a-z0-9_]+)'/" ),
	'actions'           => cbuild_api_matches( $cbuild_php, "/do_action(?:_deprecated|_ref_array)?\\(\\s*'(cbuild_[a-z0-9_]+)'/" ),
	'constants'         => cbuild_api_matches( $cbuild_php, "/defined?\\(\\s*'(CBUILD_[A-Z0-9_]+)'/" ),
	'options'           => cbuild_api_list( $cbuild_options, 'names' ),
	'site_transients'   => cbuild_api_list( $cbuild_options, 'siteTransients' ),
	'tables'            => $cbuild_tables_list,
	'scheduled_actions' => cbuild_api_list( $cbuild_options, 'jobs' ),
	'queue_group'       => cbuild_api_const( $cbuild_options, 'QUEUE_GROUP' ),
	'log_source'        => cbuild_api_const( $cbuild_options, 'LOG_SOURCE' ),
	'meta_keys'         => cbuild_api_list( $cbuild_options, 'metaKeys' ),
	'settings_keys'     => cbuild_api_list( $cbuild_options, 'settingsKeys' ),
	'rest_routes'       => array_map( static fn( string $r ): string => 'cbuild/v1/' . ltrim( $r, '/' ), cbuild_api_matches( $cbuild_php, "/register_rest_route\\(\\s*(?:self::NAMESPACE|'cbuild\\/v1')\\s*,\\s*'([^']+)'/" ) ),
	'abilities'         => cbuild_api_matches( $cbuild_php, "/wp_register_ability\\(\\s*'([^']+)'/" ),
	'cli_commands'      => cbuild_api_matches( $cbuild_php, "/WP_CLI::add_command\\(\\s*'([^']+)'/" ),
	'blocks'            => $cbuild_blocks,
	'dom_events'        => cbuild_api_matches( $cbuild_assets, "/'(cbuild:[a-z0-9-]+)'/" ),
);

$cbuild_json     = json_encode( $cbuild_surface, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
$cbuild_snapshot = $cbuild_root . '/tests/Fixtures/api-surface.json';

if ( '--write' === $cbuild_mode ) {
	if ( ! is_dir( dirname( $cbuild_snapshot ) ) ) {
		mkdir( dirname( $cbuild_snapshot ), 0775, true );
	}
	file_put_contents( $cbuild_snapshot, $cbuild_json );
	echo "Snapshot written to tests/Fixtures/api-surface.json. Update docs/HOOKS.md and CHANGELOG.md in the same commit.\n";
	exit( 0 );
}

$cbuild_status = 0;
$cbuild_stored = is_readable( $cbuild_snapshot ) ? (string) file_get_contents( $cbuild_snapshot ) : '';
if ( $cbuild_stored !== $cbuild_json ) {
	fwrite( STDERR, "The public API differs from tests/Fixtures/api-surface.json. If the change is meant, run composer api:snapshot and update docs/HOOKS.md and CHANGELOG.md in the same commit.\n" );
	$cbuild_old = json_decode( $cbuild_stored, true );
	$cbuild_old = is_array( $cbuild_old ) ? $cbuild_old : array();
	foreach ( $cbuild_surface as $cbuild_key => $cbuild_items ) {
		$cbuild_was = $cbuild_old[ $cbuild_key ] ?? ( is_array( $cbuild_items ) ? array() : '' );
		if ( $cbuild_was !== $cbuild_items ) {
			fwrite( STDERR, "  {$cbuild_key}: " . json_encode( $cbuild_was, JSON_UNESCAPED_SLASHES ) . ' -> ' . json_encode( $cbuild_items, JSON_UNESCAPED_SLASHES ) . "\n" );
		}
	}
	$cbuild_status = 1;
}

$cbuild_hooks = is_readable( $cbuild_root . '/docs/HOOKS.md' ) ? (string) file_get_contents( $cbuild_root . '/docs/HOOKS.md' ) : '';
foreach ( $cbuild_surface as $cbuild_key => $cbuild_items ) {
	foreach ( is_array( $cbuild_items ) ? $cbuild_items : array( $cbuild_items ) as $cbuild_item ) {
		if ( '' !== $cbuild_item && ! str_contains( $cbuild_hooks, '`' . $cbuild_item . '`' ) ) {
			fwrite( STDERR, "docs/HOOKS.md does not list {$cbuild_key} `{$cbuild_item}`.\n" );
			$cbuild_status = 1;
		}
	}
}

if ( 0 === $cbuild_status ) {
	$cbuild_count = 0;
	foreach ( $cbuild_surface as $cbuild_items ) {
		$cbuild_count += is_array( $cbuild_items ) ? count( $cbuild_items ) : ( '' === $cbuild_items ? 0 : 1 );
	}
	echo "API surface: {$cbuild_count} items match the snapshot and docs/HOOKS.md.\n";
}
exit( $cbuild_status );
