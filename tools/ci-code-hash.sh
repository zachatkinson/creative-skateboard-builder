#!/usr/bin/env bash
# The code's hash, for CI's scope job (D-188): every file of the commit but the ones ci.yml's paths-ignore leaves out
# (docs/, Markdown, LICENSE), by mode, contents and path. Two commits with the same hash run the same gates on the same
# code, so a full run that passed on one stands for the other. Prints 40 hex digits.
#
# Usage: bash tools/ci-code-hash.sh [commit]   (HEAD by default)
set -euo pipefail
cd "$(dirname "$0")/.."

git ls-tree -r --format='%(objectmode) %(objectname) %(path)' "${1:-HEAD}" \
	| { grep -Ev '^[0-9]+ [0-9a-f]+ (docs/|LICENSE$)|\.md$' || true; } \
	| sha256sum | cut -c1-40
