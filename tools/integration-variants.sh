#!/usr/bin/env bash
# The six variants of the compatibility matrix, one after another on one wp-env (docs/AUDIT.md: The platform; D-040):
# MySQL 8.4 and MariaDB 10.11 as their own database servers, HPOS off and syncing, a persistent object cache, and
# multisite. Each is a full run of the integration suite, which installs its own tables first, so none depends on the
# one before it. A seventh run is a few seconds: the tests that need Yoast SEO, alone, with Yoast loaded (D-102).
# Every variant runs even when an earlier one fails, and the script fails if any did.
# Run with wp-env started: bash tools/integration-variants.sh [databases|the-rest]
# CI runs the two halves in jobs of their own, about ten minutes each (D-300): `databases` is the two database servers
# and HPOS off, `the-rest` HPOS syncing, the object cache, Yoast and multisite. With no argument, all seven run.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

half="${1:-all}"
case "$half" in
	all) expected=7 ;;
	databases) expected=3 ;;
	the-rest) expected=4 ;;
	*)
		echo "Unknown half: ${half}. Use databases, the-rest, or nothing for all seven." >&2
		exit 2
		;;
esac
first() { [ "$half" != the-rest ]; }
second() { [ "$half" != databases ]; }

failed=()
ran=0

# The tests a variant runs, when not the whole suite: arguments for PHPUnit.
only=()

# variant <name> [VAR=value ...]: the integration suite with those switches.
variant() {
	local name="$1"
	shift
	echo "::group::Integration: ${name}"
	if env "$@" bash tools/integration.sh ${only[@]+"${only[@]}"}; then
		echo "Variant ${name}: passed."
	else
		echo "Variant ${name}: FAILED."
		failed+=("$name")
	fi
	echo "::endgroup::"
	ran=$((ran + 1))
}

# database <name> <image>: the suite on a database server of its own, removed afterwards.
database() {
	local name="$1" image="$2"
	if bash tools/matrix-db.sh "$image" "cbuild-${name}"; then
		variant "$name" "CBUILD_DB_HOST=cbuild-${name}"
	else
		echo "Variant ${name}: FAILED (the database server did not start)."
		failed+=("$name")
		ran=$((ran + 1))
	fi
	docker rm -f "cbuild-${name}" >/dev/null 2>&1 || true
}

if first; then
	database mysql84 mysql:8.4
	database mariadb1011 mariadb:10.11
	variant hpos-off CBUILD_HPOS=off
fi
if second; then
	variant hpos-sync CBUILD_HPOS=sync
	# A Redis server and Redis Object Cache's drop-in, for this variant alone (tools/object-cache.sh, D-066).
	if bash tools/object-cache.sh up; then
		variant object-cache CBUILD_OBJECT_CACHE=persistent
	else
		echo "Variant object-cache: FAILED (the cache server or its drop-in did not start)."
		failed+=("object-cache")
		ran=$((ran + 1))
	fi
	bash tools/object-cache.sh down
	# Yoast SEO in the site's plugins, loaded for its own tests alone (tools/yoast.sh, D-102).
	if bash tools/yoast.sh up; then
		only=(--filter "$(bash tools/yoast.sh tests)")
		variant yoast CBUILD_YOAST=1
		only=()
	else
		echo "Variant yoast: FAILED (Yoast SEO could not be installed)."
		failed+=("yoast")
		ran=$((ran + 1))
	fi
	bash tools/yoast.sh down
	# Last: it is the one variant that leaves a network's tables behind it.
	variant multisite CBUILD_MULTISITE=1
fi

if [ "$ran" -ne "$expected" ]; then
	echo "Expected ${expected} variants, ran ${ran}." >&2
	exit 1
fi
if [ "${#failed[@]}" -gt 0 ]; then
	echo "Failed variants: ${failed[*]}" >&2
	exit 1
fi
echo "Integration variants (${half}): ${ran} ran, ${ran} passed."
