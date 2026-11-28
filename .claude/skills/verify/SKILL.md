---
name: verify
description: Run every local gate (lint, analysis, architecture, grep gates, unit tests with the coverage floors, the API snapshot, the JavaScript, CSS and type checks, the asset budgets), plus the integration tests, Plugin Check and the browser tests when wp-env is up. Invoke before every commit, and with --full before every push.
---

Run `bash tools/verify.sh` (in another checkout, `bash tools/lane.sh <n> bash tools/verify.sh`). It runs the steps below in order, stops at the first failure, and ends with `VERIFIED` or a `FAILED:` line. Report a failure's output verbatim. Docs-only commits skip this skill. Run it in bash, in the background for a long run, and read its last line: `grep -E 'FAILED|VERIFIED'`.

Before a push, run `bash tools/verify.sh --full`, or `bash tools/verify.sh --full --split <lane> <checkout>` to run chromium-phone in another lane's clean checkout at the same commit (D-570). Before a commit, the browser step is focused (D-567). Before the whole script, when wp-env is up, `bash tools/focused.sh` runs only the integration and browser tests that cover the change, so an early failure shows in minutes.

One run a lane: a second run on the same sites refuses. Stopped with Ctrl-C or a signal, a run stops its own processes and the integration suite in the container.

1. `composer install` and `npm ci`, when a lock file changed since this checkout's last run
2. `composer lint`
3. `composer analyse`
4. `composer arch`
5. `composer gates`
6. `composer test:coverage`
7. `composer api:check`
8. `npm run types`, then `git status --porcelain -- assets/src/types`, which must print nothing. Generated types must already be committed.
9. `npm run check`
10. `npm run build && npm run budget`
11. If wp-env is up on the lane's port:
    1. `CBUILD_COVERAGE_DRIVER=pcov composer test:coverage:all` (the whole integration suite, and the 95 % floor on both suites together; pcov can only under-count, so it is stricter than CI's Xdebug, D-312)
    2. `composer i18n:check`
    3. `npm run plugin-check`
    4. The browser tests, chromium-desktop and chromium-phone:
       - with `--full`, every test;
       - before a commit, every test when a change is in `assets/src/builder/`, `templates/`, a stylesheet, `tests/e2e/support/` or `playwright.config.ts`, or `focused.sh` can't map a change;
       - otherwise only the tests that cover the change (`focused.sh --browser-only`).

The run ends with `VERIFIED (full)`, `VERIFIED (focused browser step…)`, or `VERIFIED, but wp-env is down…` naming what it skipped. Only report "verified" when it says so, and say which.

This is the fast path. Mutation testing, the other engines, the integration variants, Lighthouse and the benchmarks run in `/audit-milestone` and in CI. The full project set runs locally before a release push (`full-project-set-before-push`).
