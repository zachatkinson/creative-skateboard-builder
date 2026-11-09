---
name: plan-milestone
description: Start a milestone. Read its roadmap entry and spec sections, write the plan with the risks under all twelve audit dimensions into docs/reviews/M<n>.md, and raise anything that needs Zach before code is written.
---

0. If the last milestone's pull request is merged into `main` and its merge isn't tagged, tag it (`v0.<n-1>.0`, or `v1.0.0` after milestone 9) and push the tag; `release.yml` publishes it. Then branch `m<n>-<short name>` from `main`.
1. Read the milestone in `docs/ROADMAP.md`: Builds, Tests that must pass, Done when, and where the audit looks hardest.
2. Read every `docs/SPEC.md` section it touches, `docs/DECISIONS.md`, and the last milestone's review for anything carried over.
3. Where the milestone names a WordPress or WooCommerce hook, function or event, find it in the source wp-env installed now, and note its file and line. In milestone 1, do this as soon as wp-env is up, before relying on any hook. A difference from the spec is a decision to record, or a question for Zach.
4. Write `docs/reviews/M<n>.md` with the shape in `docs/AUDIT.md`, filling only "Plan":
   - what the milestone builds, in a short list;
   - the spec sections it touches;
   - the public API it adds (hooks, routes, settings, meta keys, events), which `api:check` will expect;
   - **risks**: one line per dimension, all twelve, saying what could go wrong in this milestone and the test, gate or review that will catch it. "None: <why>" is a fine line; a blank isn't;
   - the order of work, test-first items marked;
   - **questions for Zach**, if any.
5. Commit the plan alone: `M<n>: plan`.
6. If there are questions for Zach, stop and ask them. Otherwise start building.
