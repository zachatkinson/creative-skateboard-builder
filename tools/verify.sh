#!/usr/bin/env bash
# /verify's steps (.claude/skills/verify/SKILL.md), in order, stopping at the first failure (D-567).
#
#   bash tools/verify.sh [--full]                    before a commit; --full before a push
#   bash tools/lane.sh 2 bash tools/verify.sh        a second checkout's sites (tools/lane.sh)
#   bash tools/verify.sh --browser <file…>           prints "full" or "focused" for those changed files, runs nothing
#   bash tools/verify.sh --full --split <lane> <checkout>
#                                                    --full, with chromium-phone run by another lane's checkout
#
# The browser step: before a commit, the browser tests that cover what changed since HEAD (tools/focused.sh
# --browser-only); with --full, chromium-desktop and chromium-phone whole. The whole run is chosen before a commit too
# when a change is where picking tests is least sure: the builder's script, a template, a stylesheet, the browser
# tests' support files or Playwright's config, or anything focused.sh can't map. Every push runs --full first, and CI
# runs every project, so nothing reaches main unchecked by the whole browser suite.
#
# --split (D-570) halves a push's browser step: the other lane's checkout, which must be clean, is moved to this
# commit (a worktree of the same repository), installs and builds as this run does, and runs chromium-phone on its own
# sites, while this run takes the other steps and chromium-desktop. The run passes only if both halves do. This tree
# must be clean too: the other checkout can only stand on a commit. Its log is tmp/split-<lane>.log.
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

usage() {
	echo "Usage: bash tools/verify.sh [--full [--split <lane> <checkout>]] | --browser <file…>" >&2
	exit 2
}
full=0
half=''
split_lane=''
split_dir=''
case "${1:-}" in
	'') ;;
	--full)
		full=1
		if [ "${2:-}" = --split ]; then
			[ $# -eq 4 ] || usage
			split_lane="$3"
			split_dir="$4"
		elif [ $# -ne 1 ]; then
			usage
		fi
		;;
	# The other half of a split run: packages, the build and one browser project, on this checkout's sites.
	--half)
		[ $# -eq 2 ] || usage
		half="$2"
		;;
	--browser)
		shift
		browser_for "$@"
		exit 0
		;;
	*) usage ;;
esac
if [ -n "$split_dir" ]; then
	if [ -n "$(git status --porcelain)" ]; then
		echo "FAILED: --split needs a clean tree: the other checkout can only stand on a commit. Commit, or run --full alone." >&2
		exit 1
	fi
	if [ ! -d "$split_dir" ] || [ -n "$(git -C "$split_dir" status --porcelain)" ]; then
		echo "FAILED: --split's checkout ${split_dir} is missing or has changes of its own." >&2
		exit 1
	fi
fi

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
	if [ -n "${other:-}" ] && kill -0 "$other" 2> /dev/null; then
		# The other half has its own lock and trap: told to stop, it cleans up its own sites.
		kill -TERM "$other" 2> /dev/null || true
		wait "$other" 2> /dev/null || true
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

mkdir -p tmp
other=''
if [ -n "$split_dir" ]; then
	git -C "$split_dir" checkout -q --detach "$(git rev-parse HEAD)" || { echo "FAILED: --split could not move ${split_dir} to this commit"; exit 1; }
	( cd "$split_dir" && exec bash tools/lane.sh "$split_lane" bash tools/verify.sh --half chromium-phone ) > "tmp/split-${split_lane}.log" 2>&1 &
	other=$!
	echo "chromium-phone runs in ${split_dir}, lane ${split_lane} (tmp/split-${split_lane}.log)."
fi

# The packages, reinstalled when a lock file changed since this checkout's last run: a checkout moved to another
# branch keeps its old vendor/ and node_modules/, and the gates would run on the wrong versions.
locks="$(cat composer.lock package-lock.json | shasum | cut -d' ' -f1)"
mark=tmp/verify-locks
if [ "$(cat "$mark" 2> /dev/null)" != "$locks" ]; then
	step composer install -q
	step npm ci --silent
	echo "$locks" > "$mark"
fi

if [ -n "$half" ]; then
	step npm run -s build
	curl -sf "http://localhost:${CBUILD_PORT}/" > /dev/null || { echo "FAILED: the browser site isn't up on :${CBUILD_PORT}"; exit 1; }
	step npx playwright test "--project=${half}" --workers=1 --reporter=line
	echo "VERIFIED (${half})"
	exit 0
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
if [ -n "$other" ]; then
	step npx playwright test --project=chromium-desktop --workers=1 --reporter=line
	wait "$other"
	code=$?
	tail -n 4 "tmp/split-${split_lane}.log"
	if [ "$code" -ne 0 ]; then
		echo "FAILED: chromium-phone in ${split_dir} (tmp/split-${split_lane}.log)"
		exit 1
	fi
	echo "VERIFIED (full, split with lane ${split_lane})"
	exit 0
fi
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
