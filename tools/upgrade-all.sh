#!/usr/bin/env bash
# The update from every release published below this one, each from its own zip on a site with the shop's data made on
# it (milestone 9: "Upgrades: tested from every 0.x tag"). Each run is tools/upgrade.sh's, which puts the site back as
# it found it. 0.5.0 was tagged but never published, so no shop can run it and it is not here (D-232).
# Which releases is tools/upgrade-releases.sh's answer (D-549): those a shop could update from, by its support floor,
# which while unset is the previous release alone; with --every, every release, before a major and when a migration
# changes.
# Run: npm run upgrade:all [-- --every] (wp-env up, Playwright's Chromium installed, gh signed in).
set -euo pipefail
cd "$(dirname "$0")/.."
mode="${1:-}"
case "$mode" in
	'' | --every) ;;
	*) echo "upgrade:all: unknown option '$mode'; the one option is --every" >&2; exit 2 ;;
esac
slug=creative-skateboard-builder
releases=zachatkinson/creative-releases
version="$(sed -n "s/^define( 'CBUILD_VERSION', '\([^']*\)' );/\1/p" "$slug.php")"
mkdir -p tmp/releases
# Every release the repository holds, of every plugin, so the limit is well above their count.
published="$(gh release list -R "$releases" --limit 1000 --json tagName --jq '.[].tagName' | sed -n "s/^$slug-//p")"
# shellcheck disable=SC2086 # The mode is one word or none.
tags="$(printf '%s\n' "$published" | bash tools/upgrade-releases.sh "$version" $mode)"
done=0
for tag in $tags; do
	echo "== From $tag to $version"
	gh release download "$slug-$tag" -R "$releases" -p "$slug-$tag.zip" -D tmp/releases --clobber
	bash tools/upgrade.sh "tmp/releases/$slug-$tag.zip"
	done=$((done + 1))
done
if [ "$mode" = --every ]; then
	echo "Upgrade: every one of the $done releases below $version updates to it with its data."
else
	echo "Upgrade: the $done supported releases below $version ($(printf '%s\n' "$tags" | paste -sd ' ' -)) update to it with their data."
fi
