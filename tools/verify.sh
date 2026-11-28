#!/usr/bin/env bash
# /verify's steps (.claude/skills/verify/SKILL.md), in order, stopping at the first failure (D-567).
#
#   bash tools/verify.sh [--full]                    before a commit; --full before a push
#   bash tools/lane.sh 2 bash tools/verify.sh        a second checkout's sites (tools/lane.sh)
#   bash tools/verify.sh --browser <file…>           prints "full" or "focused" for those changed files, runs nothing
#
# The browser step: before a commit, the browser tests that cover what changed since HEAD (tools/focused.sh
# --browser-only); with --full, chromium-desktop and chromium-phone whole. The whole run is chosen before a commit too
# when a change is where picking tests is least sure: the builder's script, a template, a stylesheet, the browser
# tests' support files or Playwright's config, or anything focused.sh can't map. Every push runs --full first, and CI
# runs every project, so nothing reaches main unchecked by the whole browser suite.
#
# One run a lane: a second run on the same sites refuses, as two suites in one test database break each other (27
# November 2026: a stopped run's PHPUnit, still running in the container, failed the next run's coverage). Stopped with
# Ctrl-C or a signal, the run stops its own processes and the integration suite inside the container.
#
# It runs in bash: under zsh, set -e in a subshell let a run print VERIFIED past a failed gate (`verify-runs-in-bash`).
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
# shellcheck source=tools/ports.sh
source tools/ports.sh

# The changed files that make a commit's browser step the whole run.
whole_browser='^(assets/src/builder/|templates/|tests/e2e/support/|playwright\.config\.ts$)|\.(s?css)$'

browser_for() {
	if printf '%s\n' "$@" | grep -qE "$whole_browser"; then echo full; else echo focused; fi
}

full=0
case "${1:-}" in
	'') ;;
	--full) full=1 ;;
	--browser)
		shift
		browser_for "$@"
		exit 0
		;;
	*) echo "Usage: bash tools/verify.sh [--full] | --browser <file…>" >&2; exit 2 ;;
esac

lock="${TMPDIR:-/tmp}/cbuild-verify-${CBUILD_PORT}.lock"
if ! mkdir "$lock" 2> /dev/null; then
	holder="$(cat "$lock/pid" 2> /dev/null || true)"
	if [ -n "$holder" ] && kill -0 "$holder" 2> /dev/null; then
		echo "FAILED: /verify is already running on the sites at :${CBUILD_PORT} (process ${holder}). Wait for it, or stop it." >&2
		exit 1
	fi
	# Its holder is gone: a run killed with no chance to clean up.
	rm -rf "$lock"
	mkdir "$lock"
fi
echo "$$" > "$lock/pid"
stopped=0
finish() {
	if [ "$stopped" -eq 1 ]; then
		# Its own processes, then the suite inside the container, which outlives the docker exec that started it.
		pkill -TERM -P "$$" 2> /dev/null || true
		if curl -sf "http://localhost:${CBUILD_TESTS_PORT}/" > /dev/null; then
			npx wp-env run cli --config=.wp-env.tests.json -- pkill -f "[t]ools/integration/vendor/bin/phpunit" > /dev/null 2>&1 || true
		fi
	fi
	rm -rf "$lock"
}
trap finish EXIT
trap 'stopped=1; echo "Stopped."; exit 130' INT TERM

step() {
	echo "== $*"
	if ! "$@"; then
		echo "FAILED: $*"
		# Keep the browser run's traces: the next Playwright run clears test-results.
		if [ -d test-results ] && [ -n "$(ls -A test-results 2> /dev/null)" ]; then
			keep="tmp/kept-results/$(date -u +%Y%m%dT%H%M%S)"
			mkdir -p "$keep" && cp -R test-results/. "$keep/" && echo "Traces kept in $keep"
		fi
		exit 1
	fi
}

# The packages, reinstalled when a lock file changed since this checkout's last run: a checkout moved to another
# branch keeps its old vendor/ and node_modules/, and the gates would run on the wrong versions.
locks="$(cat composer.lock package-lock.json | shasum | cut -d' ' -f1)"
mark=tmp/verify-locks
mkdir -p tmp
if [ "$(cat "$mark" 2> /dev/null)" != "$locks" ]; then
	step composer install -q
	step npm ci --silent
	echo "$locks" > "$mark"
fi

step composer -q lint
step composer -q analyse
step composer -q arch
step composer -q gates
step composer -q test:coverage
step composer -q api:check
step npm run -s types
if [ -n "$(git status --porcelain -- assets/src/types)" ]; then
	git status --porcelain -- assets/src/types
	echo "FAILED: the generated types differ from the committed ones"
	exit 1
fi
step npm run -s check
step npm run -s build
step npm run -s budget

if ! curl -sf "http://localhost:${CBUILD_PORT}/" > /dev/null; then
	echo "VERIFIED, but wp-env is down on :${CBUILD_PORT}: the integration suite, i18n, Plugin Check and the browsers were skipped"
	exit 0
fi
step env CBUILD_COVERAGE_DRIVER=pcov composer test:coverage:all
step composer -q i18n:check
step npm run -s plugin-check

whole=(npx playwright test --project=chromium-desktop --project=chromium-phone --workers=1 --reporter=line)
changed="$( { git diff --name-only HEAD; git ls-files --others --exclude-standard; } | sort -u)"
if [ "$full" -eq 1 ]; then
	step "${whole[@]}"
	echo "VERIFIED (full)"
	exit 0
fi
# shellcheck disable=SC2086 # one word a path, none with spaces
if [ "$(browser_for $changed)" = full ]; then
	echo "The browser step runs whole: a change is in the builder's script, a template, a stylesheet or the tests' support."
	step "${whole[@]}"
	echo "VERIFIED (full)"
	exit 0
fi
bash tools/focused.sh --browser-only
code=$?
case "$code" in
	0) echo "VERIFIED (focused browser step; run bash tools/verify.sh --full before pushing)" ;;
	3)
		echo "The browser step runs whole: focused.sh can't map a change."
		step "${whole[@]}"
		echo "VERIFIED (full)"
		;;
	*) echo "FAILED: the focused browser tests"; exit 1 ;;
esac
