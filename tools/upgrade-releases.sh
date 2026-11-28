#!/usr/bin/env bash
# The releases `npm run upgrade:all` updates from (D-232, D-414, D-549). Reads the published versions, one a line, and
# prints the ones a shop could update from, below this build's, in version order:
# - with no support floor (support_floor below empty, while no shop but the demo runs the plugin): the previous release
#   alone, the demo's own path;
# - with one: every release of this build's major from the floor on, and the last release of each earlier major from
#   the floor on, the one a shop on that major is offered first;
# - with --every: every release below this build's, the floor aside: before a major release, and when a migration
#   changes.
# Its cases are tools/upgrade-releases-selftest.sh, which sets the floor through CBUILD_SUPPORT_FLOOR.
# Run: printf '%s\n' 0.1.0 1.0.0 | bash tools/upgrade-releases.sh 1.3.0 [--every]
set -euo pipefail

# The oldest release a shop still runs and must update from. Set it once a shop other than the demo runs the plugin
# (`no-outside-shops-yet`, 27 November 2026), and raise it as they update.
support_floor=''

floor="${CBUILD_SUPPORT_FLOOR-$support_floor}"
version="${1:-}"
every=0
case "${2:-}" in
	'') ;;
	--every) every=1 ;;
	*) echo "upgrade-releases: unknown option '$2'; the one option is --every" >&2; exit 2 ;;
esac
is_version() { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; }
is_version "$version" || { echo "upgrade-releases: '$version' is no version" >&2; exit 2; }
if [ -n "$floor" ] && ! is_version "$floor"; then
	echo "upgrade-releases: the support floor '$floor' is no version" >&2
	exit 2
fi
below() { [ "$1" != "$2" ] && [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -n 1)" = "$1" ]; }
kept=""
while read -r tag; do
	if [ -z "$tag" ] || ! below "$tag" "$version"; then continue; fi
	if [ "$every" -eq 0 ] && [ -n "$floor" ] && below "$tag" "$floor"; then continue; fi
	kept="${kept}${tag}
"
done
kept="$(printf '%s' "$kept" | sort -V)"
[ -n "$kept" ] || { echo "upgrade-releases: no release below $version${floor:+ from the floor $floor}" >&2; exit 1; }
if [ "$every" -eq 1 ]; then
	printf '%s\n' "$kept"
elif [ -z "$floor" ]; then
	printf '%s\n' "$kept" | tail -n 1
else
	# This major's releases whole; of each earlier major, its last.
	major="${version%%.*}"
	printf '%s\n' "$kept" | awk -F. -v major="$major" '
		$1 == major { print; next }
		{ last[$1] = $0; if (!($1 in seen)) { order[++n] = $1; seen[$1] = 1 } }
		END { for (i = 1; i <= n; i++) print last[order[i]] }
	' | sort -V
fi
