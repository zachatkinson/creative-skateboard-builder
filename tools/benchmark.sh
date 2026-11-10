#!/usr/bin/env bash
# The server budgets (composer test:benchmark): the unit suite's benchmark group. A run that finds no test in the group
# fails: a budget nothing measures is not met.
set -euo pipefail
cd "$(dirname "$0")/.."
if ! grep -rqE "Group\( *'benchmark' *\)|@group +benchmark" "${CBUILD_TESTS_DIR:-tests}" --include='*.php'; then
	echo "No test is in the benchmark group: there is nothing to measure, and that is a failure." >&2
	exit 1
fi
# Xdebug is off for the run: a shop does not load it, and loaded it more than doubles the engine's time.
exec php -d xdebug.mode=off vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --group benchmark "$@"
