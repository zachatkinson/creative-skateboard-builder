#!/usr/bin/env bash
# The unit suite with coverage (composer test:coverage), then the floors: 90 % of the lines phpunit.xml.dist's <source>
# lists, 100 % of src/Domain's lines and branches (docs/AUDIT.md: Unit tests). pcov measures lines; Xdebug's path
# coverage measures the domain's branches in a second run, since pcov measures lines only (D-030).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
# Read once: grep -q in a pipe closes it early, and under pipefail php's SIGPIPE then reads as "not loaded".
modules="$(php -m)"
has() { grep -qi "^$1$" <<<"$modules"; }
if has pcov; then
	lines=(-d xdebug.mode=off -d pcov.enabled=1)
elif has xdebug; then
	lines=(-d xdebug.mode=coverage)
else
	echo "Neither pcov nor Xdebug is loaded; coverage cannot be measured." >&2
	exit 1
fi
php "${lines[@]}" vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --exclude-group benchmark --coverage-clover tmp/coverage.xml --coverage-text=tmp/coverage.txt
if [ -n "$(find src/Domain -name '*.php' 2>/dev/null | head -n 1)" ]; then
	if ! has xdebug; then
		echo "Xdebug is not loaded; src/Domain's branch coverage cannot be measured." >&2
		exit 1
	fi
	rm -f tmp/coverage-domain.xml
	php -d pcov.enabled=0 -d xdebug.mode=coverage vendor/bin/phpunit -c phpunit-domain.xml.dist --testsuite unit --exclude-group benchmark --path-coverage --coverage-cobertura tmp/coverage-domain.xml --no-output
	php tools/coverage-floor.php tmp/coverage.xml tmp/coverage-domain.xml
else
	echo "src/Domain has no file yet; its branch coverage run is skipped."
	php tools/coverage-floor.php tmp/coverage.xml
fi
