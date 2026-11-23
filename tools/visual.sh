#!/usr/bin/env bash
# The visual snapshots (tests/e2e/visual.spec.ts), in Playwright's own Linux image at the version package.json pins,
# locally and in CI alike, so a baseline made on one is the picture the other takes: the same Chromium and fonts.
# The image reaches the browser site on the host's network. Arguments go to Playwright, so
# `bash tools/visual.sh --update-snapshots` writes the baselines, which change only on purpose (D-162).
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/ports.sh
source tools/ports.sh
site="${WP_BASE_URL:-http://localhost:$CBUILD_PORT}"

version=$(node -p "require('./node_modules/@playwright/test/package.json').version")
image="mcr.microsoft.com/playwright:v${version}-noble"

curl -sf "$site/" > /dev/null || {
	echo "The browser site is not up: npm run env:start." >&2
	exit 1
}
# The container has no wp-env, so the site is made ready here, as Playwright's global setup would.
bash tools/e2e-site.sh

docker run --rm --network host --ipc host \
	--user "$(id -u):$(id -g)" -e HOME=/tmp \
	-e CBUILD_VISUAL=1 -e CBUILD_E2E_SITE_READY=1 -e CI="${CI:-}" \
	-e WP_BASE_URL="$site" \
	-v "$PWD:/work" -w /work \
	"$image" node node_modules/@playwright/test/cli.js test --project=visual "$@"
