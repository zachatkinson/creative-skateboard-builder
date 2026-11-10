#!/usr/bin/env bash
# The zip a shop installs, through WordPress's own installer on wp-env's browser site (the roadmap, milestone 1: "the
# milestone's zip installs on a clean WordPress, upgrades over itself and uninstalls clean"):
#   1. the site is cleared of the plugin's data, with the checkout's copy deactivated;
#   2. the zip is installed and activated: the tables and the schema version are there;
#   3. the same zip is installed over itself: still active, the version and tables as they were, nothing logged;
#   4. it is uninstalled: no table, option, transient or scheduled action is left, and its folder is gone;
#   5. it is installed again and uninstalled by WP-CLI running on PHP 7.4, below the plugin's floor, against the same
#      site and database: Composer's platform check must not stop the uninstall, and nothing is left (D-023, D-043).
# wp-env mounts this checkout at wp-content/plugins/creative-skateboard-builder, and installing a zip there would
# replace the checkout, so the zip's folder is renamed to cbuild-zip first; every file in it is the release's.
# Run: npm run zip-lifecycle (wp-env up).
set -euo pipefail
cd "$(dirname "$0")/.."
slug=creative-skateboard-builder
copy=cbuild-zip
# A PHP below the plugin's floor that WordPress and WooCommerce still run on.
old_php=7.4
# The schema version a fresh install ends at: the registry's, read from the source so a new migration needs no edit here.
schema="$(sed -n "s/.*const VERSION = '\([0-9]*\)';.*/\1/p" src/Install/Migrations/MigrationRegistry.php)"
[ -n "$schema" ] || { echo "zip lifecycle: the schema version was not found in MigrationRegistry.php" >&2; exit 1; }
zip="$(bash tools/build-zip.sh)"
stage="$(mktemp -d)"
unzip -q "$zip" -d "$stage"
mv "$stage/$slug" "$stage/$copy"
rm -f "tmp/$copy.zip"
( cd "$stage" && zip -qr "$OLDPWD/tmp/$copy.zip" "$copy" )
rm -rf "$stage"

# wp-env's own progress lines go to stderr; WP-CLI's answer is stdout.
wp() { npx wp-env run cli -- wp "$@" 2>/dev/null | tr -d '\r' || true; }
fail() { echo "zip lifecycle: $1" >&2; exit 1; }
restore() {
	wp plugin deactivate "$copy" >/dev/null
	npx wp-env run cli -- bash -c "rm -rf wp-content/plugins/$copy" >/dev/null 2>&1 || true
	wp plugin activate "$slug" >/dev/null
	rm -f "tmp/$copy.zip" tmp/old-php.env
}
trap restore EXIT

# version | the plugin's tables | its options and transients | actions in its group | a PHP error naming the copy
state='global $wpdb; $g = $wpdb->prefix . "actionscheduler_groups"; $a = $wpdb->prefix . "actionscheduler_actions"; $log = WP_CONTENT_DIR . "/debug.log"; echo get_option( "cbuild_db_version", "none" ), "|", (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s", $wpdb->esc_like( $wpdb->prefix . "cbuild_" ) . "%" ) ), "|", (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", "cbuild\\_%", "\\_transient%cbuild\\_%" ) ), "|", (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$a} a INNER JOIN {$g} g ON g.group_id = a.group_id WHERE g.slug = %s", "creative-skateboard-builder" ) ), "|", is_readable( $log ) ? preg_match_all( "#PHP (Fatal|Warning|Deprecated|Notice)[^\n]*cbuild-zip#", (string) file_get_contents( $log ) ) : 0;'
read_state() { wp eval "$state" | tail -n 1; }
active() { wp plugin get "$copy" --field=status | tail -n 1; }

echo "1. A site with none of the plugin's data."
wp plugin deactivate "$slug" >/dev/null
wp plugin uninstall "$slug" --skip-delete >/dev/null
before="$(read_state)"
[ "${before%|*}" = "none|0|0|0" ] || fail "the site is not clean before the install: $before"
errors="${before##*|}"

echo "2. Install the zip and activate it."
wp plugin install "wp-content/plugins/$slug/tmp/$copy.zip" --activate | tail -n 2
[ "$(active)" = active ] || fail "the zip did not activate"
installed="$(read_state)"
[ "$installed" = "$schema|2|1|0|$errors" ] || [ "$installed" = "$schema|2|2|0|$errors" ] || fail "after the install: $installed (version|tables|options|actions|errors)"
echo "   version|tables|options|actions|errors: $installed"

echo "3. Install the same zip over itself."
wp plugin install "wp-content/plugins/$slug/tmp/$copy.zip" --force | tail -n 2
[ "$(active)" = active ] || fail "the plugin is not active after the upgrade over itself"
upgraded="$(read_state)"
[ "$upgraded" = "$installed" ] || fail "the upgrade over itself changed the state: $installed became $upgraded"
echo "   unchanged: $upgraded"

echo "4. Uninstall it."
wp plugin uninstall "$copy" --deactivate | tail -n 2
gone="$(read_state)"
[ "$gone" = "none|0|0|0|$errors" ] || fail "after the uninstall: $gone (version|tables|options|actions|errors)"
if npx wp-env run cli -- test -d "wp-content/plugins/$copy" >/dev/null 2>&1; then fail "the plugin's folder is still there"; fi
echo "   nothing left: $gone"
echo "5. Install it again, and uninstall it on PHP $old_php, below the floor."
wp plugin install "wp-content/plugins/$slug/tmp/$copy.zip" --activate >/dev/null
again="$(read_state)"
[ "$again" = "$installed" ] || fail "the second install: $again (version|tables|options|actions|errors)"
wp plugin deactivate "$copy" >/dev/null
# The site's own WP-CLI container gives the files, the network and the database settings; only the PHP is older.
here="$(pwd)"
cli=""
# The browser site is the one serving :8898; the integration site (:8899) mounts this folder too.
for site in $(docker ps -q --filter label=com.docker.compose.service=wordpress --filter publish=8898); do
	if docker inspect "$site" --format '{{range .Mounts}}{{println .Source}}{{end}}' | grep -x "$here" >/dev/null; then
		project="$(docker inspect "$site" --format '{{index .Config.Labels "com.docker.compose.project"}}')"
		cli="$(docker ps -q --filter label=com.docker.compose.service=cli --filter "label=com.docker.compose.project=$project" | head -n 1)"
	fi
done
[ -n "$cli" ] || fail "wp-env's cli container was not found"
network="$(docker inspect "$cli" --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' | head -n 1)"
docker inspect "$cli" --format '{{range .Config.Env}}{{println .}}{{end}}' | grep '^WORDPRESS_' > tmp/old-php.env
old_wp() {
	docker run --rm --volumes-from "$cli" --network "$network" --env-file tmp/old-php.env \
		--user "$(docker inspect "$cli" --format '{{.Config.User}}')" -w /var/www/html "wordpress:cli-php$old_php" wp "$@"
}
ran_on="$(old_wp eval 'echo PHP_VERSION;' 2>/dev/null | tail -n 1)"
case "$ran_on" in "$old_php".*) ;; *) fail "WP-CLI did not run on PHP $old_php: '$ran_on'" ;; esac
if ! old_wp plugin uninstall "$copy" > tmp/old-php-uninstall.log 2>&1; then
	tail -n 5 tmp/old-php-uninstall.log >&2
	fail "the uninstall failed on PHP $ran_on"
fi
tail -n 2 tmp/old-php-uninstall.log
below="$(read_state)"
[ "$below" = "none|0|0|0|$errors" ] || fail "after the uninstall on PHP $ran_on: $below (version|tables|options|actions|errors)"
if npx wp-env run cli -- test -d "wp-content/plugins/$copy" >/dev/null 2>&1; then fail "the plugin's folder is still there"; fi
echo "   nothing left on PHP $ran_on: $below"
echo "Zip lifecycle: $zip installs, upgrades over itself and uninstalls clean, on PHP $ran_on as well."
