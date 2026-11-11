<?php
/**
 * The public API, read from the code (docs/AUDIT.md: Contracts). `--write` rewrites tests/Fixtures/api-surface.json;
 * `--check` fails when the code's API differs from the snapshot, when an item is missing from docs/HOOKS.md, or when
 * the changelog's entry for the version HOOKS.md dates it to does not name it. A change to the API is therefore four
 * edits in one commit: the code, the snapshot, HOOKS.md and CHANGELOG.md (CLAUDE.md).
 *
 * What it reads, and from where:
 *   filters and actions   apply_filters()/do_action() of a cbuild_* hook, anywhere the plugin ships;
 *   actions listened for  add_action() of a cbuild_* hook named in the call itself: one another plugin does, and the
 *                         plugin acts on. Its own jobs' hooks are named by Support\Options and listed with them;
 *   constants             CBUILD_* constants: those a site may define (checked with defined()) and those the plugin
 *                         defines for others to read;
 *   options, jobs, meta,
 *   product types         Support\Options;
 *   tables                Support\Tables;
 *   site transients       Support\Options::siteTransients();
 *   REST routes           register_rest_route( 'cbuild/v1', ... );
 *   abilities             wp_register_ability();
 *   WP-CLI commands       WP_CLI::add_command(), and each command's arguments and flags, from the OPTIONS of its
 *                         doc comment, which is what WP-CLI itself reads them from;
 *   CSV columns           Service\SpecSheet::columns(): the export's headings, which the import matches;
 *   Site Health tests     Health\SiteHealth's TEST_* constants;
 *   ability schemas       each ability's output schema, by a digest as the document schemas are;
 *   blocks                block.json under assets/src;
 *   DOM events            'cbuild:*' strings under assets/src;
 *   document schemas      schema/*.json, each by its path, and each one's content by a digest of its decoded JSON, so
 *                         a schema cannot change shape without the snapshot changing with it;
 *   spec keys             Domain\Spec\SpecKey's cases, with each one's kind and unit;
 *   message codes         Domain\Rule\MessageCode's cases, with the severities and sources a finding carries;
 *   build problems        Domain\Fit\Problem's cases: why the validator refuses a build;
 *   rule ids              Domain\Rule\RuleId's cases, with the names of the values each message code carries;
 *   refusal codes         the cases of the domain's five error enums: why a document, a spec, a measure, a part or a
 *                         sum was refused.
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
	// The disk's order is not the same on every file system (APFS and ext4 differ), and the snapshot is compared in order.
	sort( $out, SORT_STRING );
	return $out;
}

/**
 * The changelog's entries by version ('Unreleased' included), each with its text.
 *
 * @param string $changelog CHANGELOG.md.
 * @return array<string, string>
 */
function cbuild_api_changelog_entries( string $changelog ): array {
	$entries = array();
	$parts   = preg_split( '/^## \\[([^\\]]+)\\].*$/m', $changelog, -1, PREG_SPLIT_DELIM_CAPTURE );
	for ( $i = 1; $i + 1 < count( $parts ?: array() ); $i += 2 ) {
		$entries[ $parts[ $i ] ] = $parts[ $i + 1 ];
	}
	return $entries;
}

/**
 * The version docs/HOOKS.md gives an item: the first on a line that names it, or, where a heading names it, the first
 * before the next heading.
 *
 * @param string $hooks docs/HOOKS.md.
 * @param string $item  The item.
 */
function cbuild_api_since( string $hooks, string $item ): ?string {
	$lines   = explode( "\n", $hooks );
	$version = '/\\b(\\d+\\.\\d+\\.\\d+)\\b/';
	foreach ( $lines as $at => $line ) {
		if ( ! str_contains( $line, '`' . $item . '`' ) ) {
			continue;
		}
		if ( 1 === preg_match( $version, $line, $found ) ) {
			return $found[1];
		}
		if ( ! str_starts_with( $line, '#' ) ) {
			continue;
		}
		$count = count( $lines );
		for ( $next = $at + 1; $next < $count && ! str_starts_with( $lines[ $next ], '#' ); $next++ ) {
			if ( 1 === preg_match( $version, $lines[ $next ], $found ) ) {
				return $found[1];
			}
		}
	}
	return null;
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
	'listened_actions'  => cbuild_api_matches( $cbuild_php, "/add_action\\(\\s*'(cbuild_[a-z0-9_]+)'/" ),
	'constants'         => cbuild_api_matches( $cbuild_php, "/defined?\\(\\s*'(CBUILD_[A-Z0-9_]+)'/" ),
	'options'           => cbuild_api_list( $cbuild_options, 'names' ),
	'site_transients'   => cbuild_api_list( $cbuild_options, 'siteTransients' ),
	'tables'            => $cbuild_tables_list,
	'scheduled_actions' => cbuild_api_list( $cbuild_options, 'jobs' ),
	'queue_group'       => cbuild_api_const( $cbuild_options, 'QUEUE_GROUP' ),
	'log_source'        => cbuild_api_const( $cbuild_options, 'LOG_SOURCE' ),
	'meta_keys'         => cbuild_api_list( $cbuild_options, 'metaKeys' ),
	'post_types'        => cbuild_api_list( $cbuild_options, 'postTypes' ),
	'product_types'     => cbuild_api_list( $cbuild_options, 'productTypes' ),
	'settings_keys'     => cbuild_api_list( $cbuild_options, 'settingsKeys' ),
	'rest_routes'       => array_map( static fn( string $r ): string => 'cbuild/v1/' . ltrim( $r, '/' ), cbuild_api_matches( $cbuild_php, "/register_rest_route\\(\\s*(?:self::NAMESPACE|'cbuild\\/v1')\\s*,\\s*'([^']+)'/" ) ),
	'rest_error_codes'  => cbuild_api_matches( array_merge( cbuild_api_files( $cbuild_root . '/src/Rest', '.php' ), cbuild_api_files( $cbuild_root . '/src/Abilities', '.php' ) ), "/(?:new WP_Error\\(\\s*|'code'\\s*=>\\s*)'(cbuild_[a-z0-9_]+)'/" ),
	'abilities'         => cbuild_api_matches( $cbuild_php, "/wp_register_ability\\(\\s*'([^']+)'/" ),
	'cli_commands'      => cbuild_api_matches( $cbuild_php, "/WP_CLI::add_command\\(\\s*'([^']+)'/" ),
	'blocks'            => $cbuild_blocks,
	'dom_events'        => cbuild_api_matches( $cbuild_assets, "/'(cbuild:[a-z0-9-]+)'/" ),
);

// The kit's document schemas. The digest is of the decoded document with its keys sorted, so re-indenting a file
// changes nothing and changing a bound, a pattern or a property does.
$cbuild_sort    = static function ( array &$node ) use ( &$cbuild_sort ): void {
	if ( ! array_is_list( $node ) ) {
		ksort( $node );
	}
	foreach ( $node as &$cbuild_child ) {
		if ( is_array( $cbuild_child ) ) {
			$cbuild_sort( $cbuild_child );
		}
	}
};
$cbuild_schemas = array();
$cbuild_digests = array();
foreach ( glob( $cbuild_root . '/schema/*.json' ) ?: array() as $cbuild_schema_file ) {
	$cbuild_schema = json_decode( (string) file_get_contents( $cbuild_schema_file ), true );
	if ( ! is_array( $cbuild_schema ) ) {
		fwrite( STDERR, 'schema/' . basename( $cbuild_schema_file ) . " is not a JSON object.\n" );
		exit( 1 );
	}
	$cbuild_sort( $cbuild_schema );
	$cbuild_name                    = 'schema/' . basename( $cbuild_schema_file );
	$cbuild_schemas[]               = $cbuild_name;
	$cbuild_digests[ $cbuild_name ] = hash( 'sha256', json_encode( $cbuild_schema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) );
}
// Site Health's test ids, which a site's monitoring keys on.
$cbuild_surface['site_health_tests'] = cbuild_api_matches( array( $cbuild_root . '/src/Health/SiteHealth.php' ), "/const TEST_[A-Z_]+\\s*=\\s*'([^']+)'/" );

// Each command's arguments and flags, as its OPTIONS section gives them: `Class::method` to the synopsis.
$cbuild_synopses = array();
foreach ( cbuild_api_files( $cbuild_root . '/src/Cli', '.php' ) as $cbuild_cli_file ) {
	preg_match_all( '#/\\*\\*(?:(?!\\*/).)*?\\#\\# OPTIONS((?:(?!\\*/).)*?)\\*/\\s*public function (\\w+)#s', (string) file_get_contents( $cbuild_cli_file ), $cbuild_docs, PREG_SET_ORDER );
	foreach ( $cbuild_docs as $cbuild_doc ) {
		$cbuild_options_text = (string) preg_replace( '/^\\s*\\*\\s\\#\\#.*\\z/ms', '', $cbuild_doc[1] );
		preg_match_all( '/^\\s*\\*\\s(\\[?(?:<|--[a-z])\\S+)\\s*$/m', $cbuild_options_text, $cbuild_flags );
		$cbuild_synopses[ basename( $cbuild_cli_file, '.php' ) . '::' . $cbuild_doc[2] ] = implode( ' ', $cbuild_flags[1] );
	}
}
ksort( $cbuild_synopses );
$cbuild_surface['cli_synopses'] = $cbuild_synopses;

// The CSV's columns and the abilities' output schemas. Both classes need only the translation functions to answer.
$cbuild_columns   = array();
$cbuild_abilities = array();
if ( is_file( $cbuild_root . '/src/Service/SpecSheet.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	require_once $cbuild_root . '/tests/Support/wp-shims.php';
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $cbuild_root . '/' );
	}
	$cbuild_columns = ( 'CreativeBranding\\SkateboardBuilder\\Service\\SpecSheet' )::columns();
	foreach ( cbuild_api_files( $cbuild_root . '/src/Abilities', '.php' ) as $cbuild_ability_file ) {
		$cbuild_ability_class = 'CreativeBranding\\SkateboardBuilder\\Abilities\\' . basename( $cbuild_ability_file, '.php' );
		$cbuild_ability_names = cbuild_api_matches( array( $cbuild_ability_file ), "/wp_register_ability\\(\\s*'([^']+)'/" );
		if ( array() === $cbuild_ability_names || ! method_exists( $cbuild_ability_class, 'outputSchema' ) ) {
			continue;
		}
		// What an ability takes is held with what it gives: renaming an input breaks a caller as surely.
		$cbuild_ability_schema = array(
			'input'  => method_exists( $cbuild_ability_class, 'inputSchema' ) ? $cbuild_ability_class::inputSchema() : null,
			'output' => $cbuild_ability_class::outputSchema(),
		);
		$cbuild_sort( $cbuild_ability_schema );
		$cbuild_abilities[ $cbuild_ability_names[0] ] = hash( 'sha256', json_encode( $cbuild_ability_schema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) );
	}
}
// Each route's arguments: their names, and a digest of their schemas with the descriptions left out, so a reworded
// description changes nothing and a renamed argument, a new bound or a pattern does.
$cbuild_route_args = array();
foreach ( cbuild_api_files( $cbuild_root . '/src/Rest', '.php' ) as $cbuild_route_file ) {
	$cbuild_route_class = 'CreativeBranding\\SkateboardBuilder\\Rest\\' . basename( $cbuild_route_file, '.php' );
	$cbuild_route_paths = cbuild_api_matches( array( $cbuild_route_file ), "/register_rest_route\\(\\s*(?:self::NAMESPACE|'cbuild\\/v1')\\s*,\\s*'([^']+)'/" );
	if ( array() === $cbuild_route_paths || ! is_file( $cbuild_root . '/vendor/autoload.php' ) || ! method_exists( $cbuild_route_class, 'arguments' ) ) {
		continue;
	}
	$cbuild_route_schema = array_map(
		static function ( array $argument ): array {
			unset( $argument['description'] );
			return $argument;
		},
		$cbuild_route_class::arguments()
	);
	$cbuild_sort( $cbuild_route_schema );
	$cbuild_route_args[ 'cbuild/v1/' . ltrim( $cbuild_route_paths[0], '/' ) ] = implode( ' ', array_keys( $cbuild_route_schema ) ) . ' ' . hash( 'sha256', json_encode( $cbuild_route_schema, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) );
}
$cbuild_surface['rest_route_args'] = $cbuild_route_args;

$cbuild_surface['csv_columns']     = $cbuild_columns;
$cbuild_surface['ability_schemas'] = $cbuild_abilities;

$cbuild_surface['schemas']        = $cbuild_schemas;
$cbuild_surface['schema_digests'] = $cbuild_digests;

// The spec keys, from the domain's enum. The domain loads without WordPress, through Composer's loader.
$cbuild_spec_keys = array();
$cbuild_spec_enum = 'CreativeBranding\\SkateboardBuilder\\Domain\\Spec\\SpecKey';
if ( is_file( $cbuild_root . '/src/Domain/Spec/SpecKey.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	foreach ( $cbuild_spec_enum::cases() as $cbuild_spec_key ) {
		$cbuild_spec_unit                            = $cbuild_spec_key->unit();
		$cbuild_spec_keys[ $cbuild_spec_key->value ] = $cbuild_spec_key->kind()->value . ( null === $cbuild_spec_unit ? '' : ' ' . $cbuild_spec_unit->value );
	}
}
$cbuild_surface['spec_keys']  = array_keys( $cbuild_spec_keys );
$cbuild_surface['spec_kinds'] = $cbuild_spec_keys;

// The sources a spec's value comes from, as the index's `source` column holds them.
$cbuild_index_sources = array();
$cbuild_origin_enum   = 'CreativeBranding\\SkateboardBuilder\\Domain\\Spec\\Origin';
if ( is_file( $cbuild_root . '/src/Domain/Spec/Origin.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	$cbuild_index_sources = array_column( $cbuild_origin_enum::cases(), 'value' );
}
$cbuild_surface['index_sources'] = $cbuild_index_sources;

// The message codes a finding carries, with its severities and sources.
$cbuild_rule_values = array(
	'message_codes' => array(),
	'severities'    => array(),
	'sources'       => array(),
);
if ( is_file( $cbuild_root . '/src/Domain/Rule/MessageCode.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	foreach ( array( 'message_codes' => 'MessageCode', 'severities' => 'Severity', 'sources' => 'Source' ) as $cbuild_group => $cbuild_enum ) { // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$cbuild_enum = 'CreativeBranding\\SkateboardBuilder\\Domain\\Rule\\' . $cbuild_enum;
		foreach ( $cbuild_enum::cases() as $cbuild_case ) {
			$cbuild_rule_values[ $cbuild_group ][] = $cbuild_case->value;
		}
	}
}
$cbuild_surface += $cbuild_rule_values;

// Why the validator refuses a build.
$cbuild_problems = array();
if ( is_file( $cbuild_root . '/src/Domain/Fit/Problem.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	$cbuild_problem_enum = 'CreativeBranding\\SkateboardBuilder\\Domain\\Fit\\Problem';
	foreach ( $cbuild_problem_enum::cases() as $cbuild_case ) {
		$cbuild_problems[] = $cbuild_case->value;
	}
}
$cbuild_surface['build_problems'] = $cbuild_problems;

// The rules' ids, the names of the values each message code carries, and the codes the domain refuses bad input with.
// The edge keys its notices and its translations on all three.
$cbuild_rule_ids      = array();
$cbuild_value_names   = array();
$cbuild_refusal_codes = array();
if ( is_file( $cbuild_root . '/src/Domain/Rule/RuleId.php' ) ) {
	require_once $cbuild_root . '/vendor/autoload.php';
	$cbuild_domain = 'CreativeBranding\\SkateboardBuilder\\Domain\\';
	foreach ( ( $cbuild_domain . 'Rule\\RuleId' )::cases() as $cbuild_case ) {
		$cbuild_rule_ids[] = $cbuild_case->value;
	}
	foreach ( ( $cbuild_domain . 'Rule\\MessageCode' )::cases() as $cbuild_case ) {
		$cbuild_value_names[ $cbuild_case->value ] = implode( ' ', $cbuild_case->values() );
	}
	foreach ( array( 'Kit\\DocumentError', 'Spec\\SpecError', 'Measure\\MeasureError', 'Kit\\PartError', 'Kit\\MoneyError' ) as $cbuild_enum ) {
		foreach ( ( $cbuild_domain . $cbuild_enum )::cases() as $cbuild_case ) {
			$cbuild_refusal_codes[] = $cbuild_case->value;
		}
	}
}
$cbuild_surface['rule_ids']       = $cbuild_rule_ids;
$cbuild_surface['message_values'] = $cbuild_value_names;
$cbuild_surface['refusal_codes']  = $cbuild_refusal_codes;

// Groups that describe another group's items, or whose values are too plain a word to look for in the docs, and are
// held by the snapshot alone.
$cbuild_details = array( 'schema_digests', 'spec_kinds', 'severities', 'sources', 'message_values', 'cli_synopses', 'csv_columns', 'ability_schemas', 'rest_route_args' );

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
	if ( in_array( $cbuild_key, $cbuild_details, true ) ) {
		continue;
	}
	foreach ( is_array( $cbuild_items ) ? $cbuild_items : array( $cbuild_items ) as $cbuild_item ) {
		if ( '' !== $cbuild_item && ! str_contains( $cbuild_hooks, '`' . $cbuild_item . '`' ) ) {
			fwrite( STDERR, "docs/HOOKS.md does not list {$cbuild_key} `{$cbuild_item}`.\n" );
			$cbuild_status = 1;
		}
	}
}

// The third edit: the changelog's entry for the version an item arrived in names it (issue #5). The version is the one
// docs/HOOKS.md gives beside the item; an entry not yet released is read from [Unreleased].
$cbuild_changelog = is_readable( $cbuild_root . '/CHANGELOG.md' ) ? (string) file_get_contents( $cbuild_root . '/CHANGELOG.md' ) : '';
$cbuild_entries   = cbuild_api_changelog_entries( $cbuild_changelog );
// The fourth: the plugin is at the version its newest item is dated to, and readme.txt has that version's entry for
// shop owners, which the release's manifest is made from (D-053). Without this a milestone's merge could be tagged
// while the header still gave the last version, and the release would fail, or ship under the old number (D-062).
$cbuild_main_file      = is_readable( $cbuild_root . '/creative-skateboard-builder.php' ) ? (string) file_get_contents( $cbuild_root . '/creative-skateboard-builder.php' ) : '';
$cbuild_plugin_version = 1 === preg_match( '/^ \* Version:\s+(\d+\.\d+\.\d+)\s*$/m', $cbuild_main_file, $cbuild_found ) ? $cbuild_found[1] : '0.0.0';
$cbuild_readme         = is_readable( $cbuild_root . '/readme.txt' ) ? (string) file_get_contents( $cbuild_root . '/readme.txt' ) : '';
$cbuild_ahead          = array();
if ( 1 !== preg_match( '/^= ' . preg_quote( $cbuild_plugin_version, '/' ) . ' =$/m', $cbuild_readme ) ) {
	fwrite( STDERR, "readme.txt has no changelog entry for {$cbuild_plugin_version}, the plugin header's version.\n" );
	$cbuild_status = 1;
}
foreach ( $cbuild_surface as $cbuild_key => $cbuild_items ) {
	if ( in_array( $cbuild_key, $cbuild_details, true ) ) {
		continue;
	}
	foreach ( is_array( $cbuild_items ) ? $cbuild_items : array( $cbuild_items ) as $cbuild_item ) {
		if ( '' === $cbuild_item || ! str_contains( $cbuild_hooks, '`' . $cbuild_item . '`' ) ) {
			continue;
		}
		$cbuild_since = cbuild_api_since( $cbuild_hooks, $cbuild_item );
		if ( null === $cbuild_since ) {
			fwrite( STDERR, "docs/HOOKS.md gives no version for {$cbuild_key} `{$cbuild_item}`: its row or its Since line needs one.\n" );
			$cbuild_status = 1;
			continue;
		}
		if ( version_compare( $cbuild_since, $cbuild_plugin_version, '>' ) ) {
			$cbuild_ahead[ $cbuild_since ] = ( $cbuild_ahead[ $cbuild_since ] ?? 0 ) + 1;
		}
		$cbuild_where = isset( $cbuild_entries[ $cbuild_since ] ) ? $cbuild_since : 'Unreleased';
		if ( ! str_contains( $cbuild_entries[ $cbuild_where ] ?? '', '`' . $cbuild_item . '`' ) ) {
			fwrite( STDERR, "CHANGELOG.md's [{$cbuild_where}] entry does not name {$cbuild_key} `{$cbuild_item}`, which docs/HOOKS.md dates to {$cbuild_since}.\n" );
			$cbuild_status = 1;
		}
	}
}

foreach ( $cbuild_ahead as $cbuild_since => $cbuild_count ) {
	fwrite( STDERR, "docs/HOOKS.md dates {$cbuild_count} item(s) to {$cbuild_since}, and the plugin header says {$cbuild_plugin_version}: the version moves with the first item.\n" );
	$cbuild_status = 1;
}

if ( 0 === $cbuild_status ) {
	$cbuild_count = 0;
	foreach ( $cbuild_surface as $cbuild_key => $cbuild_items ) {
		if ( in_array( $cbuild_key, $cbuild_details, true ) ) {
			continue;
		}
		$cbuild_count += is_array( $cbuild_items ) ? count( $cbuild_items ) : ( '' === $cbuild_items ? 0 : 1 );
	}
	echo "API surface: {$cbuild_count} items match the snapshot, docs/HOOKS.md and CHANGELOG.md.\n";
}
exit( $cbuild_status );
