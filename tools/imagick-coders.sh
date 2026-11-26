#!/usr/bin/env bash
# Gives the integration site's cli container ImageMagick's PNG, WebP and JPEG coders. wp-env's cli image is Alpine
# with the imagick extension and imagemagick-libs alone, so Imagick loads there and reads no format at all
# (Imagick::queryFormats() is empty), and the suite runs in that container. The layer images and the composed
# picture are tested with each library (docs/ROADMAP.md, Milestone 7), so the coders are added once a start, as root,
# from Alpine's own packages: about a second (D-191). Run by .wp-env.tests.json's afterStart.
set -euo pipefail
# shellcheck source=tools/wp-env-site.sh
source "$(dirname "$0")/wp-env-site.sh"
project=""
for container in $(docker ps -q --filter label=com.docker.compose.service=wordpress --filter "publish=$CBUILD_TESTS_PORT"); do
	if docker inspect "$container" --format '{{range .Mounts}}{{println .Source}}{{end}}' | grep -x "$here" >/dev/null; then
		project="$(docker inspect "$container" --format '{{index .Config.Labels "com.docker.compose.project"}}')"
	fi
done
cli="$(docker ps -q --filter "label=com.docker.compose.project=$project" --filter label=com.docker.compose.service=cli | head -n 1)"
if [ -z "$cli" ]; then
	echo "The integration site's cli container was not found." >&2
	exit 1
fi
docker exec -u root "$cli" apk add --no-cache --quiet imagemagick imagemagick-webp imagemagick-jpeg
for format in PNG WEBP JPEG; do
	docker exec "$cli" php -r "exit( array() === Imagick::queryFormats( '$format' ) ? 1 : 0 );" || { echo "Imagick in the cli container reads no $format." >&2; exit 1; }
done
echo "Imagick in the integration site's cli container reads PNG, WebP and JPEG."
