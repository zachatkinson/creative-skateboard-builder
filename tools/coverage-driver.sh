#!/usr/bin/env bash
# Sourced by tools/coverage.sh and tools/coverage-all.sh: sets `lines` to the php flags that measure line coverage with
# pcov when it is loaded and Xdebug otherwise, and defines has() for the loaded modules.
# Read once: grep -q in a pipe closes it early, and under pipefail php's SIGPIPE then reads as "not loaded".
modules="$(php -m)"
has() { grep -qi "^$1$" <<<"$modules"; }
if has pcov; then
	lines=(-d xdebug.mode=off -d pcov.enabled=1)
elif has xdebug; then
	lines=(-d xdebug.mode=coverage)
else
	echo "Neither pcov nor Xdebug is loaded; coverage cannot be measured." >&2
	exit 1
fi
