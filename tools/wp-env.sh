#!/usr/bin/env bash
# wp-env with this checkout's ports (tools/ports.sh): `npm run env:start`, `env:stop` and `env:clean` run through it.
# wp-env takes a port from WP_ENV_PORT before the config file, and both sites are a config's own environment, so each
# call is given the port of the site its config names: CBUILD_TESTS_PORT with --config=.wp-env.tests.json, CBUILD_PORT
# otherwise, and its MySQL port when the lane sets one (CBUILD_TESTS_MYSQL_PORT or CBUILD_MYSQL_PORT, as WP_ENV_MYSQL_PORT).
# Unset, those are the files' own ports and the call is plain `npx wp-env`.
#
# A start or clean that would move this checkout's running sites to other ports is refused: wp-env names a site by its
# config file's path, so lane 2 run in a checkout that already runs as lane 1 would restart that checkout's sites.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck source=tools/ports.sh
source tools/ports.sh
port="$CBUILD_PORT"
mysql="${CBUILD_MYSQL_PORT:-}"
for arg in "$@"; do
	case "$arg" in
		--config=.wp-env.tests.json | --config=./.wp-env.tests.json)
			port="$CBUILD_TESTS_PORT"
			mysql="${CBUILD_TESTS_MYSQL_PORT:-}"
			;;
	esac
done
# A lane's MySQL port (tools/lane.sh); without one, Docker picks it, as wp-env does.
if [ -n "$mysql" ]; then
	export WP_ENV_MYSQL_PORT="$mysql"
fi
case "${1:-}" in
	start | clean | reset)
		here="$(pwd)"
		for container in $(docker ps -q --filter label=com.docker.compose.service=wordpress 2>/dev/null); do
			if docker inspect "$container" --format '{{range .Mounts}}{{println .Source}}{{end}}' | grep -x "$here" >/dev/null; then
				published="$(docker port "$container" 80/tcp 2>/dev/null | sed -n 's/.*:\([0-9][0-9]*\)$/\1/p' | head -n 1)"
				if [ -n "$published" ] && [ "$published" != "$CBUILD_PORT" ] && [ "$published" != "$CBUILD_TESTS_PORT" ]; then
					echo "This checkout's sites already run on :$published, not :$CBUILD_PORT and :$CBUILD_TESTS_PORT. Run it with the lane they were started in, or stop them first." >&2
					exit 1
				fi
			fi
		done
		;;
esac
WP_ENV_PORT="$port" exec npx wp-env "$@"
