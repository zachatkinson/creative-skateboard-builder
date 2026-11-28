#!/usr/bin/env bash
# tools/wp-env.sh's start, with a stand-in for wp-env (CBUILD_WP_ENV_COMMAND) and no wait (CBUILD_WP_ENV_WAITS), in a
# copy of the script and its configs so no override file is written into this checkout.
# The retry of a start that fails on a download (#179, D-565):
# - under CI, a 429 or a timeout is tried again, up to three tries in all, and a start that then succeeds succeeds;
# - three download failures fail;
# - a failure that is no download's (our own) is not tried again;
# - outside CI nothing is tried again.
# The versions check after a start (D-548):
# - the site's WordPress and WooCommerce are the config's pins, or the override's where `npm run env:oldest` wrote one,
#   for the browser site and the integration site, and `npm run env:oldest -- --start` starts both;
# - either version off its pin fails the start, naming both.
set -euo pipefail
cd "$(dirname "$0")/.."
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
tree="$work/tree"
mkdir -p "$tree/tools"
cp tools/wp-env.sh tools/ports.sh tools/wp-env-oldest.sh "$tree/tools/"
cp .wp-env.json .wp-env.tests.json "$tree/"
core="$(sed -n 's#.*wordpress-\([0-9.]*\)\.zip.*#\1#p' .wp-env.json)"
woo="$(sed -n 's#.*woocommerce\.\([0-9.]*\)\.zip.*#\1#p' .wp-env.json)"
fail=0
check() {
	if eval "$2"; then echo "ok: $1"; else echo "FAILED: $1"; fail=1; fi
}

# The stand-in answers `run cli … wp core version` and `… wp plugin get woocommerce` with FAKE_CORE and FAKE_WOO, and
# a start with the next line of its script, exiting with its code and counting its starts.
cat > "$work/fake.sh" <<'EOF'
#!/usr/bin/env bash
if [ "run" = "$1" ]; then
	echo "ℹ Starting '$*' on the cli container."
	case "$*" in
		*"core version"*) echo "$FAKE_CORE" ;;
		*"plugin get woocommerce"*) echo "$FAKE_WOO" ;;
	esac
	echo "✔ Ran in 1s."
	exit 0
fi
n=$(( $(cat "$FAKE_DIR/runs" 2>/dev/null || echo 0) + 1 ))
echo "$n" > "$FAKE_DIR/runs"
line="$(sed -n "${n}p" "$FAKE_DIR/script")"
echo "${line#* }"
exit "${line%% *}"
EOF
chmod +x "$work/fake.sh"

# run <name> <CI value> <script lines...>: the wrapper's exit code in $code, its starts of the stand-in in $runs. The
# site's versions are FAKE_CORE and FAKE_WOO, the pins unless a case sets them; START_ARGS are the start's own.
run() {
	local name="$1" ci="$2"
	shift 2
	rm -f "$work/runs"
	printf '%s\n' "$@" > "$work/script"
	set +e
	# shellcheck disable=SC2086 # START_ARGS is words.
	CI="$ci" FAKE_DIR="$work" CBUILD_WP_ENV_COMMAND="$work/fake.sh" CBUILD_WP_ENV_WAITS="0 0" \
		FAKE_CORE="${FAKE_CORE:-$core}" FAKE_WOO="${FAKE_WOO:-$woo}" \
		bash "$tree/tools/wp-env.sh" start ${START_ARGS:-} > "$work/$name.log" 2>&1
	# shellcheck disable=SC2034 # read by check's eval.
	code=$?
	set -e
	# shellcheck disable=SC2034 # read by check's eval.
	runs="$(cat "$work/runs")"
}

run 429 true '1 HTTPError: Response code 429 (Too Many Requests)' '0 Done!'
check "a 429 is tried again, and the start then succeeds" '[ "$code" = 0 ] && [ "$runs" = 2 ]'

run timeout true '1 RequestError' "1 AggregateError [ETIMEDOUT]: connect" '0 Done!'
check "a timeout is tried again, up to the third try" '[ "$code" = 0 ] && [ "$runs" = 3 ]'

run thrice true '1 HTTPError: Response code 503 (Service Unavailable)' '1 HTTPError: Response code 502 (Bad Gateway)' '1 RequestError' '0 Done!'
check "three download failures fail, with no fourth try" '[ "$code" != 0 ] && [ "$runs" = 3 ]'

run ours true '1 Error: the plugin could not be activated' '0 Done!'
check "a failure of our own is not tried again" '[ "$code" != 0 ] && [ "$runs" = 1 ]'

run local '' '1 HTTPError: Response code 429 (Too Many Requests)' '0 Done!'
check "outside CI nothing is tried again" '[ "$code" != 0 ] && [ "$runs" = 1 ]'

check "the log says why it tries again" 'grep -q "download failed; trying again" "$work/429.log"'

run pinned '' '0 Done!'
check "a start on the pins succeeds" '[ "$code" = 0 ]'

FAKE_CORE=7.0.8 run core '' '0 Done!'
check "a WordPress off its pin fails the start" '[ "$code" != 0 ] && grep -q "WordPress 7.0.8, not $core" "$work/core.log"'

FAKE_WOO=11.2.0 START_ARGS=--config=.wp-env.tests.json run woo '' '0 Done!'
check "a WooCommerce off its pin fails the integration site's start" '[ "$code" != 0 ] && grep -q "WooCommerce 11.2.0, not $woo" "$work/woo.log"'

bash "$tree/tools/wp-env-oldest.sh" > /dev/null
run stale '' '0 Done!'
check "after env:oldest, the newest pair fails, as M14's stale run did" '[ "$code" != 0 ] && grep -q "WordPress $core, not 7.0.8" "$work/stale.log"'

FAKE_CORE=7.0.8 FAKE_WOO=11.2.0 START_ARGS=--config=.wp-env.tests.json run oldest '' '0 Done!'
check "after env:oldest, the oldest pair passes on the integration site" '[ "$code" = 0 ]'

rm -f "$work/runs"
printf '0 Done!\n0 Done!\n' > "$work/script"
set +e
FAKE_DIR="$work" CBUILD_WP_ENV_COMMAND="$work/fake.sh" FAKE_CORE=7.0.8 FAKE_WOO=11.2.0 CI='' \
	bash "$tree/tools/wp-env-oldest.sh" --start > "$work/both.log" 2>&1
# shellcheck disable=SC2034 # read by check's eval.
code=$?
set -e
# shellcheck disable=SC2034 # read by check's eval.
runs="$(cat "$work/runs")"
check "env:oldest --start starts both sites and checks each" '[ "$code" = 0 ] && [ "$runs" = 2 ]'

exit "$fail"
