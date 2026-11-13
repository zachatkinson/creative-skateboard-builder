#!/usr/bin/env bash
# Milestone 3's server work at a catalogue's size (composer test:benchmark:integration): tests/Benchmark, on real
# WordPress in the integration site, with Xdebug off as a shop's PHP has it. It is outside the integration suite, which
# runs with coverage on and would time that instead. The numbers it prints go into docs/BENCHMARK.md at each tag.
# CBUILD_BENCHMARK_SHARED=1, which CI sets, scales the time budgets by the runner's speed against the Mac's, at most
# 2.5 times, and holds the queries to the same counts as anywhere (D-124, D-171). Without it the budgets hold as set.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f tools/integration/vendor/bin/phpunit ]; then
	composer install --working-dir=tools/integration --no-interaction --quiet
fi
exec npx wp-env run cli --config=.wp-env.tests.json --env-cwd=wp-content/plugins/creative-skateboard-builder -- env WORDPRESS_TABLE_PREFIX=wptests_ XDEBUG_MODE=off "CBUILD_BENCHMARK_SHARED=${CBUILD_BENCHMARK_SHARED:-0}" php -d xdebug.mode=off -d memory_limit=512M tools/integration/vendor/bin/phpunit -c phpunit-integration.xml.dist tests/Benchmark "$@"
