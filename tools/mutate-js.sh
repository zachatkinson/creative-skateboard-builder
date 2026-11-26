#!/usr/bin/env bash
# StrykerJS on the plugin's TypeScript at 95 % (docs/AUDIT.md: Mutation testing): the admin's script now (D-086), and
# the builder's store from milestone 6. With no file to mutate, the run is skipped.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -z "$(find assets/src -name '*.ts' ! -name '*.d.ts' 2>/dev/null | head -n 1)" ]; then
	echo "assets/src has no TypeScript yet; the StrykerJS run is skipped."
	exit 0
fi
# Vitest's GitHub reporter writes a job summary each time it runs, and Stryker runs it once per mutant: a run left a
# report of "failures" per killed mutant on the summary page (issue #31). Stryker's own report is the one to read.
unset GITHUB_STEP_SUMMARY
# Extra arguments pass through: `npm run mutate:js -- --incremental` reuses the last run's results for the code and
# tests that did not change (tmp/stryker/incremental.json), for a lane before landing; CI runs it whole (D-465).
exec npx stryker run stryker.config.mjs "$@"
