#!/usr/bin/env bash
# Back to .wp-env.json's pins, the newest supported pair (D-004). Run before `wp-env start`.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f .wp-env.override.json
echo ".wp-env.override.json removed; .wp-env.json's pins apply. Run npx wp-env start."
