#!/usr/bin/env bash
# Starts a database server for one leg of the compatibility matrix on wp-env's Docker network, empty, with the test
# suite's credentials, and waits until it answers (docs/AUDIT.md: The platform). wp-env itself runs MariaDB's LTS image
# and cannot change it. Usage: tools/matrix-db.sh mysql:8.4 cbuild-mysql84, then
# CBUILD_DB_HOST=cbuild-mysql84 composer test:integration. Remove with `docker rm -f <name>`.
set -euo pipefail
image="$1"
name="$2"
# shellcheck source=tools/wp-env-site.sh
source "$(dirname "$0")/wp-env-site.sh"
if ! docker ps --format '{{.Names}}' | grep -qx "$name"; then
	docker rm -f "$name" >/dev/null 2>&1 || true
	docker run -d --name "$name" --network "$network" --network-alias "$name" \
		-e MYSQL_ROOT_PASSWORD=password -e MARIADB_ROOT_PASSWORD=password \
		-e MYSQL_DATABASE="$database" -e MARIADB_DATABASE="$database" \
		"$image" >/dev/null
fi
client=mysql
case "$image" in mariadb:1[1-9]*|mariadb:lts*|mariadb:10.1[1-9]*) client=mariadb ;; esac
for _ in $(seq 1 90); do
	if docker exec "$name" "$client" -uroot -ppassword -e 'SELECT 1' "$database" >/dev/null 2>&1; then
		echo "$name ($image) is ready on $network."
		exit 0
	fi
	sleep 2
done
echo "$name did not start." >&2
exit 1
