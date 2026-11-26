#!/usr/bin/env bash
# ShellCheck on every shell script git tracks (D-467): the .sh files and the pre-commit hook, with .shellcheckrc's
# settings, every severity. In composer gates, so /verify and CI's static job both run it.
#
# Like actionlint and zizmor (tools/workflow-lint.sh), it is its release's Linux binary at the version and SHA-256
# pinned here, checked before it runs, and a ShellCheck on the PATH at the pinned version is used as it is (the
# development Mac's, from Homebrew). Dependabot can't see the pin: bump it by hand. The release lists no checksum, so
# the pin is the SHA-256 of its tarball as downloaded on 26 November 2026.
set -euo pipefail
cd "$(dirname "$0")/.."

version=0.11.0
sha256=8c3be12b05d5c177a04c29e3c78ce89ac86f1595681cab149b65b97c4e227198 # the tarball shellcheck-v0.11.0.linux.x86_64.tar.xz

shellcheck=""
if command -v shellcheck > /dev/null && shellcheck --version | grep -qx "version: ${version}"; then
	shellcheck="$(command -v shellcheck)"
elif [ "$(uname -s)-$(uname -m)" = Linux-x86_64 ]; then
	bin="${RUNNER_TEMP:-tmp}/shellcheck"
	mkdir -p "$bin"
	tarball="$bin/shellcheck.tar.xz"
	curl --fail --silent --show-error --location --output "$tarball" \
		"https://github.com/koalaman/shellcheck/releases/download/v${version}/shellcheck-v${version}.linux.x86_64.tar.xz"
	if ! echo "${sha256}  ${tarball}" | sha256sum --check --strict --quiet; then
		echo "ShellCheck's download does not match its pinned SHA-256; nothing was run." >&2
		exit 1
	fi
	tar -xJf "$tarball" -C "$bin" --strip-components=1 "shellcheck-v${version}/shellcheck"
	shellcheck="$bin/shellcheck"
else
	echo "ShellCheck ${version} is not on the PATH (brew install shellcheck), and only its Linux x86-64 release is pinned here." >&2
	exit 1
fi

scripts=()
while IFS= read -r script; do
	scripts+=("$script")
done < <(git ls-files '*.sh' .githooks/pre-commit)
"$shellcheck" "${scripts[@]}"
echo "ShellCheck ${version}: ${#scripts[@]} scripts clean."
