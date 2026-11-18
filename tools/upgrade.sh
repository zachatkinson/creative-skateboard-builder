#!/usr/bin/env bash
# An update from the previous release, on wp-env's browser site, as a shop makes it (Cowork's M7 review, F3; D-208):
#   1. the previous release's own zip, as published to the releases repository, is installed on a site with none of
#      the plugin's data;
#   2. the graph check, once each for an admin request, a cron request (automatic updates) and a WP-CLI one: with the
#      previous release booted, this release's files are put in its place and its container is asked for every
#      service it can build, and each public method its callers use is compared (tools/upgrade/graph.php);
#   3. the update itself, through Plugins › Add New › Upload Plugin and "Replace current with uploaded", which deletes
#      the uploaded zip in the same request that replaced the files: the page must say the plugin was updated, with no
#      critical error, no PHP error naming the plugin, and no zip left in uploads; the next admin page, Plugins, migrates
#      to this release's schema.
# As zip-lifecycle.sh, both zips' folders are renamed to cbuild-zip, so the checkout wp-env mounts is never replaced.
# Run: npm run upgrade [-- <the previous release's zip>] (wp-env up, Playwright's Chromium installed). Without a zip,
# the newest release below this one is downloaded from the releases repository with gh.
set -euo pipefail
cd "$(dirname "$0")/.."
slug=creative-skateboard-builder
copy=cbuild-zip
releases=zachatkinson/creative-releases
version="$(sed -n "s/^define( 'CBUILD_VERSION', '\([^']*\)' );/\1/p" "$slug.php")"
schema="$(sed -n "s/.*const VERSION = '\([0-9]*\)';.*/\1/p" src/Install/Migrations/MigrationRegistry.php)"
[ -n "$version" ] && [ -n "$schema" ] || { echo "upgrade: the version or the schema was not found" >&2; exit 1; }

previous="${1:-}"
if [ -z "$previous" ]; then
	# The newest release whose version sorts below this one's, by version, not by date.
	tag="$(gh release list -R "$releases" --limit 100 --json tagName --jq '.[].tagName' | sed -n "s/^$slug-//p" | { cat; echo "$version"; } | sort -V | uniq | grep -B1 -x "$version" | head -n 1)"
	[ -n "$tag" ] && [ "$tag" != "$version" ] || { echo "upgrade: no release below $version" >&2; exit 1; }
	mkdir -p tmp/releases
	gh release download "$slug-$tag" -R "$releases" -p "$slug-$tag.zip" -D tmp/releases --clobber
	previous="tmp/releases/$slug-$tag.zip"
fi
[ -f "$previous" ] || { echo "upgrade: $previous is not there" >&2; exit 1; }

# Both zips, their folder renamed; this release's files also unzipped, for the graph check to copy in.
rm -rf tmp/upgrade && mkdir -p tmp/upgrade/old tmp/upgrade/new
unzip -q "$previous" -d tmp/upgrade/old
mv "tmp/upgrade/old/$slug" "tmp/upgrade/old/$copy"
( cd tmp/upgrade/old && zip -qr ../old.zip "$copy" )
zip="$(bash tools/build-zip.sh)"
unzip -q "$zip" -d tmp/upgrade/new
mv "tmp/upgrade/new/$slug" "tmp/upgrade/new/$copy"
upload="$copy-$version.zip"
( cd tmp/upgrade/new && zip -qr "../$upload" "$copy" )
from="$(sed -n "s/^define( 'CBUILD_VERSION', '\([^']*\)' );/\1/p" "tmp/upgrade/old/$copy/$slug.php")"

here=wp-content/plugins/$slug
# WP-CLI prints a deprecation of its own on PHP 8.5 after the answer (php-cli-tools), which is not the plugin's.
wp() { npx wp-env run cli -- wp "$@" 2>/dev/null | tr -d '\r' | grep -v 'php-cli-tools' || true; }
# WP-CLI's own error lines matter here, so stderr is kept, less wp-env's progress and a WP-CLI deprecation of its own.
wp_said() { npx wp-env run cli -- wp "$@" 2>&1 | tr -d '\r' | grep -v '^ℹ\|^✔\|^✖\|^Command failed\|php-cli-tools' || true; }
fail() { echo "upgrade: $1" >&2; exit 1; }
restore() {
	# What the seed made goes with the run, so a browser run after it meets the site as it was (audit of 1.0.0, M8).
	wp eval-file "$here/tools/upgrade/seed.php" clean >/dev/null
	wp plugin deactivate "$copy" >/dev/null
	wp plugin uninstall "$copy" --skip-delete >/dev/null
	npx wp-env run cli -- bash -c "rm -rf wp-content/plugins/$copy" >/dev/null 2>&1 || true
	wp plugin activate "$slug" >/dev/null
}
trap restore EXIT

# A PHP error naming the copy, counted in the site's debug log.
errors() { wp eval '$log = WP_CONTENT_DIR . "/debug.log"; echo is_readable( $log ) ? preg_match_all( "#PHP (Fatal|Warning|Deprecated|Notice)[^\n]*cbuild-zip#", (string) file_get_contents( $log ) ) : 0;' | tail -n 1; }

echo "1. The site without the plugin's data, and $from from the releases repository."
wp plugin deactivate "$slug" >/dev/null
wp plugin uninstall "$slug" --skip-delete >/dev/null
wp plugin install "$here/tmp/upgrade/old.zip" --force --activate >/dev/null
[ "$(wp plugin get "$copy" --field=version | tail -n 1)" = "$from" ] || fail "$from did not install"

echo "2. $from's container, asked for each of its services from $version's files."
wp plugin install "$here/tmp/upgrade/old.zip" --force >/dev/null
said="$(wp_said --skip-plugins="$copy" eval-file "$here/tools/upgrade/graph.php" services "wp-content/plugins/$copy" "$here/tmp/upgrade/services.json")"
echo "$said" | grep . | sed 's/^/   /'
case "$said" in *Success:*) ;; *) fail "the services were not listed" ;; esac
for kind in admin cron cli; do
	wp plugin install "$here/tmp/upgrade/old.zip" --force >/dev/null
	said="$(wp_said --skip-plugins="$copy" eval-file "$here/tools/upgrade/graph.php" check "wp-content/plugins/$copy" "$here/tmp/upgrade/services.json" "$here/tmp/upgrade/new/$copy" "$kind")"
	echo "$said" | grep . | sed 's/^/   /'
	case "$said" in *Success:*) ;; *) fail "$from's container does not fit $version's files in a $kind request" ;; esac
done

echo "3. $from updated to $version through the upload screen."
# The graph check left this release's files in the copy's folder, and WP-CLI booted them: a clean install of the
# previous release again, its data and schema its own.
wp plugin deactivate "$copy" >/dev/null
wp plugin uninstall "$copy" --skip-delete >/dev/null
wp plugin install "$here/tmp/upgrade/old.zip" --force --activate >/dev/null
[ "$(wp plugin get "$copy" --field=status | tail -n 1)" = active ] || fail "$from is not active before the update"
# The shop as it stood on that release (milestone 9): products with SKUs, prices and stock, which the old release's
# own jobs read into its tables, and a paid order holding them, so the update meets data and not an empty site.
seed="$(wp eval-file "$here/tools/upgrade/seed.php" | tail -n 1)"
case "$seed" in order:*) ;; *) fail "the shop's data was not made on $from: $seed" ;; esac
# The release's own kits, parts and specs, made by its own browser fixture with its own classes, so they are stored as
# that release stored them (D-232). Kits arrived in 0.4.0; before then, the fixture made none.
rm -rf tmp/upgrade/fixture && mkdir -p tmp/upgrade/fixture
git rev-parse -q --verify "refs/tags/v$from" >/dev/null || git fetch -q --depth=1 origin tag "v$from" 2>/dev/null || true
if git cat-file -e "v$from:tests/e2e/fixtures/shop.php" 2>/dev/null; then
	git archive "v$from" tests/e2e/fixtures | tar -x -C tmp/upgrade/fixture
	said="$(wp_said eval-file "$here/tmp/upgrade/fixture/tests/e2e/fixtures/shop.php")"
	case "$said" in *"Fatal error"*|*"Error:"*) echo "$said" | sed 's/^/   /'; fail "$from's own fixture did not run" ;; esac
fi
kits="$(wp eval-file "$here/tools/upgrade/seed.php" kits | tail -n 1)"
kits="${kits#kits:}"
saved="$(wp eval-file "$here/tools/upgrade/seed.php" saved | tail -n 1)"
user="${saved#saved:}"; [ "$user" = none ] && user=0
wp action-scheduler run --group=creative-skateboard-builder >/dev/null
echo "   on $from: $seed; kits $from reads: $kits; $saved"
before="$(errors)"
page="$(node tools/upgrade/upload.mjs "tmp/upgrade/$upload")"
echo "$page" | grep -E 'updated successfully|critical error' | sed 's/^/   /' || true
case "$page" in *"critical error"*) fail "the update page ended in a critical error" ;; esac
case "$page" in *"Plugin updated successfully"*) ;; *) fail "the update page did not say the plugin was updated" ;; esac
[ "$(errors)" = "$before" ] || fail "the update logged a PHP error naming the plugin ($before before, $(errors) after)"
state="$(wp plugin get "$copy" --field=version | tail -n 1)|$(wp plugin get "$copy" --field=status | tail -n 1)|$(wp option get cbuild_db_version | tail -n 1)"
[ "$state" = "$version|active|$schema" ] || fail "after the update: $state (version|status|schema)"
# WordPress keeps the upload under its own name, as an attachment, until the update's request deletes both.
left="$(wp post list --post_type=attachment --post_mime_type=application/zip --format=count | tail -n 1)|$(npx wp-env run cli -- find wp-content/uploads -name "$upload" 2>/dev/null | tr -d '\r' | grep -c . || true)"
[ "$left" = "0|0" ] || fail "the uploaded zip was left behind: $left (attachments|files)"
echo "   version|status|schema: $state; no zip left in uploads."
# The release's own jobs after the update, then the data read by this release: the order with its lines, the old
# release's kits and their presets, its saved build, and no failed job of the plugin's.
wp action-scheduler run --group=creative-skateboard-builder >/dev/null
read="$(wp eval-file "$here/tools/upgrade/seed.php" read "${seed#order:}" "$user" | tail -n 1)"
case "$read" in read:*) ;; *) fail "after the update, the shop's data did not read: $read" ;; esac
# Every preset is priced, and the old release's saved build is listed.
# Every kit the old release read, this one reads too.
kitsread="$(sed -n 's/.*; kits \([0-9]*\) of [0-9]*,.*/\1/p' <<<"$read")"
[ "$kitsread" = "$kits" ] || fail "after the update, $kitsread kits read where $from read $kits: $read"
presets="$(sed -n 's/.*presets \([0-9]*\) of \([0-9]*\) priced.*/\1 \2/p' <<<"$read")"
[ "${presets% *}" = "${presets#* }" ] || fail "after the update, not every preset was priced: $read"
[ "$user" = 0 ] || case "$read" in *"saved builds 1"*) ;; *) fail "after the update, $from's saved build was not listed: $read" ;; esac
failed="$(wp action-scheduler action list --group=creative-skateboard-builder --status=failed --format=count 2>/dev/null | tail -n 1)"
case "$failed" in ''|*[!0-9]*) fail "the plugin's failed jobs could not be counted: '$failed'" ;; esac
[ "$failed" = 0 ] || fail "after the update, $failed of the plugin's jobs failed"
[ "$(errors)" = "$before" ] || fail "the jobs after the update logged a PHP error naming the plugin"
echo "   after: $read; no failed job."
echo "Upgrade: $from updates to $version through the upload screen, and its container fits $version's files."
