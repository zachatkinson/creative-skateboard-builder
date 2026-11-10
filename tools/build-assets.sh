#!/usr/bin/env bash
# npm run build: @wordpress/scripts with script modules (docs/STANDARDS.md: Script modules), through webpack.config.js,
# which names the admin's scripts (D-086). With nothing in assets/src to bundle, build/ is left empty.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p build
if [ -z "$(find assets/src -maxdepth 2 \( -name '*.ts' ! -name '*.d.ts' -o -name '*.js' -o -name '*.scss' -o -name 'block.json' \) 2>/dev/null | head -n 1)" ]; then
	echo "assets/src has no entry yet; build/ is empty."
	exit 0
fi
exec npx wp-scripts build --experimental-modules --webpack-src-dir=assets/src --output-path=build
