#!/usr/bin/env bash
# Runs a command in a lane: a checkout's own pair of wp-env sites on its own ports, so a second or third checkout (a git
# worktree) can run the whole /verify, integration suite, Plugin Check and browsers included, beside the first.
#
#   bash tools/lane.sh <1|2|3> <command…>
#
#   lane   browser site   integration site   their MySQL
#   1      :8898          :8899              Docker's choice, as with no lane at all
#   2      :8900          :8901              :8910 and :8911
#   3      :8902          :8903              :8912 and :8913
#
# The lane sets CBUILD_PORT and CBUILD_TESTS_PORT (tools/ports.sh), which every script, Playwright and Lighthouse read,
# and CBUILD_MYSQL_PORT and CBUILD_TESTS_MYSQL_PORT, which tools/wp-env.sh gives wp-env when it starts the sites.
# Auxiliary ports stay in 8910 to 8919, so 8902 and 8903 are free for lane 3's sites. wp-env names a site by its config
# file's path, so a lane is a checkout: start, use and stop a checkout's sites in one lane (tools/wp-env.sh refuses to
# move them).
#
#   bash tools/lane.sh 2 npm run env:start
#   bash tools/lane.sh 2 composer test:integration -- --filter PictureFilesTest
#   bash tools/lane.sh 2 npx playwright test --project=chromium-desktop
#   bash tools/lane.sh 2 npm run env:stop
set -euo pipefail
usage() {
	echo "Usage: bash tools/lane.sh <1|2|3> <command…>" >&2
	exit 2
}
[ $# -ge 2 ] || usage
case "$1" in
	1) CBUILD_PORT=8898 ;;
	2) CBUILD_PORT=8900 CBUILD_MYSQL_PORT=8910 ;;
	3) CBUILD_PORT=8902 CBUILD_MYSQL_PORT=8912 ;;
	*) usage ;;
esac
shift
CBUILD_TESTS_PORT="$(( CBUILD_PORT + 1 ))"
export CBUILD_PORT CBUILD_TESTS_PORT
if [ -n "${CBUILD_MYSQL_PORT:-}" ]; then
	CBUILD_TESTS_MYSQL_PORT="$(( CBUILD_MYSQL_PORT + 1 ))"
	export CBUILD_MYSQL_PORT CBUILD_TESTS_MYSQL_PORT
else
	unset CBUILD_MYSQL_PORT CBUILD_TESTS_MYSQL_PORT
fi
exec "$@"
