#!/usr/bin/env bash
# One line-coverage number for all of src/ (composer test:coverage:all; issue #6, D-050): the unit suite's report and the
# integration suite's, merged line by line, with the 95 % floor on the result. A class is counted wherever it is
# tested, so nothing in src/ is left out by name. The integration site must be up with Xdebug in coverage mode, which
# is how `npm run env:start` starts it.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p tmp
# shellcheck source=tools/coverage-driver.sh
source tools/coverage-driver.sh
# A report left by an earlier run must not stand in for one this run failed to write.
rm -f tmp/coverage.xml tmp/coverage-integration.xml tmp/coverage-bootstrap.json
php "${lines[@]}" vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --exclude-group benchmark --coverage-clover tmp/coverage.xml --no-output
CBUILD_COVERAGE=1 bash tools/integration.sh --coverage-clover tmp/coverage-integration.xml
php tools/coverage-merge.php tmp/coverage.xml tmp/coverage-integration.xml tmp/coverage-bootstrap.json
