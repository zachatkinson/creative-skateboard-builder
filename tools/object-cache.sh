#!/usr/bin/env bash
# A real persistent object cache for the integration site (issue #4, D-066): a Redis server on wp-env's Docker network
# and Redis Object Cache's drop-in in the site's wp-content, so two PHP processes share one cache as they do on a shop.
# Usage: tools/object-cache.sh up, then CBUILD_OBJECT_CACHE=persistent bash tools/integration.sh, then
# tools/object-cache.sh down. tools/integration-object-cache.sh does all three.
# The server and the drop-in are the leg's only: `down` removes both, so no other run meets them.
set -euo pipefail
cd "$(dirname "$0")/.."

# Pinned, and the plugin's zip is checked against the hash of the release these were chosen from.
image="redis:8.10-alpine"
name="cbuild-redis"
plugin_version="3.0.1"
plugin_sha256="7a03342d3defbe94cac8d0470b42dc1a1c34331b19c47205df20ceccd3c8f54a"

site() {
	npx wp-env run cli --config=.wp-env.tests.json -- bash -c "$1"
}

down() {
	site 'rm -f wp-content/object-cache.php && rm -rf wp-content/plugins/redis-cache /tmp/redis-cache.zip' >/dev/null 2>&1 || true
	docker rm -f "$name" >/dev/null 2>&1 || true
}

case "${1:-}" in
	up)
		# shellcheck source=tools/wp-env-site.sh
		source tools/wp-env-site.sh
		docker rm -f "$name" >/dev/null 2>&1 || true
		# A cache, not a store: nothing is written to disk.
		docker run -d --name "$name" --network "$network" --network-alias "$name" "$image" \
			redis-server --save '' --appendonly no >/dev/null
		ready=""
		for _ in $(seq 1 30); do
			if [ "$(docker exec "$name" redis-cli ping 2>/dev/null)" = "PONG" ]; then
				ready=1
				break
			fi
			sleep 1
		done
		if [ -z "$ready" ]; then
			echo "$name did not start." >&2
			down
			exit 1
		fi
		if ! site "set -e
			curl -sSfL -o /tmp/redis-cache.zip https://downloads.wordpress.org/plugin/redis-cache.${plugin_version}.zip
			echo '${plugin_sha256}  /tmp/redis-cache.zip' | sha256sum -c - >/dev/null
			rm -rf wp-content/plugins/redis-cache
			unzip -q /tmp/redis-cache.zip -d wp-content/plugins
			rm /tmp/redis-cache.zip
			cp wp-content/plugins/creative-skateboard-builder/tools/object-cache/object-cache.php wp-content/object-cache.php"; then
			echo "Redis Object Cache ${plugin_version} could not be installed, or its zip is not the one pinned." >&2
			down
			exit 1
		fi
		echo "$name ($image) is ready on $network, with Redis Object Cache ${plugin_version}'s drop-in."
		;;
	down)
		down
		;;
	*)
		echo "Usage: tools/object-cache.sh up|down" >&2
		exit 2
		;;
esac
