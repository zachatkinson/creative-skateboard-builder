#!/usr/bin/env bash
# The update from every release published below this one, each from its own zip on a site with the shop's data made on
# it (milestone 9: "Upgrades: tested from every 0.x tag"). Each run is tools/upgrade.sh's, which puts the site back as
# it found it. 0.5.0 was tagged but never published, so no shop can run it and it is not here (D-232).
# Run: npm run upgrade:all (wp-env up, Playwright's Chromium installed, gh signed in).
set -euo pipefail
cd "$(dirname "$0")/.."
slug=creative-skateboard-builder
releases=zachatkinson/creative-releases
version="$(sed -n "s/^define( 'CBUILD_VERSION', '\([^']*\)' );/\1/p" "$slug.php")"
mkdir -p tmp/releases
tags="$(gh release list -R "$releases" --limit 100 --json tagName --jq '.[].tagName' | sed -n "s/^$slug-//p" | sort -V)"
done=0
for tag in $tags; do
	# Only releases below this one, by version.
	[ "$(printf '%s\n%s\n' "$tag" "$version" | sort -V | head -n 1)" = "$tag" ] && [ "$tag" != "$version" ] || continue
	echo "== From $tag to $version"
	gh release download "$slug-$tag" -R "$releases" -p "$slug-$tag.zip" -D tmp/releases --clobber
	bash tools/upgrade.sh "tmp/releases/$slug-$tag.zip"
	done=$((done + 1))
done
[ "$done" -gt 0 ] || { echo "upgrade:all: no release below $version" >&2; exit 1; }
echo "Upgrade: every one of the $done releases below $version updates to it with its data."
