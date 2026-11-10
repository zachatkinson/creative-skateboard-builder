#!/usr/bin/env bash
# Lists the branches of src/Domain that no test takes, by file, method and line: the detail behind the 100 % branch
# floor, which composer test:coverage reports only as a count.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
php -d pcov.enabled=0 -d xdebug.mode=coverage vendor/bin/phpunit -c phpunit-domain.xml.dist --exclude-group benchmark --exclude-group invariant --path-coverage --coverage-php tmp/coverage-domain.cov --no-output
php -r '
require "vendor/autoload.php";
$report = include "tmp/coverage-domain.cov";
$missed = 0;
foreach ( $report["codeCoverage"]->functionCoverage() as $file => $functions ) {
	foreach ( $functions as $name => $function ) {
		foreach ( $function->branches as $branch ) {
			if ( array() === $branch->hit ) {
				++$missed;
				printf( "%s %s lines %d to %d\n", substr( $file, strlen( getcwd() ) + 1 ), $name, $branch->line_start, $branch->line_end );
			}
		}
	}
}
echo $missed, " branch(es) not taken.\n";
'
