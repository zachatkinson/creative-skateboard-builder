#!/usr/bin/env bash
# One line-coverage number for all of src/ (composer test:coverage:all; issue #6, D-050): the unit suite's report and the
# integration suite's, merged line by line, with the 95 % floor on the result. A class is counted wherever it is
# tested, so nothing in src/ is left out by name. The integration site must be up with Xdebug in coverage mode, which
# is how `npm run env:start` starts it.
#
# CBUILD_COVERAGE_DRIVER picks the driver of both halves. Unset, the unit half takes pcov where this PHP loads it
# (tools/coverage-driver.sh) and the integration half Xdebug, as CI does. =pcov measures the integration half, the slow
# one, with pcov and Xdebug off, after tools/pcov.sh has built pcov into the integration site's cli container. =xdebug
# measures both halves with Xdebug.
#
# CBUILD_COVERAGE_SHARDS=<folder> is CI's: the integration suite ran in shards, each in a job of its own
# (tools/integration-shards.php), and the folder holds each shard's artifact. Their reports are merged with this run's
# unit report in place of a run of the suite here, which then needs no site. Every shard's report must be there.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
# shellcheck source=tools/coverage-driver.sh
source tools/coverage-driver.sh
# A report left by an earlier run must not stand in for one this run failed to write.
rm -f tmp/coverage.xml tmp/coverage-integration.xml tmp/coverage-bootstrap.json
php "${lines[@]}" vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --exclude-group benchmark --coverage-clover tmp/coverage.xml --no-output
if [ -n "${CBUILD_COVERAGE_SHARDS:-}" ]; then
	shards="$(php tools/integration-shards.php count)"
	reports=()
	for ((k = 1; k <= shards; k++)); do
		reports+=("$CBUILD_COVERAGE_SHARDS/integration-shard-$k/coverage-integration.xml" "$CBUILD_COVERAGE_SHARDS/integration-shard-$k/coverage-bootstrap.json")
	done
	php tools/coverage-merge.php tmp/coverage.xml "${reports[@]}"
	exit
fi
if [ "${CBUILD_COVERAGE_DRIVER:-}" = pcov ]; then
	bash tools/pcov.sh
fi
CBUILD_COVERAGE=1 bash tools/integration.sh --coverage-clover tmp/coverage-integration.xml
php tools/coverage-merge.php tmp/coverage.xml tmp/coverage-integration.xml tmp/coverage-bootstrap.json
