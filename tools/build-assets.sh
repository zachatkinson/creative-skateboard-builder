#!/usr/bin/env bash
# npm run build: @wordpress/scripts with script modules (docs/STANDARDS.md: Script modules). Until the first entry
# point lands (milestone 6) there is nothing to bundle, so build/ is made empty and the budgets measure nothing.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p build
if [ -z "$(find assets/src -maxdepth 2 \( -name '*.ts' -o -name '*.js' -o -name '*.scss' -o -name 'block.json' \) 2>/dev/null | head -n 1)" ]; then
	echo "assets/src has no entry yet; build/ is empty."
	exit 0
fi
exec npx wp-scripts build --experimental-modules --webpack-src-dir=assets/src --output-path=build
