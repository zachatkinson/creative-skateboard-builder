#!/usr/bin/env bash
# Builds the zip, installs it on the browser site as a copy and runs Plugin Check on that copy, so the check sees what a
# shop installs (the roadmap: "Plugin Check on the built zip"). Fails on any error or warning. Plugin Check itself
# exits 0 when it finds some. plugin_updater_detected flags the `Update URI` header, a WordPress.org hosting rule; the
# plugin is installed from a zip and updates from the agency's releases (Updates\Updater, D-003), and the header is
# what points core at them and stops WordPress.org offering a same-slug plugin over it. Every other finding fails.
# --slug tells Plugin Check the plugin's real slug, since the copy's folder has another name and the text domain is
# checked against it.
set -euo pipefail
cd "$(dirname "$0")/.."
zip="$(bash tools/build-zip.sh)"
copy=cbuild-check
npx wp-env run cli -- bash -c "rm -rf wp-content/plugins/${copy} && mkdir -p wp-content/plugins/${copy}" >/dev/null
npx wp-env run cli -- bash -c "cd wp-content/plugins/${copy} && unzip -qo /var/www/html/wp-content/plugins/creative-skateboard-builder/${zip} && mv creative-skateboard-builder/* . && rmdir creative-skateboard-builder" >/dev/null
out="$(npx wp-env run cli -- wp plugin check "${copy}" --slug=creative-skateboard-builder --format=csv --fields=line,column,type,code,message 2>&1)" || { echo "$out"; exit 1; }
npx wp-env run cli -- bash -c "rm -rf wp-content/plugins/${copy}" >/dev/null
echo "$out"
if grep -E ',(ERROR|WARNING),' <<<"$out" | grep -qvE ',plugin_updater_detected,'; then
	echo "Plugin Check found problems." >&2
	exit 1
fi
echo "Plugin Check: no errors or warnings in ${zip} (apart from the Update URI header, allowed above)."
