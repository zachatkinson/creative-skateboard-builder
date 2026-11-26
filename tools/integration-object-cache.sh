#!/usr/bin/env bash
# The integration suite on a real persistent object cache (composer test:integration:object-cache; issue #4, D-066):
# starts the leg's Redis server and drop-in, runs the suite, and removes both whatever the suite's result.
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
trap 'bash tools/object-cache.sh down' EXIT
bash tools/object-cache.sh up || exit 1
CBUILD_OBJECT_CACHE=persistent bash tools/integration.sh "$@"
