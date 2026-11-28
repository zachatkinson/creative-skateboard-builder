#!/usr/bin/env bash
# Writes .wp-env.override.json, and the same for the integration site (.wp-env.tests.override.json), for the oldest supported pair (D-004): WordPress 7.0 with WooCommerce 11.2.0, on the PHP
# 8.5 floor. Run before `wp-env start`; `npm run env:newest` removes it. With --start (`npm run env:oldest -- --start`)
# it starts both sites on them too, and each start fails unless its site then runs the pair (tools/wp-env.sh, D-548).
set -euo pipefail
cd "$(dirname "$0")/.."
cat > .wp-env.override.json <<'JSON'
{
	"core": "https://wordpress.org/wordpress-7.0.8.zip",
	"phpVersion": "8.5",
	"mappings": {
		"wp-content/plugins/woocommerce": "https://downloads.wordpress.org/plugin/woocommerce.11.2.0.zip"
	}
}
JSON
cp .wp-env.override.json .wp-env.tests.override.json
if [ "--start" = "${1:-}" ]; then
	echo "The override files are written (WordPress 7.0.8, WooCommerce 11.2.0, PHP 8.5). Starting both sites on them."
	bash tools/wp-env.sh start
	exec bash tools/wp-env.sh start --config=.wp-env.tests.json --xdebug=coverage
fi
echo "The override files are written (WordPress 7.0.8, WooCommerce 11.2.0, PHP 8.5). Run npm run env:start, or npm run env:oldest -- --start next time."
