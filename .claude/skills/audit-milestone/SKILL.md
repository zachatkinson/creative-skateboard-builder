---
name: audit-milestone
description: Close a milestone. Run the full gates, have the spec-reviewer and auditor agents review the diff with fresh context, fix every blocker and should-fix with a test that fails without the fix, write the review, update the docs and open the pull request.
---

1. **Everything green, in full.** With wp-env running, run `/verify`, then:
   - `composer infection`
   - `npm run mutate:js`
   - `composer test:benchmark`
   - `composer test:benchmark:integration`, which holds the state route to its 80 ms budget on this machine; CI holds it to its query counts and the budget scaled by the runner's speed (D-124, D-171)
   - the integration variants: `composer test:integration:hpos-off`, `:hpos-sync`, `:multisite` and `:object-cache`
   - the oldest leg: `npm run env:oldest`, the integration and browser suites, then `npm run env:newest`
   - `npm run test:e2e -- --project=chromium-desktop --project=chromium-phone --project=chromium-tablet --project=webkit-desktop --project=webkit-phone --project=webkit-tablet --project=firefox-desktop --project=firefox-phone --project=firefox-tablet --project=no-js --project=fr-ca --project=rtl` (everything but `visual`, which runs in its Linux container through `bash tools/visual.sh`)
   - `composer infection:diff`, CI's own run on the lines the pull request changes (D-465)
   - `composer i18n:check`
   - `npm run lhci`
   - `composer audit:deps`

   Fix anything red before going on.
2. **Two reviews with fresh context.** Start the `spec-reviewer` and `auditor` agents in parallel. Give each the milestone number; they diff the branch against `git merge-base main HEAD`. Don't give them your own view of the code.
3. **Fix.**
   - **Blocker or should-fix:** write a test or gate that fails on the current code, fix the code, and show the test passing. Then revert the fix once and show the test failing (the deliberate break). Note both in the findings table.
   - **Should-fix you can't fix now:** stop and ask Zach to defer it. Record his answer.
   - **Minor:** fix it, or list it.
4. **Rerun** step 1's gates on what changed.
5. **Write the review.** Fill the rest of `docs/reviews/M<n>.md`:
   - what was built, against the plan;
   - the gates table with numbers (tests, coverage, MSI for PHP and JavaScript, runs per engine and width, budgets measured, Lighthouse medians);
   - the twelve verdicts, each with its evidence;
   - the findings and what was done with each;
   - the decisions taken;
   - For Zach.
6. **Docs.** Update every doc the milestone touched:
   - `CHANGELOG.md`;
   - `docs/HOOKS.md` (and the API snapshot);
   - `docs/SETTINGS.md` and `docs/PRIVACY.md`;
   - `docs/SECURITY.md` (walk it before the tag);
   - `docs/BENCHMARK.md` and `docs/A11Y.md`;
   - `readme.txt`.
7. **The walkthrough.** For a milestone with a screen, take its screenshots into `docs/reviews/M<n>/`: every screen at desktop and phone width, light, in English; and the main screens (for the builder, the review and the phone bar) once more in dark and once in French. The visual snapshots hold the rest of the combinations (Zach, 16 Nov 2026). Cover what the milestone built. Admin-only milestones walk their screens. From milestone 6, also cover the ways in built so far, a change that breaks later picks, a sold-out part in a preset, and the phone bar.
8. **The pull request.** Open it as a draft with `.github/pull_request_template.md` filled from the review, and mark it ready once the gates pass locally, which starts the full CI run (D-041). When the full run is green, merge it and tag the release (D-223). Tell Zach it's ready for Claude in Cowork's review of the release; its findings go into the next milestone's plan. Then stop: the next milestone waits for "Milestone N is approved".
