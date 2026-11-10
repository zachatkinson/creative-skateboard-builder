<?php
/**
 * Merges the unit suite's Clover report with the integration suite's, line by line, and applies the 95 % floor to the
 * result, so a class is counted wherever it is tested (issue #6, D-050). Prints each file under the floor's level.
 *
 * The unit report (PHPUnit 13) names the lines that count: it lists every file in src/, tested or not. A line is
 * covered when either suite ran it. A line only the integration report lists is left out, so the number can only be
 * lower than the truth where the two PHPUnit versions name a statement by different lines.
 *
 * One line of a file is never counted: the `exit` of its direct-access guard, which runs only when the file is requested
 * outside WordPress, where no test can be (D-061). The rule is here once, so no file carries a note of its own, and it
 * takes that line and nothing else.
 *
 * PHPUnit measures from the first test, so the lines that run while WordPress loads the plugin come in a third file,
 * written by the integration bootstrap: file to line numbers, as JSON.
 *
 * Run: php tools/coverage-merge.php tmp/coverage.xml tmp/coverage-integration.xml tmp/coverage-bootstrap.json
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

/**
 * A Clover report's statement lines by file, keyed from src/: line number to whether it ran.
 *
 * @param string $cbuild_path The report.
 * @return array<string, array<int, bool>>
 */
function cbuild_clover_lines( string $cbuild_path ): array {
	$cbuild_xml = is_readable( $cbuild_path ) ? simplexml_load_file( $cbuild_path ) : false;
	if ( false === $cbuild_xml ) {
		fwrite( STDERR, "No readable coverage report at {$cbuild_path}.\n" );
		exit( 1 );
	}
	$cbuild_files = array();
	foreach ( $cbuild_xml->xpath( '//file' ) ?: array() as $cbuild_file ) {
		$cbuild_name = (string) $cbuild_file['name'];
		$cbuild_at   = strrpos( $cbuild_name, '/src/' );
		if ( false === $cbuild_at ) {
			continue;
		}
		$cbuild_lines = array();
		foreach ( $cbuild_file->line as $cbuild_line ) {
			if ( 'stmt' === (string) $cbuild_line['type'] ) {
				$cbuild_lines[ (int) $cbuild_line['num'] ] = (int) $cbuild_line['count'] > 0;
			}
		}
		ksort( $cbuild_lines );
		$cbuild_files[ substr( $cbuild_name, $cbuild_at + 1 ) ] = $cbuild_lines;
	}
	ksort( $cbuild_files );
	return $cbuild_files;
}

/**
 * The line of a file's direct-access guard that exits, or 0 when the file has no guard. Only the guard as every file
 * writes it is recognised: the ABSPATH test on one line and a bare `exit;` on the next.
 *
 * @param string $cbuild_path The file.
 */
function cbuild_guard_exit_line( string $cbuild_path ): int {
	$cbuild_source = is_readable( $cbuild_path ) ? file( $cbuild_path, FILE_IGNORE_NEW_LINES ) : false;
	foreach ( false === $cbuild_source ? array() : $cbuild_source as $cbuild_index => $cbuild_text ) {
		if ( "if ( ! defined( 'ABSPATH' ) ) {" === $cbuild_text && "\texit;" === ( $cbuild_source[ $cbuild_index + 1 ] ?? '' ) ) {
			return $cbuild_index + 2;
		}
	}
	return 0;
}

if ( $argc < 4 ) {
	fwrite( STDERR, "Usage: php tools/coverage-merge.php <unit clover> <integration clover> <bootstrap lines>\n" );
	exit( 1 );
}
$cbuild_unit        = cbuild_clover_lines( $argv[1] );
$cbuild_integration = cbuild_clover_lines( $argv[2] );
if ( array() === $cbuild_unit ) {
	fwrite( STDERR, "The unit report lists no file of src/; nothing was measured.\n" );
	exit( 1 );
}
$cbuild_boot        = json_decode( is_readable( $argv[3] ) ? (string) file_get_contents( $argv[3] ) : '', true );
if ( ! is_array( $cbuild_boot ) || array() === $cbuild_boot ) {
	fwrite( STDERR, "No lines from the integration bootstrap at {$argv[3]}: was the suite run with CBUILD_COVERAGE=1?\n" );
	exit( 1 );
}
foreach ( $cbuild_boot as $cbuild_name => $cbuild_lines ) {
	foreach ( is_array( $cbuild_lines ) ? $cbuild_lines : array() as $cbuild_num ) {
		$cbuild_integration[ (string) $cbuild_name ][ (int) $cbuild_num ] = true;
	}
}
$cbuild_ran = 0;
foreach ( $cbuild_integration as $cbuild_lines ) {
	$cbuild_ran += count( array_filter( $cbuild_lines ) );
}
if ( 0 === $cbuild_ran ) {
	// A run without a coverage driver writes a report with every count at 0, and the floor would read the unit suite alone.
	fwrite( STDERR, "The integration report ran no line of src/: was the site started with --xdebug=coverage?\n" );
	exit( 1 );
}

$cbuild_total   = 0;
$cbuild_covered = 0;
$cbuild_rows    = array();
$cbuild_guards  = 0;
foreach ( $cbuild_unit as $cbuild_name => $cbuild_lines ) {
	$cbuild_guard = cbuild_guard_exit_line( dirname( __DIR__ ) . '/' . $cbuild_name );
	if ( isset( $cbuild_lines[ $cbuild_guard ] ) ) {
		unset( $cbuild_lines[ $cbuild_guard ] );
		++$cbuild_guards;
	}
	$cbuild_other = $cbuild_integration[ $cbuild_name ] ?? array();
	$cbuild_nums  = array_keys( $cbuild_lines );
	$cbuild_hit   = 0;
	foreach ( $cbuild_nums as $cbuild_num ) {
		$cbuild_is = $cbuild_lines[ $cbuild_num ] || ( $cbuild_other[ $cbuild_num ] ?? false );
		$cbuild_hit += $cbuild_is ? 1 : 0;
	}
	$cbuild_total   += count( $cbuild_nums );
	$cbuild_covered += $cbuild_hit;
	$cbuild_rows[]   = array( $cbuild_name, $cbuild_hit, count( $cbuild_nums ) );
}

foreach ( $cbuild_rows as [ $cbuild_name, $cbuild_hit, $cbuild_of ] ) {
	// A file whose only statement was its guard has no line left to count.
	if ( $cbuild_of > 0 && 100.0 * $cbuild_hit / $cbuild_of < 95.0 ) {
		printf( "  %6.2f%% (%d/%d) %s\n", 100.0 * $cbuild_hit / $cbuild_of, $cbuild_hit, $cbuild_of, $cbuild_name );
	}
}
$cbuild_pct = $cbuild_total > 0 ? 100.0 * $cbuild_covered / $cbuild_total : 0.0;
printf( "Lines, both suites: %.2f%% (%d/%d) of src/, %d files; %d direct-access guards left out.\n", $cbuild_pct, $cbuild_covered, $cbuild_total, count( $cbuild_rows ), $cbuild_guards );
if ( $cbuild_pct < 95.0 ) {
	fwrite( STDERR, "Line coverage of src/ across both suites is under 95%.\n" );
	exit( 1 );
}
