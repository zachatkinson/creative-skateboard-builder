#!/usr/bin/env bash
# The releases `npm run upgrade:all` updates from (D-232, D-414). Reads the published versions, one a line, and prints
# those below this build's, in version order. With --sample, only every release from 1.0.0 on, and 0.1.0 and 0.9.0:
# the first release, and the last before 1.0.0, whose saved builds and schemas 1.0.0 had to read. A sample without
# either fails, so a release taken down can't shrink the run unseen. Its cases are tools/upgrade-releases-selftest.sh.
# Run: printf '%s\n' 0.1.0 1.0.0 | bash tools/upgrade-releases.sh 1.3.0 [--sample]
set -euo pipefail
version="${1:-}"
sample=0
case "${2:-}" in
	'') ;;
	--sample) sample=1 ;;
	*) echo "upgrade-releases: unknown option '$2'" >&2; exit 2 ;;
esac
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "upgrade-releases: '$version' is no version" >&2; exit 2; }
below() { [ "$1" != "$2" ] && [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -n 1)" = "$1" ]; }
kept=""
while read -r tag; do
	if [ -z "$tag" ] || ! below "$tag" "$version"; then continue; fi
	if [ "$sample" -eq 1 ] && [ "$tag" != 0.1.0 ] && [ "$tag" != 0.9.0 ] && below "$tag" 1.0.0; then continue; fi
	kept="${kept}${tag}
"
done
kept="$(printf '%s' "$kept" | sort -V)"
[ -n "$kept" ] || { echo "upgrade-releases: no release below $version" >&2; exit 1; }
if [ "$sample" -eq 1 ]; then
	for needed in 0.1.0 0.9.0; do
		printf '%s\n' "$kept" | grep -Fqx "$needed" || { echo "upgrade-releases: the sample has no $needed below $version" >&2; exit 1; }
	done
fi
printf '%s\n' "$kept"
