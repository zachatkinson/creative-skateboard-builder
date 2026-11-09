<?php
/**
 * Fails when coverage is under the floors: 90 % of the lines phpunit.xml.dist's <source> lists, 100 % of
 * src/Domain's lines, and 100 % of its branches when the domain's Cobertura report is given (docs/AUDIT.md: Unit tests).
 *
 * Run: php tools/coverage-floor.php tmp/coverage.xml [tmp/coverage-domain.xml]
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

$cbuild_file = $argv[1] ?? 'tmp/coverage.xml';
$cbuild_dir  = $argv[2] ?? '';
if ( ! is_readable( $cbuild_file ) ) {
	fwrite( STDERR, "No coverage file at {$cbuild_file}.\n" );
	exit( 1 );
}
$cbuild_xml = simplexml_load_file( $cbuild_file );
if ( false === $cbuild_xml ) {
	fwrite( STDERR, "Unreadable coverage file.\n" );
	exit( 1 );
}

$cbuild_total  = array( 'statements' => 0, 'covered' => 0 );
$cbuild_domain = array( 'statements' => 0, 'covered' => 0 );
foreach ( $cbuild_xml->xpath( '//file' ) ?: array() as $cbuild_node ) {
	$cbuild_metrics = $cbuild_node->metrics;
	$cbuild_s       = (int) $cbuild_metrics['statements'];
	$cbuild_c       = (int) $cbuild_metrics['coveredstatements'];
	$cbuild_total['statements'] += $cbuild_s;
	$cbuild_total['covered']    += $cbuild_c;
	if ( str_contains( (string) $cbuild_node['name'], '/src/Domain/' ) ) {
		$cbuild_domain['statements'] += $cbuild_s;
		$cbuild_domain['covered']    += $cbuild_c;
	}
}

$cbuild_pct = static fn( array $m ): float => $m['statements'] > 0 ? 100.0 * $m['covered'] / $m['statements'] : 100.0;
printf( "Lines: %.2f%% (%d/%d) overall; %.2f%% (%d/%d) in src/Domain.\n", $cbuild_pct( $cbuild_total ), $cbuild_total['covered'], $cbuild_total['statements'], $cbuild_pct( $cbuild_domain ), $cbuild_domain['covered'], $cbuild_domain['statements'] );

$cbuild_status = 0;
if ( $cbuild_pct( $cbuild_total ) < 90.0 ) {
	fwrite( STDERR, "Line coverage is under 90%.\n" );
	$cbuild_status = 1;
}
if ( $cbuild_pct( $cbuild_domain ) < 100.0 ) {
	fwrite( STDERR, "src/Domain line coverage is under 100%.\n" );
	$cbuild_status = 1;
}

// Branches, from a Cobertura report of src/Domain alone, collected with path coverage (phpunit-domain.xml.dist).
// PHPUnit's XML report carries lines only: reading branches from it found none and passed whatever the tests did.
if ( '' !== $cbuild_dir ) {
	$cbuild_report = is_readable( $cbuild_dir ) ? simplexml_load_file( $cbuild_dir ) : false;
	if ( false === $cbuild_report || ! isset( $cbuild_report['branches-valid'], $cbuild_report['branches-covered'] ) ) {
		fwrite( STDERR, "The domain's branch coverage could not be read from {$cbuild_dir}.\n" );
		exit( 1 );
	}
	$cbuild_valid   = (int) $cbuild_report['branches-valid'];
	$cbuild_covered = (int) $cbuild_report['branches-covered'];
	printf( "Branches in src/Domain: %d of %d.\n", $cbuild_covered, $cbuild_valid );
	if ( 0 === $cbuild_valid ) {
		// The domain has files and no branch was counted: path coverage was not collected, and the floor measured nothing.
		fwrite( STDERR, "No branch of src/Domain was measured; the branch floor cannot be checked.\n" );
		$cbuild_status = 1;
	} elseif ( $cbuild_covered < $cbuild_valid ) {
		fwrite( STDERR, "src/Domain branch coverage is under 100%.\n" );
		$cbuild_status = 1;
	}
}
exit( $cbuild_status );
