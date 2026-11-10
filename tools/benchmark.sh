#!/usr/bin/env bash
# The server budgets (composer test:benchmark): the unit suite's benchmark group. PHPUnit fails a run that executes no
# test, so until the first benchmark exists (the fit engine's, milestone 2) this says so and passes; from then on an
# empty run is a failure again.
set -euo pipefail
cd "$(dirname "$0")/.."
if ! grep -rqE "Group\( *'benchmark' *\)|@group +benchmark" tests --include='*.php'; then
	echo "No test is in the benchmark group yet; nothing to measure."
	exit 0
fi
exec vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --group benchmark "$@"
