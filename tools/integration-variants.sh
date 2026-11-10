#!/usr/bin/env bash
# The six variants of the compatibility matrix, one after another on one wp-env (docs/AUDIT.md: The platform; D-040):
# MySQL 8.4 and MariaDB 10.11 as their own database servers, HPOS off and syncing, a persistent object cache, and
# multisite. Each is a full run of the integration suite, which installs its own tables first, so none depends on the
# one before it. Every variant runs even when an earlier one fails, and the script fails if any did.
# Run with wp-env started: bash tools/integration-variants.sh
set -uo pipefail
cd "$(dirname "$0")/.."

failed=()
ran=0

# variant <name> [VAR=value ...]: the integration suite with those switches.
variant() {
	local name="$1"
	shift
	echo "::group::Integration: ${name}"
	if env "$@" bash tools/integration.sh; then
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

database mysql84 mysql:8.4
database mariadb1011 mariadb:10.11
variant hpos-off CBUILD_HPOS=off
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
# Last: it is the one variant that leaves a network's tables behind it.
variant multisite CBUILD_MULTISITE=1

if [ "$ran" -ne 6 ]; then
	echo "Expected 6 variants, ran ${ran}." >&2
	exit 1
fi
if [ "${#failed[@]}" -gt 0 ]; then
	echo "Failed variants: ${failed[*]}" >&2
	exit 1
fi
echo "Integration variants: 6 ran, 6 passed."
