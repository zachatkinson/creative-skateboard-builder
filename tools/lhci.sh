#!/usr/bin/env bash
# Lighthouse CI (npm run lhci) on the browser site as a shop serves it: minified assets and the shop open (D-032).
# wp-env runs with SCRIPT_DEBUG on, and a new WooCommerce shop shows its "coming soon" page; both are put back after
# the run, so the browser tests that follow see the site they expect.
set -euo pipefail
cd "$(dirname "$0")/.."
wp() { npx wp-env run cli -- wp "$@" 2>/dev/null; }
soon="$(wp option get woocommerce_coming_soon | tr -d '\r' | tail -n 1 || true)"
restore() {
	wp config set SCRIPT_DEBUG true --raw >/dev/null || true
	wp option update woocommerce_coming_soon "${soon:-yes}" >/dev/null || true
}
trap restore EXIT
wp config set SCRIPT_DEBUG false --raw >/dev/null
wp option update woocommerce_coming_soon no >/dev/null
npx lhci autorun --config=lighthouserc.cjs "$@"
