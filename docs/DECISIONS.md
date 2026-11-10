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
**Status:** Accepted. Zach left the measuring to Claude on 9 November 2026. The margin is looked into in milestone 6's plan, before the builder page exists.

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

As Creative Search: a pull request that changes only `tests/e2e/`, `.github/workflows/`, `docs/` or Markdown runs the standards, the unit tests and the newest leg, not the other legs, mutation testing, the benchmarks or the release dry run. If git cannot read the diff, everything runs. A push to `main` always runs everything, and a release waits for that run. The oldest leg runs the integration suite, Plugin Check, the zip's lifecycle and two browser projects (`chromium-desktop` and `webkit-phone`); the newest leg runs every browser project. `/audit-milestone` runs all nine local projects on both legs before the pull request. Revised by D-043: a change under `.github/workflows/` takes the full run.

**Why:** The oldest leg is there for the WordPress and WooCommerce versions, which the integration suite and one desktop and one phone engine show; the engines themselves are covered on the newest. A change to a browser test or a workflow cannot change what the other legs measure. Raised by the milestone 1 spec review, since the narrowing was not written down here.
**Status:** Accepted.

### D-037 · The schema is the first migration, and the 7.0 leg is its latest patch · 2026-11-09

The tables are created by a migration, `schema_1`, which runs first; the no-op step the roadmap asks for, `noop_1`, runs second and proves a step with nothing to do. Activation runs the migrations, so "activation creates the two tables" holds. A later schema change is a `schema_<n>` migration calling the same `Schema::install()`.

The oldest leg pins WordPress 7.0.8, the 7.0 branch's latest release on 9 November 2026, with WooCommerce 11.2.0. `Requires at least` stays 7.0.

**Why:** One path creates and changes tables, on activation and on upgrade alike, under the same lock and with the same failure handling (D-033); a schema outside the migrations would be a second path to keep right. A shop on the 7.0 branch gets its security releases, so its latest patch is what a real 7.0 site runs. Both raised by the milestone 1 spec review as differences from the roadmap's wording.
**Status:** Accepted.

### D-038 · What the 90 % floors measure · 2026-11-09

"90 % of lines overall" and "90 % MSI outside the domain" are measured on everything in `src/` except the classes that cannot run without WordPress, WooCommerce or the database, which `phpunit.xml.dist` and `infection.json5` exclude by name: `src/Hooks`, `src/Repository`, `Plugin.php`, the two WordPress-bound adapters, `Install\Activator`, `Deactivator`, `Schema`, `Uninstaller`, `Migrations\SchemaMigration`, `Updates\HttpManifestSource` and `Updates\Updater`. A new class is measured unless it is added to that list with a reason. The excluded classes are proven by the integration suite, whose coverage is not measured. On 9 November 2026 the measured set is 299 lines at 91.97 %, and 313 mutants at 92 %.

Adding a class to the exclusions narrows a floor, and needs Zach as moving one does.

**Why:** The unit suite runs without WordPress, so a class that calls it cannot be covered there, and the WordPress test suite on PHPUnit 9.6 has no coverage driver wired. Before the milestone 1 audit the floor read a hand-picked list of files, so a new untested class was invisible to it; `Support\Options` and `Support\Tables` had no unit tests for that reason. The updater is the largest excluded class and the most security-sensitive: its reader (`Updates\Manifest`) is measured, and its hooks are driven by 14 integration tests. Raised by both milestone 1 reviews.
**Status:** Accepted for milestone 1. Zach agreed on 9 November 2026 that milestone 2 starts by measuring the integration suite's coverage inside wp-env (`wp-env start --xdebug=coverage`) and merging it with the unit suite's, so the 90 % line floor counts every class in `src/` where it is tested and the named exclusions go from `phpunit.xml.dist`. The list stays in `infection.json5`: mutation testing runs on the unit suite only.
Done in milestone 2: D-050.

### D-039 · One uninstaller · 2026-11-09

`uninstall.php` holds no logic of its own. With the autoloader it calls `Install\Uninstaller::run()`; without it (a checkout where Composer never ran) it loads the three files the uninstaller is made of, `Support\Options`, `Support\Tables` and `Install\Uninstaller`, and calls the same method. A test fails if the uninstaller comes to need a class `uninstall.php` does not load.

**Why:** The first version repeated the uninstaller's list by hand for the no-autoloader case, kept in step by a test that matched strings. Both milestone 1 reviews found it: a rule written twice, whose second copy never ran in any test and had already fallen behind the first.
**Status:** Accepted.

### D-040 · CI is shaped by what it costs · 2026-11-09

The repository is private, so GitHub bills every job to the whole minute. The same gates run as before, in fewer jobs:

- The six variants of the matrix (MySQL 8.4, MariaDB 10.11, HPOS off, HPOS syncing, a persistent object cache, multisite) run one after another on one wp-env, in one leg, through `tools/integration-variants.sh`. Each is still a full run of the integration suite; every variant runs when an earlier one fails, and the leg fails if any did. The legs are now `newest`, `oldest` and `variants`.
- The secrets scan runs in the job that decides what a change runs. The dependency audits, the benchmarks and the PHP 7.4 parse run in the standards job. The release dry run runs after mutation testing, whatever mutation testing found. This revises D-036 in one place: the benchmarks now run on a narrow pull request too.
- Every job has a time limit, and no checkout leaves its token in the working copy.
- Dependabot opens one pull request per ecosystem, monthly, for releases at least a week old. Security updates are a repository setting of their own and are not delayed.

**Why:** Zach asked on 9 November 2026 that CI not overspend on minutes. The first full runs billed 48 to 56 minutes each. Six of the legs spent about three minutes each starting wp-env to run a 25 second suite, and five jobs billed a minute each for under 20 seconds of work. Dependabot's weekly, ungrouped pull requests would each have been a full run. No gate is removed and no floor moves.
**Status:** Accepted. The first full run in this shape passed in 7 jobs and billed 28 minutes.

### D-041 · A draft pull request takes the lighter run · 2026-11-09

A pull request marked as a draft runs what a narrow one runs (D-036): the secrets scan, the standards, the unit tests, the audits, the benchmarks and the newest leg with every browser project. Marking it ready for review starts the full run: the oldest leg, the six variants, mutation testing and the release dry run. A push to `main` always runs everything.

**Why:** Zach agreed on 9 November 2026, to save billed minutes (D-040): about 12 a push while the work is in progress instead of 28. GitHub does not merge a draft, so nothing reaches `main` without the full run, and a release still waits for `main`'s own.
**Status:** Accepted.

### D-042 · Work owed lives in GitHub's issues; the plan stays in the roadmap · 2026-11-09

`docs/ROADMAP.md` and `docs/SPEC.md` remain the only statement of what each milestone builds; none of it is copied into issues. An issue is for work the roadmap does not list: a finding a review left unfixed, a follow-up a decision promised, a defect, a flaky test, a red canary. Each such issue sits in a GitHub milestone named as the roadmap names it, and one a review found carries the `from-review` label. `/plan-milestone` reads the milestone's open issues into its plan, and `/audit-milestone` closes them or says in the review why one moved.

**Why:** Seven items from milestone 1 existed only as lines in its review and in these decisions, where the milestone they fall due in would not see them. Copying the roadmap into issues as well would make a second plan that drifts from the first. Agreed with Zach on 9 November 2026.
**Status:** Accepted.

### D-043 · What Claude in Cowork's review of the pull request changed · 2026-11-09

Each of its four should-fix findings was checked against the installed source first, and each holds.

- **Under the lock, the states come from the table.** Once the runner holds the lock it reads every migration's state from the table (`MigrationStore::freshStates()`, which drops the options cache's copy and its note in `notoptions`), checks again for a failure still waiting, and works from that copy. After each batch it refreshes the lock before it saves, not after, and after a failure too, so a run that was taken over writes no state, no version and no retry. D-021 and D-033 stand; this closes what they left open.
- **A running job queues its own continuation as a plain action.** Action Scheduler's unique check matches an action that is running as well as one that is pending (WooCommerce 11.2.2, `classes/data-stores/ActionScheduler_DBStore.php:212`), so the `cbuild_migrate` action could not queue the rest of its own run or its own retry, and a quiet or fully cached shop waited for a request to notice. From inside the job the runner now uses `Queue::enqueue()` and `Queue::schedule()`, which are not unique; the lock keeps two runs apart. Everywhere else the action stays unique, so at most one is pending beside the one running. D-022 stands.
- **The integration suite runs on the store shops run.** The suite's freshly installed site ran `ActionScheduler_HybridStore`, which has no unique insert and checks pending actions only, so the fault above could not show there. wp-env's browser site, like any shop that has finished Action Scheduler's move to its own tables, runs `ActionScheduler_DBStore`. `tests/bootstrap-integration.php` now tells the suite's site the move is done.
- **An inline run on an admin page works for 2 seconds** (`MigrationRunner::INLINE_BUDGET`) and leaves the rest to the job, which keeps its 20.
- **`uninstall.php` loads Composer's autoloader only at or above the floors.** Below them the autoloader's platform check throws, which made deleting the plugin on a host below the floor a fatal error: the case D-023 exists for. There it loads the four files the uninstaller is made of (`Support\Options`, `Support\Tables`, `Install\Sites`, `Install\Uninstaller`), which CI lints on PHP 7.4, and `npm run zip-lifecycle` uninstalls the built zip with WP-CLI on PHP 7.4 against wp-env's own site and database. The platform check itself stays on: the main file checks the floors before it loads the autoloader. D-039 stands: there is still one uninstaller.
- **The floor notice prints its own markup where `wp_admin_notice()` does not exist** (WordPress below 6.4), so the notice for a WordPress below the floor is not itself a fatal error.
- **With updates off, the plugin refuses its own package** (`cbuild_updates_off`). WordPress can still hold an offer made before updates were turned off; answering `false` let it download and install that package unchecked. Cowork suggested checking it against the manifest whatever the setting; refusing it keeps the promise that the plugin makes no request while updates are off.
- **The release's token and its toolchain never share a runner.** `release.yml` is three jobs after the CI check: `state` holds `RELEASES_TOKEN` and runs only `gh`; `build` checks the code out, installs the dev dependencies and builds the zip, the SBOMs, `info.json` and the release notes with no secret, by running `tools/release-dry-run.sh`, the script CI runs on every full run; `publish` starts on a fresh runner, downloads those files and runs only `gh`. Gate 9 fails a job of `release.yml` that holds a secret and checks the code out or runs a tool (rewritten as an allow-list in D-048). Creative Search's `release.yml`, which this was copied from, has the same shape and is Zach's to change.
- **A change to a workflow takes the full CI run**, revising D-036: it is what can break the other legs, and Dependabot's monthly bump of the actions is such a change.
- **The grep gates and PHPStan's banned list also refuse** `wp_date()`, `date_i18n()` and `human_time_diff()` as clocks (and gate 3 alone `REQUEST_TIME`, which is not a function), and `file_get_contents()`, `fopen()`, `readfile()`, `file()`, `getimagesize()`, `exif_read_data()`, `get_headers()`, `simplexml_load_file()`, `wp_oembed_get()`, `wp_get_http_headers()`, `fetch_feed()` and `_wp_http_get_object()` as ways out to the network. A class that must read a local file (milestone 7's pictures) is exempted there by name, with its reason.
- **One walk over a network's sites, and one check for Action Scheduler's tables** (`Install\Sites`), where there were three and two.

**Why:** The review, of 9 November 2026, on `m1-foundation`. The findings and their tests are in `docs/reviews/M1.md`.
**Status:** Accepted.

### D-044 · The npm audit gates what the zip ships · 2026-11-09

`composer audit:deps` runs `npm audit --omit=dev`: it fails on an advisory in a package webpack bundles into `build/`, which `package.json` keeps in `dependencies`. Today there is none, so the audit passes over nothing, and the docs say that and no longer call the tree clean. The dev toolchain (2,098 packages, all of them dev) had 48 advisories on 9 November 2026: 13 high, 29 moderate and 6 low, most of them under `@wordpress/scripts`, whose fix is a major version these docs pin against. None reaches the zip. Dependabot's security updates are on for the whole tree, and the release's build job holds no secret (D-043), which is what a compromised dev package could otherwise have reached.

**Why:** Raised by Claude in Cowork's review (minor 6). Failing the build on the dev tree's advisories would be a new floor, and clearing them means moving `@wordpress/scripts` a major version, the one Creative Search uses; both are Zach's to decide. Issue #9 holds the triage.
**Status:** Accepted for milestone 1. Zach to say whether the dev tree should gate. The count is revised by D-046: 4 low advisories are left.

### D-045 · What reading CI's logs changed · 2026-11-09

Zach asked for the logs of a passing run to be read for warnings as well as failures. Four things came of it.

- **The runner image is named.** Every job runs on `ubuntu-26.04`, the image `ubuntu-latest` already resolved to, so a run no longer carries the notice that the label is moving, and the image changes when a commit changes it.
- **`gitleaks/gitleaks-action` is v3.0.0**, which runs on Node 24. v2 targets Node 20, which the runners have dropped; it ran only because the runner forced it onto Node 24.
- **Every dependency's install script is denied by name** in `package.json`'s `allowScripts`: `core-js`, `core-js-pure`, `@parcel/watcher`, `fs-ext-extra-prebuilt` and, on macOS, `fsevents`. npm 11 already skips a script nobody has approved, and every gate passes without them, so this changes nothing that runs; it records the choice, and a dependency that adds an install script later shows up as a warning to review.
- **The mutants that escaped were test gaps, and are closed.** Infection reported 25 on the 345 mutants outside the domain. Ten new unit tests kill 19 of them, among them the anchors of the manifest's version and checksum patterns and the release prefix check. Five were lines whose removal changed nothing (a guard for a value that is not an array, in three readers; a `true` no code read; a cast `sprintf()` makes itself), and those lines are simpler now. The last, `Container::hookables()` returning only its first Hookable, could not be told apart while the plugin has one Hookable: the container now takes its list of Hookable classes as an optional constructor argument, the plugin's own by default, and a test builds two. None escapes.

The floor stays at 90 %: the run kills every mutant (2 of 341 by timing out), and raising a floor is Zach's to decide.

Left alone, with the reason: npm's deprecation warnings come from packages under `@wordpress/scripts` and `@wordpress/env` (issue #9); git's hint about the default branch name comes from `actions/checkout`'s own `git init`.

**Why:** A warning nobody reads hides the one that matters, and an escaped mutant in the updater's validation is a test that would not have caught a real fault.
**Status:** Accepted.

### D-046 · The dev toolchain's advisories are closed with npm overrides · 2026-11-09

`package.json` carries an `overrides` block that moves nine packages deep in the dev toolchain to the release that fixes their advisories: `basic-ftp`, `isomorphic-git`, `js-yaml` 5, `katex`, `minimatch` 7 to 11, `qs`, `rimraf` 4 to 6 (which brings a fixed `glob`), `serialize-javascript` and `smol-toml`. `npm audit` on the whole tree goes from 48 advisories (13 high) to 4, all low and all one fault: `deepmerge` under `@wordpress/scripts`' SVG loader, which has no fixed release. None of this reaches the zip.

`@wordpress/scripts`, `@wordpress/env` and `@lhci/cli` are already on their latest major. The "fix" `npm audit` offers for each is an older major, so the packages beneath them are overridden instead. Four of the overrides cross a major version of the package they replace (`basic-ftp`, `minimatch`, `rimraf`, `serialize-javascript`), so the whole toolchain was run against them before they were committed: a fresh install, the standards, the build, StrykerJS, a fresh `wp-env start --update`, the integration suite, Plugin Check, four browser projects, Lighthouse, the zip's lifecycle and the release dry run.

An override is dropped when the package above it ships the fix itself: `npm ls <package>` shows it, and issue #9 holds the list.

Four more overrides clear five of the seven deprecation warnings an install printed:

- `eslint` 10, the version `@wordpress/scripts` lints with. A second copy, 9.39.5, was installed only to satisfy `eslint-plugin-import`'s peer range.
- `chrome-launcher` 1 under `@lhci/cli`, the version Lighthouse itself uses, in place of 0.13 with its `rimraf` 3 and `glob` 7.
- `source-map-loader` 5, in place of 3 with its `abab`. A webpack development build carried a source map through it.
- `@octokit/webhooks` 13 under wp-env's Playground runtime, which no longer uses `@octokit/webhooks-types`. This repository runs wp-env on Docker and never loads it.

Two warnings stay: `inflight` and `glob` 7, from `npm-packlist` 3, which `@wordpress/scripts` uses for its `plugin-zip` command alone. Every later `npm-packlist` either keeps a deprecated `glob` or drops the function `plugin-zip` calls, so an override would break that command. This repository builds its zip with `composer build`, but a broken tool in the tree is worse than two lines in an install log. Zach agreed on 9 November 2026.

**Why:** Zach pasted the install's output on 9 November 2026. A dev-only fault cannot reach a shop, but it runs on the machine and the runner that build the zip, and the fixes existed.
**Status:** Accepted. Whether the whole tree should fail the build is still Zach's to decide (D-044).

### D-047 · The integration suite has a wp-env environment of its own · 2026-11-09

wp-env 12 warns on every start that its built-in tests environment, `testsPort` and `env.tests` are deprecated, and names the replacement: a second config file, started with `--config` (`node_modules/@wordpress/env/lib/commands/start.js:97`, and its README, "Running parallel environments"). So:

- `.wp-env.json` is the browser site alone, on :8898, with `"testsEnvironment": false`.
- `.wp-env.tests.json` is the integration site, on :8899: the same WordPress, WooCommerce and PHP, without Plugin Check, the extra themes or the browser tests' locale file.
- `npm run env:start`, `env:stop` and `env:clean` act on both. `npm run env:oldest` and `env:canary` write the same pins for both (`.wp-env.override.json` and `.wp-env.tests.override.json`), and `env:newest` removes both.
- `tools/integration.sh` runs the suite in the integration site's `cli` container. `tools/matrix-db.sh` and `tools/zip-lifecycle.sh` tell the two sites apart by the port each serves.
- CI's newest and oldest legs start both; the variants leg, which runs the integration suite only, starts the integration site alone.

The ports are D-019's. D-031 stands: the suite keeps its own tables beside the integration site's.

**Why:** Zach asked on 9 November 2026 for it to be done in milestone 1 and not left as issue #10. Nothing breaks on `@wordpress/env` ^12, but the option goes in a later major, and the harness is cheapest to move while it is small. Creative Search still uses the built-in tests site and shows the same warning; this repository does not change a sibling.
**Status:** Accepted.

### D-048 · What the audit of the post-review changes found · 2026-11-09

Everything changed after Claude in Cowork's review had been read by nobody but its author, and would not be in milestone 2's diff, so Zach had the `auditor` agent read `git diff 11ff4f5` before the merge. It found 1 blocker, 2 should-fix and 7 minor.

- **The release's build job installs Composer's packages.** `tools/release-dry-run.sh` reads the manifest back through the plugin's own class and so needs the autoloader; D-043's split of `release.yml` left the build job without it, and the first tag would have published nothing. CI's dry run could not show it, since its job installs first. The build job now runs the same pinned install step, and gate 10 fails a `release.yml` that runs the dry run without installing Composer's and npm's packages first. Checked on a clean export of the tree: without the install the script stops at the autoloader; with it the files are built.
- **What a release run still has to do is decided by `tools/release-plan.sh`**, with its cases in `tools/release-plan-selftest.sh` (part of `composer gates`). `info.json` never moves back: a run for a version older than the one it names, such as an old run started again, leaves it alone. A zip this run builds has a new checksum, so `info.json` is written again even when it already names the version (a release deleted by hand and built again). The `state` job, which holds the token, reports what is there; the `build` job, which holds none, runs the script; `publish` does what it says.
- **A release left as a draft is not a published release.** `gh release create` uploads to a draft and then publishes it; a run that died between the two left a draft that the next run took for the release. `state` now tells the two apart and `publish` removes the draft before it starts again.
- **The tag of a commit is looked for on every page** of the repository's tags, not the first hundred.
- **Gate 9 is an allow-list.** The first version listed tools to refuse, and six easy shapes got past it: the token in a workflow-level `env`, a comment after the job's name, `secrets['X']`, a script piped to a shell, a file from the build job run directly, and tools not on the list. A job that holds a secret may now use `actions/download-artifact` and no other action, may not name a shell, and every command its scripts run must be one of `gh`, `test`, `echo`, `printf`, `base64`, `jq`, `exit` and `true`; a secret named outside a job fails. `tools/gate-release.awk` reads the scripts word by word. Each of the six is a fixture, and a release workflow of the right shape is one that must pass.
- **Two tests that could not fail are fixed.** The fake queue counts plain scheduled actions, so "the retry stays unique outside the job" fails when the runner schedules a plain one. The floor notice's fallback moved into `Requirements::printNotice()`, tested with and without `wp_admin_notice()`.

One finding is answered and not changed. The audit showed a retry can go unscheduled: a manager's inline run fails in the moment between a retry coming due and Action Scheduler running it, the inline run's unique retry is dropped beside the pending one, and the pending one then finds the new wait and queues nothing. Action Scheduler runs only when a request reaches PHP, and any such request that cannot run inline queues the job once the wait has passed (`InstallHooks::queueWhenBehind()`, on `init`, WP-Cron's requests included). So the retry is late by one request at most, and the window needs a manager on an admin page, whose next page runs it inline. Scheduling from a waiting job as well would keep a duplicate action alive for as long as a migration keeps failing.

**Why:** A release path that has never run is the likeliest thing to fail on its first tag, and a guard that lists what to refuse passes whatever nobody thought to list.
**Status:** Accepted.

### D-049 · TypeScript 6.0, and what Dependabot's first run showed · 2026-11-09

Dependabot's first two pull requests arrived with the merge of milestone 1. The npm one failed twice, for two unrelated reasons.

- **TypeScript moves to 6.0, not 7.0.** Dependabot offered 7.0.2, and ESLint stopped with "typescript-eslint does not support TS 7.0". The newest `typescript-eslint` (8.72.2, the one `@wordpress/eslint-plugin` already carries, and its canary) accepts TypeScript below 6.1, so there is no newer linter to move to. 6.0.3 is the newest release the toolchain can lint, and replaces D-030's 5.9. `.github/dependabot.yml` ignores TypeScript 6.1 and later until that range moves.
- **`@types/node` stays on 24.** The types describe the Node the project runs (`.nvmrc`), so they move with it, by hand. Dependabot ignores their major versions.
- **The Plugins screen test has a page of its own.** In Firefox the test failed once on an uncaught `{ message, code }`: the screen's own request for `/wp/v2/users/me`, cancelled by the test's reload and reported to the listener attached just before it (the run's trace shows the request ending `NS_BINDING_ABORTED`). The reload was there so WebKit's errors for the dashboard being left were not heard; it moved the same race one page along. The test now opens a new page and listens from its first request, so no document is left while it listens. A deliberate page error still fails it.
- **`actions/github-script` 9.0.0** in the canary. Its breaking changes are to `require('@actions/github')` and a script's own `getOctokit`; the canary's script uses neither.

**Why:** A dependency pull request that cannot pass teaches everyone to ignore red ones, and a test that fails one run in many does the same.
**Status:** Accepted.

### D-050 · One coverage number for all of `src/` · 2026-11-09

Issue #6, as D-038 promised. `composer test:coverage:all` runs the unit suite with coverage, runs the integration suite with coverage inside the integration site, and merges the two reports line by line (`tools/coverage-merge.php`). The 90 % line floor is on the merged number, and `phpunit.xml.dist` no longer leaves any class out by name. On 9 November 2026 it reads 91.46 % (621 of 679 lines, 34 files); the unit suite alone covers 45.75 % of the same lines.

- **A line counts when either suite ran it.** The unit report lists every file in `src/` and names the lines that count. A line only the integration report lists is left out, so where PHPUnit 9.6 and 13 name a statement by different lines the number errs low, never high.
- **The lines that run while WordPress loads are recorded by the bootstrap.** PHPUnit measures from the first test, so the main file's `require`s, `Plugin::boot()` and every hook's registration read as never run. With `CBUILD_COVERAGE=1`, `tests/bootstrap-integration.php` has Xdebug record from its first line to its last and writes the lines to `tmp/coverage-bootstrap.json`, which the merge reads. Without them the number was 88.95 %.
- **The merge refuses to guess.** It fails when the integration report ran no line (a site started without Xdebug), or the bootstrap's lines are missing, and it deletes the last run's reports before it starts.
- **`npm run env:start` starts the integration site with `--xdebug=coverage`**, so the command works wherever the site is up. The browser site has no Xdebug.
- **`composer test:coverage` keeps the domain's floors** (100 % of lines and branches) and prints the unit suite's share without judging it.
- **In CI the newest leg runs it** in place of `composer test:integration`: that leg already has the site up, so the number costs no job (D-040). The oldest leg and the variants run the suite without coverage.
- **`infection.json5` keeps its list**, as agreed: mutation testing runs on the unit suite only.
- **Shown failing:** a 32-line class with no test, added to `src/Support` and then to `src/Repository`, took the number to 87.34 % and the command to exit 1 both times.

34 of the 58 lines not covered are one line each: the `exit` of every file's direct-access guard, which runs only when a file is requested outside WordPress. They are counted as not covered. Leaving them out would raise the number by five points and is a change to what the floor measures, so it is Zach's call, and not made here.

**Why:** A floor that leaves the updater and the repositories out by name says nothing about the code most likely to hurt a shop.
**Status:** Accepted. Supersedes the list in D-038 for line coverage.

### D-051 · The dev toolchain's advisories reach the canary, not pull requests · 2026-11-09

Issue #9's two calls, answered by Zach on 9 November 2026.

- The weekly canary runs `npm audit --audit-level=high` on the whole tree and opens its issue when red. Pull requests still gate only what the zip ships (D-044). The tree stands at 4 low, with no fix published (D-046).
- `@wordpress/scripts` stays on the major Creative Search uses, and moves when Search's does.

**Why:** A new advisory in a dev tool should reach Zach within a week without turning an unrelated pull request red, and at no cost in minutes.
**Status:** Accepted.

### D-052 · How the domain is written to meet its floors · 2026-11-09

The first domain classes (measures and their parser) met four things the floors and Plugin Check force on every class after them.

- **A measure is a whole number of ten-thousandths of its unit**, the precision of the index's `DECIMAL(10,4)`. Inches and millimetres compare exactly through a common step (a hundred-thousandth of a millimetre; an inch is 25.4 mm exactly). A conversion, a fifth decimal place and a fraction finer than four places (1/32 in) round half up.
- **Global functions are imported by name** (`use function trim;`). Since PHP 8.4 an unqualified call to some built-in functions compiles a branch for "a function of this name exists in the namespace", which no test can take, and Xdebug's branch coverage counts it.
- **An enum's values come from a table, not a `match`.** A `match` with no default compiles a branch for the case that cannot happen. The table is an untyped constant: PHPCompatibility reads a typed constant in an enum as a constant named `array`, and reports `$this` in an enum's method as PHP 7.1's error, so that one sniff (`ForbiddenThisUseContexts.OutsideObjectContext`) is off in `phpcs.xml.dist`. PHP and PHPStan both refuse `$this` outside an object.
- **A domain exception is made through a named constructor with no argument** (`InvalidMeasure::malformed()`). Plugin Check reads any argument to `throw new` as output to be escaped, and the domain has no WordPress to escape with. The message is the reason's code.
- **PHPStan kills mutants in the domain's mutation run** (`staticAnalysisTool` in `infection-domain.json5`). A mutant that removes a cast leaves code level 10 refuses, and level 10 on the domain is already a gate. The floor stays 100 %.
- **The domain's tests run a second time on their own**, with a bootstrap that is the autoloader and nothing else (`phpunit-domain.xml.dist`), which is the milestone's "done when".
- `tools/domain-branches.sh` lists the branches no test takes; `composer test:coverage` only counts them.

Also here: the browser site maps `tests/e2e/mu-plugins` as a folder. Mapped as one file, the locale mu-plugin vanished from the container whenever git rewrote the file.

**Why:** 100 % of branches and mutants is only worth holding if none of the misses is a phantom, so each phantom is removed at its source and not excused.
**Status:** Accepted.

### D-053 · The update popup shows the shop owner's changelog · 2026-11-09

Claude in Cowork checked 0.1.0 on the demo: the "View details" popup showed `CHANGELOG.md`'s bullets, with their class names and decision numbers. `tools/release-manifest.php` now takes a release's lines from `readme.txt`'s `== Changelog ==` entry for the version, which is written for shop owners, and fails when that entry is missing or carries a decision number, code or a path. `CHANGELOG.md` stays the developers' record. The release's notes on the releases repository are built from the same lines.

0.1.0's published manifest is left as it is; the popup reads the new lines from 0.2.0.

**Why:** The popup is the one place a shop owner reads about a release before installing it.
**Status:** Accepted.

### D-054 · R3's seeds stand after the check against their sources · 2026-11-09

The spec asks milestone 2 to check R3's seeded class limits (low 53 mm, mid 56 mm, high 56 mm) and riser table against its sources. Read on 9 November 2026:

- Hammer's guide gives the riser table as the spec has it: no riser up to 56 mm, ¼ in for 57 to 58 mm, ½ in for 59 to 62 mm, with 1¼ in bolts for a ¼ in riser and 1½ in for ½ in, which is also R4's table.
- Skate Warehouse UK gives 48 to 53 mm for low trucks, which is the low seed. For high trucks it gives 53 to 58 mm, 2 mm past the seed. It gives no range for mid.
- Switch says to consider risers from 57 mm, which agrees with a 56 mm limit.
- Thunder's truck styles page carries its figures in images, and nothing could be read from it.

Nothing changes. The high seed stays at 56 mm, Independent's own figure for its 55 mm Standard and Hammer's, and not Skate Warehouse UK's 58 mm.

**Why:** R3 only warns, so a limit 2 mm low costs a shopper a riser suggestion they can ignore, while a limit 2 mm high sells wheel bite without a word. The limit is a kit setting, and a maker's `max_wheel` on the truck overrides it.
**Status:** Accepted.

### D-055 · How the fit rules answer · 2026-11-09

Choices made while building R1 to R6, where the spec leaves them open:

- **A finding, not a verdict.** A rule returns a list of findings, each with its rule, severity, message code, values, source and the slots it read. No finding means the picks fit as far as that rule goes. A finding of severity `fits` is a note: a default taken for a spec the part does not give (`pattern_assumed`, `axle_assumed`, `limit_from_class`, `length_unchecked`), which the Catalogue check will list.
- **A missing spec is the part's own block.** A picked part that lacks a spec a rule needs gets `spec_missing` on its own slot, whether or not the other slot is filled, so a deck with no width is not offered at step one and does not go on to hide every truck.
- **R1.** A range is both `deck_min` and `deck_max`; a truck with one end only goes by its axle. The kit's `range_tolerance` widens a maker's range at both ends and is 0 unless the shop sets it. `axle_fit` (0.25 in) and `axle_warn` (0.5 in) are the kit's too.
- **R2.** A deck drilled for both patterns fits either baseplate, as a baseplate that takes both fits either deck.
- **R3.** How far over is measured, not counted in whole millimetres: anything over the limit and up to the first row's reach gets that row's riser. Past the last row the warning says no riser covers it, and a riser does not clear it.
- **R4.** 7/8 in, 1 in and the ¼ in of slack are constants, not kit settings: they are the trade's published table, and the spec gives the kit nothing to set here.
- **R5.** The mismatch's source is the maker when the truck gives its axle, and the standard when 8 mm was assumed.
- **R6.** A sheet needs `grip_length` only where it is held to a deck's length: the length check is on and the deck picked has a length.

**Why:** Each keeps the page able to say why (P1) and keeps a guess from being sold as a fit, without the rules knowing anything of step order, stock or price.
**Status:** Accepted.

### D-056 · The fit engine, and quantities · 2026-11-09

- **A slot's options are held against the picks before it**, in step order, as the spec has it. A pick in the slot itself or after it is not read, so going back to the deck step offers every deck, and what happens to the later picks is the change list's to say.
- **A whole build is held together**: each pick against every other pick. A pair that does not fit is found on both its slots, and a riser picked after the wheels clears R3's warning, which the wheels step on its own could not know. Whatever the steps offered, this accepts, since every pair was checked when its later part was picked; the invariant test holds the two to that.
- **A hidden part is in one group**, the one for its first blocking finding, grouped by rule and then by reason, so the counts add up to the number hidden. The group carries its first part's finding, for the figure a heading names ("too narrow for 8.5 in").
- **R7.** A build takes as many of a part as cover the slot: the slot's units over the part's `set_size`, rounded up. `set_size` is required on every part. One without it is not offered, with `spec_missing` under `r7`, which is the engine's own rule and not one a kit can switch off. The alternative, counting a missing set size as 1, would put two pairs of trucks in a build whenever a pair was entered without it.
- **The engine does not sort.** Options come back in the order the candidates were given. Sold-out parts after those in stock is the page's order, for milestone 6.

**Why:** One engine answers the step, the cart and the change list, and none of them needs to know how the others ask.
**Status:** Accepted.

### D-057 · The change list and the recommendation · 2026-11-09

**The change list.**

- It walks the slots after the changed one in step order and holds each later pick against the build as it now stands, swaps included, so a truck swapped for the wider deck is what the bearings are then held against. Slots before the change are kept and not asked about.
- A later pick is changed only when it is blocked. One that now warns is kept: a warning lets the build go ahead, and the step shows it.
- "Nearest spec" is measured on one spec per slot, the one a shopper picks that part by: deck width, axle width, wheel diameter, riser thickness, bearing bore, bolt length, grip width. A part with no figure for it is the furthest, and still better than an empty slot. A slot the rules do not know goes straight to brand and price.
- Then the same brand as the part picked, then the nearest price to it, then the first given. Only parts in stock that the slot offers, warned ones included.
- With nothing that fits, the slot is empty in the proposed build and the shopper picks again.
- It is a value: the build as it was, the build proposed, the slots kept and the changes. Applying or undoing it is the caller's.

**The recommendation.**

- "Fits" is the severity: a part that only fits with a warning is offered and never recommended, since the total counts a recommendation from the start (P9) and a warned part should be the shopper's own choice.
- "The middle of the slot's prices" is the median of every candidate's price, hidden and sold-out parts included, so one dear part does not pull it. For an even number it is halfway between the two in the middle. Where two parts are as near as each other, the first given.
- A slot the kit does not require gets a recommendation only when a finding among the picks before it reads that slot: wheels over a truck's limit name the risers slot, so the risers step then starts on the riser that clears the warning (P2). Otherwise an optional slot starts empty, whatever its default.
- With nothing fitting and in stock, there is none, and the shopper picks.

**Why:** Both ask the engine and decide nothing about fit; these are the choices the spec's sentences leave open.
**Status:** Accepted.

### D-058 · Money in the domain, and the build discount's rounding · 2026-11-09

- **Money is whole minor units of the shop's price decimals.** A part's price reaches the domain as an integer the server made from WooCommerce's price and `wc_get_price_decimals()` (`includes/wc-formatting-functions.php:568`), and the assembly fee is turned into the same units from its document. Nothing is added up as a float.
- **The build discount is basis points off each part's price for one**, rounded half up to the minor unit, and a line is that price times the quantity. The cart charges one price per line, so this is the figure `woocommerce_before_calculate_totals` will set in milestone 5, and the builder's total and the cart's are the same sum.
- **Half up, though `wc_round_discount()` rounds half down** (`includes/wc-core-functions.php:2488`; `WC_DISCOUNT_ROUNDING_MODE` is 2, `includes/class-woocommerce.php:549`). That function rounds a coupon's amount. The build discount is not a coupon: it sets a price, and WooCommerce rounds a price half up. Milestone 5 checks the cart's own totals against the domain's with the discount on.
- **The discount is from 0 to just under 100 %.** A part is never free because it is in a build, since at most one line of a build may be 0, the parent (spec: Pricing). The fee is never discounted.
- **A part with no quantity has no line.** The engine blocks it, so it is never sold, and it adds nothing to a running total.

**Why:** The spec says the discount is rounded per line to the shop's decimals and leaves the direction and the unit open.
**Status:** Accepted.

### D-059 · The build validator, and where the invariant runs · 2026-11-09

- **The validator is the engine asked about a whole build**, plus four things that are not fit: a pick for a slot the kit lacks, a required slot left empty, a part that is not one of its slot's parts, and a part that is sold out. It takes each slot's part ids from the server, so an id the browser sent for the wrong slot, or for a product the kit never listed, is refused. Everything wrong is reported at once, unknown slots first and then in step order. A build that only warns is valid.
- **The invariant** runs 200 seeded builds on a generated catalogue of 30 parts a slot, five ways: what the steps offer the validator accepts; what a step hides the validator refuses; the recommendations taken one after another make a build with nothing wrong but slots left to fill; a change list's proposed build has nothing wrong but slots left to fill; and the generator itself makes parts that fit, warn, are blocked, lack a spec, lack a set size and are sold out. A failure names its seed.
- **It is in the group `invariant`.** `composer test:unit` and the line-coverage run include it. The domain's branch run (Xdebug's path coverage) and Infection leave it out: path coverage turns its five seconds into more than ten minutes, and the 100 % floors are the case tests' to meet. A branch or a mutant that only a random walk reaches is not one a test has pinned down, so leaving it out makes the floors harder to meet, not easier. Both runs were at 100 % without it when it was added.

**Why:** The spec asks for the invariant and for the floors; this keeps both without one hiding gaps in the other.
**Status:** Accepted.

### D-060 · The picture's layout · 2026-11-09

The spec says what is drawn and by which spec; these are the choices it leaves.

- **The view and the units.** The board is seen from underneath with the nose up, as the layer images are shot. Every number is a whole ten-thousandth of a millimetre from the deck's centre, x to the right and y towards the nose, so a renderer's scale is one multiplication and no float is compared.
- **The stack.** The deck, the grip, the two trucks, the four wheels, in that order from the bottom. The kit's Picture section can reorder it in milestone 7. Risers, bearings, hardware and spacers are hidden by the board and have no layer.
- **Nothing is laid out before a deck with a width.** Every other position hangs off it. The labelled picture covers that case.
- **The trucks.** A wheelbase runs between the inner mounting holes, so a truck's middle is half the wheelbase and half the hole spacing from the centre: 2.125 in between the rows, 2.5 in on a deck drilled old school only. A truck is drawn its axle's width across and the spacing plus 0.875 in along.
- **The usual wheelbase,** when the deck gives none: 0.445 of its length when that is known (a 32 in deck gets 14.24 in), otherwise by width: under 8 in, 14 in; from 8 in, 14.25 in; from 8.5 in, 14.5 in. Street decks of 8 to 8.25 in average about 14.25 in, in a range of 14 to 15.5 in (SkateboardersHQ's and Concrete Waves' wheelbase guides, read 9 November 2026). A kit's own anchor overrides it in milestone 7.
- **The other usual values.** A deck with no length is drawn 32 in long. Trucks with no axle width, and wheels picked before trucks, take the deck's width. Wheels with no diameter are drawn at 54 mm. A wheel is drawn three fifths as wide as its diameter, its outer face at the axle's end.
- **The grip** is a 1 in strip the deck's length, flush with its right edge. The spec asks for a strip along the edge and says no more.
- **Every usual value is named.** The layout lists the specs it assumed, so the page and the Catalogue check can say the picture is approximate, and for which part.

**Why:** One layout for both renderers, exact to the specs where they are known and honest where they are not.
**Status:** Accepted. The constants are the domain's until milestone 7 shows them against real layer images.
