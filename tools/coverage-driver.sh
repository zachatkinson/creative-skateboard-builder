#!/usr/bin/env bash
# Sourced by tools/coverage.sh and tools/coverage-all.sh: sets `lines` to the php flags that measure line coverage with
# pcov when it is loaded and Xdebug otherwise, and defines has() for the loaded modules. CBUILD_COVERAGE_DRIVER=pcov or
# xdebug asks for one, and fails when this PHP does not load it (tools/coverage-all.sh gives the integration half the
# same driver).
# Read once: grep -q in a pipe closes it early, and under pipefail php's SIGPIPE then reads as "not loaded".
modules="$(php -m)"
has() { grep -qi "^$1$" <<<"$modules"; }
case "${CBUILD_COVERAGE_DRIVER:-}" in
	'') ;;
	pcov | xdebug)
		if ! has "$CBUILD_COVERAGE_DRIVER"; then
			echo "CBUILD_COVERAGE_DRIVER is ${CBUILD_COVERAGE_DRIVER}, which this PHP does not load." >&2
			exit 1
		fi
		;;
	*)
		echo "CBUILD_COVERAGE_DRIVER is pcov or xdebug, not ${CBUILD_COVERAGE_DRIVER}." >&2
		exit 1
		;;
esac
if [ "${CBUILD_COVERAGE_DRIVER:-}" = xdebug ]; then
	lines=(-d pcov.enabled=0 -d xdebug.mode=coverage)
elif has pcov; then
	lines=(-d xdebug.mode=off -d pcov.enabled=1)
elif has xdebug; then
	lines=(-d xdebug.mode=coverage)
else
	echo "Neither pcov nor Xdebug is loaded; coverage cannot be measured." >&2
	exit 1
fi
