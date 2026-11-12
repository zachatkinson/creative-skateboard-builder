#!/usr/bin/env bash
# The browser site as every spec expects it: the plugin active, the default theme, and the shop's fixture (a kit and
# its parts, written to tmp/e2e-builder.json). Playwright's global setup runs it; tools/visual.sh runs it on the host
# before the browser starts in its container, which has no wp-env (D-162).
set -euo pipefail
cd "$(dirname "$0")/.."

# Activate only what is not active: WP-CLI warns about the rest, and a warning nobody needs hides one that matters.
npx wp-env run cli -- sh -c "wp plugin is-active creative-skateboard-builder || wp plugin activate creative-skateboard-builder" > /dev/null
npx wp-env run cli -- sh -c "wp theme is-active twentytwentyfive || wp theme activate twentytwentyfive" > /dev/null
npx wp-env run cli -- wp eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php > /dev/null
