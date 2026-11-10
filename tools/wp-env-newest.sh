#!/usr/bin/env bash
# Back to .wp-env.json's pins, the newest supported pair (D-004). Run before `wp-env start`.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f .wp-env.override.json .wp-env.tests.override.json
echo "The override files are removed; the pins in .wp-env.json and .wp-env.tests.json apply. Run npm run env:start."
