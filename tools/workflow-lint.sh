#!/usr/bin/env bash
# The workflows' lint (D-299, #74): actionlint, with ShellCheck on every run script, and zizmor at its pedantic persona,
# with its online audits when GH_TOKEN is set. CI's scope job runs it.
#
# The repository allows GitHub's own actions and three named ones (Settings > Actions), so the two tools are not
# actions: each is its release's Linux binary, at the version and SHA-256 pinned here, checked before it runs. A tool on
# the PATH at the pinned version is used as it is (the development Mac's, from Homebrew). Dependabot can't see these
# pins: bump them by hand, with the checksum the release lists.
set -euo pipefail
cd "$(dirname "$0")/.."

actionlint_version=1.7.12
actionlint_sha256=8aca8db96f1b94770f1b0d72b6dddcb1ebb8123cb3712530b08cc387b349a3d8 # actionlint_1.7.12_linux_amd64.tar.gz
zizmor_version=1.32.0
zizmor_sha256=bb110cc765eca9cd7efb2e284a956be5974c60027b0970f16397d86208a9957b # zizmor-x86_64-unknown-linux-gnu.tar.gz

bin="${RUNNER_TEMP:-tmp}/workflow-lint"
mkdir -p "$bin"

# fetch <name> <url> <sha256>: the release's tarball, checked, unpacked into $bin. Every step returns on failure by
# hand: it runs inside $( ), where set -e does not reach.
fetch() {
	local tarball="$bin/$1.tar.gz"
	curl --fail --silent --show-error --location --output "$tarball" "$2" || return 1
	if ! echo "$3  $tarball" | sha256sum --check --strict --quiet; then
		echo "$1's download does not match its pinned SHA-256; nothing was run." >&2
		return 1
	fi
	tar -xzf "$tarball" -C "$bin" "$1" || return 1
}

# tool <name> <version>: the command to run it, the PATH's when it is the pinned version, else the release's.
tool() {
	if command -v "$1" > /dev/null && "$1" --version 2>&1 | grep -qF "$2"; then
		command -v "$1"
		return
	fi
	if [ "$(uname -s)-$(uname -m)" != Linux-x86_64 ]; then
		echo "$1 $2 is not on the PATH, and only the Linux x86-64 release is pinned here." >&2
		return 1
	fi
	case "$1" in
		actionlint) fetch actionlint "https://github.com/rhysd/actionlint/releases/download/v${2}/actionlint_${2}_linux_amd64.tar.gz" "$actionlint_sha256" || return 1 ;;
		zizmor) fetch zizmor "https://github.com/zizmorcore/zizmor/releases/download/v${2}/zizmor-x86_64-unknown-linux-gnu.tar.gz" "$zizmor_sha256" || return 1 ;;
	esac
	echo "$bin/$1"
}

actionlint="$(tool actionlint "$actionlint_version")" || exit 1
zizmor="$(tool zizmor "$zizmor_version")" || exit 1

echo "actionlint ${actionlint_version}, with ShellCheck on the run scripts"
"$actionlint" -color

# Offline without a token: its online audits (known-vulnerable and impostor actions) need GitHub's API.
online=(--offline)
if [ -n "${GH_TOKEN:-}" ]; then online=(); fi
echo "zizmor ${zizmor_version}, pedantic${online[*]:+, offline}"
# ${a[@]+...}: an empty array is "unbound" to macOS's bash 3.2 under set -u.
"$zizmor" --persona=pedantic --config=.github/zizmor.yml ${online[@]+"${online[@]}"} .github
