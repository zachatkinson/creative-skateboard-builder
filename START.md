# Starting Creative Skateboard Builder with Claude Code

Updated 9 November 2026. This replaces the 6 November package. The brief now covers the whole project around the spec.

## Contents

- [What's in the package](#whats-in-the-package)
- [1. Confirm the proposed decisions](#1-confirm-the-proposed-decisions)
- [2. The repository](#2-the-repository)
- [3. The first prompt for Claude Code](#3-the-first-prompt-for-claude-code)
- [4. How each milestone is reviewed](#4-how-each-milestone-is-reviewed)
- [5. What waits on the owner](#5-what-waits-on-the-owner)
- [6. Beside the builder](#6-beside-the-builder)
- [7. After 1.0](#7-after-10)

## What's in the package

`repo/` holds everything Claude Code needs, laid out as the repository's root:

| File | What it's for |
| --- | --- |
| `CLAUDE.md` | Read by Claude Code every session: how a milestone runs, the commands, the rules, what to ask first, and the quirks the sibling repositories already hit |
| `docs/SPEC.md` | The build spec, updated with the UX deep dive, the picture, SEO, privacy, upgrades and resilience |
| `docs/PROJECT.md` | The whole Skatehouse project: the shop, the five phases, the agency's other plugins and where each meets the builder, how the work runs |
| `docs/STANDARDS.md` | The industry standards for every language and platform used, with sources, and the house rules |
| `docs/AUDIT.md` | Your twelve dimensions: what each means for the builder, the gates that check it, the evidence a review needs, and when the audit runs |
| `docs/ROADMAP.md` | All nine milestones in full, then the companion releases, Skatehouse's setup, and the two modules after 1.0 |
| `docs/UX-RESEARCH.md` | The deep dive, arranged as patterns P1 to P12 and failures W1 to W9 that the reviews check |
| `docs/DECISIONS.md` | D-001 to D-019: the name, the open questions' answers as proposals, and the engineering calls made with the brief |
| `.claude/` | Two reviewer agents (`spec-reviewer`, `auditor`) and three skills (`/plan-milestone`, `/verify`, `/audit-milestone`) that run the audit through every milestone |
| `.github/pull_request_template.md` | The audit's twelve rows in every pull request |

**What changed since 6 November:**

- The audit matrix is twelve dimensions, run at every milestone.
- The board's layered picture is in 1.0.
- There are nine milestones instead of eight; the picture got its own.
- The floors follow Creative Search for PHP and WordPress: PHP 8.5, WordPress 7.0, and WooCommerce 11.2, the first release with the cart filters the builder needs.
- The spec has four new sections: SEO and sharing, privacy and data handling, upgrades and the public API, and resilience.

## 1. Confirm the proposed decisions

They're in `docs/DECISIONS.md`, each marked Proposed, with our suggestion. D-001, the name, is settled: Creative Skateboard Builder. Five are still needed before milestone 1:

| Decision | Our suggestion |
| --- | --- |
| D-002, build or buy | Build |
| D-003, repository and releases | Private `zachatkinson/creative-skateboard-builder`, GPL-2.0-or-later, released through `creative-releases` like Search |
| D-004, the floors | PHP 8.5 and WordPress 7.0, as Search's D-023; WooCommerce 11.2, for its cart filters |
| D-006, the picture | Layered 2D in 1.0; 3D on a tap after 1.0 |
| D-007, shoppers' own graphics | A module after 1.0, once the owner answers |

The rest can wait:

- **By milestone 5:** D-009 and D-010 (the cart count, the Mini-Cart script).
- **By milestone 6:** D-008 (where it lives).
- **Before Skatehouse's setup:** D-011 (dealers).

Say yes to all of them in the first prompt below, or name the ones you'd change.

## 2. The repository

```bash
mkdir -p ~/web-projects/creative-skateboard-builder
cd ~/web-projects/creative-skateboard-builder
git init -b main
cp -R ~/tmp/"Claude outputs"/creative-skateboard-builder-brief/repo/. .
git add -A && git commit -m "Creative Skateboard Builder: brief, spec and audit"
gh repo create zachatkinson/creative-skateboard-builder --private --source . --push
gh secret set RELEASES_TOKEN
```

`RELEASES_TOKEN` is the same kind of fine-grained token Creative Search's release workflow uses: Contents read and write on `zachatkinson/creative-releases` only.

Connect the folder to this chat (Add folder in the Claude app), so each milestone's review can read the code as the Search and Stockroom reviews did.

## 3. The first prompt for Claude Code

Paste this into Claude Code in the new folder:

> Read CLAUDE.md, then docs/SPEC.md in full (starting with "What changed on 9 Nov"), docs/PROJECT.md, docs/STANDARDS.md, docs/AUDIT.md and docs/ROADMAP.md, before writing anything. You're building Creative Skateboard Builder, the agency's WooCommerce plugin for building complete skateboards and the showpiece of the Skatehouse project, in the nine milestones of docs/ROADMAP.md. This session is milestone 1, Foundation, only.
>
> I accept the proposed decisions D-002 to D-011 as written; mark them Accepted with today's date. D-001, the name, is already accepted.
>
> Run /plan-milestone first and ask me anything the plan raises. Then build milestone 1 with /verify before every commit, and close it with /audit-milestone. Where the docs say "as Creative Search", read ~/web-projects/creative-search to see how it was done, and change nothing there. Stop when the pull request is open. Don't start milestone 2 until I say so.

After each review's fixes are in, merge the pull request, then tell Claude Code: "Milestone N is approved; build milestone N+1." It starts each milestone with `/plan-milestone`, which tags the merge you made and publishes it through `release.yml`.

## 4. How each milestone is reviewed

1. Claude Code's own audit is written into `docs/reviews/M<n>.md` and the pull request. That includes the two reviewer agents' findings and what was done with each.
2. You send me the pull request's link, or the IDE's summary, as for Stockroom and Search.
3. I review the code against the spec and the twelve dimensions, and check what shows. I list blockers, should-fixes and minors, with file and line. Fixes land before the next milestone starts.

Two milestones need more than code review:

- **Milestone 5:** Shop Kit can't run in CI, so I check its checkout with a build in it, on a local copy of the demo or on the demo itself if you install that milestone's zip there.
- **Milestone 9:** ends with the manual screen reader pass (by someone who didn't write the code), the benchmarks, and a hallway test. For the hallway test, three people who haven't seen the builder build a board on their own phones while you watch.

## 5. What waits on the owner

None of this holds up milestones 1 to 9:

- **Before Skatehouse sells completes:** questions 13 (which trucks and bearings), 17 (assembly), 18 (returns of assembled completes) and 19 (a build discount). None was answered by 9 November. The plugin ships with defaults for 17 to 19.
- **For the picture:** the trucks, bearings and risers themselves, and their photos shot to `docs/PICTURE.md` (written in milestone 7). We make the deck cut-outs from today's photos.
- **For the graphic module after 1.0:** the seven questions in `docs/UX-RESEARCH.md`.

## 6. Beside the builder

Small releases of the agency's other plugins, once the builder's hooks are in a tagged build (`docs/ROADMAP.md`, After 1.0):

- **Creative Checkout** (me): the arrival date and delivery line.
- **Mature Content** (me): blurring flagged parts in the builder and the picture.
- **Shipment Tracking** (Claude Code): builds grouped in "In this parcel".
- **Reviews** (Claude Code): the kit left out of requests.
- **Creative Loyalty** (Claude Code, from its own brief): the points line.

## 7. After 1.0

Skatehouse's setup, as `docs/SPEC.md` describes under What Skatehouse needs:

- the kit;
- the part specs, and a clean Catalogue check;
- the Builder block on /build-a-complete/;
- "Make it a complete" on deck pages;
- the presets, approved by the owner;
- the picture's photos;
- a build-and-checkout test in the demo's end-to-end suite.

Then one build is paid for on the demo through Stripe's test mode, by card and by Apple Pay, with you paying, as in the 4 November dry run.
