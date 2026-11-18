# Release audit: 1.0.0

The whole codebase on `m9-hardening`, 18 November 2026. The reviewer was the `auditor` agent, with a fresh context and no part in writing the code, as `docs/AUDIT.md` asks for every release from 1.0. The milestone's own review is `docs/reviews/M9.md`, and the gates' numbers are there.

Each table gives a rule of the dimension and the evidence that holds the code to it. "M8" marks evidence from 0.8.0's review where the gate was not run again for this audit. Each of those gates runs again in the full CI run of the pull request.

## 1. DRY and SOLID

| Rule | Evidence |
| --- | --- |
| One fit engine in `src/Domain`, called by the route, the cart, the no-JS form, WP-CLI and the abilities | deptrac: "Violations 0, Uncovered 0". The gate "WordPress calls in the domain" is clean. `Fit\BuildValidator` and `Fit\FitEngine` are the only places fit is decided. |
| The browser never decides fit or price | Gate 14 (no markup in the script) and gate 15 (no words in it). The store reads `view` only. `CartPriceTest`, `CartInvariantTest`. |
| One template per piece, re-rendered by the store | `templates/builder/*.php`, with `data-wp-*` directives only; gate 14. |
| One price path | `wc_get_price_to_display()` in `WooKits` and `WooPartCards` only. The cart's prices are WooCommerce's. |
| One writer per table | `SavedBuildRepository`, `SpecIndexRepository`, `PartFactsRepository`; gates 1 and 1b are clean. |
| Ports for the catalogue, stock, prices, clock, cache and logger | `src/Port/*`. PHPStan at level 9, and level 10 on the domain: "[OK] No errors". |
| No rule or message repeated | The bar's estimate reads the summary's own amount (`BuilderTest`). Undo reuses the cap's sentence and `SavedBuilds`' write. |
| Long classes looked at again | `BuilderView` (1,348 lines), `BuildTab` (1,150), `PartSpecsPanel` and `KitEditor`: open item O11. |

## 2. Anti-patterns

| Rule | Evidence |
| --- | --- |
| No logic in hooks | Each Hookable hands its work to a service through a closure (`SavedBuildHooks`, `InstallHooks::networkFile()`). |
| No SQL on WordPress's or WooCommerce's tables | One recorded exception, D-202: `ProductMetaRepository` primes `wp_postmeta` into WooCommerce's cache with its public `prime_raw_meta_data_cache()`. The options rows of the upgrade lock (`MigrationRepository`). Orders go through CRUD only. |
| No unbounded queries | No `-1` limits in `src/`. `SavedBuildRepository::of()` has no `LIMIT`, and the cap is checked before the insert: minor 10, open item O16. |
| No query in a loop | Query counts are held on the front end (`SavedBuildsPageTest`: as many queries for twenty builds as for one; `StateRouteBenchmarkTest`). Two bounded admin loops, in `CheckScreen` and `BuildTab`: minor 11, O16. |
| No `admin-ajax`, no jQuery on the front, no inline data scripts or handlers | No `wp_ajax_` hooks. jQuery only in admin scripts. One inline script with no data in it, the ticket's Print (`TicketPage`). |
| No WooCommerce internals | A grep for `Automattic\WooCommerce\Internal` is empty (gate 7). |
| No `date()`, `time()` or `NOW()` | Gate 3 is clean. Undo's kept dates are parsed as the repository parses its rows. |
| No outbound requests but the updater | Gate 5 is clean. |
| No `catch (Throwable)` that swallows | At the boundaries only: the render boundary, the migration runner, the index jobs and the import, each logged. |
| No `!important`, no IDs, no styles that leak | Recorded exceptions: `BuildTab` (DECISIONS.md, the Build tab's notice) and `PageHooks` (the theme's skip link). The 1.0.0 focus ring wins by specificity, with no `!important` (D-229). AUDIT.md's "none, anywhere" should name these: O15. |

## 3. Security

| Rule | Evidence |
| --- | --- |
| A capability and a nonce on every change, each tested three ways | Each admin screen, the Build tab, the Part specs panel, the ticket and My builds' four forms. `SavedBuildsPageTest::test_a_form_without_its_nonce_or_from_no_one_changes_nothing`; Undo with another customer's form, no nonce and a second send (`…test_a_deleted_build_comes_back_with_undo_as_it_was_once_and_by_its_own_nonce`). |
| The public state route is read-only | GET only, with an explicit `__return_true`, an argument schema that refuses extra keys, 429 with `Retry-After`, and `no-store` and `noindex` headers (`StateRouteTest`). |
| Picks are IDs and prices are the server's | `BuildLink::read()`; `CartAddTest`, `CartPriceTest`, `CartInvariantTest`. Undo sends a UUID, and the build it puts back is the session's copy (D-231). |
| Saved builds are their owner's alone | Every statement is scoped by user and UUID (`…test_another_customer_cannot_rename_or_delete_a_build_by_its_uuid_or_a_guessed_one`). |
| Sanitised in, escaped out, `%i`, safe redirects | PHPCS at zero. `wp_safe_redirect()`. `BuilderPageTest::test_markup_in_every_text_the_builder_shows_is_printed_as_text`. |
| CSV limits, the dry run, formulas neutralised | `CsvTest`, `ImportScreenTest`; `is_uploaded_file()` (D-076). |
| Generated files hashed, checked and capped | SHA-256 names, the queue's cap (D-205), `PictureJobTest`. |
| Supply chain | Every `uses:` pinned to a 40-character SHA. gitleaks; CycloneDX SBOMs; the release token in the publish job alone (gate 9: "Release plan self-test: 13 cases"); `composer audit:deps` clean on 18 November; Dependabot. |
| The updater: HTTPS, its own releases, SHA-256 | `UpdaterTest`, `ManifestTest`. |

`docs/SECURITY.md` has the v1.0.0 column of this walk. Some line numbers it cites from earlier releases have moved: minor 7, O17.

## 4. Privacy and data handling

| Rule | Evidence |
| --- | --- |
| The inventory is complete | `docs/PRIVACY.md` now names the three log lines this audit found missing, says the must-use file is written by a network admin alone (D-226), and says the session's Undo copy ends with the session, not at uninstall. |
| Personal data is saved builds alone | The `cbuild_builds` table, and from 1.0.0 the build just deleted, held in the customer's own WooCommerce session for one view of My builds (D-231). The eraser cannot reach another person's session: recorded in D-231, O18 for Zach. |
| The rate limiter keeps hashes, never the address | `StateRouteTest::test_nothing_kept_for_the_limit_names_an_address`, `RateLimitTest`. |
| Exporter, eraser, policy text, a deleted account | `PrivacyTest`, on a single site and on a network. |
| Share links hold no customer | `ShareCardTest`: `cbuild_saved` is never in `og:url` or the canonical. |
| No cookie, no third-party request | No `setcookie`, `document.cookie` or `localStorage` in the code; the browser test S5 (M8). |
| Logs hold no personal data | Log contexts are IDs, codes, a random UUID and a file's base name. |

## 5. Accessibility

| Rule | Evidence |
| --- | --- |
| A fieldset a slot, focus to the step's heading, one status region, the error summary | `builder.spec.ts` (M8: 746 passed on the newest leg). |
| Distinct names | Each row's controls are named for the build (`SavedBuildsPageTest`). Undo is "Undo deleting Street", and an unnamed build gets the list's name for it (`…test_undo_names_an_unnamed_build_as_the_list_did_and_is_offered_on_the_next_view_alone`). |
| The phone bar never covers focus | "never covers the control in focus", for a blank build and with the bar giving the estimate, on three engines (D-230). |
| Focus seen on dark and light | `focus.spec.ts`: 3:1 against the page on the demo's dark palette, in Chromium and Firefox; the visual baseline `kadence-demo-dark-desktop-card-focus.png` (D-229). |
| Forced colours | The outline is solid in that run (`builder.spec.ts`), and Highlight in the stylesheet. The test does not read the colour: minor, O16. |
| 24 px targets, reflow, text spacing, reduced motion | `builder.spec.ts`, the Motion and reflow suites (M8). |
| Admin screens by keyboard and axe | `workshop.spec.ts`, `check.spec.ts`, `import.spec.ts` and the others (M8). |
| Undo | Pressed by keyboard, with axe on the page that offers it, and focus on WooCommerce's notice after, in seven projects, `no-js` among them. |
| The manual pass, with VoiceOver and NVDA | Owed: the 1.0.0 row of `docs/A11Y.md`, by a tester Zach arranges (O2). |

## 6. Internationalisation

| Rule | Evidence |
| --- | --- |
| Every string through the text domain, `_n()` for counts | WPCS's I18n sniffs at zero. Four counts without `_n()`: minor 3, O16. |
| The domain returns codes | The gate "words in the domain" is clean. |
| Locale formatters | `number_format_i18n()`, `wc_price()`, `Words::measure()` with a real no-break space. `i18n-check` refuses an escape in a msgid (D-228). |
| The script carries no strings | Gate 15. |
| fr_CA complete | `composer i18n:check`: "i18n: 769 strings, the POT current, 1 translation(s) complete", and it fails with no fr_CA (D-233). Gate 8b reads the translation. Read by a person before the tag: O1. |
| Logical CSS, right to left | The `rtl` and `fr-ca` projects (M8); the RTL stylesheet is built. |

## 7. SEO

| Rule | Evidence |
| --- | --- |
| The clean page can be indexed, with one H1 | Lighthouse SEO 0.92 on Twenty Twenty-Five and Kadence (M8), against a floor of 0.9. |
| States are GET forms, with nofollow on state links, and noindex and a canonical on states | `SeoHooks`; each state link carries `rel="nofollow"`; `ShareCardTest`. |
| The kit is kept out of sitemaps and feeds, with no product markup | `composer test:integration:yoast`, five tests (M8). |

## 8. Performance

| Rule | Evidence |
| --- | --- |
| The route's p95 under 80 ms at 500 candidates | `docs/BENCHMARK.md`, v1.0.0. |
| Fixed query counts | `SavedBuildsPageTest`'s twenty against one; `StateRouteBenchmarkTest`. |
| The script under 15 kB and the styles under 10 kB | `npm run budget` on 18 November: `view.js` 3,027 B, `style-index.css` 2,987 B. |
| LCP, CLS, TBT | `docs/BENCHMARK.md`, v1.0.0. The TBT flake on Twenty Twenty-Five is #52, O5. |
| Nothing on pages without a builder | The cart's styles only with a build in the cart, the account's on account pages only (`ShowHooks`, `SavedBuildHooks`). |

## 9. Testing

| Rule | Evidence |
| --- | --- |
| The domain's floors | 100 % of lines (1,162 of 1,162) and of branches (1,145 of 1,145) on 18 November. |
| 95 % of `src/`, both suites merged | `docs/reviews/M9.md`, the gates table. |
| Infection at 100 % and 95 %, Stryker at 90 % | `docs/reviews/M9.md`, the gates table. |
| Invariant and contract tests | `CartInvariantTest`; the answers checked against the schema; `npm run types`. |
| Visual snapshots | Three new baselines, made on purpose and committed with their tests (D-229, D-230). |
| A flaky test the same day | #36 and #51 have their causes handled in the tests; #49, #50 and #52 have not happened again on this branch, and stay open with what is owed (O5). |
| The update from every tag | `npm run upgrade:all`: every published release, with its own kits, parts and saved build, read back (D-232). An order holding a build from an old release's checkout is not made: O7. |
| The spec's cases tested | Undo's cases (a part gone, a second send, a full account, an unnamed build), each red first (D-231). |

## 10. Upgrades and compatibility

| Rule | Evidence |
| --- | --- |
| The public API listed with `@since`, and the snapshot matches | "API surface: 232 items match the snapshot, docs/HOOKS.md and CHANGELOG.md." The freeze is stated at the head of `docs/HOOKS.md`. |
| The store is private | D-148. |
| Semantic Versioning from 1.0 | Nothing removed or renamed in the milestone: the snapshot gains `--cbuild-focus-contrast` alone. |
| Migrations are idempotent, locked and resumable | `MigrationTest`, `MigrationRunnerTest`. The renditions migration has no cursor: O6. |
| The update from every tag | D-232; the newest leg in CI. |
| The platform matrix, HPOS, multisite, the object cache, Yoast | M8: 585 tests a leg, and every variant. |
| No deprecations | PHPStan's deprecation rules are clean. |
| The canary green | The fix for trunk's attribute order is on this branch (D-227); the next scheduled run after the merge shows it (O3). |
| WooCommerce tested up to the current release | "WC tested up to: 11.3"; "Tested up to: 7.1". |

## 11. Resilience and error handling

| Rule | Evidence |
| --- | --- |
| The render boundary | The builder logs once an hour and says "This builder isn't available right now", with the reason for managers (`BuilderPageTest`). |
| Typed exceptions become `WP_Error` | The state route's 400, 404, 429 and 500. |
| It degrades without the route | `builder.spec.ts`, with the route made to fail (M8). |
| Drift is checked again | Saved builds when listed and opened; the cart judged again (`CartLinesTest`). Undo keeps a drifted build and does not lose it (D-231). |
| Jobs are idempotent, logged and in Site Health | `IndexJobs`, `SiteHealthTest`. |

## 12. UX and content

| Rule | Evidence |
| --- | --- |
| Patterns P1 to P12, failures W1 to W9 | 0.8.0's review; Cowork's F1 to F6, done in this milestone. |
| The copy rules | Gate 8 on the POT and 8b on the translation. The new strings are plain: "Complete from about %s", "The build, as drawn", "No specs", "That build can no longer be brought back." |
| Undo behaves as an undo | The build comes back as it was, once, named for itself (D-231). |
| The hallway test | Owed (O4). |

## Open items

| # | Item | Owner | When |
| --- | --- | --- | --- |
| O1 | French read by a person | A reader Zach arranges | Before the tag (D-225) |
| O2 | The manual pass of `docs/A11Y.md`, steps 1 to 14, with VoiceOver on Safari and NVDA on Firefox | A tester Zach arranges | Before the tag |
| O3 | The canary green on trunk (#43) | Claude Code | The first scheduled run after the merge |
| O4 | The hallway test: three people new to the builder, on their own phones | Zach | Before the tag |
| O5 | Flaky tests: #49 (a pick painted in 144 ms), #50 (WebKit's early submit), #51 (the session written at shutdown), #52 (TBT on Twenty Twenty-Five) | Claude Code | Each the day it happens again |
| O6 | The renditions migration has no cursor (0.8.0's review, auditor 19) | Claude Code | Measured in `docs/BENCHMARK.md`; a cursor in 1.0.x if it is over budget |
| O7 | No order holding a build from an old release's own checkout in the update runs (0.8.0's review, auditor 20) | Claude Code | 1.1 |
| O8 | Firefox's axe crash on the error summary (#36), quarantined | Claude Code | When Playwright or axe-core moves |
| O9 | Sign in goes to WordPress's login, not WooCommerce's (0.8.0's review, auditor 18) | Zach to decide | 1.1 |
| O10 | The board's turn is said by its caption alone (0.8.0's review, auditor 16) | The screen reader tester (O2), then Claude Code | With O2 |
| O11 | Classes over 300 lines (`BuilderView`, `BuildTab`, `PartSpecsPanel`, `KitEditor`) | Claude Code | 1.1 |
| O12 | #59: the demo's emails, readable for a review | Zach, on the demo | Before Cowork's 1.0.0 review |
| O13 | Earlier reviews' "For Zach": a carrier plugin's $0 kit line, the Stripe and WooPayments buttons, Shop Kit's checkout and template, Venture 5.0's 7.62 in axle, the sources for the Thunder 143, 148 and 161 | Cowork on the demo; Zach for the sources | Cowork's 1.0.0 review |
| O14 | The dev toolchain's low `deepmerge` advisory, with no fix (#9, D-051) | Claude Code | Watched |
| O15 | AUDIT.md's dimension 2 to name its recorded exceptions | Zach to approve the wording | 1.0.x |
| O16 | Minors listed in `docs/reviews/M9.md`: counts without `_n()`, the saved builds read with no `LIMIT` and the cap checked before the insert, two bounded admin loops, the forced-colours test not reading the colour, a pressed filter's inset line under the focus ring | Claude Code | 1.0.x |
| O17 | `docs/SECURITY.md`'s line numbers from earlier releases | Claude Code | 1.0.x |
| O18 | The eraser does not reach a session's Undo copy, held for one page view (D-231) | Zach to confirm | Before the tag |
| O19 | The spec's grip sheets sized "9 × 33 in" in one attribute, which the mapping cannot split (spec review S4) | Zach to decide | Before the tag |
| O20 | A preset's tile shows its deck's photo, not the preset's board (spec review S5; D-154 said milestone 7) | Zach to decide | Before the tag |
