#!/usr/bin/env bash
# Yoast SEO for the integration site (milestone 4, D-102): Yoast builds its own sitemap and never asks WordPress's, so
# the test that a kit stays out of it needs Yoast itself. The free plugin, pinned, its zip checked against the hash of
# the release it was chosen from. It is never activated in the site: the suite's bootstrap loads it for the one leg.
# Usage: tools/yoast.sh up, then CBUILD_YOAST=1 bash tools/integration.sh --filter "$(bash tools/yoast.sh tests)",
# then tools/yoast.sh down. tools/integration-yoast.sh does all three, and tools/integration-variants.sh's yoast variant
# takes its filter from here too.
# `tests` prints the one filter both use: every class whose name ends in YoastTest. CI's variant once named
# KitYoastTest alone, so BuilderYoastTest and ShareCardYoastTest never ran there, and ShareCardYoastTest stayed red
# from D-460 (1.3.0) until 1.4.0's full set found it (D-520).
set -euo pipefail
cd "$(dirname "$0")/.."

plugin_version="28.9"
plugin_sha256="1f1af9d3dff70288b37e6edc04e27cff4979b258f62960c0b91c092614bf2301"

site() {
	npx wp-env run cli --config=.wp-env.tests.json -- bash -c "$1"
}

down() {
	site 'rm -rf wp-content/plugins/wordpress-seo /tmp/wordpress-seo.zip' >/dev/null 2>&1 || true
}

case "${1:-}" in
	up)
		if ! site "set -e
			curl -sSfL -o /tmp/wordpress-seo.zip https://downloads.wordpress.org/plugin/wordpress-seo.${plugin_version}.zip
			echo '${plugin_sha256}  /tmp/wordpress-seo.zip' | sha256sum -c - >/dev/null
			rm -rf wp-content/plugins/wordpress-seo
			unzip -q /tmp/wordpress-seo.zip -d wp-content/plugins
			rm /tmp/wordpress-seo.zip"; then
			echo "Yoast SEO ${plugin_version} could not be installed, or its zip is not the one pinned." >&2
			down
			exit 1
		fi
		echo "Yoast SEO ${plugin_version} is in the integration site's plugins."
		;;
	down)
		down
		;;
	tests)
		echo 'YoastTest::'
		;;
	*)
		echo "Usage: tools/yoast.sh up|down|tests" >&2
		exit 2
		;;
esac
