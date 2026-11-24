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
# The builder's page, with the shop's fixture kit on it, unless another page is asked for.
bash tools/e2e-site.sh
export CBUILD_LHCI_URL="${CBUILD_LHCI_URL:-$(node -p "require('./tmp/e2e-builder.json').page")}"
# Measured warm, as a shop's page is: once on each theme, the jobs that visit queued (the ready builds' pictures,
# D-252) run, then once more. Cold, the first two runs answered in 1.1 to 1.2 s where the third took 0.56 s, while
# those jobs ran beside them on CI's two cores (PR #109's first run, LCP 3.19, 2.85 and 2.25 s). The gate's 2.5 s
# stands (D-342).
for warm in twentytwentyfive kadence twentytwentyfive kadence; do
	curl -sf -o /dev/null -H "Cookie: cbuild_e2e_theme=$warm" "$CBUILD_LHCI_URL" || true
	[ "$warm" = kadence ] && wp action-scheduler run --group=creative-skateboard-builder >/dev/null || true
done
# Twenty Twenty-Five's font moves the page as it arrives, so its layout shift is reported; Kadence gates (D-165).
CBUILD_LHCI_THEME=twentytwentyfive CBUILD_LHCI_SHIFT=warn npx lhci autorun --config=lighthouserc.cjs "$@"
CBUILD_LHCI_THEME=kadence npx lhci autorun --config=lighthouserc.cjs "$@"
# A build at its review, its deck, trucks and wheels drawn as layers: the board's deck layer must not hold LCP back.
build="$(node -p "const f=require('./tmp/e2e-builder.json'),p=f.parts;f.page+'?cbuild[deck]='+p['builder-test-deck-8-0']+'&cbuild[trucks]='+p['builder-test-trucks-139']+'&cbuild[wheels]='+p['builder-test-wheels-54']+'&cbuild[step]=review'")"
CBUILD_LHCI_URL="$build" CBUILD_LHCI_THEME=kadence CBUILD_LHCI_NAME=kadence-build CBUILD_LHCI_SEO=off npx lhci autorun --config=lighthouserc.cjs "$@"
