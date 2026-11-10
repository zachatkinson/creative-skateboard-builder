#!/usr/bin/env bash
# The cases of tools/release-plan.sh, one per line: version, published, what info.json names, then the answer.
# Part of composer gates.
set -euo pipefail
cd "$(dirname "$0")"
status=0
count=0
while read -r version published current release point; do
	[ -z "$version" ] && continue
	[ "$current" = - ] && current=""
	got="$(bash release-plan.sh "$version" "$published" "$current" | tr '\n' ' ')"
	want="release=${release} point=${point} "
	count=$((count + 1))
	if [ "$got" != "$want" ]; then
		echo "release-plan ${version} ${published} '${current}': got ${got}, want ${want}"; status=1
	fi
done <<'CASES'
0.2.0 no  0.1.0  yes yes
0.1.0 no  -      yes yes
0.2.0 yes 0.1.0  no  yes
0.2.0 yes 0.2.0  no  no
0.2.0 no  0.2.0  yes yes
0.1.0 yes 0.2.0  no  no
0.1.0 no  0.2.0  yes no
0.9.0 yes 0.10.0 no  no
0.10.0 no 0.9.0  yes yes
1.0.0 no  0.9.9  yes yes
0.2.0 no  junk   yes yes
CASES
for bad in "v0.1.0 no 0.1.0" "0.1.0 maybe 0.1.0"; do
	# shellcheck disable=SC2086 -- the words are the arguments.
	if bash release-plan.sh $bad >/dev/null 2>&1; then
		echo "release-plan accepted '${bad}'."; status=1
	fi
	count=$((count + 1))
done
if [ "$status" -eq 0 ]; then echo "Release plan self-test: ${count} cases."; fi
exit "$status"
