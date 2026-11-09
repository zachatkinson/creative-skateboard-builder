#!/usr/bin/env bash
# Builds tmp/creative-skateboard-builder-<version>.zip without dev files, with a production vendor/ autoloader.
# (tmp/ is outside what Plugin Check scans; a zip inside the plugin folder would be flagged.) The version must agree
# across the plugin header, the constant, readme.txt's stable tag and package.json (docs/STANDARDS.md: Versions).
set -euo pipefail
cd "$(dirname "$0")/.."
main=creative-skateboard-builder.php
version="$(grep -E '^ \* Version:' "$main" | awk '{print $3}')"
constant="$(grep -E "define\( 'CBUILD_VERSION'" "$main" | sed -E "s/.*'CBUILD_VERSION', '([^']+)'.*/\1/")"
stable="$(grep -E '^Stable tag:' readme.txt | awk '{print $3}')"
package="$(node -p "require('./package.json').version")"
for other in "$constant" "$stable" "$package"; do
	if [ "$other" != "$version" ]; then
		echo "Version mismatch: header ${version}, constant ${constant}, readme ${stable}, package.json ${package}." >&2
		exit 1
	fi
done
npm run build >/dev/null
stage="$(mktemp -d)/creative-skateboard-builder"
mkdir -p "$stage"
# No dotfile ships, whatever tool left it: caches and configs are all dot-prefixed. .distignore lists the rest.
rsync -a --exclude='.*' --exclude-from=.distignore ./ "$stage/"
# Checked as well as excluded, so a change to the line above cannot let one through.
dotfiles="$(find "$stage" -mindepth 1 -name '.*' -print)"
if [ -n "$dotfiles" ]; then
	echo "Dev files would ship; add them to .distignore:" >&2
	echo "$dotfiles" >&2
	exit 1
fi
cp composer.json composer.lock "$stage/"
# composer.json stays beside vendor/ so a reviewer (and Plugin Check's missing_composer_json_file) can see what it holds.
( cd "$stage" && composer install --no-dev --no-interaction --quiet --optimize-autoloader && rm -f composer.lock )
mkdir -p tmp
rm -f "tmp/creative-skateboard-builder-${version}.zip"
( cd "$(dirname "$stage")" && zip -qr "$OLDPWD/tmp/creative-skateboard-builder-${version}.zip" creative-skateboard-builder )
rm -rf "$(dirname "$stage")"
echo "tmp/creative-skateboard-builder-${version}.zip"
