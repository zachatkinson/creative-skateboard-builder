# The audit matrix

Creative Branding audits every plugin it ships against the same dimensions, at every milestone and every release. This file says what each dimension means for Creative Skateboard Builder, which gates check it on every commit, what a reviewer checks by hand, and what evidence goes into the review.

`SPEC.md` says what to build. `STANDARDS.md` says how the code is written. This file says how both are judged. Where they disagree, the spec wins and the disagreement is recorded in `DECISIONS.md`.

Twelve dimensions. The first ten are the agency's own list (Zach, 9 November 2026). The last two were proposed with this brief and approved by Zach the same day, because the builder is the shop's showpiece: what it does when something fails, and what it is like to use.

| # | Dimension | The question it asks |
| --- | --- | --- |
| 1 | DRY and SOLID | Is each rule, price and decision made in exactly one place, by a class with one job? |
| 2 | Anti-patterns | Does the code avoid what WordPress, WooCommerce and the web have learned not to do? |
| 3 | Security | Can anyone see, change or break what they shouldn't? |
| 4 | Privacy and data handling | Do we keep only what we need, say what we keep, and let people take it or erase it? |
| 5 | Accessibility | Can everyone build a board and run the admin, by keyboard, screen reader, zoom or touch? |
| 6 | Internationalisation | Does it read right in French, in a right-to-left language, and in any shop's currency and units? |
| 7 | SEO | Is the builder page a strong page for search, with no crawl traps, duplicates or junk in the index? |
| 8 | Performance | Does it meet its budgets on a phone and on a cheap host? |
| 9 | Testing | Would the tests catch the next mistake? |
| 10 | Upgrades and compatibility | Does every upgrade just work, and can code built on the plugin trust it not to break? |
| 11 | Resilience and error handling | When something fails, does the shopper still get somewhere and does the owner find out? |
| 12 | UX and content | Is it clear and quick, as the deep dive's patterns ask, in plain words, with motion that helps? |

## When the audit runs

Through the whole build:

| When | Who | What happens | Written to |
| --- | --- | --- | --- |
| Before a milestone's first line of code | Claude Code, with the `plan-milestone` skill | The plan: what the milestone builds (from `ROADMAP.md`), the spec sections it touches, and one line per dimension on the risk and how it will be covered. A risk that needs a decision from Zach is asked before coding starts, not after | `docs/reviews/M<n>.md`, "Plan" |
| Every commit | Claude Code, with the `verify` skill | Every fast gate below. Nothing is committed on red. Docs-only commits skip it; in milestone 1 it runs the gates that exist so far | The commit |
| Every push and pull request | CI | Every gate, on the full version matrix | GitHub Actions |
| End of a milestone | Claude Code, with the `audit-milestone` skill | Two reviewer agents with fresh context read the milestone's diff (`git diff $(git merge-base main HEAD)..HEAD`): `spec-reviewer` (does the code do what the milestone and the spec say) and `auditor` (these twelve dimensions). Every blocker and should-fix is fixed, each with a test or gate that fails on the old code where one can be written | `docs/reviews/M<n>.md`: gates, the twelve verdicts, the findings |
| Milestone review | Claude in Cowork | An independent review of the code against the spec and this matrix, and a check on the demo once the milestone shows there | Findings to Zach, after the release is tagged; fixed in the next milestone (D-223) |
| Every release, from 1.0 | Claude Code, then Claude in Cowork | An audit of the whole codebase: every dimension, every rule, open items carried forward | `docs/AUDIT-<version>.md` |

## Severity and verdicts

| Severity | Means | The milestone |
| --- | --- | --- |
| Blocker | A requirement unmet; a security or privacy hole; data loss; an accessibility failure on a main path; a broken upgrade; a public API broken without deprecation | Can't close |
| Should-fix | A real defect or gap with limited reach | Fixed in the milestone, unless Zach defers it in writing (recorded in the review) |
| Minor | Polish | Listed; may carry to a later milestone |

Each dimension gets one verdict per review: **Pass**, **Pass with notes** (minors only) or **Fail** (any blocker, or a should-fix not fixed or deferred). A dimension the milestone doesn't touch says **Not touched** and why, in a line.

**Evidence is required.** Every verdict cites something checkable: a test's name, a gate's output, a file and line, a measurement with its number. "Looks fine" is not evidence.

**A fix proves itself.** A finding is closed only with a test or gate that fails when the fix is reverted (the agency's deliberate-break run). Where no automated check is possible, the review says so and says how it was checked.

## The gates

The commands are in `CLAUDE.md`. The fast gates run on every commit through `verify`; CI runs all of them.

| Gate | Command | Dimensions |
| --- | --- | --- |
| PHPCS: WordPress-Extra, WordPress-Docs, WordPress-VIP-Go, PHPCompatibilityWP (8.5-), zero warnings | `composer lint` | 2, 3, 6 |
| PHPStan 2: level 9 on `src/` with strict rules, deprecation rules and banned calls; level 10 on `src/Domain` | `composer analyse` | 1, 2, 3, 10 |
| Architecture: deptrac layers (the domain depends on nothing; WordPress only in adapters) | `composer arch` | 1 |
| Grep gates: SQL outside repositories, banned functions, `date()`, `strict_types`, outbound requests, WordPress calls in the domain, the copy rules in translatable strings | `composer gates` | 1, 2, 3, 12 |
| Unit tests and the domain's coverage floors (100 % lines and branches on `src/Domain`) | `composer test:coverage` | 9 |
| The coverage floor on all of `src/` (95 % of lines, the unit and integration suites together) | `composer test:coverage:all` | 9 |
| Mutation testing: Infection (100 % MSI on `src/Domain`, 95 % elsewhere), StrykerJS (90 % on the builder's store) | `composer infection`, `npm run mutate:js` | 9 |
| Public API snapshot: hooks, REST schemas, block attributes, settings keys, against `docs/HOOKS.md` and the committed snapshot | `composer api:check` | 10 |
| Integration: WordPress test suite in wp-env, oldest and newest, HPOS on, off and syncing, multisite, a persistent object cache | `composer test:integration` and its variants | 3, 4, 9, 10, 11 |
| Benchmarks: the server budgets as tests | `composer test:benchmark` | 8 |
| JavaScript and CSS standards, types, unit tests | `npm run check` | 1, 2, 9 |
| Asset budgets: script and styles, compressed | `npm run budget` | 8 |
| Browser: Playwright on Chromium, WebKit and Firefox, phone and desktop; JavaScript off; fr_CA; right to left; visual snapshots; axe on every state | `npm run test:e2e` | 5, 6, 7, 9, 11, 12 |
| Lighthouse CI on the builder page: LCP, CLS, TBT, SEO | `npm run lhci` | 7, 8 |
| Plugin Check on the built zip, zero errors or warnings | `npm run plugin-check` | 2, 3, 10 |
| i18n: the POT current at every milestone; fr_CA complete from milestone 9, reported as a percentage before then | `composer i18n:check` | 6 |
| Dependencies: `composer audit`, `npm audit --omit=dev`, a secrets scan | `composer audit:deps`, CI | 3 |
| Weekly canary: WordPress trunk and WooCommerce's latest pre-release; opens an issue when red, blocks nothing | CI schedule | 10 |

## The twelve dimensions

### 1. DRY and SOLID

**What it means here**

- One fit engine, in PHP, in `src/Domain`. The state route, the cart validation, the no-JavaScript form, WP-CLI and the abilities all call it. The browser never decides fit or price.
- One template per piece of the builder: the server renders it for the first load and the no-JavaScript path, and the Interactivity API re-renders the same markup from the state route's answer. No second copy of the markup in JavaScript.
- One price path (`wc_get_price_to_display()`), one layout calculation for the picture shared by both of its renderers, one JSON Schema per stored document, one writer per table.
- Classes with one reason to change. Dependencies come in through interfaces (catalogue, stock, prices, clock, cache, logger), so tests swap them. No static state outside the bootstrap and the container.
- Names from the spec's Vocabulary, in code, tables, settings and docs alike.

**Checked automatically:** deptrac's layers; PHPStan; the grep gate for WordPress calls in the domain.

**Checked by hand:** logic repeated in PHP and JavaScript; a rule or message written twice; a class that knows about two concerns; a long method or class (no hard limit, but over about 40 lines or 300 lines it gets a second look).

**Evidence:** deptrac's output; the classes the milestone added, each with its one job in a line.

**Blockers look like:** fit or a price worked out in JavaScript; SQL outside a repository; a rule copied rather than called.

### 2. Anti-patterns

**What it means here.** None of these, anywhere:

- Logic in a hook callback. Hooks call services.
- SQL on WordPress's or WooCommerce's own tables. Products and orders go through CRUD, so HPOS and caches stay right.
- Unbounded queries (`posts_per_page => -1`, no `LIMIT`), a query in a loop, or work on the request that saves a product.
- Large autoloaded options, transients used as storage, `query_posts()`, `extract()`, globals, `@` suppression.
- `admin-ajax.php` (REST instead), jQuery on the front end, inline `<script>` carrying data (`wp_interactivity_state()` or a JSON script tag instead), inline event handlers.
- Overriding WooCommerce's or Shop Kit's templates. Markup changes go through hooks and filters, or the HTML API where markup must be rewritten.
- WooCommerce internals: `Automattic\WooCommerce\Internal\*`, the private Interactivity API cart store, events marked internal.
- `date()`, `time()` or MySQL `NOW()`. Time comes from the Clock.
- Remote requests, except the update check.
- `catch ( \Throwable )` that swallows an error. Exceptions are caught only at a boundary that logs them and says what happened.
- `!important`, styles leaking outside the builder's root, IDs used for styling.

**Checked automatically:** PHPStan's banned calls, the grep gates, PHPCS (VIP rules), Plugin Check, and query counts in the integration tests (a page or route costs a fixed number of queries whatever the number of options).

**Evidence:** the gates' output; the query counts per page and route, against their budget.

### 3. Security

**What it means here.** The bar is OWASP ASVS 5.0 at level 2, where it applies to a WordPress plugin, and the WordPress Plugin Handbook's security chapter in full.

- Every state-changing action checks a capability and a nonce. Each is tested allowed, refused without the capability, and refused without the nonce.
- The state route is public and read-only: explicit `permission_callback`, an argument schema with types and limits, a rate limit answered with 429 and `Retry-After`, and `Cache-Control: no-store`.
- Nothing from the browser is trusted. Picks are IDs, re-validated on every use, and prices are always the server's.
- Saved builds are readable and changeable only by their owner. A test tries another customer's ID and a guessed UUID.
- Input sanitised where it arrives, output escaped where it's printed, SQL prepared with `%i` for identifiers, redirects through `wp_safe_redirect()`.
- CSV imports have size and row limits and a dry run. Exports neutralise cells that start with `=`, `+`, `-` or `@`.
- Generated files (the composed picture) are named by hash, never by input, and made only for validated builds, from a capped queue.
- Supply chain: lock files committed; `composer audit` and `npm audit` clean; Dependabot on; every GitHub Action pinned to a commit SHA; a CycloneDX SBOM and a SHA-256 for each release; a secrets scan in CI; no third-party PHP package at run time (if one becomes necessary, it is prefixed with Strauss and recorded in `DECISIONS.md`).
- The updater downloads only over HTTPS from the agency's releases repository and checks the zip's SHA-256 against the manifest.

**Checked automatically:** PHPCS's escaping, sanitising and nonce sniffs at zero; the grep gates; the capability matrix tests; the IDOR tests; the route's schema refusals and 429; the dependency audits; the secrets scan.

**Checked by hand:** `docs/SECURITY.md`, walked before every tag, each item ticked with the test that proves it.

**Blockers look like:** an unescaped echo; a route without a permission callback; a price read from the request; another customer's build readable.

### 4. Privacy and data handling

**What it means here**

- `docs/PRIVACY.md` keeps the data inventory: every table, option, meta key, transient, file, log line, cookie and browser storage key the plugin writes. For each: whether it's personal, why it's kept, how long, who can see it, and how it's exported and erased. A milestone that stores something new updates the inventory in the same pull request.
- Personal data in 1.0 is only saved builds (a customer's ID, their picks and the name they give it). The rate limiter keeps a salted hash of the address for its one-minute window, never the address. Logs hold no names, emails or addresses.
- WordPress's privacy tools: a personal data exporter and eraser for saved builds, suggested privacy policy text (`wp_add_privacy_policy_content()`), and a deleted account's saved builds deleted with it.
- Share links carry part IDs, the step and assembly on or off, never a customer, an order or a saved build's ID.
- The plugin sets no cookies of its own and sends nothing to anyone. It fires DOM events a shop's own analytics may listen to; consent for that is the shop's.
- Privacy by default, as Quebec's Law 25 asks and PIPEDA's principles expect: nothing that identifies or profiles a shopper is on unless the shop turns it on.
- After 1.0, the custom graphic module keeps uploads private, out of the Media Library, seen by staff alone, and deletes them on the retention the owner sets.

**Checked automatically:** the exporter and eraser tests; the account-deletion test; a browser test that the builder sets no cookie of its own and makes no request to another host; a test that log lines written during the suites contain no email address or IP address.

**Evidence:** `PRIVACY.md` current, with the tests beside each line.

### 5. Accessibility

**What it means here.** WCAG 2.2 AA everywhere a shopper or an admin looks, using WAI's multi-page form pattern and the ARIA Authoring Practices, plus the spec's own requirements:

- Native radio buttons in a fieldset per slot; focus to each step's heading; the page title and heading name the step; one polite status region, in the page from the start, for the total and fit changes; an error summary that takes focus.
- Distinct accessible names on every control ("Choose Independent 149 trucks", never seven buttons called "Add").
- The phone bar never covers the focused control (2.4.11). Targets of at least 24 px (2.5.8). Every drag has a button alternative (2.5.7). Picks carry from step to step, so nothing is entered twice (3.3.7).
- The picture has a written description that changes with the build, and a change is announced once, not once per layer.
- Reflow at 320 px (1.4.10), text spacing (1.4.12), Windows forced colours, 200 % zoom, reduced motion, and dark and light themes all work.
- Every admin screen is complete by keyboard and passes axe.

**Checked automatically:** axe with the WCAG 2.0, 2.1 and 2.2 A and AA rules on every step and state, every cart and every admin screen; a keyboard-only build from the first step to a placed order; focus after each step change; the status region's text after each pick; target sizes measured; reflow at 320 px; forced colours emulated; no animation running under reduced motion; the text-spacing override applied with nothing clipped.

**Checked by hand:** the scripted pass in `docs/A11Y.md` with VoiceOver on Safari (macOS and iOS) and NVDA on Firefox, before every release, by someone who did not write the code.

### 6. Internationalisation

**What it means here.** As the spec's French section says, and:

- Every string through the text domain, with context where a word needs it, `_n()` for counts, numbered placeholders with translator comments, and no sentence built from pieces.
- The domain returns message codes and values, never English. Words are added at the edge, in the page's language.
- Numbers, units, dates and prices through the locale's formatters: `number_format_i18n()`, the unit patterns, `wp_date()`, `wc_price()`.
- The script carries no strings. They arrive with the markup, in `wp_interactivity_state()` and in the state route's answers.
- The owner's own words (slot labels, preset names, the lead time, the returns notice) pass through filters a translation plugin can answer.
- Logical CSS properties throughout; French and right to left tested at phone width, where text runs longest.

**Checked automatically:** WPCS's I18n sniffs; `composer i18n:check` (the POT current at every milestone, fr_CA complete from milestone 9); the browser suite once in fr_CA and once right to left.

### 7. SEO

**What it means here**

- The builder's clean URL (Skatehouse's /build-a-complete/) is an indexable, fast page with real content in the HTML: one H1, an intro, the first step's options with names and prices, and links to the parts' own pages.
- Builder state never becomes a crawl trap. Picks, filters, Show more and presets are buttons in GET forms, so crawlers that don't submit forms never enter the builder's states. Every state travels in the `cbuild[…]` parameters, written in slot order. Pages that carry them get `noindex, follow` through `wp_robots` and a canonical link to the clean URL. The only links into states, the done steps and the review's Change links, carry `rel="nofollow"`. There's no robots.txt line, so shared builds can still unfurl (D-017).
- A link naming parts that are gone still opens the builder, says which part is gone, and offers the closest fit, under `noindex`.
- The kit itself stays out of the catalogue, search results, feeds and sitemaps (WordPress's own and Yoast's), and prints no product markup, since its own price is only the assembly fee.
- A shared build link unfurls as that build: its composed picture, its name and its total. `og:url` is the share link, so the card shows that build; `rel=canonical` is the clean page, so search engines index one page. Both go through the SEO plugin's filters when one is active (Yoast's on Skatehouse) and are printed by the plugin when none is.
- Core Web Vitals count for search too (see Performance).

**Checked automatically:** browser tests for the robots meta, canonical, nofollow links, sitemap exclusion and missing product markup; one H1 on the clean page; Lighthouse's SEO category on the clean page.

### 8. Performance

**What it means here.** The spec's budgets, plus:

| Budget | Floor | How it's measured |
| --- | --- | --- |
| State route, server time | p95 under 80 ms with 500 candidate parts | Benchmark group, generated catalogue, cold and warm, with and without a persistent object cache |
| Add a 7-part build to the cart | Under 300 ms | Benchmark group |
| Queries | A fixed number per page and per route, whatever the number of options | Integration tests count every query |
| The builder's own script module | Under 15 kB compressed with Brotli at quality 11, as a shop's server sends it (the Interactivity API's runtime is shared and not counted) | `npm run budget` |
| The builder's styles | Under 10 kB compressed, measured the same way | `npm run budget` |
| Builder page, lab | LCP 2.5 s or less, CLS under 0.1 (0 is the target), TBT under 200 ms, on Lighthouse's mobile profile, median of three runs | `npm run lhci` |
| A pick | The pick's own selected state painted within 100 ms on a 4× slowed CPU; the total follows when the route answers | Playwright, Chromium with CPU throttling |
| Composed picture | Made off the request, in Action Scheduler; a page never waits on it | Integration test |

- The picture's deck layer is the page's largest paint: explicit width and height, `fetchpriority="high"`, AVIF or WebP at the size shown, never lazy-loaded. Layers below the first screen load lazily.
- Nothing loads on pages without a builder.

**Evidence:** `docs/BENCHMARK.md`, updated each milestone with the numbers and the machine they ran on.

### 9. Testing

**What it means here.** The spec's floors and suites, and these rules:

- The test cases written into the spec are written as tests first, before the code that passes them.
- A bug gets a failing test first; a fix gets a deliberate-break run.
- The invariant holds: across seeded random builds over a generated catalogue, whatever the state route offers, the cart's validation accepts, and whatever it blocks, validation refuses.
- Contract tests keep PHP and JavaScript honest: every state route answer is validated against its published JSON Schema, and the TypeScript types are generated from that schema.
- Visual snapshots of the builder's main states on Kadence, Storefront and Twenty Twenty-Five, in dark and light, catch layout regressions. Run in CI's Linux container only; baselines change only on purpose.
- Tests are deterministic: time from the Clock, randomness seeded and printed on failure, no network.
- A flaky test is a bug. It is fixed or quarantined with an issue the same day, never retried into green.
- No test is deleted or weakened to make a run pass, and no floor is lowered without Zach.

**Evidence:** the gates table in each review, with numbers: tests, coverage, MSI, runs per engine and width.

### 10. Upgrades and compatibility

**For code that builds on the plugin.** Skatehouse Core, Creative Checkout, Mature Content, Shipment Tracking, Reviews, Creative Loyalty and a shop's own code all build on Creative Skateboard Builder.

- The public API is listed in `docs/HOOKS.md`, each item with `@since`:
  - hooks (`cbuild_*`);
  - REST routes and their response shapes (`cbuild/v1`);
  - the abilities;
  - the block's name and attributes;
  - WP-CLI commands and flags;
  - settings keys and option names;
  - product and order item meta keys and the JSON document schemas;
  - documented CSS classes and custom properties;
  - DOM events.
- The Interactivity API store is private (locked), so nothing can come to depend on it.
- From 1.0, Semantic Versioning holds:
  - No minor or patch release breaks the public API.
  - REST responses only gain fields. A removal or rename means a `v2` namespace, with `v1` kept until the next major release.
  - Hooks are never renamed or removed in a minor release. They are deprecated with `apply_filters_deprecated()` or `do_action_deprecated()`, which call `_deprecated_hook()`. Functions and methods use `_deprecated_function()`, arguments `_deprecated_argument()`. A deprecated item is kept until the next major release, and for at least six months.
  - Settings and meta keys are never renamed in place. A migration copies them, and the old key stays readable until the next major release.
  - Block attributes keep working through the block's `deprecated` entries.
- Before 1.0, the API may still change between milestones, but every change is in the changelog and `HOOKS.md`, because companion releases build on the hooks as they land.
- `composer api:check` compares the API with the committed snapshot. Any difference fails unless the same commit updates the snapshot (`composer api:snapshot`), `docs/HOOKS.md` and `CHANGELOG.md` (under Added, Changed, Deprecated or Removed). From 1.0, removing something public needs a deprecation first.
- The snapshot comes from `tools/api-surface.php`, which reads:
  - the hook names from `src/`, with PHP's tokenizer: literal `cbuild_` names in `apply_filters`, `do_action` and their deprecated forms;
  - the block's attributes from `block.json`;
  - the settings keys from the settings class;
  - the DOM events and query parameters from their constants.

  An integration test adds the REST routes' schemas and the abilities from a booted WordPress.

**Upgrades.**

- Upgrading from every earlier tag works with no manual step. Migrations are small, ordered and idempotent. Each one is recorded once it's done.
- Migrations take a lock that expires, so two requests never run the same one and a crashed one never blocks for good.
- Long migrations run in Action Scheduler batches with a saved cursor.
- The shop keeps working while a migration runs.
- A half-finished migration or reindex recovers cleanly. A test stops one after batch N, runs it again, and gets the same result as an uninterrupted run, with no duplicate rows.
- Within a major release, migrations only add. Nothing is dropped or renamed until the next major, so rolling back one minor release leaves a working shop.
- Uninstalling, then installing again, works.

**The platform.**

- The floors are PHP 8.5, WordPress 7.0 and WooCommerce 11.2. The matrix tests the oldest pair (7.0 with 11.2.0) and the newest (7.1.5 with 11.3.0, D-139, D-235), on MySQL 8.4 and MariaDB 10.11, each run as its own database server for the leg, as Creative Search's `tools/matrix-db.sh` does. MySQL 8.0 and MariaDB 10.6 reached end of life in 2026.
- HPOS is tested on, off and syncing; the Cart and Checkout blocks and a classic checkout; the Interactivity API Mini-Cart.
- Themes: Kadence, Storefront and Twenty Twenty-Five.
- Page caches, and a persistent object cache: the leg runs on a Redis server with Redis Object Cache's drop-in, so two processes share it (`tools/object-cache.sh`, D-066).
- Multisite: the plugin activates per site.
- The agency's other plugins, and Yoast SEO.
- Zero PHP deprecations. PHPUnit fails on them.
- The weekly canary runs WordPress trunk and WooCommerce's pre-release.
- Compatibility is declared:
  - true for `custom_order_tables` and `cart_checkout_blocks`;
  - false for `product_block_editor`, only if the installed WooCommerce still has it (its beta was retired in 2026).
- The "WC tested up to" header is kept current.

**Evidence:** the upgrade tests from each tag; the API snapshot's diff; the matrix legs; the canary's last result.

### 11. Resilience and error handling

**What it means here**

- Nothing the builder does takes down the page around it. Its render callback is a boundary: it logs an exception, shows the shopper "This builder isn't available right now" and shows an admin the reason.
- Without WooCommerce, or below the floors, the plugin hooks nothing and shows an admin notice.
- The domain throws typed exceptions. The edges turn them into `WP_Error` with codes, the right HTTP status (400, 404, 429) and translated messages.
- The builder degrades rather than breaks:
  - If the state route fails, the form still submits and the server renders the next step.
  - A slow answer shows a pending state after 300 ms, never a flicker.
  - A stale answer is dropped (each request carries a sequence number and aborts the last).
- Data drifts, and every use re-checks it. A part can be deleted, unpublished, sold out, repriced or re-specced after a build was shared, saved or carted. The shopper is told which part changed and what to do.
- Jobs are idempotent and retried with backoff. A failure is logged with its context and shows in Site Health and the Catalogue check.
- Logging goes through `wc_get_logger()` with the source `creative-skateboard-builder`, at the right level, with no personal data.
- Site Health checks the specs index, failed jobs and the Catalogue check, and its Info tab lists the plugin's versions and settings.
- The browser suites fail on any console error or failed request of the plugin's own.

**Checked automatically:** an integration test for each failure listed; a browser test with the state route forced to fail, still finishing a build; the console-error rule.

### 12. UX and content

**What it means here**

- **Baymard.** Every screen is checked against Baymard's guidelines for its kind of page (complex configurations, product lists, the cart, the checkout), each check cited in the review.
- **The deep dive's patterns.** `UX-RESEARCH.md` numbers them, P1 to P12. Each one is either in, with its test, or marked out with a reason. Its list of what goes wrong (W1 to W9) is checked as never done.
- **Copy.** Plain words and sentence case. No em dashes, superlatives or templated constructions. An error names the problem and the fix. Numbers carry their units. Every string is read in English and French. The grep gate flags em dashes and the banned words in the POT file.
- **Motion helps or it isn't there.** It confirms a pick (the card settles, the part slides into place in the picture) and keeps the shopper oriented between steps (view transitions).
  - 150 to 250 ms, easing out.
  - Only `transform` and `opacity` animate.
  - Input is never blocked.
  - Nothing loops, autoplays or moves behind text.
  - Nothing moves under reduced motion.
- **Look.** The builder takes the theme's fonts, colours and spacing through custom properties with preset fallbacks, so it looks right on dark and light themes:
  - text contrast of 4.5:1 and interface contrast of 3:1;
  - it fits a narrow column (container queries) as well as the full width.
- **Walkthrough.** Each milestone with a screen ends with a scripted walkthrough of what it built, with screenshots on desktop and phone, dark and light, English and French. Milestones with admin screens only walk those. From milestone 6 the walkthrough covers:
  - the ways in built so far;
  - a change that breaks later picks;
  - a sold-out part in a preset;
  - the phone bar within one thumb's reach.

  Before 1.0, three people who haven't seen the builder build a board on their own phones while Zach watches.

## The review file

`docs/reviews/M<n>.md`, one per milestone, in the shape Creative Search's reviews use:

```markdown
# Milestone <n> review: <name>

Tag `v0.<n>.0` (`v1.0.0` for milestone 10) · <date> · written by Claude Code; reviewed by spec-reviewer, auditor, then Claude in Cowork.

## Plan
What the milestone builds (from ROADMAP.md), the spec sections it touches, and the risk under each dimension with how it will be covered. Written before the code.

## What was built
Against the plan; any gap named.

## Gates
| Gate | Result |
| --- | --- |
(every gate above, with its numbers)

## The twelve dimensions
| Dimension | Verdict | Evidence |
| --- | --- | --- |
(one row each; "Not touched" with the reason where it applies)

## Findings
| # | From | Severity | Finding (file:line) | Done (the fix, and the test or gate that fails without it) |
| --- | --- | --- | --- | --- |

## Decisions taken
D-numbers added in this milestone, a line each.

## For Zach
Anything that needs his decision, and any should-fix he is asked to defer.
```

## The release audit

`docs/AUDIT-<version>.md` at 1.0 and every release after, in the shape Creative Reviews' `AUDIT-1.0.5.md` uses: a section per dimension with a table of rules and the evidence that holds the code to each, then the open items, each with an owner and a date. Open items from the last audit are carried until closed.
