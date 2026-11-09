# Decisions

Every decision about Creative Skateboard Builder that the spec doesn't already make: numbered, dated, with its reason. Claude Code adds one for each decision it makes on its own, continuing from D-020, in this shape. Where an entry changes something the spec says, the entry says so.

Status is one of:

- **Proposed**: Claude in Cowork's suggestion, waiting on Zach. The open question it answers is named.
- **Accepted**: Zach agreed, or it's an engineering call made with the brief.
- **Replaced by D-n.**

Zach accepted D-002 to D-011 as proposed on 9 November 2026, before milestone 1.

### D-001 · The name: Creative Skateboard Builder · 2026-11-09

Zach chose Creative Skateboard Builder over the working name, Creative Builder, which read as generic (a page builder, a site builder). The names follow two rules: anything WordPress expects to match the slug uses the slug, and everything the code prefixes keeps the short prefix.

| Thing | Name |
| --- | --- |
| Plugin name | Creative Skateboard Builder |
| Slug, text domain and main file | `creative-skateboard-builder`, `creative-skateboard-builder.php` |
| Namespace | `CreativeBranding\SkateboardBuilder` |
| Block | `creative-skateboard-builder/builder` |
| Interactivity store | `creative-skateboard-builder` |
| Abilities and their category | `creative-skateboard-builder/*` |
| Log source and Action Scheduler group | `creative-skateboard-builder` |
| Prefix for tables, options, meta, hooks and actions | `cbuild_` |
| WP-CLI | `wp cbuild` |
| REST namespace | `cbuild/v1` |
| Query parameters | `cbuild[…]` |
| DOM events | `cbuild:*` |
| Constants | `CBUILD_*` |

**Why:** the name says what the plugin does, on Skatehouse and on any other skate shop the agency builds. Its rules and spec tables were always skateboard rules; the kit model underneath stays general. The short prefix keeps hooks, keys and tables readable, and nothing else on the shop uses it.
**Status:** Accepted (Zach, 9 November 2026).

### D-002 · Build our own rather than buy Composite Products · 2026-11-09

**Why:** the four reasons under The bar to clear:

- fit checked from specs;
- each deck width shown as its own option;
- the accessibility bar and the no-JavaScript version;
- one codebase for every shop the agency builds, with no licence per site.

**Status:** Accepted (Zach, 9 November 2026; open question 2).

### D-003 · Repository, licence and releases · 2026-11-09

The repository:

- a private repository, `zachatkinson/creative-skateboard-builder`, at `~/web-projects/creative-skateboard-builder`;
- licensed GPL-2.0-or-later.

Releases go out as Creative Search's do:

- a version tag runs `release.yml` once CI has passed on that commit;
- the zip, `info.json` with its SHA-256 and a CycloneDX SBOM go to `zachatkinson/creative-releases`;
- shops update through `Updates\Updater`.

**Why:** one release path for every agency plugin. The license follows WordPress's own.
**Status:** Accepted (Zach, 9 November 2026; open question 3).

### D-004 · Floors: PHP 8.5, WordPress 7.0, WooCommerce 11.2 · 2026-11-09

`Requires PHP: 8.5`, `Requires at least: 7.0`, `WC requires at least: 11.2`.

CI tests two pairs, both on PHP 8.5, on MySQL 8.4 and MariaDB 10.11:

- the oldest: WordPress 7.0 with WooCommerce 11.2.0;
- the newest: WordPress 7.1.4 with WooCommerce 11.2.2.

**Why:** Creative Search's D-023, Zach's call on 21 October 2026:

- Infection 0.33 and later need PHP 8.3, and PHPUnit 13 needs 8.4.1.
- WordPress 6.8 doesn't support PHP 8.5.
- WooCommerce 11.2 itself requires WordPress 7.0.
- The agency's shops run WordPress 7 on PHP 8.5.

WooCommerce 11.2 is the first release with the Store API filters the cart relies on: `woocommerce_store_api_cart_item_quantity_validation`, which refuses a part's quantity changed on its own, and the validation for a build's first add (advisory, 24 September 2026). On 11.0 and 11.1 a locked line is refused only above its maximum, so a build could be split. A repair after the fact was considered and rejected: it can't refuse the change, only undo it. The agency's other WooCommerce plugins have an 11.0 floor; the builder is the first to need 11.2. It also leaves only one Mini-Cart to support: the Interactivity API one, the default since 10.4.

**Trade-off:** a host without PHP 8.5 can't run the plugin. The agency controls the hosting of the shops it ships to.
**Status:** Accepted (Zach, 9 November 2026; open question 8). This changes the spec's earlier floors (WordPress 6.8, WooCommerce 10.0, PHP 8.2).

### D-005 · The toolchain: Creative Search's, with additions · 2026-11-09

**From Search:**

- PHPUnit 13 through Composer for unit tests.
- PHPUnit 9.6 with the Yoast polyfills in `tools/integration/`, its own Composer project, for the WordPress test suite (Search D-021). Core ticket #62004, to move the suite past PHPUnit 9, is still open.
- Infection 0.35.
- PHPCS with Search's ruleset.
- PHPStan 2 at level 9.

**Added:**

- PHPStan at level 10 on `src/Domain`, with the strict, deprecation and disallowed-calls rules;
- deptrac;
- StrykerJS;
- Lighthouse CI;
- Playwright's visual snapshots;
- TypeScript (D-015);
- a secrets scan in CI;
- the weekly canary.

**Why:** each one is evidence one of the twelve dimensions asks for: architecture, mutation in the script, lab Core Web Vitals, layout regressions, the server-to-script contract, supply chain, and future compatibility.
**Status:** Accepted.

### D-006 · The picture in 1.0: layered 2D, with 3D later · 2026-11-09

The picture is drawn through one renderer interface. Two renderers ship in 1.0:

- layers in the page;
- one composed image, made in the background, for carts, emails and share cards.

A 3D renderer that loads on a tap is a module after 1.0 (ROADMAP.md, Module B).

**Why:** the deep dive's option A. It works on every device and without JavaScript, stays within the largest-paint budget, and needs no models of trucks the shop doesn't stock yet. Independent research finds 3D's lift small. The picture is still what catches the eye, which is why it is now a milestone of its own (D-012).
**Status:** Accepted (Zach, 9 November 2026; open question 9). This changes the spec's earlier non-goal, which ruled out a layered preview in 1.0.

### D-007 · Shoppers' own graphics: a module after 1.0 · 2026-11-09

**Why:** it changes what Skatehouse makes and waits on the owner's seven questions (UX-RESEARCH.md). The picture's deck layer already takes any image, so the module adds uploads, the editor, proofs, approval and print files without changing the picture.
**Status:** Accepted (Zach, 9 November 2026; open question 9).

### D-008 · Where the builder lives on Skatehouse · 2026-11-09

The Builder block on /build-a-complete/, already in the menu, with the kit's own page pointing there.

**Status:** Accepted (Zach, 9 November 2026; open question 4).

### D-009 · One cart count per build · 2026-11-09

`woocommerce_cart_contents_count` counts a build once, as Bundles and Mix and Match count theirs. It sets the header count and the Store API's `items_count`, which the Mini-Cart then shows (checked in WooCommerce 11.2.1).

**Status:** Accepted (Zach, 9 November 2026; open question 5).

### D-010 · No checkout script on every page for the Mini-Cart · 2026-11-09

**Why:** it would add about 140 kB compressed to every page. Item data and CSS group a build's parts in the Mini-Cart, and removing any part removes the whole build.
**Status:** Accepted (Zach, 9 November 2026; open question 6).

### D-011 · Dealers see retail prices in 1.0 · 2026-11-09

A signed-in dealer sees a line saying the builder is for retail orders. This is revisited with the owner's trade terms in Phase 4.

**Status:** Accepted (Zach, 9 November 2026; open question 7).

### D-012 · Nine milestones · 2026-11-09

The builder's front end (6) and the picture (7) are separate; orders are 8 and hardening 9.

**Why:** with the deep dive's additions, milestone 6 would otherwise hold the whole front end and both of the picture's renderers, too much to review well as one.
**Status:** Accepted. This changes the spec's earlier eight milestones.

### D-013 · The audit matrix: twelve dimensions, through the whole build · 2026-11-09

Zach's ten dimensions are:

- DRY and SOLID, and anti-patterns;
- security, and privacy and data handling;
- accessibility and internationalisation;
- SEO and performance;
- testing;
- upgrades and compatibility.

Two more were proposed with the brief and approved: resilience and error handling, and UX and content.

The audit runs at every stage, as `AUDIT.md` sets out:

1. a plan with the risks under each dimension;
2. every gate on every commit;
3. two reviewer agents at each milestone's end;
4. Claude in Cowork's review;
5. a full audit before each release.

**Status:** Accepted (Zach, 9 November 2026).

### D-014 · One template, re-rendered from the state route; no full-page client navigation · 2026-11-09

- The server renders the builder from the URL's state.
- The Interactivity API re-renders the same template with directives from the state route's answer.
- Each step change pushes the URL the no-JavaScript form would have made.

**Why:** one rendering path, so markup never lives twice. `@wordpress/interactivity-router` was considered for step changes: it fetches the whole page, which costs a full WordPress render (0.7 to 1.1 s to first byte on the demo's host without a page cache), where the state route answers in under 80 ms.
**Status:** Accepted, to be confirmed by a spike at the start of milestone 6. A different result replaces this entry.

### D-015 · TypeScript, with types generated from the route's schema · 2026-11-09

- The store, the Cart block filters and the admin scripts are TypeScript in strict mode.
- `tsc --noEmit` runs in `npm run check`.
- The state route's response types are generated from its JSON Schema.

**Why:** the seam between the server and the store is where the builder is most likely to drift. Generated types turn drift into a compile error, and the contract test catches what types can't. The agency's other plugins use plain JavaScript; this is the first in TypeScript.
**Status:** Accepted.

### D-016 · Abilities · 2026-11-09

These go through WordPress's Abilities API, as Creative Reviews registers its own:

- read-only `creative-skateboard-builder/check-build` and `creative-skateboard-builder/price-build` (milestone 4);
- `creative-skateboard-builder/catalogue-check` for those who can manage WooCommerce (milestone 3).

All three sit in the ability category `creative-skateboard-builder`, registered on `wp_abilities_api_categories_init`, since WordPress 6.9 requires a category.

**Why:** it's the same engine through one more adapter, so the shop's own tools and AI assistants get exactly the builder's answers, under the same permissions.
**Status:** Accepted.

### D-017 · Builder state in `cbuild[…]` parameters, reached by forms · 2026-11-09

- Picks, step, filters and presets travel in `cbuild[…]` query parameters, in slot order.
- They are changed by buttons in GET forms, not by links. The only links into states are the progress list's done steps and the review's Change links, which carry `rel="nofollow"`.
- Pages carrying them get `noindex, follow` and a canonical link to the clean page.
- There is no robots.txt line.

**Why:**

- One prefix nothing else uses.
- Forms keep crawlers out of the builder's states.
- `noindex` and the canonical cover build links arriving from outside.
- A robots.txt `Disallow` would also stop the crawlers that respect it, X's among them, from unfurling a shared build.

**Status:** Accepted.

### D-018 · No theme template overrides in 1.0 · 2026-11-09

**Why:** a template a theme copies becomes an API that has to be versioned. Themes restyle the builder through its documented classes and custom properties; plugins change its content through filters.
**Status:** Accepted.

### D-019 · wp-env on ports 8898 and 8899 · 2026-11-09

**Why:** 8888 to 8897 belong to Stockroom, Search, Shipment Tracking, Restock and Reviews, so all of them can run at once.
**Status:** Accepted.

### D-020 · The layout and its layers, as deptrac enforces them · 2026-11-09

`docs/STANDARDS.md`'s layout, with these refinements:

- `Hooks`, `Rest`, `Abilities`, `Cli`, `Block`, `Front`, `Cart`, `Order`, `Admin`, `Privacy`, `Health` and `Updates` form one deptrac layer, Presentation. It may depend on `Service`, `Domain`, `Port`, `Support`, `Install` and the core (`Plugin`, `Container`, `Hookable`), never on `Adapter` or `Repository`.
- `Install` may depend on `Domain`, `Port`, `Support` and the core; the migration framework's pure parts live in `Install\Migrations` and reach the database, the clock, the log and the queue only through ports, so the unit suite covers them.
- WordPress and WooCommerce classes (`WP_*`, `WC_*`, `wpdb`, `Automattic\WooCommerce\*`, `ActionScheduler*`) form a layer of their own that `Domain`, `Port` and `Service` may not reach. Their global functions are caught by `tools/gates.sh` (no WordPress in `src/Domain`).
- PHPUnit 9.6 and the Yoast polyfills live in `tools/integration/`, their own Composer project, and the plugin's Composer loader is registered behind PHPUnit 9.6's (Creative Search's D-021 and D-023).

**Why:** deptrac needs every dependency covered by a layer, and the presentation directories share one rule, so one layer says it once. The WordPress layer turns "the domain has no WordPress in it" into a failing build.
**Status:** Accepted.

### D-021 · The migration lock is one atomic statement, taken over by compare-and-swap · 2026-11-09

`cbuild_migration_lock` is taken with `INSERT IGNORE` into the options table, autoload off, and read straight from the table. An expired lock is taken over with `UPDATE ... WHERE option_name = %s AND option_value = %s`, the expired value, so only one of two requests racing for it wins. The lock holds a timestamp from the Clock; it expires after 300 seconds and a long migration refreshes it between batches.

**Why:** `add_option()` writes `INSERT ... ON DUPLICATE KEY UPDATE` (WordPress 7.1.4, wp-includes/option.php:1142), so a second request that races past the existence check also gets a truthy result, and `get_option()` answers from the object cache. `WP_Upgrader::create_lock()` uses `INSERT IGNORE` with autoload off (wp-admin/includes/class-wp-upgrader.php:1096); `ActionScheduler_OptionLock::set()` reads the row from the table and takes over an expired lock by compare-and-swap (WooCommerce 11.2.2, packages/action-scheduler/classes/ActionScheduler_OptionLock.php:37-72 and :150). The lock follows both. Its test runs two real `wp` processes against one database, since two calls in one PHPUnit process pass even with a broken lock and the test case's transaction hides one connection's writes from the other. Raised by Claude in Cowork's review of the milestone 1 plan.
**Status:** Accepted.

### D-022 · Where migrations run · 2026-11-09

When `cbuild_db_version` is behind:

- a signed-in user with `manage_woocommerce` on an admin page that is not admin-ajax.php, admin-post.php or cron runs the migrations inline, on `admin_init`;
- a `wp cbuild` command runs them before its work;
- every other request, on `init`, queues one `cbuild_migrate` action with Action Scheduler's unique flag and runs nothing itself;
- the code keeps working on the previous schema until the job finishes.

**Why:** `admin_init` also fires on admin-ajax.php (wp-admin/admin-ajax.php:45) and admin-post.php (wp-admin/admin-post.php:27), which storefront requests reach, and nothing else would queue the job after an unattended update. `as_enqueue_async_action()` and `as_schedule_single_action()` take `$unique` (WooCommerce 11.2.2, packages/action-scheduler/functions.php:19 and :69). This narrows the spec's "migrations run on load": they are started on load and finish off the shopper's request. Raised by Claude in Cowork's review of the milestone 1 plan.
**Status:** Accepted. This narrows the spec's Upgrades and the public API.

### D-023 · The floors are checked in the main file, before the autoloader · 2026-11-09

`creative-skateboard-builder.php` checks `PHP_VERSION` and the WordPress version before it loads the autoloader. Below either floor, or without WooCommerce 11.2, it shows one notice and hooks nothing. The check is `Support\Requirements`, a file the main file requires directly, so the unit suite tests it with the versions injected. The main file, `uninstall.php` and `Support\Requirements` parse on PHP 7.4, the oldest PHP WordPress 7.0 and 7.1 run on, and CI lints them with PHP 7.4.

**Why:** WordPress reads `Requires PHP` and `Requires at least` only when a plugin is activated (`validate_plugin_requirements()`, called from `activate_plugin()`, wp-admin/includes/plugin.php:663), so a host downgraded after activation would load the plugin anyway; the main file is then the only thing that runs. WordPress 7.0.8 and 7.1.4 require PHP 7.4 (api.wordpress.org version-check, 9 November 2026), and the PHPCompatibility run at `8.5-` cannot flag 8.5-only syntax. Raised by Claude in Cowork's review of the milestone 1 plan.
**Status:** Accepted.

### D-024 · Uninstall without Action Scheduler · 2026-11-09

Deactivation unschedules every pending `cbuild_*` action through Action Scheduler, which is loaded then, since `Requires Plugins` keeps WooCommerce active while the builder is. Uninstall deletes the group's remaining rows through Action Scheduler's store when it is loaded, and otherwise with prepared SQL on Action Scheduler's own tables, when they exist: the one exception to "SQL only on the plugin's tables" in `docs/STANDARDS.md`.

**Why:** WordPress refuses to deactivate a plugin with active dependents (wp-admin/includes/class-wp-plugins-list-table.php:981), so a shop removing both deactivates the builder first, then WooCommerce, then deletes them, and `uninstall.php` runs with no Action Scheduler loaded. Leaving the rows would leave the plugin's data behind, which the spec's Uninstall forbids; the alternative, loading Action Scheduler from WooCommerce's folder during uninstall, runs another plugin's code while it is deactivated. Raised by Claude in Cowork's review of the milestone 1 plan.
**Status:** Accepted.

### D-025 · Network activation installs every site · 2026-11-09

On a network, activating the plugin for the whole network installs each site in turn, as Creative Search does; a site added to the network later installs itself the first time its admin loads, through the migration runner, since its `cbuild_db_version` is behind. Deleting a site drops its tables through `wpmu_drop_tables` (wp-includes/ms-site.php:832). Uninstall walks every site.

**Why:** the spec says the plugin activates per site; refusing network activation would make a network's admin repeat the same click on every site for the same result. Raised by Claude in Cowork's review of the milestone 1 plan.
**Status:** Accepted.

### D-026 · The attribute mapping and which source wins are shop-wide · 2026-11-09

The attribute mapping (which attribute feeds which spec, in which unit) and which source wins where two disagree move from the kit's Build tab to WooCommerce > Settings > Builder. Kits keep their rule switches and tolerances, which apply when fit is checked, not when parts are indexed. The two tables don't change.

**Why:** the specs index holds one value per part and spec, so neither can differ by kit. The spec's Data model, Part specs, Compatibility rules, Build tab and Settings paragraphs were updated with this entry. Raised by Claude in Cowork's review of the milestone 1 plan; it lands in milestone 3.
**Status:** Accepted. This changes the spec's Data model and Admin.

### D-027 · No `product_block_editor` declaration · 2026-11-09

The plugin declares `custom_order_tables` and `cart_checkout_blocks` compatible and declares nothing about `product_block_editor`.

**Why:** the feature is gone from both ends of the matrix. WooCommerce 11.2.2's `FeaturesController` defines `cart_checkout_blocks` (src/Internal/Features/FeaturesController.php:339) and `custom_order_tables` (src/Internal/DataStores/Orders/CustomOrdersTableController.php:624) and no `product_block_editor`; 11.2.0 is the same. `FeaturesUtil::declare_compatibility()` returns false for an unknown feature (FeaturesController.php:1007-1022), so declaring it would be a silent no-op at best. The declarations are registered from the main file's top level on `before_woocommerce_init`, which WooCommerce fires while it is included (includes/class-woocommerce.php:998), before `plugins_loaded`.
**Status:** Accepted. This settles the spec's open check under Cart, checkout and orders.

### D-028 · No `load_plugin_textdomain()` · 2026-11-09

**Why:** WordPress registers a plugin's `Domain Path` with the text domain registry as it loads the plugin (WordPress 7.1.4, wp-settings.php:589-592), and translations load just in time on the first translated string. `docs/STANDARDS.md` says the domain loads on `init`; with the floor at WordPress 7.0 nothing needs to.
**Status:** Accepted.

### D-029 · The API snapshot is not empty · 2026-11-09

`tests/Fixtures/api-surface.json` holds the `cbuild_updates_enabled` filter, the `CBUILD_DISABLE_UPDATES` constant, the three options, the two tables, the Action Scheduler group and the `cbuild_migrate` action, and the log source.

**Why:** the roadmap says "empty, committed", but the updater's switch and the migration framework's names exist from this milestone and `CLAUDE.md` names them. The snapshot holds what exists.
**Status:** Accepted. This changes the roadmap's milestone 1 test list.

### D-030 · Toolchain pins and tools · 2026-11-09

- Coverage: pcov measures lines; Xdebug's path coverage measures `src/Domain`'s branches in a second run (`tools/coverage.sh`), since pcov measures lines only.
- Node 24 (`.nvmrc`), at 24.15 or later, which `@wordpress/scripts` 37 requires.
- TypeScript 5.9: `typescript-eslint`, which `@wordpress/eslint-plugin` carries, allows only below 6.1.
- The secrets scan is gitleaks, pinned to a commit, on every push and pull request (Zach, 9 November 2026).
- The CycloneDX SBOM is written in `release.yml` only, by `@cyclonedx/cyclonedx-npm` and `cyclonedx/cyclonedx-php-composer`, so neither joins the lock files (Zach, 9 November 2026). It lists what the zip ships: Composer's non-dev packages and the npm packages webpack bundles into `build/`, which stay in `dependencies` so `--omit dev` counts them.
- Every GitHub Action is pinned to a commit SHA; Dependabot updates them weekly with the lock files.

**Status:** Accepted.

### D-031 · The integration suite keeps its own tables · 2026-11-09

`tools/integration.sh` passes `WORDPRESS_TABLE_PREFIX=wptests_`, so the WordPress test suite installs into `wptests_` tables and leaves wp-env's tests site (`wp_`) alone. The tests that need a second PHP process (two requests racing for the migration lock, uninstall with WooCommerce off, boot without WooCommerce) run WP-CLI against that site, with the prefix taken back out of the child's environment. On a matrix leg's empty database server the test case installs the site first, so the lock's race runs on that server too. The multisite leg is switched on with `WP_MULTISITE=1`.

**Why:** wp-env 12 writes one `wp-tests-config.php` for both, with `wp_` as the default prefix (`/wordpress-phpunit/wp-tests-config.php:102`), so the suite's installer dropped the tests site's tables on every run and the second process found no active plugin. The core bootstrap reads `WP_MULTISITE` from the environment (`/wordpress-phpunit/includes/bootstrap.php:244`), not `WP_TESTS_MULTISITE`, which is a constant. Creative Search has no test that starts a second process, so its script never met either.
**Status:** Accepted.

### D-032 · Lighthouse measures the site as a shop serves it · 2026-11-09

`npm run lhci` runs through `tools/lhci.sh`, which turns `SCRIPT_DEBUG` off and WooCommerce's "coming soon" mode off on wp-env's browser site for the run and puts both back afterwards. The budgets in `docs/AUDIT.md` are unchanged. Until the Builder block ships (milestone 6) the page measured is the shop's front page with the plugin active.

**Why:** wp-env sets `SCRIPT_DEBUG`, so WordPress and WooCommerce serve unminified scripts and styles, and with them the front page alone misses the LCP budget whether the plugin is active or not: 2,723 ms without it and 2,728 ms with it (median of three, mobile profile, 9 November 2026). With minified assets the same page measures 2,380 ms. A budget for the builder page should not fail on a debug setting no shop runs with. The margin under 2,500 ms is about 120 ms before the builder draws anything, which milestone 6 has to work within.
**Status:** Accepted. Zach to confirm at the milestone's review.

### D-033 · The migration lock names its holder, and a failed step waits · 2026-11-09

The lock's value is the moment it was taken or last refreshed, a colon, and a random token of the process that holds it. A run refreshes and releases the lock only where the row still carries its own token, refreshes it between migrations as well as between batches, and stops at once, recording no version, when a refresh finds the lock is no longer its own. Deactivation removes the lock whoever holds it.

A migration that fails records when it may be tried again: 60 seconds after the first failure, then 5 minutes, 30 minutes, and an hour after every later one. Until then no request queues a job and no admin page runs it inline; the runner schedules one `cbuild_migrate` for that moment. `SchemaMigration` fails when a table is still missing after `dbDelta()`, which does not report a failed `CREATE TABLE`.

**Why:** Raised by the milestone 1 audit. With a bare timestamp, a run that outlived the 300 second expiry and was taken over would carry on, write its own time over the new holder's and then delete the new holder's lock, letting a third run in beside it (`docs/AUDIT.md`: "two requests never run the same one"). Without a wait, a step that fails every time was queued again by the next front-end request and run again on every manager's admin page, with an error logged each time (`docs/AUDIT.md`: "retried with backoff").
**Status:** Accepted.

### D-034 · Plugin Check's one allowed finding · 2026-11-09

`npm run plugin-check` fails on any error or warning but one: `plugin_updater_detected`, which Plugin Check raises for the `Update URI` header. `tools/plugin-check.sh` names that code and no other.

**Why:** The finding is a WordPress.org hosting rule. This plugin is installed from a zip and updates from the agency's releases (D-003); the header is what points core at them and stops WordPress.org offering a plugin with the same slug over it. Creative Search allows the same one for the same reason. Raised by the milestone 1 spec review, since the allowance was written only in the script.
**Status:** Accepted.

### D-035 · axe runs on what the plugin draws · 2026-11-09

The axe helper fails on any violation of the WCAG 2.0, 2.1 and 2.2 A and AA rules, whatever its impact, inside the region it is given. It is run on every screen and state the plugin draws. On a page where the plugin draws nothing, the test asserts that instead (no element, no request, no console error of the plugin's) and axe is not run. In milestone 1 that is the shop, a product, the cart and the checkout; axe runs on the plugin's row of the Plugins screen, with core's own two links in that row left out at the call.

**Why:** The milestone 1 plan said the smoke test would run axe on the shop's pages. Run there on 9 November 2026 with WooCommerce 11.2.2 and Twenty Twenty-Five, axe reports `aria-hidden-focus` in WooCommerce's Mini-Cart drawer and `list` in the theme's navigation block, on every page, with the plugin adding nothing to any of them. Passing would mean exempting other projects' markup rule by rule, and a failure there would say nothing about this plugin. From milestone 6 the builder's own region is checked on every state.
**Status:** Accepted.

### D-036 · What a narrow pull request runs, and the oldest leg's browsers · 2026-11-09

As Creative Search: a pull request that changes only `tests/e2e/`, `.github/workflows/`, `docs/` or Markdown runs the standards, the unit tests and the newest leg, not the other legs, mutation testing, the benchmarks or the release dry run. If git cannot read the diff, everything runs. A push to `main` always runs everything, and a release waits for that run. The oldest leg runs the integration suite, Plugin Check, the zip's lifecycle and two browser projects (`chromium-desktop` and `webkit-phone`); the newest leg runs every browser project. `/audit-milestone` runs all nine local projects on both legs before the pull request.

**Why:** The oldest leg is there for the WordPress and WooCommerce versions, which the integration suite and one desktop and one phone engine show; the engines themselves are covered on the newest. A change to a browser test or a workflow cannot change what the other legs measure. Raised by the milestone 1 spec review, since the narrowing was not written down here.
**Status:** Accepted.

### D-037 · The schema is the first migration, and the 7.0 leg is its latest patch · 2026-11-09

The tables are created by a migration, `schema_1`, which runs first; the no-op step the roadmap asks for, `noop_1`, runs second and proves a step with nothing to do. Activation runs the migrations, so "activation creates the two tables" holds. A later schema change is a `schema_<n>` migration calling the same `Schema::install()`.

The oldest leg pins WordPress 7.0.8, the 7.0 branch's latest release on 9 November 2026, with WooCommerce 11.2.0. `Requires at least` stays 7.0.

**Why:** One path creates and changes tables, on activation and on upgrade alike, under the same lock and with the same failure handling (D-033); a schema outside the migrations would be a second path to keep right. A shop on the 7.0 branch gets its security releases, so its latest patch is what a real 7.0 site runs. Both raised by the milestone 1 spec review as differences from the roadmap's wording.
**Status:** Accepted.

### D-038 · What the 90 % floors measure · 2026-11-09

"90 % of lines overall" and "90 % MSI outside the domain" are measured on everything in `src/` except the classes that cannot run without WordPress, WooCommerce or the database, which `phpunit.xml.dist` and `infection.json5` exclude by name: `src/Hooks`, `src/Repository`, `Plugin.php`, the two WordPress-bound adapters, `Install\Activator`, `Deactivator`, `Schema`, `Uninstaller`, `Migrations\SchemaMigration`, `Updates\HttpManifestSource` and `Updates\Updater`. A new class is measured unless it is added to that list with a reason. The excluded classes are proven by the integration suite, whose coverage is not measured. On 9 November 2026 the measured set is 299 lines at 91.97 %, and 315 mutants at 93 %.

Adding a class to the exclusions narrows a floor, and needs Zach as moving one does.

**Why:** The unit suite runs without WordPress, so a class that calls it cannot be covered there, and the WordPress test suite on PHPUnit 9.6 has no coverage driver wired. Before the milestone 1 audit the floor read a hand-picked list of files, so a new untested class was invisible to it; `Support\Options` and `Support\Tables` had no unit tests for that reason. The updater is the largest excluded class and the most security-sensitive: its reader (`Updates\Manifest`) is measured, and its hooks are driven by 14 integration tests. Raised by both milestone 1 reviews.
**Status:** Accepted for milestone 1. Zach to confirm the list, and whether measuring the integration suite's coverage should be a later milestone's work.

### D-039 · One uninstaller · 2026-11-09

`uninstall.php` holds no logic of its own. With the autoloader it calls `Install\Uninstaller::run()`; without it (a checkout where Composer never ran) it loads the three files the uninstaller is made of, `Support\Options`, `Support\Tables` and `Install\Uninstaller`, and calls the same method. A test fails if the uninstaller comes to need a class `uninstall.php` does not load.

**Why:** The first version repeated the uninstaller's list by hand for the no-autoloader case, kept in step by a test that matched strings. Both milestone 1 reviews found it: a rule written twice, whose second copy never ran in any test and had already fallen behind the first.
**Status:** Accepted.
