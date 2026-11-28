#!/usr/bin/env bash
# The unit suite with coverage (composer test:coverage), then the domain's floors: 100 % of src/Domain's lines and
# branches (docs/AUDIT.md: Unit tests). The 95 % floor on all of src/ is read from both suites together, by
# tools/coverage-all.sh (D-050). pcov measures lines; Xdebug's path
# coverage measures the domain's branches in a second run, since pcov measures lines only (D-030). That run leaves out
# the seeded invariant (group invariant): path coverage makes its walks take minutes, and the floor is the case tests'
# to meet (D-059).
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/domain-present.sh
source tools/domain-present.sh
mkdir -p tmp
# shellcheck source=tools/coverage-driver.sh
source tools/coverage-driver.sh
php "${lines[@]}" vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --exclude-group benchmark --coverage-clover tmp/coverage.xml --coverage-text=tmp/coverage.txt
if ! has xdebug; then
	echo "Xdebug is not loaded; src/Domain's branch coverage cannot be measured." >&2
	exit 1
fi
# The path run is most of the time (some 270 s on one core), and its tests are independent, so it runs as shards, one a
# core up to eight, each with its own files, and their coverage is merged (tools/coverage-domain-merge.php). Files go
# to the shard with the least so far, largest first, by size. CBUILD_COVERAGE_SHARDS=1 runs it as one.
rm -f tmp/coverage-domain.xml tmp/coverage-domain-*.cov tmp/coverage-domain-*.txt tmp/coverage-domain-*.log
cores="$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 1)"
shards="${CBUILD_COVERAGE_SHARDS:-$(( cores < 8 ? cores : 8 ))}"
# Every shard loads the domain in one order first, so each compiles it alike (tests/bootstrap-domain.php).
domain=(env CBUILD_COVERAGE_PRELOAD=1 php -d pcov.enabled=0 -d xdebug.mode=coverage vendor/bin/phpunit -c phpunit-domain.xml.dist --testsuite unit --exclude-group benchmark --exclude-group invariant --exclude-group cap --exclude-group exhaustive --do-not-record-test-run-history)
# A read loop, not mapfile: macOS's bash is 3.2.
files=()
while IFS= read -r file; do files+=("$file"); done < <("${domain[@]}" --list-test-files | grep -E '\.php$' | sed -E 's/^ *- *//')
if [ "${#files[@]}" -eq 0 ]; then
	echo "No domain test files listed." >&2
	exit 1
fi
load=()
for (( i = 1; i <= shards; i++ )); do load[i]=0; : > "tmp/coverage-domain-${i}.txt"; done
while read -r size file; do
	least=1
	for (( i = 2; i <= shards; i++ )); do (( load[i] < load[least] )) && least=$i; done
	load[least]=$(( load[least] + size ))
	echo "$file" >> "tmp/coverage-domain-${least}.txt"
done < <(for f in "${files[@]}"; do printf '%s %s\n' "$(wc -c < "$f" | tr -d ' ')" "$f"; done | sort -rn)
pids=()
for (( i = 1; i <= shards; i++ )); do
	[ -s "tmp/coverage-domain-${i}.txt" ] || continue
	"${domain[@]}" --path-coverage --test-files-file "tmp/coverage-domain-${i}.txt" --coverage-php "tmp/coverage-domain-${i}.cov" > "tmp/coverage-domain-${i}.log" 2>&1 &
	pids+=("$i:$!")
done
failed=0
for entry in "${pids[@]}"; do
	i="${entry%%:*}"
	if ! wait "${entry#*:}"; then
		echo "The domain's path-coverage shard ${i} failed:" >&2
		cat "tmp/coverage-domain-${i}.log" >&2
		failed=1
	fi
done
[ "$failed" -eq 0 ] || exit 1
php -d memory_limit=1G tools/coverage-domain-merge.php tmp/coverage-domain.xml tmp/coverage-domain-*.cov
php tools/coverage-floor.php tmp/coverage.xml tmp/coverage-domain.xml
