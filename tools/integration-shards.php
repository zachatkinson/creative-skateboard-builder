<?php
/**
 * Splits the integration suite into shards, one CI job each, and checks that the shards ran the whole suite once.
 *
 * The suite is what phpunit-integration.xml.dist names: every file with its suffix under its directories, and its
 * files. Each test class is weighed by its time in tools/integration-shards.json (a class not listed there counts as
 * the median), and the classes go, heaviest first, to the shard with the least time so far; a tie goes to the lower
 * shard, and equal weights in name order. The split depends only on the files and that JSON, so every job works out
 * the same one, and a class added later lands in a shard without anyone listing it. Within a shard the classes run in
 * name order, the order the whole suite runs them in.
 *
 * Commands:
 *   count            The number of shards.
 *   plan             Each shard's classes and estimated seconds; fails unless every class is in exactly one shard.
 *   config <k>       Writes tmp/phpunit-integration-shard.xml: the suite's config with only shard k's files, and
 *                    prints its path. tools/integration.sh runs it when CBUILD_SHARD is set.
 *   verify <dir>     Reads the JUnit report of each shard's job from <dir>/integration-shard-<k>/integration-junit.xml
 *                    and fails unless every shard is there, each ran the classes planned for it, and every class of
 *                    the suite ran exactly once across them, with no failure or error.
 *
 * Run: php tools/integration-shards.php plan
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

/**
 * Stops with a message on standard error.
 *
 * @param string $cbuild_message The reason.
 * @return never
 */
function cbuild_shards_fail( string $cbuild_message ): never {
	fwrite( STDERR, $cbuild_message . "\n" );
	exit( 1 );
}

/**
 * The suite's test files, relative to the repository and in name order, as phpunit-integration.xml.dist names them.
 * Anything in its testsuite element but a directory or a file stops the script, so the split never runs less than
 * the config does.
 *
 * @param string $cbuild_root The repository.
 * @return list<string>
 */
function cbuild_shards_suite( string $cbuild_root ): array {
	$cbuild_dom = new DOMDocument();
	if ( ! $cbuild_dom->load( $cbuild_root . '/phpunit-integration.xml.dist' ) ) {
		cbuild_shards_fail( 'phpunit-integration.xml.dist could not be read.' );
	}
	$cbuild_suites = $cbuild_dom->getElementsByTagName( 'testsuite' );
	if ( 1 !== $cbuild_suites->length ) {
		cbuild_shards_fail( 'phpunit-integration.xml.dist must have one testsuite to split.' );
	}
	$cbuild_files = array();
	foreach ( $cbuild_suites->item( 0 )->childNodes as $cbuild_node ) {
		if ( ! $cbuild_node instanceof DOMElement ) {
			continue;
		}
		$cbuild_path = trim( $cbuild_node->textContent );
		if ( 'file' === $cbuild_node->tagName ) {
			$cbuild_files[] = $cbuild_path;
			continue;
		}
		if ( 'directory' !== $cbuild_node->tagName ) {
			cbuild_shards_fail( "The testsuite's <{$cbuild_node->tagName}> is not understood here; teach tools/integration-shards.php it." );
		}
		$cbuild_suffix = $cbuild_node->getAttribute( 'suffix' );
		$cbuild_suffix = '' === $cbuild_suffix ? 'Test.php' : $cbuild_suffix;
		$cbuild_walk   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_root . '/' . $cbuild_path, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $cbuild_walk as $cbuild_file ) {
			if ( $cbuild_file instanceof SplFileInfo && $cbuild_file->isFile() && str_ends_with( $cbuild_file->getFilename(), $cbuild_suffix ) ) {
				$cbuild_files[] = substr( $cbuild_file->getPathname(), strlen( $cbuild_root ) + 1 );
			}
		}
	}
	$cbuild_files = array_values( array_unique( $cbuild_files ) );
	sort( $cbuild_files, SORT_STRING );
	if ( array() === $cbuild_files ) {
		cbuild_shards_fail( 'The integration suite has no test file.' );
	}
	return $cbuild_files;
}

/**
 * The shard count and each class's weight in seconds, from tools/integration-shards.json.
 *
 * @param string $cbuild_root The repository.
 * @return array{0: int, 1: array<string, float>}
 */
function cbuild_shards_weights( string $cbuild_root ): array {
	$cbuild_json = json_decode( (string) file_get_contents( $cbuild_root . '/tools/integration-shards.json' ), true );
	$cbuild_n    = is_array( $cbuild_json ) ? ( $cbuild_json['shards'] ?? null ) : null;
	if ( ! is_int( $cbuild_n ) || $cbuild_n < 1 ) {
		cbuild_shards_fail( 'tools/integration-shards.json must give "shards" as a whole number from 1.' );
	}
	$cbuild_weights = array();
	foreach ( (array) ( $cbuild_json['seconds'] ?? array() ) as $cbuild_class => $cbuild_seconds ) {
		if ( is_int( $cbuild_seconds ) || is_float( $cbuild_seconds ) ) {
			$cbuild_weights[ (string) $cbuild_class ] = (float) $cbuild_seconds;
		}
	}
	return array( $cbuild_n, $cbuild_weights );
}

/**
 * The split: for each shard, from 1, its files in name order and its estimated seconds.
 *
 * @param list<string>         $cbuild_files   The suite's files.
 * @param int                  $cbuild_n       The number of shards.
 * @param array<string, float> $cbuild_weights Seconds by class name.
 * @return array<int, array{files: list<string>, seconds: float}>
 */
function cbuild_shards_split( array $cbuild_files, int $cbuild_n, array $cbuild_weights ): array {
	$cbuild_known  = array_values( $cbuild_weights );
	$cbuild_median = 1.0;
	if ( array() !== $cbuild_known ) {
		sort( $cbuild_known );
		$cbuild_mid    = intdiv( count( $cbuild_known ), 2 );
		$cbuild_median = 0 === count( $cbuild_known ) % 2 ? ( $cbuild_known[ $cbuild_mid - 1 ] + $cbuild_known[ $cbuild_mid ] ) / 2 : $cbuild_known[ $cbuild_mid ];
	}
	$cbuild_items = array();
	foreach ( $cbuild_files as $cbuild_file ) {
		$cbuild_items[] = array( $cbuild_file, $cbuild_weights[ basename( $cbuild_file, '.php' ) ] ?? $cbuild_median );
	}
	usort(
		$cbuild_items,
		static fn( array $cbuild_a, array $cbuild_b ): int => array( $cbuild_b[1], $cbuild_a[0] ) <=> array( $cbuild_a[1], $cbuild_b[0] )
	);
	$cbuild_shards = array();
	for ( $cbuild_k = 1; $cbuild_k <= $cbuild_n; $cbuild_k++ ) {
		$cbuild_shards[ $cbuild_k ] = array(
			'files'   => array(),
			'seconds' => 0.0,
		);
	}
	foreach ( $cbuild_items as [ $cbuild_file, $cbuild_seconds ] ) {
		$cbuild_to = 1;
		foreach ( $cbuild_shards as $cbuild_k => $cbuild_shard ) {
			if ( $cbuild_shard['seconds'] < $cbuild_shards[ $cbuild_to ]['seconds'] ) {
				$cbuild_to = $cbuild_k;
			}
		}
		$cbuild_shards[ $cbuild_to ]['files'][]  = $cbuild_file;
		$cbuild_shards[ $cbuild_to ]['seconds'] += $cbuild_seconds;
	}
	foreach ( $cbuild_shards as $cbuild_k => $cbuild_shard ) {
		sort( $cbuild_shard['files'], SORT_STRING );
		$cbuild_shards[ $cbuild_k ]['files'] = $cbuild_shard['files'];
	}
	return $cbuild_shards;
}

/**
 * Fails unless the shards hold every file of the suite exactly once, and none is empty.
 *
 * @param list<string>                                           $cbuild_files  The suite's files.
 * @param array<int, array{files: list<string>, seconds: float}> $cbuild_shards The split.
 */
function cbuild_shards_check_union( array $cbuild_files, array $cbuild_shards ): void {
	$cbuild_seen = array();
	foreach ( $cbuild_shards as $cbuild_k => $cbuild_shard ) {
		if ( array() === $cbuild_shard['files'] ) {
			cbuild_shards_fail( "Shard {$cbuild_k} has no test class: lower \"shards\" in tools/integration-shards.json." );
		}
		foreach ( $cbuild_shard['files'] as $cbuild_file ) {
			$cbuild_seen[ $cbuild_file ] = ( $cbuild_seen[ $cbuild_file ] ?? 0 ) + 1;
		}
	}
	$cbuild_missing = array_diff( $cbuild_files, array_keys( $cbuild_seen ) );
	$cbuild_twice   = array_keys( array_filter( $cbuild_seen, static fn( int $cbuild_times ): bool => $cbuild_times > 1 ) );
	$cbuild_extra   = array_diff( array_keys( $cbuild_seen ), $cbuild_files );
	if ( array() !== $cbuild_missing || array() !== $cbuild_twice || array() !== $cbuild_extra ) {
		cbuild_shards_fail(
			"The shards are not the suite.\n  In no shard: " . implode( ', ', $cbuild_missing )
			. "\n  In two or more: " . implode( ', ', $cbuild_twice ) . "\n  Not in the suite: " . implode( ', ', $cbuild_extra )
		);
	}
}

/**
 * Writes shard k's config beside the others in tmp/: the suite's config with its testsuite holding only the shard's
 * files. Every path in it is relative to the config's own folder, as PHPUnit reads them, so each gains a "../".
 *
 * @param string       $cbuild_root  The repository.
 * @param list<string> $cbuild_files The shard's files.
 * @return string The config's path, relative to the repository.
 */
function cbuild_shards_write_config( string $cbuild_root, array $cbuild_files ): string {
	$cbuild_dom = new DOMDocument();
	$cbuild_dom->load( $cbuild_root . '/phpunit-integration.xml.dist' );
	$cbuild_top = $cbuild_dom->documentElement;
	foreach ( array( 'bootstrap', 'cacheResultFile' ) as $cbuild_attribute ) {
		if ( $cbuild_top->hasAttribute( $cbuild_attribute ) ) {
			$cbuild_top->setAttribute( $cbuild_attribute, '../' . $cbuild_top->getAttribute( $cbuild_attribute ) );
		}
	}
	foreach ( iterator_to_array( $cbuild_dom->getElementsByTagName( 'directory' ) ) as $cbuild_dir ) {
		$cbuild_dir->textContent = '../' . trim( $cbuild_dir->textContent );
	}
	$cbuild_suite = $cbuild_dom->getElementsByTagName( 'testsuite' )->item( 0 );
	while ( null !== $cbuild_suite->firstChild ) {
		$cbuild_suite->removeChild( $cbuild_suite->firstChild );
	}
	foreach ( $cbuild_files as $cbuild_file ) {
		$cbuild_suite->appendChild( $cbuild_dom->createTextNode( "\n\t\t\t" ) );
		$cbuild_suite->appendChild( $cbuild_dom->createElement( 'file', '../' . $cbuild_file ) );
	}
	$cbuild_suite->appendChild( $cbuild_dom->createTextNode( "\n\t\t" ) );
	if ( ! is_dir( $cbuild_root . '/tmp' ) ) {
		mkdir( $cbuild_root . '/tmp', 0777, true );
	}
	$cbuild_path = 'tmp/phpunit-integration-shard.xml';
	if ( false === $cbuild_dom->save( $cbuild_root . '/' . $cbuild_path ) ) {
		cbuild_shards_fail( "{$cbuild_path} could not be written." );
	}
	return $cbuild_path;
}

/**
 * The test classes a JUnit report ran, as their files relative to the repository, with each one's count of tests,
 * failures and errors. A class's file is named from its tests/ folder on, so a container's path reads as the host's.
 *
 * @param string $cbuild_path The report.
 * @return array<string, array{tests: int, failures: int, errors: int}>
 */
function cbuild_shards_junit( string $cbuild_path ): array {
	$cbuild_xml = is_readable( $cbuild_path ) ? simplexml_load_file( $cbuild_path ) : false;
	if ( false === $cbuild_xml ) {
		cbuild_shards_fail( "No readable JUnit report at {$cbuild_path}." );
	}
	$cbuild_ran = array();
	foreach ( $cbuild_xml->xpath( '//testsuite[@file]' ) ?: array() as $cbuild_suite ) {
		$cbuild_file = (string) $cbuild_suite['file'];
		$cbuild_at   = strrpos( $cbuild_file, '/tests/' );
		if ( false === $cbuild_at || str_contains( (string) $cbuild_suite['name'], '::' ) ) {
			continue;
		}
		$cbuild_ran[ substr( $cbuild_file, $cbuild_at + 1 ) ] = array(
			'tests'    => (int) $cbuild_suite['tests'],
			'failures' => (int) $cbuild_suite['failures'],
			'errors'   => (int) $cbuild_suite['errors'],
		);
	}
	return $cbuild_ran;
}

$cbuild_root                = dirname( __DIR__ );
$cbuild_command             = $argv[1] ?? '';
[ $cbuild_n, $cbuild_weights ] = cbuild_shards_weights( $cbuild_root );
$cbuild_files               = cbuild_shards_suite( $cbuild_root );
$cbuild_shards              = cbuild_shards_split( $cbuild_files, $cbuild_n, $cbuild_weights );
cbuild_shards_check_union( $cbuild_files, $cbuild_shards );

if ( 'count' === $cbuild_command ) {
	echo $cbuild_n, "\n";
	exit( 0 );
}

if ( 'plan' === $cbuild_command ) {
	$cbuild_unweighed = array_filter( $cbuild_files, static fn( string $cbuild_file ): bool => ! isset( $cbuild_weights[ basename( $cbuild_file, '.php' ) ] ) );
	foreach ( $cbuild_shards as $cbuild_k => $cbuild_shard ) {
		printf( "Shard %d of %d: %d classes, about %.0f s\n", $cbuild_k, $cbuild_n, count( $cbuild_shard['files'] ), $cbuild_shard['seconds'] );
		foreach ( $cbuild_shard['files'] as $cbuild_file ) {
			echo '  ', $cbuild_file, "\n";
		}
	}
	printf( "%d classes in %d shards, each exactly once.\n", count( $cbuild_files ), $cbuild_n );
	if ( array() !== $cbuild_unweighed ) {
		echo 'Counted as the median, with no time in tools/integration-shards.json: ', implode( ', ', $cbuild_unweighed ), "\n";
	}
	exit( 0 );
}

if ( 'config' === $cbuild_command ) {
	$cbuild_asked = (string) ( $argv[2] ?? '' );
	$cbuild_k     = ctype_digit( $cbuild_asked ) ? (int) $cbuild_asked : 0;
	if ( ! isset( $cbuild_shards[ $cbuild_k ] ) ) {
		cbuild_shards_fail( "There is no shard \"{$cbuild_asked}\"; the shards are 1 to {$cbuild_n}." );
	}
	echo cbuild_shards_write_config( $cbuild_root, $cbuild_shards[ $cbuild_k ]['files'] ), "\n";
	exit( 0 );
}

if ( 'verify' === $cbuild_command ) {
	$cbuild_dir = rtrim( $argv[2] ?? '', '/' );
	if ( '' === $cbuild_dir || ! is_dir( $cbuild_dir ) ) {
		cbuild_shards_fail( 'Usage: php tools/integration-shards.php verify <folder of the shards\' artifacts>' );
	}
	$cbuild_found = glob( $cbuild_dir . '/integration-shard-*', GLOB_ONLYDIR ) ?: array();
	if ( count( $cbuild_found ) !== $cbuild_n ) {
		cbuild_shards_fail( sprintf( '%d shard reports in %s, not %d: a shard did not finish, or did not upload.', count( $cbuild_found ), $cbuild_dir, $cbuild_n ) );
	}
	$cbuild_times  = array();
	$cbuild_tests  = 0;
	$cbuild_errors = array();
	foreach ( $cbuild_shards as $cbuild_k => $cbuild_shard ) {
		$cbuild_ran = cbuild_shards_junit( "{$cbuild_dir}/integration-shard-{$cbuild_k}/integration-junit.xml" );
		$cbuild_off = array_merge( array_diff( $cbuild_shard['files'], array_keys( $cbuild_ran ) ), array_diff( array_keys( $cbuild_ran ), $cbuild_shard['files'] ) );
		if ( array() !== $cbuild_off ) {
			$cbuild_errors[] = "Shard {$cbuild_k} did not run what was planned for it: " . implode( ', ', $cbuild_off );
		}
		foreach ( $cbuild_ran as $cbuild_file => $cbuild_counts ) {
			$cbuild_times[ $cbuild_file ] = ( $cbuild_times[ $cbuild_file ] ?? 0 ) + 1;
			$cbuild_tests                += $cbuild_counts['tests'];
			if ( 0 === $cbuild_counts['tests'] || $cbuild_counts['failures'] > 0 || $cbuild_counts['errors'] > 0 ) {
				$cbuild_errors[] = sprintf( 'Shard %d: %s ran %d tests, %d failures, %d errors.', $cbuild_k, $cbuild_file, $cbuild_counts['tests'], $cbuild_counts['failures'], $cbuild_counts['errors'] );
			}
		}
	}
	foreach ( $cbuild_files as $cbuild_file ) {
		$cbuild_ran_times = $cbuild_times[ $cbuild_file ] ?? 0;
		if ( 1 !== $cbuild_ran_times ) {
			$cbuild_errors[] = "{$cbuild_file} ran in {$cbuild_ran_times} shards, not one.";
		}
	}
	if ( array() !== $cbuild_errors ) {
		cbuild_shards_fail( implode( "\n", $cbuild_errors ) );
	}
	printf( "The %d shards ran the whole integration suite once: %d classes, %d tests.\n", $cbuild_n, count( $cbuild_files ), $cbuild_tests );
	exit( 0 );
}

cbuild_shards_fail( 'Usage: php tools/integration-shards.php count | plan | config <shard> | verify <folder>' );
