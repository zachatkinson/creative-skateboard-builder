#!/usr/bin/env bash
# Builds pcov into the integration site's cli container, for CBUILD_COVERAGE_DRIVER=pcov (tools/coverage-all.sh).
# wp-env's cli image has Xdebug alone, and its PHP is docker-library's, so pcov comes from PECL, built with the
# image's own phpize tools: about five seconds, once a start. The extension is only put in PHP's extension folder;
# nothing loads it but tools/integration.sh's `-d extension=pcov.so`, so every other run in the container is unchanged.
set -euo pipefail
cd "$(dirname "$0")/.."
version=1.0.12
# shellcheck source=tools/wp-env-site.sh
source tools/wp-env-site.sh
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
if ! docker exec "$cli" sh -c 'test -f "$(php-config --extension-dir)/pcov.so"'; then
	docker exec -u root "$cli" sh -c "pecl install pcov-${version} > /tmp/pcov-install.log 2>&1" || {
		docker exec "$cli" cat /tmp/pcov-install.log >&2
		echo "pcov ${version} could not be built in the integration site's cli container." >&2
		exit 1
	}
fi
docker exec "$cli" php -d extension=pcov.so -d pcov.enabled=1 -r 'exit( extension_loaded( "pcov" ) ? 0 : 1 );' || {
	echo "pcov does not load in the integration site's cli container." >&2
	exit 1
}
echo "pcov ${version} loads in the integration site's cli container."
