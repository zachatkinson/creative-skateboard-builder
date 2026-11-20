# Release audit: 1.0.0

The whole codebase on `m10-configurator` at 0448968, 19 November 2026. The reviewer was the `auditor` agent, with a fresh context and no part in writing the code, as `docs/AUDIT.md` asks for every release from 1.0. The milestone's own review is `docs/reviews/M10.md`. "Not run here" marks a gate running elsewhere at the same time; its numbers go into the review from that run.

## 1. DRY and SOLID

| Rule | Evidence |
| --- | --- |
| One fit engine in `src/Domain`, called by every edge | deptrac: "Violations 0, Uncovered 0, Allowed 3434". The gate for WordPress calls in the domain is clean. From 1.0.0 `Service\BuildState::cleared()` (src/Service/BuildState.php:272) sends the wheels' and risers' options through `Domain\Fit\RiserClearance`, so the route, the link and the shelf hide the same parts (D-245). |
| The browser never decides fit or price | Gates 14 and 15 are clean. The store is locked (`assets/src/builder/view.ts:769`, `{ lock: true }`). `currency` on the route only says how the shop writes a price, for milestone 11. |
| One template per piece | `templates/builder/*.php` use directives only. Gate 14 is clean. |
| One price path | `wc_get_price_to_display()` is in `WooKits` and `WooPartCards` only. |
| One check, one set of sentences, for a layer image | `Service\LayerCheck` and `Admin\Sentences::layer()` serve both the Part specs field and the CSV (D-287). |
| No rule written twice | Open (minor): "a variation's own layer image, else its parent's" is now written in four places: `WooPartCards.php:371`, `WooPartLooks.php:171-176`, `WpLayerImages.php:187-197` and `PartSpecsPanel.php:419-427`. `Shelf::MOST` and `BuildLink::MOST` are two constants for the same 500 (`src/Domain/Look/Shelf.php:58`, `src/Front/BuildLink.php:64`). |
| Long classes | Worse than 0.9.0. `BuilderView` has 1,934 lines (1,348 at 0.9.0), `BuildTab` 1,258 (1,150), `StateAnswer` 997, `KitEditor` 760, `PartSpecsPanel` 736. See O11. |

## 2. Anti-patterns

| Rule | Evidence |
| --- | --- |
| No logic in hooks | Hookables hand their work to services. `StartButton` now registers on the storefront only (`Container.php:522`). |
| No SQL on WordPress's or WooCommerce's tables | Gates 1 and 1b are clean. The one recorded exception is still D-202. `WooPartLayers::named()` asks through `WP_Query` with a `meta_query`, with no SQL of its own. |
| No unbounded queries | No `-1` limits: a grep of `src/` finds none. `SavedBuildRepository::of()` still has no `LIMIT` (`src/Repository/SavedBuildRepository.php:51`). See O16. |
| No query in a loop | Open. Each specs import row with a `layer_image` cell runs a `WP_Query`, a full image decode and a resolve (`SpecImport.php:504-520`). The dry run plans every row in one request (`ImportScreen.php:192`). Should-fix, P1. `WooPartCards::layer()` reads a parent product per card (`:371-378`): minor. |
| No `admin-ajax`, no jQuery on the front, no inline data scripts | No `wp_ajax_` hooks. |
| No WooCommerce internals | Gate 7 is clean. `FeaturesUtil` is a public utility. |
| No `date()`, `time()` or `NOW()` | Gate 3 is clean. Undo's window is read from the Clock (`SavedBuildHooks.php:380`). |
| No outbound requests but the updater | Gate 5 is clean. |
| No `catch (Throwable)` that swallows | Eight catches, in five files: `MigrationRunner`, `IndexJobs`, `BuilderPage`, `SpecImport` and, from 1.0.0, `WpLayerRenditions.php:199`. Each logs. |
| No `!important`, no IDs, no leaking styles | A grep of `assets/src` finds no `!important`. AUDIT.md still says "none, anywhere": O15 is unchanged. |

## 3. Security

| Rule | Evidence |
| --- | --- |
| A capability and a nonce on every change | Unchanged since 0.9.0. The new CSV column writes only through the import, which keeps its capability, nonce and sender checks. `WooPartLayers::assign()` saves through the product's CRUD. |
| The state route is public and read-only | `__return_true` at `StateRoute.php:127`, `Retry-After` at `:254`, `no-store` and `noindex` at `:398-399`. `from` has a schema maximum (`:220`). |
| Picks are IDs and prices are the server's | `BuildLink::read()`. The `reopened` panel is built from the signed-in user's own build (`Builder.php:102`, `SavedBuilds::reopened( get_current_user_id(), … )`). |
| Saved builds are their owner's alone | Every statement in `SavedBuildRepository` is scoped by user and UUID. Another user's `_saved` UUID falls back to a plain link (`Builder.php:104-105`). |
| Sanitised in, escaped out | PHPCS reports nothing. The new `aria-label` is escaped (`templates/admin/build-tab.php:199`). The block's wrapper attributes go through the HTML API (`PageHooks.php:921-934`). |
| Direct-access guard on every file outside the domain | Open (minor). `src/Front/BuildLink.php`, `src/Service/OrderBuilds.php` and `src/Service/BuildAgain.php` have no `ABSPATH` guard, and have had none since before 0.9.0. They only declare classes, but D-063 (DECISIONS.md:717) says "Every other file in `src/` keeps its guard". |
| CSV limits, dry run, formulas neutralised | Unchanged. The new column is held to the Layer image field's rules. |
| Supply chain | Every `uses:` is pinned to a 40-character SHA: a grep for unpinned lines finds none. `gh api …/actions/permissions` shows `"sha_pinning_required":true` and `"allowed_actions":"selected"`, with patterns gitleaks, ramsey and shivammathur. The audits are clean today. |
| The updater | `Updater.php:31` and `:231` (`hash_equals`); `Manifest.php:27`. |

## 4. Privacy and data handling

| Rule | Evidence |
| --- | --- |
| The inventory is complete | Mostly. Every name in `Support\Options` appears in PRIVACY.md (cross-checked by script), and `look_<part>` was added with the code. Gaps: the rendition log line now also holds the editor's error text (`WpLayerRenditions.php:248-256`; PRIVACY.md:61 does not say so). The Action Scheduler rows of `cbuild_compose_picture` (arguments: kit and part IDs, `ComposedPictures.php:180-186`) and `cbuild_prune_pictures` have no row of their own. Both minor. |
| Personal data is saved builds alone, kept as stated | Fails. PRIVACY.md:13 says the Undo copy "goes at … 15 minutes after the Delete". The code only stops offering it after 15 minutes (`SavedBuildHooks.php:380` returns null and drops nothing). The copy stays in the WooCommerce session until the next My builds view (`:413-415`), sign-out or the session's expiry (48 hours), and the eraser cannot reach it. Should-fix, P2. |
| The rate limiter keeps hashes | Unchanged since 0.9.0. |
| Exporter, eraser, policy text, a deleted account | `PrivacyTest` (integration, not run here). |
| Share links hold no customer | Rebuilt links drop `_saved` (`Builder.php:105`, `:107`). `ShareCardTest`. |
| No cookie, no third-party request | No `setcookie`, `document.cookie`, `localStorage` or `sessionStorage` in `src`, `assets/src` or `templates`. Gate 5. The browser test S5 was not run here. |
| Logs hold no personal data | Every log call site was read (15 of them). Contexts are IDs, codes, a UUID, a base name and error text. The search query `q` is never logged. Creative Search logs it under the source `builder` (`CreativeSearchRanking.php:54`), as PRIVACY.md:7 says. |

## 5. Accessibility

| Rule | Evidence |
| --- | --- |
| Distinct names | Width chips are named "8.25″, 12 in stock" and filter chips "Independent, 4 parts" (`BuilderView.php:908`, `:863`; `step.php:37`, `:77`). Each preset's photo button is "Choose a photo for %s" (`BuildTab.php:676-680`). Open (minor): on a variable product the Part specs panel gives each variation's button the same name, "Choose an image" (`PartSpecsPanel.php:451`, `templates/admin/part-specs.php:109`). |
| Focus never covered (2.4.11) | `bar.ts:146-174` keeps `--cbuild-stage-size`, and `view.ts` `reveal()` scrolls focus clear of the stage and the bar. The Vitest case "sets the stage's foot on the page while it sticks". Browser runs: not run here. |
| The search's Enter | The default button is out of sight, `tabindex="-1"` and `aria-hidden="true"` (`step.php:12-17`, `style.scss:204`). |
| One announcement per count | The duplicate count at the head of the step was removed (`step.php:117`). |
| The manual pass, VoiceOver and NVDA | Owed: the A11Y.md:179 row reads "The tag waits for this row". O2. |

## 6. Internationalisation

| Rule | Evidence |
| --- | --- |
| Every string through the text domain, `_n()` for counts | The I18n sniffs report nothing, and the new counts use `_n()` (`BuilderView.php:852`, `:863`). Still open (O16): "Show %s more" (`BuilderView.php:849`, `:876`), "%s parts" (`SiteHealth.php:167`) and "%1$s of %2$s rows" (`templates/admin/import.php:77`). New, minor: lists of IDs joined with `', '` rather than `wp_sprintf_l()` (`CatalogueCheck.php:215`, `LayerCheck.php:78`). |
| The domain returns codes | Gate 6 is clean. |
| fr_CA complete | Every new string checked (the risers' lead, look_shared, the layer sentences, "Choose a photo for %s", "What it says") is in the POT and translated in the .po. `composer i18n:check` needs wp-env and was not run here. Per D-260 no person reads the French before 1.0.0. |
| Locale formatters | `number_format_i18n( 6.5, 1 )` in `WidthGuide.php:48`. The `currency` answer's `format` keeps `&nbsp;` as an HTML entity while `symbol` is plain text (`ShopMoney.php:65`): minor, but frozen at 1.0. |

## 7. SEO

| Rule | Evidence |
| --- | --- |
| State links nofollow | The start button now has `rel="nofollow"` (`StartButton.php:104`). The remaining `<a>` in `templates/builder` go to anchors, the shop, the cart or product pages. |
| noindex and a canonical on states; the kit out of sitemaps | `SeoHooks`, `ShareCardTest`, `composer test:integration:yoast` (not run here). |
| Lighthouse SEO | Not run here (LHCI). |

## 8. Performance

| Rule | Evidence |
| --- | --- |
| Route p95 under 80 ms | `StateRouteBenchmarkTest` now also times "a search with a chip and a sort". Not run here. BENCHMARK.md has no v1.0.0 section yet. O21 is open. |
| Fixed query counts | Open: the specs import's dry run costs at least one query and one image decode per row with a layer image (P1). `SpecsBenchmarkTest` (`:103`, `:174`, `:195`) has no layer images, so it cannot catch this. |
| Script and styles budgets | `npm run budget` was not run here: it builds, and the build is in use by the browser run. |
| Nothing on pages without a builder | The start button prints markup only and loads no asset. |

## 9. Testing

| Rule | Evidence |
| --- | --- |
| The domain's floors | "100.00% (1582/1582) in src/Domain", "Branches in src/Domain: 1601 of 1601" (today). |
| Unit and JS suites | "OK (1920 tests, 13553 assertions)"; Vitest "199 passed (199)". |
| 95 % of `src/`, Infection, Stryker | Not run here. `WooPartLayers.php` joins Infection's adapter exclusions (`infection.json5:47-49`). |
| The spec's cases | Open: no test reads back an export that holds layer images where two uploads share a file name, or where an image no longer passes (P3). `SpecImportTest::test_an_export_read_back_changes_nothing` uses fakes with neither. |
| Flaky tests | #49, #50, #51, #52 and #36 are still open on GitHub (O5, O8). |

## 10. Upgrades and compatibility

| Rule | Evidence |
| --- | --- |
| The API listed and the snapshot matching | "245 items match". The freeze is stated at HOOKS.md:7. |
| Additions only, each documented | `photo`, `bearings_included`, `currency`, `reopened`, `align`, `--cbuild-stage-size`, `layer_image`, `look_shared`. Each is in HOOKS.md, the snapshot and the changelog. |
| A cached asset of an older release | Gate 16 holds `block.json`'s version to `CBUILD_VERSION`. |
| Old cache entries | A card kept without `photo` is read again (`WooPartCards.php:419-421`). |
| Migrations idempotent and resumable | Unchanged. O6 is open. |
| The canary | Run on `main`, 19 November 07:50 UTC: "success". O3 can close; issue #43 is still open. |
| Headers and declarations | "Version: 1.0.0", "WC tested up to: 11.3", "Tested up to: 7.1", `custom_order_tables` and `cart_checkout_blocks` declared (creative-skateboard-builder.php:83-84). |
| The update from every tag | `tools/upgrade.sh:149` now also checks that an old saved build reopens with no prices from then. Not run here. |

## 11. Resilience and error handling

| Rule | Evidence |
| --- | --- |
| Rendition failures don't fail the job | A throw is logged as a rendition not made (`WpLayerRenditions.php:199-201`, D-286). Minor: a throw from `set_quality()` or `resize()` is logged with step `save`. |
| The render boundary | Unchanged (`BuilderPage.php:224`). `PageHooks::wrapped()` returns the HTML as it was when there is no `.cbuild` root (`:924-926`). |
| Drift checked again | A changed saved build opens on "Your build has changed" (D-254). |
| A slow answer while typing | `Navigator::drop()` (`navigate.ts:136-147`) and its Vitest case. |

## 12. UX and content

| Rule | Evidence |
| --- | --- |
| Copy rules | Gate 8 on the POT is clean. New strings are plain: "Your %1$s wheels need %2$s risers with these trucks.", "Total". |
| The import round trip | Open: a re-imported export can refuse rows that nobody changed (P3). |
| Block placement | `align` defaults to `full` (block.json:20-23). Minor: choosing None in the editor clears the attribute, which then reads as its default, `full`, so the content-column choice may not be available. Check in the editor. |
| The hallway test | Owed (O4). |

## Open items

| # | Item | Status on 19 November | Owner | When |
| --- | --- | --- | --- | --- |
| O1 | French read by a person | Closed by decision: D-260 says no person reads it before 1.0.0; the checks and the fr-ca project hold it | Zach | Done |
| O2 | The manual A11Y.md pass with VoiceOver and NVDA | Open. A11Y.md:179: "The tag waits for this row" | A tester Zach arranges | Before the tag |
| O3 | Canary green on trunk (#43) | Closed in fact: the 19 November run on `main` succeeded. Close #43 | Claude Code | Now |
| O4 | The hallway test | Open | Zach | Before the tag |
| O5 | Flaky tests #49 to #52 | Open: all four issues open | Claude Code | The day each happens again |
| O6 | The renditions migration has no cursor | Open (BENCHMARK.md:256) | Claude Code | 1.0.x |
| O7 | No order from an old release's checkout in the update runs | Open | Claude Code | 1.1 |
| O8 | Firefox axe crash (#36) | Open, quarantined | Claude Code | When Playwright or axe-core moves |
| O9 | Sign in goes to WordPress's login | Open, no decision | Zach | 1.1 |
| O10 | The board's turn said by its caption alone | Open, with O2 | Tester, then Claude Code | With O2 |
| O11 | Classes over 300 lines | Worse: BuilderView 1,934 lines, BuildTab 1,258, StateAnswer 997 | Claude Code | 1.1 |
| O12 | #59, the demo's emails | Open on GitHub. M10.md:15 says there is nothing to build | Zach | Close #59 if done |
| O13 | Earlier "For Zach" items on the demo | Not re-checked here | Cowork, Zach | Cowork's 1.0.0 review |
| O14 | Dev toolchain advisories (`deepmerge`, `picomatch`) | Closed: full `npm audit` says "found 0 vulnerabilities" | Claude Code | Done |
| O15 | AUDIT.md's dimension 2 to name its exceptions | Open: AUDIT.md:98 still says "None of these, anywhere" | Zach | 1.0.x |
| O16 | Minors from M9 (counts without `_n()`, no `LIMIT` on saved builds, admin loops, forced-colours colour, inset line) | Open. The `_n()` and `LIMIT` items were re-checked and are still present | Claude Code | 1.0.x |
| O17 | SECURITY.md's line numbers | Open and drifting: `PartSpecsPanel.php:221`/`:247` are now `:249`/`:275`; `ShareHooks.php:115` is `:150`; `WpLayerRenditions.php:182` is `:270`; `Composition.php:105` still holds | Claude Code | 1.0.x |
| O18 | The eraser doesn't reach the session's Undo copy | Partly closed by D-275's 15 minutes, but the copy is not dropped at 15 minutes (P2) | Claude Code | Before the tag |
| O19 | Grip sheets sized "9 × 33 in" in one attribute | Open, no decision found | Zach | Before the tag |
| O20 | A preset's tile shows its deck's photo | Closed by D-252 (rider photo, else the composed picture) | Done | Done |
| O21 | With Redis, the route's first request after a part moves is over 80 ms | Open. BENCHMARK.md has no 1.0.0 numbers yet | Zach | Before the tag |
| O22 (new) | P1 to P3 below | Open | Claude Code | Before the tag |

Release-audit findings:

| # | Dimension | Severity | Finding (file:line) | Why it matters | Smallest fix | Test or gate |
| --- | --- | --- | --- | --- | --- | --- |
| P1 | 8, 2 | should-fix | `SpecImport.php:504-520` checks every `layer_image` cell: a `WP_Query` with a meta LIKE (`WooPartLayers.php:72-91`), a full GD decode (`WpLayerImages.php:103-117`) and `LayerCheck::size()` (`kits->all()` and `indexer->resolve()`, `LayerCheck.php:572-582`). `ImportScreen.php:192` and `SpecsCommand.php:152` plan all rows, up to 5,000, in one request | Re-reading a 2,000-part export with layer images is thousands of queries and decodes on one admin page view, which will time out on a cheap host | Pass over a cell equal to the part's current image (its `fileName()` or ID) before any lookup or check; check the rest | Add layer images to `SpecsBenchmarkTest::test_two_thousand_parts_are_indexed_exported_checked_and_dry_run_within_a_request_each` |
| P2 | 4 | should-fix | `SavedBuildHooks.php:377-382` only stops offering the copy at 15 minutes; `drop()` runs only on a My builds view or an Undo (`:325`, `:413-415`). PRIVACY.md:13 says it "goes" at 15 minutes | Personal data stays longer than the inventory says, out of the eraser's reach | Call `self::drop()` in `kept()` when the copy is past its window, and drop it early on any signed-in request that holds it; or correct PRIVACY.md:13 | `SavedBuildsPageTest`: the session holds no `cbuild_deleted` after the clock moves 16 minutes and any page loads |
| P3 | 9, 12 | should-fix | `SpecImport.php:512-519` looks up and checks a cell before it compares it with the part's own image (`:519`) | An export read back is refused row by row, specs included, when two uploads share a file name (`layer_ambiguous`; WordPress allows this across months, LayerColumnTest.php:130) or when an image no longer passes the rules | The same short-circuit as P1 | Integration: export a part whose image has a same-name twin, import the file, and no row is refused |
| P4 | 3 | minor | `src/Front/BuildLink.php`, `src/Service/OrderBuilds.php` and `src/Service/BuildAgain.php` have no `ABSPATH` guard | Against D-063 and SECURITY.md A05 | Add the guard | A gate: every PHP file outside `src/Domain` holds `defined( 'ABSPATH' )` |
| P5 | 5 | minor | `PartSpecsPanel.php:451`: every variation's button is "Choose an image" | Controls without distinct names | Add an `aria-label`, "Choose an image for %s", as the presets have | An integration test on the panel's markup |
| P6 | 4 | minor | PRIVACY.md:61 doesn't mention the rendition log's `error` (`WpLayerRenditions.php:248-256`), and the compose and prune jobs have no Action Scheduler row | The inventory is incomplete | Add both to PRIVACY.md | By hand |

## SECURITY.md walk for v1.0.0

The rules:

1. **Holds.** `BuildLink::read()` takes IDs only, and prices come from `wc_get_price_to_display()`. `reopened` comes from the server's own copy of the build.
2. **Holds.** `StateRoute.php:127`, `:254` and `:398-399`, plus the argument schema (the `from` maximum at `:220`).
3. **Holds.** PHPCS reports nothing. No new admin action skips the existing checks: the CSV column rides on the import's.
4. **Holds.** Gates 1 and 1b are clean. `WooPartLayers` uses `WP_Query` and CRUD.
5. **Holds.** Gate 2 is clean.
6. **Holds.** Gate 5 is clean. `Updater.php:31` and `:231`, `Manifest.php:27`.
7. **Holds.** `SavedBuilds::reopened( get_current_user_id(), $uuid )` (`Builder.php:102`), and every statement is scoped by user and UUID.
8. **Holds.** `ComposedPictures::ask()` is capped (`:176-178`). Names are hashes (`Composition.php:105`, `WpLayerRenditions.php:270`).
9. **Holds,** with an inventory gap. Log contexts are IDs, codes, a UUID, a base name and error text. The rendition error text is not in PRIVACY.md.
10. **Holds.** Both lock files are committed. The audits are clean today. Every action is pinned; `sha_pinning_required: true`. gitleaks and the release token in the publish job are held by gate 9 ("Release plan self-test: 13 cases").
11. **Not re-checked:** `UninstallTest` was not run here.

The repository (`gh api`, today):
- **Who can write:** collaborators are `zachatkinson` alone, and `fork-pr-contributor-approval` is `all_external_contributors`. Holds.
- **`main`:** ruleset 27576433, active, bypass `[]`. Rules: `deletion`, `non_fast_forward`, `pull_request` (merge only, review threads resolved) and `required_status_checks` with 12 contexts (7 plus 5 `Browser (…)`). Holds. There is no `required_signatures` rule yet, which SECURITY.md does not claim; D-259 plans to add it.
- **Release tags:** ruleset 27576435, `refs/tags/v*`, rules `deletion`, `non_fast_forward` and `update`, bypass `[]`. Holds.
- **Secrets:** secret scanning and push protection are enabled. Holds.
- **Actions:** `allowed_actions: selected`, GitHub-owned plus the three patterns, `default_workflow_permissions: read`, `can_approve_pull_request_reviews: false`. Holds.
- **Reporting:** private vulnerability reporting `{"enabled":true}`. Holds.

The walk by check:
- **A01:** holds. The `_saved` owner check is at `Builder.php:102-105`; `withLayers` adds no route. Stale citations: `PartSpecsPanel.php:221`/`:247` are now `:249`/`:275` (O17).
- **A02:** holds. `hash_equals` is at `Updater.php:231`. The `WpLayerRenditions.php:182` citation is now `:270`.
- **A03:** holds. PHPCS is clean, and so are gates 1, 1b, 1c and 2. The block wrapper goes through `WP_HTML_Tag_Processor`. The `ShareHooks.php:115` citation is now `:150`.
- **A04:** holds. Clearance is decided on the server (`BuildState.php:110`, `:116`).
- **A05:** partly holds. Three files outside the domain have no guard (P4), so the column's "every new file has the guard" is true for 1.0.0's new files but not for all of `src/`.
- **A06:** holds. Today's audits are clean.
- **A07:** holds. No new state change outside existing nonces.
- **A08:** holds. Gate 16 now also keeps a stale cached asset from being served.
- **A09:** holds, with the PRIVACY.md gap (P6).
- **A10:** holds. Gate 5, and layer images are found by name in the Media Library, never fetched.

## PRIVACY.md walk for v1.0.0

- **L3, kept in the same pull request:** mostly holds. `look_<part>` was added, but the rendition error text was not (P6).
- **L5, no cookie, nothing sent, nothing from another host:** holds by grep and gate 5. The browser S5 test was not run here.
- **L7, search:** holds. `_q` is only in the link (`BuildLink.php:215-216`), `'source' => 'builder'` is at `CreativeSearchRanking.php:54`, and no log call carries the query.
- **L9, session and tax address:** not re-checked here (`CartPriceTest`, integration).
- **L13, saved builds and the Undo copy:** does not hold for "15 minutes after the Delete" (P2). Everything else on the line holds.
- **L15 to L19, export, erase, a deleted account, the customer, the policy text:** not re-run here (`PrivacyTest`, `SavedBuildsPageTest`).
- **L21, order meta:** holds; unchanged.
- **The table rows:** every `Support\Options` name is present. Two rows are inaccurate or missing: L61 (the rendition log now has `error`) and the compose and prune Action Scheduler jobs. L35, `cbuild_deleted`: "Undo; the view … after the one that offered it; the session's end" is accurate, but it doesn't match L13's 15 minutes.
- **L75 to L80, what leaves the site:** holds (gate 5, `Updater.php:31`).
- **L84 to L86, what is left behind:** not re-run here (`UninstallTest`).
- **L90 to L97, who sees it:** holds. `look_shared` is on the same `manage_woocommerce` screen.

## Second pass on M10: `git diff 5913a97..HEAD`

**Blockers:** none.

**Should-fix:**

1. `src/Service/SpecImport.php:512-519`. A `layer_image` cell is looked up and checked before it is compared with the part's own image. The plugin's own export writes a name that can be ambiguous (D-287 refuses those) or an image that no longer passes, so an untouched export read back refuses those rows whole, specs included. LayerColumnTest.php:130-152 shows such a twin can exist but never re-imports an export. Smallest fix: at the top of `layer()`, return `KEEP_LAYER` with no problems when the cell equals `$this->layers->fileName( $owned['own'] )` or `(string) $owned['own']`. Test: an integration round trip with a same-name twin.
2. `src/Admin/ImportScreen.php:192` with `src/Service/SpecImport.php:504-520`. The dry run plans every row in one request, and each layer cell costs a meta LIKE `WP_Query` (`WooPartLayers.php:72-91`), a full image decode (`WpLayerImages.php:103-117`) and a `resolve()` (`LayerCheck.php:579`). This is not bounded per request and the benchmark doesn't cover it. Smallest fix: fix 1 removes the cost for unchanged cells. Then memoise `named()` and `size()` per plan, and add layer images to `SpecsBenchmarkTest`.

**Minor:**

3. `src/Adapter/WooPartLayers.php:35`, `:79-91`, `:262-266`. LIKE `%stem%`, cut at 20 rows by ID, then matched exactly. In a library where more than 20 files contain the stem, the right image can fall past the cut and the row says `layer_not_found`. The comment's "refused as ambiguous anyway" is wrong. Fix: match `_wp_attached_file` with `REGEXP '(^|/)stem(-scaled)?\.ext$'`, or drop the cap and count exact matches.
4. `src/Adapter/ShopMoney.php:65`. `format` is WooCommerce's pattern with `&nbsp;` as an entity, while `symbol` is decoded to plain text. This is frozen API from 1.0. Fix: decode `format` as `symbol` is, and update `schema/state.json`'s description, before the tag.
5. `src/Adapter/WooPartCards.php:371-378`, called at `:135` and `:388`. A variation with no photo and no own layer image reads its parent with `wc_get_product()` per card, twice, unprimed. Fix: collect the parents' IDs and `load()` them with the missed products.
6. `src/Adapter/WpLayerRenditions.php:199-201`. A throw from `set_quality()` or `resize()` is logged as step `save`, and the new `error` field isn't in PRIVACY.md:61. Fix: track the step in a variable, and add "and the error's message" to the row.
7. `src/Service/CatalogueCheck.php:215` and `src/Service/LayerCheck.php:78`. ID lists are joined with `', '`. Fix: `wp_sprintf( '%l', … )` at the edge (`Sentences`).
8. Four copies of the own-else-parent layer rule (`WooPartCards.php:371`, `WooPartLooks.php:171-176`, `WpLayerImages.php:187-197`, `PartSpecsPanel.php:419-427`). Two copies of 500 (`Shelf.php:58`, `BuildLink.php:64`), and HOOKS.md:422 still says `from` runs 0 to 2,000 while the shelf lists at most 500. Fix: one adapter method; `BuildLink::MOST = Shelf::MOST`; HOOKS wording.
9. `src/Service/SpecExport.php:59-62` and `src/Service/SpecImport.php:127-131`. `withLayers()` changes `$this`, where every other `with…()` returns a clone (`CatalogueCheck.php:1365-1368`, `Builder.php:822-827`). Fix: clone.
10. `assets/src/builder/block.json:20-23`. With `align` defaulting to `full`, choosing None in the editor likely clears the attribute, which reads back as `full`, so the content-column option HOOKS.md:653 implies ("unless the editor chose otherwise") is lost. Fix: check in the editor; if confirmed, add a `none` value or handle an empty one.
11. `src/Service/Health.php:141`. Site Health runs `CatalogueCheck::run()`, which now works out `shared()` per kit and slot (`CatalogueCheck.php:199-219`) only for Health to discard it. Fix: a flag, or Health builds the check without `withLooks()`.

Count: 0 blockers, 4 should-fix (P1, P2, P3 in the release audit, P1 and P3 being the same two code issues as second-pass 1 and 2), 13 minor (P4 to P6, and second-pass 3 to 11, with second-pass 6 overlapping P6). In distinct findings: 0 blockers, 3 should-fix, 12 minor.

## What was done with each finding

Written by Claude Code after the audit, not by the auditor. Each fix has a test that failed before it; the red runs are in the commits' messages, and the review (`docs/reviews/M10.md`) has the deliberate breaks.

| Finding | Done |
| --- | --- |
| P1, P3 (second pass 1, 2) | Fixed. `SpecImport::layer()` keeps a cell that names the part's own image, by ID or by the name an export writes, before any lookup. `LayerColumnTest` re-imports a part whose image has a same-name twin; `SpecsBenchmarkTest` plans 2,000 parts' export with layer images in 0.47 s (16.3 s before). |
| P2 | Fixed. `SavedBuildHooks::kept()` drops an Undo copy read past its 15 minutes, and PRIVACY.md says each way the copy goes. |
| P4 | Fixed, and gate 17 now fails any file outside the domain without its `ABSPATH` guard. |
| P5 | Fixed: "Choose an image for <part>". |
| P6, second pass 6 | Fixed. PRIVACY.md has the rendition log's error and the picture jobs' rows, and a throw is logged with the step it came from (`quality`, `resize` or `save`). |
| Second pass 3 | Fixed. The lookup matches the file's whole name, in any folder or as WordPress's `-scaled` copy, so any number of near names don't push it out of the 20 read. With LIKE, since VIP's ruleset refuses REGEXP. |
| Second pass 4 | Fixed before the freeze: `currency.format` is plain text (D-290). |
| Second pass 5 | Fixed: the parents of variations that fall back to theirs are read once for all the cards. |
| Second pass 7 | Fixed: ID lists go through `wp_sprintf( '%l' )` where the sentence is made. |
| Second pass 8 | Fixed. `Support\LayerMeta` is the one own-else-parent rule its four readers share; `BuildLink::MOST` is `Shelf::MOST`; the route's `from` stops at 500, and HOOKS.md says so. |
| Second pass 9 | Fixed: both `withLayers()` return a copy. |
| Second pass 10 | Not a defect, now tested. WordPress's editor stores None as an empty `align` on a block with a default (`wp-includes/js/dist/block-editor.js:85184-85191`), and the builder then takes the content column's width. |
| Second pass 11 | Fixed: Site Health runs the check `withoutLooks()`. |
| O2, O4, O19 | Not before the tag, by Zach's call (D-291). They stay open. |
| O3 | #43 closed: the canary is green on `main`. |
| O16 (M9's minors) | Fixed: counts through `_n()`; saved builds read with `LIMIT` and capped by the insert itself; the two admin loops read their products together; the forced-colours test reads the colour; a pressed filter keeps its inset line under the ring. |
| O17 | Fixed: SECURITY.md names methods, not lines. |
| O21 | Measured for 1.0.0 in BENCHMARK.md: with Redis the cold rows are 89 to 92 ms, the first request after a part moves 79.4 ms, the warm rows under 60 ms. 1.0.0 ships with the cold rows over the budget, and it stays at 80 ms (D-294). |
