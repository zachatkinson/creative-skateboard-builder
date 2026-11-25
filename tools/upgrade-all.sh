#!/usr/bin/env bash
# The update from every release published below this one, each from its own zip on a site with the shop's data made on
# it (milestone 9: "Upgrades: tested from every 0.x tag"). Each run is tools/upgrade.sh's, which puts the site back as
# it found it. 0.5.0 was tagged but never published, so no shop can run it and it is not here (D-232).
# With --sample, only every release from 1.0.0 on, and 0.1.0 and 0.9.0 (D-414): CI's run, once every release took its
# job past 15 minutes. Which releases is tools/upgrade-releases.sh's answer.
# Run: npm run upgrade:all [-- --sample] (wp-env up, Playwright's Chromium installed, gh signed in).
set -euo pipefail
cd "$(dirname "$0")/.."
mode="${1:-}"
case "$mode" in
	'' | --sample) ;;
	*) echo "upgrade:all: unknown option '$mode'; the one option is --sample" >&2; exit 2 ;;
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
if [ "$mode" = --sample ]; then
	echo "Upgrade: the $done releases of the sample below $version ($(printf '%s\n' "$tags" | paste -sd ' ' -)) update to it with their data."
else
	echo "Upgrade: every one of the $done releases below $version updates to it with its data."
fi
