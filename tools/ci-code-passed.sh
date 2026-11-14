#!/usr/bin/env bash
# Whether a full CI run has passed on this code (D-188): prints the run's ID and exits 0, or exits 1. A full run uploads
# the marker `passed-code-<hash>` (tools/ci-code-hash.sh) when it starts, so only its run's conclusion says whether it
# passed. A marker counts only from this repository's own ci.yml, in a run other than this one, that ended in success.
# Anything this cannot read counts as not passed, so the full run happens.
#
# Usage: bash tools/ci-code-passed.sh <hash>   (needs GH_TOKEN with actions: read, and GITHUB_REPOSITORY)
set -uo pipefail

code="${1:?the code hash}"
repo="${GITHUB_REPOSITORY:?the repository}"

runs="$(gh api "repos/${repo}/actions/artifacts?name=passed-code-${code}&per_page=100" \
	--jq '.artifacts[] | select(.expired | not) | .workflow_run.id')" || exit 1

for run in $runs; do
	if [ "$run" = "${GITHUB_RUN_ID:-}" ]; then
		continue
	fi
	verdict="$(gh api "repos/${repo}/actions/runs/${run}" \
		--jq "select(.path == \".github/workflows/ci.yml\" and .head_repository.full_name == \"${repo}\") | .conclusion")" || continue
	if [ "$verdict" = success ]; then
		echo "$run"
		exit 0
	fi
done
exit 1
