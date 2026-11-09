#!/usr/bin/env bash
# Starts a database server for one leg of the compatibility matrix on wp-env's Docker network, empty, with the test
# suite's credentials, and waits until it answers (docs/AUDIT.md: The platform). wp-env itself runs MariaDB's LTS image
# and cannot change it. Usage: tools/matrix-db.sh mysql:8.4 cbuild-mysql84, then
# CBUILD_DB_HOST=cbuild-mysql84 composer test:integration. Remove with `docker rm -f <name>`.
set -euo pipefail
image="$1"
name="$2"
# wp-env's tests site for this checkout is the tests-wordpress container that mounts this folder; other projects'
# wp-env containers may be running beside it.
here="$(cd "$(dirname "$0")/.." && pwd)"
network=""
for container in $(docker ps -q --filter label=com.docker.compose.service=tests-wordpress); do
	if docker inspect "$container" --format '{{range .Mounts}}{{println .Source}}{{end}}' | grep -x "$here" >/dev/null; then
		network="$(docker inspect "$container" --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' | head -n 1)"
	fi
done
if [ -z "$network" ]; then
	echo "wp-env's network was not found: run npx wp-env start first." >&2
	exit 1
fi
if ! docker ps --format '{{.Names}}' | grep -qx "$name"; then
	docker rm -f "$name" >/dev/null 2>&1 || true
	docker run -d --name "$name" --network "$network" --network-alias "$name" \
		-e MYSQL_ROOT_PASSWORD=password -e MARIADB_ROOT_PASSWORD=password \
		-e MYSQL_DATABASE=tests-wordpress -e MARIADB_DATABASE=tests-wordpress \
		"$image" >/dev/null
fi
client=mysql
case "$image" in mariadb:1[1-9]*|mariadb:lts*|mariadb:10.1[1-9]*) client=mariadb ;; esac
for _ in $(seq 1 90); do
	if docker exec "$name" "$client" -uroot -ppassword -e 'SELECT 1' tests-wordpress >/dev/null 2>&1; then
		echo "$name ($image) is ready on $network."
		exit 0
	fi
	sleep 2
done
echo "$name did not start." >&2
exit 1
