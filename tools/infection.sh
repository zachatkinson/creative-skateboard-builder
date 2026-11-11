#!/usr/bin/env bash
# Mutation testing (composer infection): 95 % outside the domain (infection.json5), 100 % inside it
# (infection-domain.json5). The domain's run fails when the domain has no file, and when static analysis alone caught a
# mutant that tools/infection-static-allowed.txt does not list.
# CBUILD_INFECTION and CBUILD_INFECTION_REPORT stand in for Infection and the domain's report in tools/gates-selftest.sh.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/domain-present.sh
source tools/domain-present.sh
mkdir -p tmp
infection="${CBUILD_INFECTION:-php -d xdebug.mode=off -d memory_limit=1G vendor/bin/infection}"
report="${CBUILD_INFECTION_REPORT:-tmp/infection-domain.json}"
# Infection's "max" is every core but one, which is one thread on a runner with two. CI names its own count (D-107).
threads="${CBUILD_INFECTION_THREADS:-max}"
$infection "--threads=${threads}" --configuration=infection.json5 "$@"
# An earlier run's report must not stand in for this one's.
rm -f "$report"
$infection "--threads=${threads}" --configuration=infection-domain.json5 "$@"
# A mutant only static analysis caught must be one the list allows (D-063). A pull request that changes no line of the
# domain has nothing to mutate there: Infection says so, succeeds and writes no report, and there is nothing to hold.
if [ -f "$report" ]; then
	php tools/infection-static.php "$report" tools/infection-static-allowed.txt
else
	echo "The domain's run wrote no report: nothing was mutated there, so no static-analysis kill to check."
fi
