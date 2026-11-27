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
read -r -a wp_env <<< "${CBUILD_WP_ENV_COMMAND:-npx wp-env}"
# Under CI, a start that fails on a download is tried again: wp-env fetches WordPress and the pinned plugins from
# wordpress.org on each job's first start, and twenty jobs at once from GitHub's addresses meet its rate limit and
# timeouts (#179, D-565). Three tries in all, 20 then 60 s apart. Any other failure, our own, fails at once.
if [ "start" = "${1:-}" ] && [ -n "${CI:-}" ]; then
	read -r -a waits <<< "${CBUILD_WP_ENV_WAITS:-20 60}"
	log="$(mktemp)"
	trap 'rm -f "$log"' EXIT
	try=1
	while true; do
		set +e
		WP_ENV_PORT="$port" "${wp_env[@]}" "$@" 2>&1 | tee "$log"
		code="${PIPESTATUS[0]}"
		set -e
		if [ "$code" = 0 ]; then
			exit 0
		fi
		if [ "$try" -ge 3 ] || ! grep -Eq 'RequestError|HTTPError: Response code (429|5[0-9][0-9])|ETIMEDOUT|ECONNRESET|EAI_AGAIN' "$log"; then
			exit "$code"
		fi
		wait="${waits[$((try - 1))]:-60}"
		echo "wp-env's start: a download failed; trying again in ${wait} s (try $((try + 1)) of 3)." >&2
		sleep "$wait"
		try=$((try + 1))
	done
fi
WP_ENV_PORT="$port" exec "${wp_env[@]}" "$@"
