#!/usr/bin/env bash
# Everything release.yml does short of publishing: the zip, its SBOMs and its manifest, and the manifest read back by
# the class a shop's site reads it with (Updates\Manifest), so a release never meets a step for the first time and
# never publishes a manifest its own updater would refuse. Run in CI on every full run, and by hand:
# bash tools/release-dry-run.sh
set -euo pipefail
cd "$(dirname "$0")/.."
zip="$(bash tools/build-zip.sh)"
version="$(basename "$zip" .zip | sed 's/^creative-skateboard-builder-//')"
bash tools/sbom.sh "$version"
php tools/release-manifest.php "$zip" > tmp/info.json
# Every class file of the plugin exits unless WordPress is loaded, so the check says ABSPATH is defined, and the script
# insists on the check's own last line: a check that ended early and quietly would otherwise pass.
checked="$(php -r '
define( "ABSPATH", getcwd() . "/" );
require "vendor/autoload.php";
$data     = json_decode( (string) file_get_contents( "tmp/info.json" ), true );
$manifest = \CreativeBranding\SkateboardBuilder\Updates\Manifest::fromArray( $data );
if ( null === $manifest ) {
	fwrite( STDERR, "The updater would refuse the manifest release.yml would publish (tmp/info.json).\n" );
	exit( 1 );
}
if ( hash_file( "sha256", $argv[1] ) !== $manifest->sha256 ) {
	fwrite( STDERR, "The manifest does not carry the zip checksum.\n" );
	exit( 1 );
}
echo "Manifest: ", $manifest->version, ", ", count( $manifest->changelog ), " changelog line(s), accepted by Updates\\Manifest.\n";
' "$zip")"
echo "$checked"
grep -q 'accepted by Updates' <<<"$checked" || { echo "The manifest was not checked." >&2; exit 1; }
echo "Release dry run: ${zip}, two SBOMs and info.json are ready; nothing was published."
