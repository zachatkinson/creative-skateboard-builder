#!/usr/bin/env bash
# Sourced, not run: the two wp-env sites' ports, the one place a script reads them. CBUILD_PORT is the browser site's
# (.wp-env.json) and CBUILD_TESTS_PORT the integration site's (.wp-env.tests.json); unset, they are the files' own 8898
# and 8899, so nothing changes in CI or in the first checkout. tools/lane.sh sets both for a second or third checkout's
# pair (8900 and 8901, 8902 and 8903), with their MySQL ports from 8910 (tools/wp-env.sh).
CBUILD_PORT="${CBUILD_PORT:-8898}"
CBUILD_TESTS_PORT="${CBUILD_TESTS_PORT:-8899}"
export CBUILD_PORT CBUILD_TESTS_PORT
