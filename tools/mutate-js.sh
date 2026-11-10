#!/usr/bin/env bash
# StrykerJS on the builder's store at 90 % (docs/AUDIT.md: Mutation testing). The run waits for the store's first file
# (milestone 6).
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -z "$(find assets/src -name '*.ts' ! -name '*.d.ts' 2>/dev/null | head -n 1)" ]; then
	echo "assets/src has no TypeScript yet; the StrykerJS run is skipped."
	exit 0
fi
exec npx stryker run stryker.config.mjs
