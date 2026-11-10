#!/usr/bin/env bash
# The unit suite with coverage (composer test:coverage), then the domain's floors: 100 % of src/Domain's lines and
# branches (docs/AUDIT.md: Unit tests). The 90 % floor on all of src/ is read from both suites together, by
# tools/coverage-all.sh (D-050). pcov measures lines; Xdebug's path
# coverage measures the domain's branches in a second run, since pcov measures lines only (D-030). That run leaves out
# the seeded invariant (group invariant): path coverage makes its walks take minutes, and the floor is the case tests'
# to meet (D-059).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
# shellcheck source=tools/coverage-driver.sh
source tools/coverage-driver.sh
php "${lines[@]}" vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --exclude-group benchmark --coverage-clover tmp/coverage.xml --coverage-text=tmp/coverage.txt
if [ -n "$(find src/Domain -name '*.php' 2>/dev/null | head -n 1)" ]; then
	if ! has xdebug; then
		echo "Xdebug is not loaded; src/Domain's branch coverage cannot be measured." >&2
		exit 1
	fi
	rm -f tmp/coverage-domain.xml
	php -d pcov.enabled=0 -d xdebug.mode=coverage vendor/bin/phpunit -c phpunit-domain.xml.dist --testsuite unit --exclude-group benchmark --exclude-group invariant --path-coverage --coverage-cobertura tmp/coverage-domain.xml --no-output
	php tools/coverage-floor.php tmp/coverage.xml tmp/coverage-domain.xml
else
	echo "src/Domain has no file yet; its branch coverage run is skipped."
	php tools/coverage-floor.php tmp/coverage.xml
fi
