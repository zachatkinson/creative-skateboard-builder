#!/usr/bin/env bash
# tools/wp-env.sh's retry of a start that fails on a download (#179), with a stand-in for wp-env
# (CBUILD_WP_ENV_COMMAND) and no wait (CBUILD_WP_ENV_WAITS):
# - under CI, a 429 or a timeout is tried again, up to three tries in all, and a start that then succeeds succeeds;
# - three download failures fail;
# - a failure that is no download's (our own) is not tried again;
# - outside CI nothing is tried again.
set -euo pipefail
cd "$(dirname "$0")/.."
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
fail=0
check() {
	if eval "$2"; then echo "ok: $1"; else echo "FAILED: $1"; fail=1; fi
}

# The stand-in prints the next line of its script and exits with its code, counting its runs.
cat > "$work/fake.sh" <<'EOF'
#!/usr/bin/env bash
n=$(( $(cat "$FAKE_DIR/runs" 2>/dev/null || echo 0) + 1 ))
echo "$n" > "$FAKE_DIR/runs"
line="$(sed -n "${n}p" "$FAKE_DIR/script")"
echo "${line#* }"
exit "${line%% *}"
EOF
chmod +x "$work/fake.sh"

# run <name> <CI value> <script lines...>: the wrapper's exit code in $code, its runs of the stand-in in $runs.
run() {
	local name="$1" ci="$2"
	shift 2
	rm -f "$work/runs"
	printf '%s\n' "$@" > "$work/script"
	set +e
	CI="$ci" FAKE_DIR="$work" CBUILD_WP_ENV_COMMAND="$work/fake.sh" CBUILD_WP_ENV_WAITS="0 0" \
		bash tools/wp-env.sh start > "$work/$name.log" 2>&1
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

exit "$fail"
