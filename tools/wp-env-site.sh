#!/usr/bin/env bash
# Sourced, not run: finds this project's integration site among the wp-env containers and sets `network` (its Docker
# network) and `database` (its database's name), for the servers a matrix leg adds beside it (tools/matrix-db.sh,
# tools/object-cache.sh). The integration site (.wp-env.tests.json) is the wordpress container that mounts this folder
# and serves CBUILD_TESTS_PORT (tools/ports.sh, :8899 unless a lane sets it); the browser site mounts it too, and other
# projects' wp-env containers, or another checkout's lane, may be running beside them.
# shellcheck source=tools/ports.sh
source "$(dirname "${BASH_SOURCE[0]}")/ports.sh"
here="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
network=""
database=""
for container in $(docker ps -q --filter label=com.docker.compose.service=wordpress --filter "publish=$CBUILD_TESTS_PORT"); do
	if docker inspect "$container" --format '{{range .Mounts}}{{println .Source}}{{end}}' | grep -x "$here" >/dev/null; then
		network="$(docker inspect "$container" --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' | head -n 1)"
		database="$(docker exec "$container" printenv WORDPRESS_DB_NAME)"
	fi
done
if [ -z "$network" ] || [ -z "$database" ]; then
	echo "The integration site was not found: run npm run env:start first." >&2
	exit 1
fi
