#!/usr/bin/env bash
# The tests that need Yoast SEO (composer test:integration:yoast; D-102): puts the pinned plugin in the integration
# site, runs those tests alone, and removes it whatever their result.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
trap 'bash tools/yoast.sh down' EXIT
bash tools/yoast.sh up || exit 1
CBUILD_YOAST=1 bash tools/integration.sh --filter "$(bash tools/yoast.sh tests)" "$@"
