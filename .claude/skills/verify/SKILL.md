---
name: verify
description: Run every local gate (lint, analysis, architecture, grep gates, unit tests with the coverage floors, the API snapshot, the JavaScript, CSS and type checks, the asset budgets), plus the integration tests, Plugin Check and a browser smoke run when wp-env is up. Invoke before every commit.
---

Run these in order. Stop at the first failure and report its output verbatim. Docs-only commits skip this skill. In milestone 1, run the steps whose tools exist so far, and say which were skipped.

1. `composer lint`
2. `composer analyse`
3. `composer arch`
4. `composer gates`
5. `composer test:coverage`
6. `composer api:check`
7. `npm run types`, then `git status --porcelain -- assets/src`, which must print nothing (a diff misses a types file that was never committed). Generated types must already be committed.
8. `npm run check`
9. `npm run build && npm run budget`
10. If wp-env is up (`curl -sf http://localhost:8898/ > /dev/null`):
    1. `composer test:coverage:all` (the integration suite, and the 95 % floor on both suites together)
    2. `composer i18n:check`
    3. `npm run plugin-check`
    4. `npx playwright test --project=chromium-desktop --project=chromium-phone`

Only report "verified" when every step that ran passed. If step 10 was skipped because wp-env is down, say so in the same line.

This is the fast path. Mutation testing, the other engines, the integration variants, Lighthouse and the benchmarks run in `/audit-milestone` and in CI.
