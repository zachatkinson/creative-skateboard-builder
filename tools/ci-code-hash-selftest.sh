#!/usr/bin/env bash
# The cases of tools/ci-code-hash.sh (D-188), in a throwaway repository: a change ci.yml's paths-ignore leaves out keeps
# the hash, and any other change moves it. A hash that missed a change to the code would let CI skip a run it owes.
# Part of composer gates.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

mkdir -p "$work/tools" "$work/docs" "$work/src/docs" "$work/tests"
cp "$here/ci-code-hash.sh" "$work/tools/"
cd "$work"
git init -q
git config user.email ci@example.com
git config user.name ci
echo '<?php' > src/A.php
echo 'notes' > docs/PLAN.md
echo 'readme' > README.md
echo 'licence' > LICENSE
echo 'kept' > src/docs/kept.php
echo 'stable tag' > readme.txt
git add -A && git commit -qm base
base="$(bash tools/ci-code-hash.sh)"

status=0
count=0
# Each case: what to change, and whether the hash may stay.
check() {
	local what="$1" same="$2" now
	git add -A && git commit -qm "$what" --allow-empty
	now="$(bash tools/ci-code-hash.sh)"
	count=$((count + 1))
	if [ "$same" = same ] && [ "$now" != "$base" ]; then
		echo "ci-code-hash: ${what} moved the hash, which CI's paths-ignore leaves out."; status=1
	elif [ "$same" = moves ] && [ "$now" = "$base" ]; then
		echo "ci-code-hash: ${what} kept the hash, so CI would skip code it never ran."; status=1
	fi
	base="$now"
}

echo 'more notes' >> docs/PLAN.md;              check 'a file under docs/' same
mkdir -p docs/new && echo x > docs/new/B.txt;  check 'a new file under docs/' same
echo 'more' >> README.md;                       check 'a Markdown file' same
echo 'x' > tests/notes.md;                      check 'a Markdown file elsewhere' same
echo 'more' >> LICENSE;                         check 'LICENSE' same
echo '// change' >> src/A.php;                  check 'a source file' moves
echo 'more' >> src/docs/kept.php;               check 'a file in a docs folder below the root' moves
echo 'more' >> readme.txt;                      check 'readme.txt' moves
git mv src/A.php src/B.php;                     check 'a renamed file' moves
chmod +x src/B.php;                             check 'a file made executable' moves
echo 'x' > tests/new.php;                       check 'a new file' moves

if [ "$status" -eq 0 ]; then echo "CI code hash self-test: ${count} cases."; fi
exit "$status"
