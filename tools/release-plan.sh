#!/usr/bin/env bash
# What a release run still has to do (release.yml, D-048): publish the release, point info.json at it, both or neither.
# Usage: tools/release-plan.sh <version> <published: yes|no> <the version info.json names, or ''>
# Prints two lines: release=yes|no and point=yes|no.
#
# info.json never moves backwards: a run for an older version than the one it names (an old run started again) leaves
# it alone. A zip built by this run has a new checksum, so info.json is written again even when it already names this
# version (a release deleted by hand and built again).
set -euo pipefail
version="$1"
published="$2"
current="${3:-}"
plain='^[0-9]+\.[0-9]+\.[0-9]+$'
[[ "$version" =~ $plain ]] || { echo "release-plan: '$version' is not a version." >&2; exit 2; }
case "$published" in yes|no) ;; *) echo "release-plan: published must be yes or no." >&2; exit 2 ;; esac

release=yes
if [ "$published" = yes ]; then release=no; fi

if [[ ! "$current" =~ $plain ]]; then
	# No info.json yet, or one this script cannot read: write it.
	point=yes
elif [ "$current" = "$version" ]; then
	point="$release"
elif [ "$(printf '%s\n%s\n' "$current" "$version" | sort -V | tail -n 1)" = "$version" ]; then
	point=yes
else
	point=no
fi
printf 'release=%s\npoint=%s\n' "$release" "$point"
