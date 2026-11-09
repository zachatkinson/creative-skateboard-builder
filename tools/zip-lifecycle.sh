#!/usr/bin/env bash
# The zip a shop installs, through WordPress's own installer on wp-env's browser site (the roadmap, milestone 1: "the
# milestone's zip installs on a clean WordPress, upgrades over itself and uninstalls clean"):
#   1. the site is cleared of the plugin's data, with the checkout's copy deactivated;
#   2. the zip is installed and activated: the tables and the schema version are there;
#   3. the same zip is installed over itself: still active, the version and tables as they were, nothing logged;
#   4. it is uninstalled: no table, option, transient or scheduled action is left, and its folder is gone.
# wp-env mounts this checkout at wp-content/plugins/creative-skateboard-builder, and installing a zip there would
# replace the checkout, so the zip's folder is renamed to cbuild-zip first; every file in it is the release's.
# Run: npm run zip-lifecycle (wp-env up).
set -euo pipefail
cd "$(dirname "$0")/.."
slug=creative-skateboard-builder
copy=cbuild-zip
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
	rm -f "tmp/$copy.zip"
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
[ "$installed" = "1|2|1|0|$errors" ] || [ "$installed" = "1|2|2|0|$errors" ] || fail "after the install: $installed (version|tables|options|actions|errors)"
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
echo "Zip lifecycle: $zip installs, upgrades over itself and uninstalls clean."
