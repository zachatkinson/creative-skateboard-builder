<?php
/**
 * Holds the domain's mutation run to its tests: a mutant only static analysis caught must be one the list allows, by
 * mutator and file (D-063). Without this, a mutant that changes what the code does could count as caught while no test
 * notices, as three did before milestone 2's review.
 *
 * Run: php tools/infection-static.php tmp/infection-domain.json tools/infection-static-allowed.txt
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

if ( $argc < 3 ) {
	fwrite( STDERR, "Usage: php tools/infection-static.php <infection json> <allowed list>\n" );
	exit( 1 );
}
$cbuild_report = json_decode( is_readable( $argv[1] ) ? (string) file_get_contents( $argv[1] ) : '', true );
if ( ! is_array( $cbuild_report ) || ! isset( $cbuild_report['stats'] ) ) {
	fwrite( STDERR, "No mutation report at {$argv[1]}.\n" );
	exit( 1 );
}
$cbuild_lines = is_readable( $argv[2] ) ? file( $argv[2], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : false;
if ( false === $cbuild_lines ) {
	fwrite( STDERR, "No list at {$argv[2]}.\n" );
	exit( 1 );
}
$cbuild_allowed = array();
foreach ( $cbuild_lines as $cbuild_line ) {
	if ( str_starts_with( $cbuild_line, '#' ) ) {
		continue;
	}
	$cbuild_parts = preg_split( '/\s+/', trim( $cbuild_line ) );
	if ( ! is_array( $cbuild_parts ) || 3 !== count( $cbuild_parts ) ) {
		fwrite( STDERR, "Not a line of the list: {$cbuild_line}\n" );
		exit( 1 );
	}
	$cbuild_allowed[ $cbuild_parts[0] . ' ' . $cbuild_parts[1] ] = (int) $cbuild_parts[2];
}

$cbuild_found = array();
$cbuild_where = array();
$cbuild_root  = dirname( __DIR__ ) . '/';
foreach ( $cbuild_report['killedByStaticAnalysis'] ?? array() as $cbuild_mutant ) {
	$cbuild_file = (string) ( $cbuild_mutant['mutator']['originalFilePath'] ?? '' );
	$cbuild_file = str_starts_with( $cbuild_file, $cbuild_root ) ? substr( $cbuild_file, strlen( $cbuild_root ) ) : $cbuild_file;
	$cbuild_key  = ( $cbuild_mutant['mutator']['mutatorName'] ?? '' ) . ' ' . $cbuild_file;
	$cbuild_found[ $cbuild_key ]   = ( $cbuild_found[ $cbuild_key ] ?? 0 ) + 1;
	$cbuild_where[ $cbuild_key ][] = (int) ( $cbuild_mutant['mutator']['originalStartLine'] ?? 0 );
}
$cbuild_status = 0;
foreach ( $cbuild_found as $cbuild_key => $cbuild_count ) {
	if ( $cbuild_count > ( $cbuild_allowed[ $cbuild_key ] ?? 0 ) ) {
		fwrite( STDERR, sprintf( "Caught by static analysis and by no test: %s, %d (allowed %d), at line(s) %s.\n", $cbuild_key, $cbuild_count, $cbuild_allowed[ $cbuild_key ] ?? 0, implode( ', ', $cbuild_where[ $cbuild_key ] ) ) );
		$cbuild_status = 1;
	}
}
if ( 0 === $cbuild_status ) {
	printf( "Mutants caught by static analysis alone: %d, each on the list.\n", array_sum( $cbuild_found ) );
}
exit( $cbuild_status );
