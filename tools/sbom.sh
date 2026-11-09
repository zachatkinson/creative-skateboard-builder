#!/usr/bin/env bash
# The CycloneDX SBOMs of what the zip ships (D-003, D-030): Composer's non-dev packages, and the npm packages webpack
# bundles into build/, which package.json keeps in `dependencies` so --omit dev counts them. Both tools are fetched
# here, at a pinned version, so neither joins the lock files. Run by release.yml, and by CI without publishing, so
# a release never meets this step for the first time. Usage: tools/sbom.sh <version>; writes
# tmp/creative-skateboard-builder-<version>.composer.cdx.json and .npm.cdx.json.
set -euo pipefail
cd "$(dirname "$0")/.."
version="$1"
slug=creative-skateboard-builder
composer_tool='cyclonedx/cyclonedx-php-composer:6.2.0'
npm_tool='@cyclonedx/cyclonedx-npm@7.0.0'
mkdir -p tmp
# Composer refuses a plugin it was not told to trust, and then has no CycloneDX command.
composer global config --no-plugins allow-plugins.cyclonedx/cyclonedx-php-composer true
composer global require "$composer_tool" --no-interaction --quiet
composer CycloneDX:make-sbom --omit=dev --output-format=JSON --output-file="tmp/${slug}-${version}.composer.cdx.json" --spec-version=1.6
npx --yes "$npm_tool" --omit dev --output-format JSON --output-file "tmp/${slug}-${version}.npm.cdx.json" --spec-version 1.6
for kind in composer npm; do
	file="tmp/${slug}-${version}.${kind}.cdx.json"
	node -e 'const s=JSON.parse(require("fs").readFileSync(process.argv[1],"utf8"));if(s.bomFormat!=="CycloneDX"){process.exit(1)}console.log(`${process.argv[1]}: CycloneDX ${s.specVersion}, ${(s.components||[]).length} component(s).`)' "$file"
done
