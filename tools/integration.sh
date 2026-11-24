#!/usr/bin/env bash
# Runs the integration suite inside the integration site's container (.wp-env.tests.json, D-047) on PHPUnit 9.6, the newest version the WordPress core
# test suite supports (D-020; Creative Search's D-021). PHPUnit 9.6 and the Yoast polyfills live in their own Composer
# project under tools/integration/ (lock committed, vendor gitignored), because Composer cannot install two versions of
# one package. The matrix's switches pass into the container: CBUILD_HPOS=off|sync, CBUILD_MULTISITE=1,
# CBUILD_OBJECT_CACHE=persistent, CBUILD_YOAST=1 (tools/yoast.sh) and CBUILD_DB_HOST (another database server on wp-env's
# network, tools/matrix-db.sh).
# CBUILD_COVERAGE=1 is tools/coverage-all.sh's: the bootstrap then records the lines that run while WordPress loads.
# CBUILD_COVERAGE_DRIVER=pcov measures with pcov in place of Xdebug (tools/pcov.sh puts it in the container): pcov is
# loaded for this run alone and Xdebug is turned off for it, as the site was started in its coverage mode.
# CBUILD_SHARD=<k> runs shard k of the suite alone, as tools/integration-shards.php splits it (CI's newest leg).
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f tools/integration/vendor/bin/phpunit ]; then
	composer install --working-dir=tools/integration --no-interaction --quiet
fi
config=phpunit-integration.xml.dist
if [ -n "${CBUILD_SHARD:-}" ]; then
	config="$(php tools/integration-shards.php config "$CBUILD_SHARD")"
fi
# The suite keeps its own tables (wptests_), so its installer leaves the integration site (wp_) alone: the tests that need
# a second PHP process run WP-CLI against that site (D-031).
# shellcheck source=tools/ports.sh
source tools/ports.sh
# The site's port goes in too: a test that installs the second site on an empty database server gives it the address
# the integration site has (tests/Support/IntegrationTestCase.php).
pass=("WORDPRESS_TABLE_PREFIX=wptests_" "CBUILD_TESTS_PORT=$CBUILD_TESTS_PORT")
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
php=()
case "${CBUILD_COVERAGE_DRIVER:-}" in
	'' | xdebug) ;;
	pcov) php=(-d extension=pcov.so -d pcov.enabled=1 -d pcov.directory=/var/www/html/wp-content/plugins/creative-skateboard-builder/src -d xdebug.mode=off) ;;
	*) echo "CBUILD_COVERAGE_DRIVER is pcov or xdebug, not ${CBUILD_COVERAGE_DRIVER}." >&2; exit 1 ;;
esac
exec npx wp-env run cli --config=.wp-env.tests.json --env-cwd=wp-content/plugins/creative-skateboard-builder -- env ${pass[@]+"${pass[@]}"} php ${php[@]+"${php[@]}"} tools/integration/vendor/bin/phpunit -c "$config" "$@"
