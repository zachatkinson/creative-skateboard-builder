<?php
/**
 * Fails when coverage is under the floors: 90 % of the lines phpunit.xml.dist's <source> lists, 100 % of
 * src/Domain's lines, and 100 % of its branches when the path-coverage XML is given (docs/AUDIT.md: Unit tests).
 *
 * Run: php tools/coverage-floor.php tmp/coverage.xml [tmp/coverage-domain]
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

// Branches, from PHPUnit's XML report with path coverage: each file's <totals><branches .../>.
if ( '' !== $cbuild_dir ) {
	$cbuild_branches = array( 'total' => 0, 'covered' => 0 );
	$cbuild_files    = glob( rtrim( $cbuild_dir, '/' ) . '/**/*.xml', GLOB_BRACE ) ?: array();
	$cbuild_iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $cbuild_iterator as $cbuild_path ) {
		$cbuild_path = (string) $cbuild_path;
		if ( ! str_ends_with( $cbuild_path, '.xml' ) || str_ends_with( $cbuild_path, '/index.xml' ) ) {
			continue;
		}
		$cbuild_report = simplexml_load_file( $cbuild_path );
		if ( false === $cbuild_report ) {
			continue;
		}
		$cbuild_report->registerXPathNamespace( 'p', 'https://schema.phpunit.de/coverage/1.0' );
		$cbuild_file_node = $cbuild_report->xpath( '//p:file' )[0] ?? null;
		if ( null === $cbuild_file_node || ! str_contains( (string) $cbuild_file_node['path'], 'src/Domain' ) ) {
			continue;
		}
		foreach ( $cbuild_report->xpath( '//p:file/p:totals/p:branches' ) ?: array() as $cbuild_b ) {
			$cbuild_branches['total']   += (int) $cbuild_b['count'];
			$cbuild_branches['covered'] += (int) $cbuild_b['tested'];
		}
	}
	$cbuild_branch_pct = $cbuild_branches['total'] > 0 ? 100.0 * $cbuild_branches['covered'] / $cbuild_branches['total'] : 100.0;
	printf( "Branches in src/Domain: %.2f%% (%d/%d).\n", $cbuild_branch_pct, $cbuild_branches['covered'], $cbuild_branches['total'] );
	if ( $cbuild_branch_pct < 100.0 ) {
		fwrite( STDERR, "src/Domain branch coverage is under 100%.\n" );
		$cbuild_status = 1;
	}
}
exit( $cbuild_status );
