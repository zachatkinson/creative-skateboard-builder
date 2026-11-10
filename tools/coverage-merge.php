<?php
/**
 * Merges the unit suite's Clover report with the integration suite's, line by line, and applies the 90 % floor to the
 * result, so a class is counted wherever it is tested (issue #6, D-050). Prints each file under the floor's level.
 *
 * The unit report (PHPUnit 13) names the lines that count: it lists every file in src/, tested or not. A line is
 * covered when either suite ran it. A line only the integration report lists is left out, so the number can only be
 * lower than the truth where the two PHPUnit versions name a statement by different lines.
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
foreach ( $cbuild_unit as $cbuild_name => $cbuild_lines ) {
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
	// A file missing one line misses its direct-access guard's exit, as every file does.
	if ( $cbuild_of - $cbuild_hit > 1 && 100.0 * $cbuild_hit / $cbuild_of < 90.0 ) {
		printf( "  %6.2f%% (%d/%d) %s\n", 100.0 * $cbuild_hit / $cbuild_of, $cbuild_hit, $cbuild_of, $cbuild_name );
	}
}
$cbuild_pct = $cbuild_total > 0 ? 100.0 * $cbuild_covered / $cbuild_total : 0.0;
printf( "Lines, both suites: %.2f%% (%d/%d) of src/, %d files.\n", $cbuild_pct, $cbuild_covered, $cbuild_total, count( $cbuild_rows ) );
if ( $cbuild_pct < 90.0 ) {
	fwrite( STDERR, "Line coverage of src/ across both suites is under 90%.\n" );
	exit( 1 );
}
