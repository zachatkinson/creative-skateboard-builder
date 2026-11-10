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
		6-*|11-*|12-*) mkdir -p "$root/src/Domain"; cp "$fixture" "$root/src/Domain/Probe.php" ;;
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
if [ "$status" -eq 0 ]; then echo "Gates self-test: ${count} snippets, each rejected by its gate (and the clean one passed)."; fi
exit "$status"
