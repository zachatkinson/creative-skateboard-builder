#!/usr/bin/env bash
# Yoast SEO for the integration site (milestone 4, D-102): Yoast builds its own sitemap and never asks WordPress's, so
# the test that a kit stays out of it needs Yoast itself. The free plugin, pinned, its zip checked against the hash of
# the release it was chosen from. It is never activated in the site: the suite's bootstrap loads it for the one leg.
# Usage: tools/yoast.sh up, then CBUILD_YOAST=1 bash tools/integration.sh --filter KitYoastTest, then
# tools/yoast.sh down. tools/integration-yoast.sh does all three.
set -euo pipefail
cd "$(dirname "$0")/.."

plugin_version="28.8"
plugin_sha256="27cc01cb149db7186ef750552a5cc2ae5028933473886e419918af273b075105"

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
	*)
		echo "Usage: tools/yoast.sh up|down" >&2
		exit 2
		;;
esac
