#!/usr/bin/env bash
# tools/verify.sh's own rules (D-567), without running a gate:
# - the browser step before a commit is the whole run for a change to the builder's script, a template, a stylesheet,
#   the browser tests' support or Playwright's config, and the focused tests for anything else;
# - a second run on one lane's sites refuses while the first still runs, and leaves the first's lock alone;
# - focused.sh --browser-only exits 3 for a change it can't map, so verify.sh runs the whole browser step;
# - --split refuses a tree with changes, and a partner checkout with changes of its own, before any gate (D-570).
set -euo pipefail
cd "$(dirname "$0")/.."
fail=0
check() {
	if eval "$2"; then echo "ok: $1"; else echo "FAILED: $1"; fail=1; fi
}

for file in assets/src/builder/view.ts templates/builder/card.php assets/src/builder/style.scss tests/e2e/support/lock.ts playwright.config.ts assets/src/admin/form.css; do
	check "a change to $file runs the browser step whole" '[ "$(bash tools/verify.sh --browser src/Plugin.php "$file")" = full ]'
done
for file in src/Admin/Form/Field.php tests/e2e/builder.spec.ts assets/src/admin/build-tab.ts docs/SPEC.md; do
	check "a change to $file alone runs the focused tests" '[ "$(bash tools/verify.sh --browser "$file")" = focused ]'
done

work="$(mktemp -d)"
sleep 30 &
holder=$!
trap 'kill "$holder" 2> /dev/null || true; wait "$holder" 2> /dev/null || true; rm -rf "$work"' EXIT
mkdir "$work/cbuild-verify-9990.lock"
echo "$holder" > "$work/cbuild-verify-9990.lock/pid"
set +e
TMPDIR="$work" CBUILD_PORT=9990 bash tools/verify.sh > "$work/second.log" 2>&1
# shellcheck disable=SC2034 # read by check's eval.
code=$?
set -e
check "a second run on the same lane refuses" '[ "$code" = 1 ] && grep -q "already running on the sites at :9990 (process $holder)" "$work/second.log"'
check "and leaves the first run's lock" '[ "$(cat "$work/cbuild-verify-9990.lock/pid")" = "$holder" ]'
check "and runs no gate" '! grep -q "^== " "$work/second.log"'

# A tree with a change to composer.json, which focused.sh can't map, and this checkout's focused.sh.
tree="$work/tree"
git worktree add -q --detach "$tree" HEAD
cp tools/focused.sh "$tree/tools/focused.sh"
echo ' ' >> "$tree/composer.json"
set +e
( cd "$tree" && bash tools/focused.sh --dry-run --browser-only > "$work/focused.log" 2>&1 )
# shellcheck disable=SC2034 # read by check's eval.
code=$?
set -e
git worktree remove --force "$tree"
check "focused.sh --browser-only exits 3 for a change it can't map" '[ "$code" = 3 ]'

# Two worktrees at a commit holding this checkout's verify.sh, so each starts clean. The commit may be empty: on a
# clean checkout, as in CI, the worktree already holds it.
a="$work/a"
b="$work/b"
git worktree add -q --detach "$a" HEAD
cp tools/verify.sh "$a/tools/verify.sh"
git -C "$a" -c user.name=selftest -c user.email=selftest@example.com commit -q -a --allow-empty -m "selftest: this checkout's verify.sh" --no-verify
git worktree add -q --detach "$b" "$(git -C "$a" rev-parse HEAD)"
echo ' ' >> "$b/README.md"
set +e
( cd "$a" && TMPDIR="$work" CBUILD_PORT=9991 bash tools/verify.sh --full --split 2 "$b" ) > "$work/split-b.log" 2>&1
# shellcheck disable=SC2034 # read by check's eval.
split_b=$?
echo ' ' >> "$a/README.md"
( cd "$a" && TMPDIR="$work" CBUILD_PORT=9991 bash tools/verify.sh --full --split 2 "$b" ) > "$work/split-a.log" 2>&1
# shellcheck disable=SC2034 # read by check's eval.
split_a=$?
( cd "$a" && bash tools/verify.sh --full --split 2 ) > /dev/null 2>&1
# shellcheck disable=SC2034 # read by check's eval.
split_usage=$?
set -e
git worktree remove --force "$a"
git worktree remove --force "$b"
check "--split refuses a partner checkout with changes of its own" '[ "$split_b" = 1 ] && grep -q "has changes of its own" "$work/split-b.log" && ! grep -q "^== " "$work/split-b.log"'
check "--split refuses a tree with changes" '[ "$split_a" = 1 ] && grep -q "needs a clean tree" "$work/split-a.log"'
check "--split without its lane and checkout is a usage error" '[ "$split_usage" = 2 ]'

exit "$fail"
