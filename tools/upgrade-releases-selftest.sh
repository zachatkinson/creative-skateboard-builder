#!/usr/bin/env bash
# The cases of tools/upgrade-releases.sh (D-414): the releases on creative-releases, this build's version and the mode,
# then the releases `npm run upgrade:all` must update from, in order. Part of composer gates.
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
check 'every release below this one' 1.3.0 '' "$published" "$published"
check 'every release below this one, in version order' 1.3.0 '' '1.2.0 0.10.0 0.9.0 1.0.0' '0.9.0 0.10.0 1.0.0 1.2.0'
check 'not this one, nor a later one' 1.1.0 '' "$published" '0.1.0 0.2.0 0.3.0 0.3.1 0.4.0 0.6.0 0.7.0 0.8.0 0.9.0 1.0.0'
check 'the sample: every 1.x, 0.1.0 and 0.9.0' 1.3.0 --sample "$published" '0.1.0 0.9.0 1.0.0 1.1.0 1.2.0'
check 'the sample keeps each 1.x point release' 2.0.0 --sample "$published 1.2.1 1.10.0" '0.1.0 0.9.0 1.0.0 1.1.0 1.2.0 1.2.1 1.10.0'
check 'the sample keeps a 2.x below this one' 2.1.0 --sample "$published 2.0.0" '0.1.0 0.9.0 1.0.0 1.1.0 1.2.0 2.0.0'
check 'the sample drops 0.10.0' 1.3.0 --sample "$published 0.10.0" '0.1.0 0.9.0 1.0.0 1.1.0 1.2.0'
check 'the sample, not this one' 1.2.0 --sample "$published" '0.1.0 0.9.0 1.0.0 1.1.0'
refuse() { # what, version, mode, the releases
	# shellcheck disable=SC2086 # The mode is one word or none.
	if printf '%s\n' $4 | bash upgrade-releases.sh "$2" $3 >/dev/null 2>&1; then
		echo "upgrade-releases accepted $1."; status=1
	fi
	count=$((count + 1))
}
refuse 'a sample without 0.1.0' 1.3.0 --sample '0.2.0 0.9.0 1.0.0'
refuse 'a sample without 0.9.0' 1.3.0 --sample '0.1.0 0.8.0 1.0.0'
refuse 'a sample of a build not above 0.9.0' 0.9.0 --sample "$published"
refuse 'no release below this one' 0.1.0 '' "$published"
refuse 'an unknown option' 1.3.0 --every "$published"
refuse 'a version that is no version' v1.3.0 '' "$published"
if [ "$status" -eq 0 ]; then echo "Upgrade releases self-test: ${count} cases."; fi
exit "$status"
