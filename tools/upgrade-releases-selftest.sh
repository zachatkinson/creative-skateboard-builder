#!/usr/bin/env bash
# The cases of tools/upgrade-releases.sh (D-414, D-549): the releases on creative-releases, this build's version, the
# mode and the support floor (CBUILD_SUPPORT_FLOOR, in place of the script's own), then the releases `npm run
# upgrade:all` must update from, in order. Part of composer gates.
set -euo pipefail
cd "$(dirname "$0")"
status=0
count=0
published='0.1.0 0.2.0 0.3.0 0.3.1 0.4.0 0.6.0 0.7.0 0.8.0 0.9.0 1.0.0 1.1.0 1.2.0'
check() { # what, version, mode, the releases, the answer
	local got
	# shellcheck disable=SC2086 # The mode is one word or none.
	got="$(printf '%s\n' $4 | bash upgrade-releases.sh "$2" $3 | tr '\n' ' ' | sed 's/ $//' || true)"
	count=$((count + 1))
	if [ "$got" != "$5" ]; then
		echo "upgrade-releases, $1: got '${got}', want '$5'"; status=1
	fi
}
# With no support floor set (no shop but the demo runs the plugin): the previous release alone.
check 'no floor: the previous release alone' 1.3.0 '' "$published" '1.2.0'
check 'no floor: the previous release, in version order' 1.3.0 '' '1.2.0 0.10.0 1.10.0 1.0.0' '1.2.0'
check 'no floor: not this one, nor a later one' 1.1.0 '' "$published" '1.0.0'
check 'every release below this one' 1.3.0 --every "$published" "$published"
check 'every release below this one, in version order' 1.3.0 --every '1.2.0 0.10.0 0.9.0 1.0.0' '0.9.0 0.10.0 1.0.0 1.2.0'
check 'every release, not this one, nor a later one' 1.1.0 --every "$published" '0.1.0 0.2.0 0.3.0 0.3.1 0.4.0 0.6.0 0.7.0 0.8.0 0.9.0 1.0.0'
# With a floor: every release of this major from the floor, and the last of each major before.
later="$published 1.3.0 1.4.0 1.5.0 1.5.1 1.6.0 2.0.0 2.1.0"
CBUILD_SUPPORT_FLOOR=1.5.0 check 'floor 1.5.0 at 1.7.0: 1.5.0 on' 1.7.0 '' "$later" '1.5.0 1.5.1 1.6.0'
CBUILD_SUPPORT_FLOOR=1.5.0 check 'floor 1.5.0 at 2.1.0: the last 1.x and 2.0.0' 2.1.0 '' "$later" '1.6.0 2.0.0'
CBUILD_SUPPORT_FLOOR=1.5.0 check 'floor 1.5.0 at 1.5.1: 1.5.0 alone' 1.5.1 '' "$later" '1.5.0'
CBUILD_SUPPORT_FLOOR=2.0.0 check 'a floor in this major leaves the last major out' 2.1.0 '' "$later" '2.0.0'
CBUILD_SUPPORT_FLOOR=1.5.0 check '--every ignores the floor' 1.3.0 --every "$published" "$published"
refuse() { # what, version, mode, the releases
	# shellcheck disable=SC2086 # The mode is one word or none.
	if printf '%s\n' $4 | bash upgrade-releases.sh "$2" $3 >/dev/null 2>&1; then
		echo "upgrade-releases accepted $1."; status=1
	fi
	count=$((count + 1))
}
refuse 'no release below this one' 0.1.0 '' "$published"
refuse 'no release below this one, every' 0.1.0 --every "$published"
CBUILD_SUPPORT_FLOOR=1.5.0 refuse 'a floor with no release from it below this one' 1.5.0 '' "$published 1.5.0"
CBUILD_SUPPORT_FLOOR=v1.5 refuse 'a floor that is no version' 1.7.0 '' "$published"
refuse 'an unknown option' 1.3.0 --sample "$published"
refuse 'a version that is no version' v1.3.0 '' "$published"
if [ "$status" -eq 0 ]; then echo "Upgrade releases self-test: ${count} cases."; fi
exit "$status"
