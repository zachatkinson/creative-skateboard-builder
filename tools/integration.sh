#!/usr/bin/env bash
# Runs the integration suite inside the integration site's container (.wp-env.tests.json, D-047) on PHPUnit 9.6, the newest version the WordPress core
# test suite supports (D-020; Creative Search's D-021). PHPUnit 9.6 and the Yoast polyfills live in their own Composer
# project under tools/integration/ (lock committed, vendor gitignored), because Composer cannot install two versions of
# one package. The matrix's switches pass into the container: CBUILD_HPOS=off|sync, CBUILD_MULTISITE=1,
# CBUILD_OBJECT_CACHE=persistent, CBUILD_YOAST=1 (tools/yoast.sh) and CBUILD_DB_HOST (another database server on wp-env's
# network, tools/matrix-db.sh).
# CBUILD_COVERAGE=1 is tools/coverage-all.sh's: the bootstrap then records the lines that run while WordPress loads.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f tools/integration/vendor/bin/phpunit ]; then
	composer install --working-dir=tools/integration --no-interaction --quiet
fi
# The suite keeps its own tables (wptests_), so its installer leaves the integration site (wp_) alone: the tests that need
# a second PHP process run WP-CLI against that site (D-031).
pass=("WORDPRESS_TABLE_PREFIX=wptests_")
for name in CBUILD_HPOS CBUILD_OBJECT_CACHE CBUILD_MULTISITE CBUILD_COVERAGE CBUILD_YOAST; do
	if [ -n "${!name:-}" ]; then
		pass+=("$name=${!name}")
	fi
done
if [ -n "${CBUILD_MULTISITE:-}" ]; then
	pass+=("WP_MULTISITE=1")
fi
if [ -n "${CBUILD_DB_HOST:-}" ]; then
	pass+=("WORDPRESS_DB_HOST=$CBUILD_DB_HOST")
fi
exec npx wp-env run cli --config=.wp-env.tests.json --env-cwd=wp-content/plugins/creative-skateboard-builder -- env ${pass[@]+"${pass[@]}"} php tools/integration/vendor/bin/phpunit -c phpunit-integration.xml.dist "$@"
