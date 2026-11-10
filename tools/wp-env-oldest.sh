#!/usr/bin/env bash
# Writes .wp-env.override.json, and the same for the integration site (.wp-env.tests.override.json), for the oldest supported pair (D-004): WordPress 7.0 with WooCommerce 11.2.0, on the PHP
# 8.5 floor. Run before `wp-env start`; `npm run env:newest` removes it.
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
echo "The override files are written (WordPress 7.0.8, WooCommerce 11.2.0, PHP 8.5). Run npm run env:start."
