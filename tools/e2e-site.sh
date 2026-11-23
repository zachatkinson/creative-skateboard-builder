#!/usr/bin/env bash
# The browser site as every spec expects it: the plugin active, the default theme, and the shop's fixture (a kit and
# its parts, written to tmp/e2e-builder.json). Playwright's global setup runs it; tools/visual.sh runs it on the host
# before the browser starts in its container, which has no wp-env (D-162).
set -euo pipefail
cd "$(dirname "$0")/.."

# shellcheck source=tools/ports.sh
source tools/ports.sh

# Activate only what is not active: WP-CLI warns about the rest, and a warning nobody needs hides one that matters.
# The site's address comes back with it: the browser tests go to http://localhost:$CBUILD_PORT, and a checkout whose
# sites run in another lane (tools/lane.sh) would otherwise send them to another checkout's site.
home="$(npx wp-env run cli -- sh -c "(wp plugin is-active creative-skateboard-builder || wp plugin activate creative-skateboard-builder) > /dev/null && wp option get home" 2>/dev/null | tr -d '\r' | tail -n 1)"
if [ -z "${WP_BASE_URL:-}" ] && [ "$home" != "http://localhost:$CBUILD_PORT" ]; then
	echo "This checkout's browser site is $home, not http://localhost:$CBUILD_PORT: run the tests in the lane it was started in (bash tools/lane.sh <n> …)." >&2
	exit 1
fi
npx wp-env run cli -- sh -c "wp theme is-active twentytwentyfive || wp theme activate twentytwentyfive" > /dev/null
npx wp-env run cli -- wp eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php > /dev/null
