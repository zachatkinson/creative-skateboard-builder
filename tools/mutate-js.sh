#!/usr/bin/env bash
# StrykerJS on the plugin's TypeScript at 90 % (docs/AUDIT.md: Mutation testing): the admin's script now (D-086), and
# the builder's store from milestone 6. With no file to mutate, the run is skipped.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -z "$(find assets/src -name '*.ts' ! -name '*.d.ts' 2>/dev/null | head -n 1)" ]; then
	echo "assets/src has no TypeScript yet; the StrykerJS run is skipped."
	exit 0
fi
exec npx stryker run stryker.config.mjs
