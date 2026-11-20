<?php
/**
 * Merges the domain's path-coverage shards into the one Cobertura report the branch floor reads (tools/coverage.sh,
 * tools/coverage-floor.php). Each shard ran some of the domain's test files with Xdebug's path coverage and saved its
 * coverage with --coverage-php. php-code-coverage's own Merger joins their lines, branches and paths, and the report is
 * built as PHPUnit builds it, so it is the one a single run of every file writes.
 *
 * Run: php tools/coverage-domain-merge.php tmp/coverage-domain.xml tmp/coverage-domain-1.cov [tmp/coverage-domain-2.cov ...]
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

use SebastianBergmann\CodeCoverage\Node\Builder;
use SebastianBergmann\CodeCoverage\Report\Cobertura;
use SebastianBergmann\CodeCoverage\Serialization\Merger;
use SebastianBergmann\CodeCoverage\StaticAnalysis\Registry;

require dirname( __DIR__ ) . '/vendor/autoload.php';

$cbuild_out    = $argv[1] ?? '';
$cbuild_shards = array_slice( $argv, 2 );
if ( '' === $cbuild_out || array() === $cbuild_shards ) {
	fwrite( STDERR, "Usage: php tools/coverage-domain-merge.php <cobertura out> <shard.cov> [<shard.cov> ...]\n" );
	exit( 1 );
}
foreach ( $cbuild_shards as $cbuild_shard ) {
	if ( ! is_readable( $cbuild_shard ) ) {
		fwrite( STDERR, "No coverage shard at {$cbuild_shard}.\n" );
		exit( 1 );
	}
}

// The shards are this run's, on one machine and one commit: the merger's PHP, driver and git checks hold.
$cbuild_merged = ( new Merger() )->merge( $cbuild_shards );

// As CodeCoverage::getReport() builds it, with PHPUnit's defaults: annotations that ignore code are read, and
// deprecated code is not left out (phpunit-domain.xml.dist sets neither).
$cbuild_report = ( new Builder( Registry::analyser( null, true, false ) ) )->build(
	$cbuild_merged['codeCoverage'],
	$cbuild_merged['testResults'],
	$cbuild_merged['basePath']
);
( new Cobertura() )->process( $cbuild_report, $cbuild_out );
echo 'Merged ' . count( $cbuild_shards ) . " coverage shards into {$cbuild_out}.\n";
