#!/usr/bin/env bash
# Proves the grep gates can fail (docs/AUDIT.md: Testing): runs tools/gates.sh on each snippet in tests/Fixtures/gates
# in an otherwise empty copy of the plugin's layout, and fails unless the gate the snippet's name starts with rejects
# it. 0-clean.fixture must pass, so a gate that rejects everything is caught too. Part of composer gates.
set -euo pipefail
tools="$(cd "$(dirname "$0")" && pwd)"
fixtures="$tools/../tests/Fixtures/gates"
status=0
count=0
for fixture in "$fixtures"/*.fixture; do
	name="$(basename "$fixture" .fixture)"
	gate="${name%%-*}"
	root="$(mktemp -d)"
	mkdir -p "$root/src/Support" "$root/languages"
	printf '<?php\ndeclare( strict_types=1 );\n' > "$root/creative-skateboard-builder.php"
	printf '<?php\ndeclare( strict_types=1 );\n' > "$root/uninstall.php"
	case "$name" in
		*.pot) cp "$fixture" "$root/languages/creative-skateboard-builder.pot" ;;
		*.yml) mkdir -p "$root/.github/workflows"; cp "$fixture" "$root/.github/workflows/release.yml" ;;
		6-*|11-*|12-*|13-*) mkdir -p "$root/src/Domain"; cp "$fixture" "$root/src/Domain/Probe.php" ;;
		14-*|15-*) mkdir -p "$root/assets/src/builder"; cp "$fixture" "$root/assets/src/builder/probe.ts" ;;
		*) cp "$fixture" "$root/src/Support/Probe.php" ;;
	esac
	code=0
	out="$(CBUILD_GATES_ROOT="$root" bash "$tools/gates.sh" 2>&1)" || code=$?
	rm -rf "$root"
	count=$((count + 1))
	if [ "$gate" = 0 ]; then
		if [ "$code" -ne 0 ]; then
			echo "Gates self-test: the clean snippet was rejected:"; echo "$out"; status=1
		fi
	elif [ "$code" -eq 0 ] || ! grep -q "^Gate ${gate}[: ]" <<<"$out"; then
		echo "Gates self-test: gate ${gate} did not reject ${name}."; status=1
	fi
done
# The runs that measure the domain, and the benchmark, must fail when there is nothing for them to measure (D-062).
empty="$(mktemp -d)"
for run in analyse-domain infection coverage; do
	if CBUILD_DOMAIN_DIR="$empty" bash "$tools/$run.sh" > /dev/null 2>&1; then
		echo "Gates self-test: tools/$run.sh passed with no domain to measure."; status=1
	fi
	count=$((count + 1))
done
if CBUILD_TESTS_DIR="$empty" bash "$tools/benchmark.sh" > /dev/null 2>&1; then
	echo "Gates self-test: tools/benchmark.sh passed with no benchmark to run."; status=1
fi
count=$((count + 1))
rmdir "$empty"
# The mutation run with Infection stood in for (D-064). A run that mutates nothing in the domain writes no report and
# must pass, whatever an earlier run left behind; a report with a static-analysis kill off the list must fail.
stub="$(mktemp -d)"
printf '#!/usr/bin/env bash\nexit 0\n' > "$stub/nothing.sh"
printf '#!/usr/bin/env bash\ncase "$*" in *infection-domain*) cp "$CBUILD_STUB_REPORT" "$CBUILD_INFECTION_REPORT" ;; esac\n' > "$stub/writes.sh"
unlisted='{"stats":{},"killedByStaticAnalysis":[{"mutator":{"mutatorName":"CastInt","originalFilePath":"src/Domain/Probe.php","originalStartLine":1}}]}'
printf '%s' "$unlisted" > "$stub/stale.json"
printf '%s' "$unlisted" > "$stub/unlisted.json"
printf '{"stats":{},"killedByStaticAnalysis":[]}' > "$stub/clean.json"
if ! CBUILD_INFECTION="bash $stub/nothing.sh" CBUILD_INFECTION_REPORT="$stub/stale.json" bash "$tools/infection.sh" > /dev/null 2>&1; then
	echo "Gates self-test: tools/infection.sh failed a run that mutated nothing in the domain."; status=1
fi
if [ -f "$stub/stale.json" ]; then
	echo "Gates self-test: tools/infection.sh left an earlier run's report in place."; status=1
fi
if ! CBUILD_INFECTION="bash $stub/writes.sh" CBUILD_STUB_REPORT="$stub/clean.json" CBUILD_INFECTION_REPORT="$stub/report.json" bash "$tools/infection.sh" > /dev/null 2>&1; then
	echo "Gates self-test: tools/infection.sh failed a report with no static-analysis kill."; status=1
fi
if CBUILD_INFECTION="bash $stub/writes.sh" CBUILD_STUB_REPORT="$stub/unlisted.json" CBUILD_INFECTION_REPORT="$stub/report.json" bash "$tools/infection.sh" > /dev/null 2>&1; then
	echo "Gates self-test: tools/infection.sh passed a static-analysis kill that is not on the list."; status=1
fi
count=$((count + 3))
rm -rf "$stub"
if [ "$status" -eq 0 ]; then echo "Gates self-test: ${count} snippets, each rejected by its gate (and the clean one passed)."; fi
exit "$status"
