#!/usr/bin/env bash
# Sourced by the runs that measure src/Domain (level 10, the branch floor, the 100 % mutation run). Until milestone 2
# each skipped while the domain had no file; a run that measures nothing must not pass, so now each fails (D-062).
# CBUILD_DOMAIN_DIR points it at another folder: tools/gates-selftest.sh uses an empty one.
if [ -z "$(find "${CBUILD_DOMAIN_DIR:-src/Domain}" -name '*.php' 2>/dev/null | head -n 1)" ]; then
	echo "${CBUILD_DOMAIN_DIR:-src/Domain} has no PHP file: there is nothing to measure, and that is a failure." >&2
	exit 1
fi
