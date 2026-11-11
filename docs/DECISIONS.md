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
**Status:** Accepted. Revised by D-085: a draft's newest leg runs two browser projects, not every one.

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
- **R1.** A range is both `deck_min` and `deck_max`; a truck with one end only goes by its axle (revised by D-062: one end is a range, open at the other). The kit's `range_tolerance` widens a maker's range at both ends and is 0 unless the shop sets it. `axle_fit` (0.25 in) and `axle_warn` (0.5 in) are the kit's too.
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
**Status:** Accepted. Revised by D-064: a slot that takes one unit needs no `set_size`.

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
**Status:** Accepted. Revised by D-064: a truck is measured to the new deck, and a size the build asks for comes before the middle price.

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

### D-061 · The floors outside the domain, settled · 2026-11-09

Zach's, delegated: asked on 9 November 2026 about the questions left open for him, he answered "whatever is best practice for them all". Three changes follow, and each moves a floor up.

- **Infection outside the domain: 95 %, from 90 %.** The run is at 100 % (344 of 346 mutants killed by tests; the other 2 timed out, which Infection counts as caught). A floor ten points under the run lets ten points of regression through unseen. It is not 100 %, as the domain's is, because hook and adapter code produces equivalent mutants that only a contorted test would kill.
- **A file's direct-access guard leaves the coverage count.** The `exit` of `if ( ! defined( 'ABSPATH' ) )` runs only when a file is requested outside WordPress, where no test can be. Counting 34 such lines as not covered blurred what the number says about the code tests can reach. The rule is written once, in `tools/coverage-merge.php`, which recognises the guard as every file writes it (the ABSPATH test on one line, a bare `exit;` on the next) and leaves out that line alone. A first version marked each of the 34 lines with a coverage-ignore comment; Zach saw the repetition, and a new file could have forgotten its comment. A guard written any other way is still counted: `exit( 0 );` in one file took the count from 34 guards to 33 and added the line back.
- **The merged line floor: 95 %, from 90 %.** Leaving the guards out lifts the number from 95.69 % to 98.23 % (1,334 of 1,358 lines, 89 files). Left at 90 %, the floor would have become looser by the same change that made the number clearer.

The other two open questions stand as decided: the invariant stays out of the two measuring runs (D-059), and the layout's constants wait for milestone 7's layer images (D-060). This revises D-050's last paragraph and the 90 % figures in D-038 and D-050.

**Why:** A floor should sit close under what the code holds, and a rule should be written once.
**Status:** Accepted.

### D-062 · What the milestone 2 reviews changed · 2026-11-09

The spec review and the audit found seven should-fix gaps and a dozen minors. Zach asked for all of them fixed. The choices made in fixing them:

- **R1: one end of a maker's range is a range.** The spec's own seed table has "8.62 and up" and "up to 7.9". A truck with `deck_min` or `deck_max` alone is held to that end and is open at the other; the axle is asked only when the truck gives neither. The finding carries the ends that are published. This revises D-055.
- **The risers come before the hardware.** A slots document with both, and the hardware first, is refused with `slots_risers_after_hardware`. The bolts are picked to reach through the riser (R4), so with the bolts first, wheels that ask for a riser found every riser hidden by bolts already chosen (W2).
- **The assembly fee is in the shop's own minor units at any price decimals from 0 to 8.** It was clamped to four, so at five or more the fee and the parts' prices were added in different units. Above eight the domain refuses with `price_decimals_over_eight`: a sum that could leave whole numbers is not made. This adds to D-058.
- **R7's save-time check is the engine's.** The spec has quantities "checked when the kit is saved". The domain's part of it is `FitEngine::judge()`, which answers `spec_missing` under `r7` for a part with no `set_size`; milestone 4's Build tab asks it for each candidate when the kit is saved, and the Catalogue check lists what it finds. Nothing more is built for it here. This adds to D-056.
- **R8 is what the deck's option shows, and no rule.** It has no rule id, no code and no finding. A deck's `wheelbase` and `deck_length` ride on its option's part, a deck without them is offered all the same, and a test pins both.
- **A preset fills every slot the kit requires.** The spec says "every slot"; an optional slot (risers) may be left empty in a preset as it may in any build, and a preset that leaves a required slot empty is refused.
- **What the domain bounds and what it leaves to the schema.** The domain now holds every bound the schemas state: id lists to 500, each text to its length in characters, an attribute's taxonomy to its form. A spec's count stops at 1,000 and a measure of nothing is refused. A document that never met the schema (a direct meta write, an import) meets the same limits.
- **A spec key this version does not know is passed over in the index.** It was refused, which would have cost a part every spec it has when a newer version's row survived a rollback. Imports and the Part specs panel check keys at the edge, against `SpecKey`.
- **Refusals are typed, and finding values are named.** No exception of PHP's own is made in the domain (gate 12); each refusal has a reason code, listed in `docs/HOOKS.md` and held by `api:check`. A message code names the values it may carry and a finding may not add one, so a translation's placeholders cannot drift.
- **One place each** for the blocking findings among some (`Finding::blocking()`), rounding half up and reading decimal digits (`Measure\Rounding`), and the hole patterns and height classes (two enums the spec keys' choices are read from).
- **No words in the domain is a gate** (11), with snippets it must reject, in place of the test the plan named: a gate reads every file, and a test reads what it is pointed at.
- **A run that measures nothing fails.** The level 10 run, the branch floor, the 100 % mutation run and the benchmark each skipped while the domain or the first benchmark did not exist. Each now fails when it finds nothing to measure, from one check (`tools/domain-present.sh`), and the gates' self-test runs each against an empty folder.
- **The version moves with the first item dated to it.** `api:check` fails when `docs/HOOKS.md` dates an item to a version newer than the plugin header's, or `readme.txt` has no entry for the header's version. The branch said 0.1.0 with 47 items dated 0.2.0, and the first failure would have been the tag.
- **Mutants caught by static analysis.** After the fixes 4 of 870 are caught by PHPStan and not by a test, down from 10. All four remove a cast or a null-safe arrow where PHP would do the same at run time; none changes what the code does. The three that did change behaviour (a filtered list left with gaps in its keys) are now held by tests. Whether static analysis should count towards the domain's 100 % at all (D-052) is put to Zach in the review.
- **Looked at and left:** `Kit\Fields` is over 400 lines. It is one reader with one job, a method per kind of field, and splitting it would scatter that. The parser reads the one decimal separator it is given; whether the edge tries both for an attribute term is milestone 3's to settle with the attribute mapping.

**Why:** Each was a place the code and what is written about it had come apart, or a check that could pass without checking.
**Status:** Accepted.

### D-063 · Static-analysis kills are listed, and a domain file only declares · 2026-11-10

Zach asked for best practice on the two questions milestone 2's review put to him.

- **A mutant static analysis alone catches counts only if it is on a list.** Infection counts a mutant PHPStan refuses as caught (D-052), which is sound for a mutant that cannot differ at run time and unsound for one that can: three of the ten before the review changed behaviour with no test noticing. `tools/infection-static-allowed.txt` names the four that remain by mutator and file, each a cast or a null-safe arrow PHP makes no use of at run time. `tools/infection.sh` fails on any other, in a full run or a pull request's, until a test catches it or it is looked at and listed with its reason. Ignoring the four in the code instead would have put four notes in the domain to say the same thing.
- **A domain file has no direct-access guard, and gate 13 is why that is safe.** The guard's job is to stop a file doing anything when it is requested outside WordPress. The domain cannot name WordPress's constant (gate 6), and a guard there would make the domain's own tests need a shim. So the property is held directly: gate 13 fails on any statement at the top of a file in `src/Domain` that is not `declare`, `namespace`, `use` or the declaration of a class, an interface, an enum or a trait. A file that only declares runs nothing when requested. Every other file in `src/` keeps its guard.

**Why:** In both, a claim the review made in words ("none changes behaviour", "each only declares") is now a check that fails when the claim stops being true.
**Status:** Accepted.

### D-064 · What Claude in Cowork's review of milestone 2 changed · 2026-11-10

Cowork found no blocker, four should-fix gaps and eight minors. All are fixed but two that are notes for later milestones.

- **A slot that takes one unit needs no `set_size`.** D-056 required it on every part. In a one-unit slot the quantity is 1 whatever the part holds, so the requirement bought no safety there and would have hidden every deck and grip sheet on Skatehouse until the field was filled in. `r7` still blocks a part with no `set_size` in a slot that takes more than one unit, where guessing 1 would double a pair. The spec's "What Skatehouse needs" table now lists `set_size` for trucks, wheels, risers, bearings and hardware. Zach agreed to the one-unit change in Cowork's review. This revises D-056.
- **The risers step starts on the riser the wheels ask for.** R3's warning names a thickness. Among the parts that fit and are in stock, one that covers the size asked for comes first, then the nearest to it, and only then the nearest the middle price. The kit's default still comes before all of that. Nearest the middle price alone could start the step on a ½ in riser no stocked bolt reaches through. `Recommender::ASKED` maps a message code to the value that names the size and the spec it is measured on; R3's is the only one today. This revises D-057.
- **A swapped truck is the one nearest the new deck.** "Nearest spec" was measured to the part replaced, so an 8.0 in deck's truck changed for an 8.5 in deck proposed the 8.375 in axle next to the old one, not the 8.5. For the trucks slot the target is the deck's width in the build as it now stands; with no width on the deck it is the old truck's axle, as before. Other slots are unchanged. Zach leaned this way in Cowork's review. This revises D-057.
- **A row of the index this version cannot read is passed over**, whatever is wrong with it: a choice it does not offer, a count out of range, a measure of nothing, a value in another form. D-062 did this for an unknown key only. `Specs::fromIndex()` no longer throws; the part keeps its other specs, a rule that needs the missing one answers `spec_missing`, and the Catalogue check lists it. What a person types is still refused at the edge, by `Specs::with()` and `MeasureParser::parse()`.
- **A missing spec has the source `none`.** It was `maker`, though no figure came from a maker. Sources go on the page from milestone 4.
- **A whole number followed by a fraction of one or more is refused** ("8 5/4"). A fraction alone may still be improper ("9/8"), as hardware is sometimes written.
- **The schemas' patterns end with `(?!\n)$`.** WordPress runs a pattern without the D modifier, so a bare `$` let a trailing line break through that the domain then refused.
- **Gate 13 refuses a namespace or a declare with a block of its own**, since what is inside the braces runs as the top of the file does.
- **The mutation script deletes the domain's report before the domain's run, and checks the static-analysis list only when the run wrote one.** A pull request that changes no line of the domain mutates nothing there, Infection succeeds without writing a report, and D-063's check then failed for want of one. The gates' self-test holds both halves with a stand-in for Infection.
- **Left as notes.** A part's stock is a yes or no in the domain; a build that takes two of a part with one in stock is stopped by WooCommerce at the cart, not by the builder. Carrying the count is milestone 3's and 5's (issue #14). And from milestone 3 each rule's or fix's failing cases are committed before the code, so the red run is in the history: put to Zach in the review, since it changes "keep commits small and green".

**Why:** Each should-fix was a way the first shop would have met an empty step or a red pull request.
**Status:** Accepted.

### D-065 · Every commit is green, and the red run is recorded in it · 2026-11-10

Cowork asked that from milestone 3 a rule's or a fix's failing cases be committed before the code, so the red run is in the history. Zach asked for whatever is best practice.

- **No commit fails.** The history stays bisectable and every commit can be built, which is what trunk-based work and `git bisect` depend on. A red commit, or a test marked as expected to fail and unmarked a commit later, breaks the first or adds churn for the second.
- **The cases are still written first, and the commit says so.** A test and the code that makes it pass go in one commit. Its message carries the red run: the test's name and the line it failed with before the code was written.
- **That a test bites is shown by breaking the code, not by the order of commits.** The mutation floors do it for every line on every pull request, and the review's deliberate-break table does it by name for each blocker and should-fix. An order of commits shows a test once failed; a mutant or a break shows it would fail today.

**Why:** The thing wanted is proof that each test can fail for the right reason. Mutation testing and a recorded break give that without a broken commit.
**Status:** Accepted, on Zach's "whatever is best practice and industry standard".

### D-066 · The object-cache leg runs on Redis, and the plugin's cache rule · 2026-11-10

Issue #4: the object-cache leg called `wp_using_ext_object_cache( true )` and ran no cache server, so nothing was shared between two PHP processes. Zach asked for the industry standard, and for the plugin to work with other WordPress caches too.

- **The leg's cache is Redis with Redis Object Cache's drop-in.** `tools/object-cache.sh up` starts `redis:8.10-alpine` on the integration site's Docker network, installs Redis Object Cache 3.0.0 from WordPress.org (its zip checked against a pinned SHA-256) and puts a drop-in in `wp-content`; `down` removes all of it. `composer test:integration:object-cache` and the variants script do both around the run, so no other leg meets the cache. The client is Predis, which the plugin carries, so wp-env's PHP image needs no extension.
- **The drop-in is a three-line file of ours** (`tools/object-cache/object-cache.php`) that names the server and loads Redis Object Cache's own, unchanged. The suite's process reads `wp-tests-config.php` and the WP-CLI processes its tests start read `wp-config.php`; a setting in the drop-in reaches both.
- **A bare `CBUILD_OBJECT_CACHE=persistent` is refused** by the bootstrap when the drop-in is missing, and `ActivationTest` proves on every leg what a second process reads: the value on this leg, nothing on the others.
- **The plugin's rule is in `docs/STANDARDS.md` (WordPress: Object cache).** In short: only `wp_cache_*` in the `cbuild` group; a miss is answered from the database; plain values; entries go out of date by key, never by a flush; `wp_cache_flush_group()` and the `*_multiple` functions only behind `wp_cache_supports()`. Gate 2 now rejects `wp_cache_flush()` and `wp_cache_flush_runtime()`.
- **One back end is tested, and the rule covers the rest.** The drop-in interface is the same whatever is behind it. Where back ends differ is the optional features (`flush_group` is missing from the Memcached drop-ins and from some of LiteSpeed's and W3 Total Cache's modes), the serialiser, and whether an entry outlives the request. The rule keeps the plugin off each of those. A second cache server in the matrix would cost a suite's run per pull request to test code the plugin does not have.

**Why Redis:** it is what managed WordPress hosts offer for the object cache (Valkey, its fork, speaks the same protocol and the drop-in supports it), and Redis Object Cache is the most installed drop-in that does only this (500,000 active installs, tested to WordPress 7.1.4; WordPress.org, 2026-11-10). The Memcached drop-in on WordPress.org was last updated in 2022. LiteSpeed Cache (7 million installs) and W3 Total Cache include an object cache that is itself Redis or Memcached behind the same drop-in interface. The Skatehouse demo has Redis Object Cache installed, and Creative Search was checked against the same pair (its D-051).
**Differs from Creative Search:** its `object-cache` leg still sets the flag only. The sibling repository is not changed from here.
**Status:** Accepted, on Zach's "figure out the industry standard for the object cache and make sure its compatible with other wordpress caches too".

### D-067 · A Hookable is built only where its hooks can fire; the migration's status is an enum · 2026-11-10

Issues #3 and #2, from the milestone 1 audit. Zach said to do both now.

- **Each Hookable has scopes.** `Container::hookableClasses()` maps each class to a list of `Scope` cases: `Everywhere`, `Admin`, `Cli`, `Cron`, `Rest`, `Storefront`. `Plugin::boot()` asks WordPress what the request is (`is_admin()`, `WP_CLI`, `wp_doing_cron()`) and builds the Hookables in those scopes. One in no scope of the request is never built, nor are its services.
- **REST Hookables are built on `rest_api_init`, at priority 0.** WordPress defines `REST_REQUEST` in `rest_api_loaded()` on `parse_request` (WordPress 7.1.4, `wp-includes/rest-api.php:478`, hooked at `wp-includes/default-filters.php:549`), long after `plugins_loaded`, so a REST request cannot be told at boot. `rest_api_init` fires only when a REST server is made (`rest-api.php:657`), and a callback added at priority 0 can still add its own at 10. At boot a REST request counts as `Storefront`.
- **A Hookable is handed out once.** One with two scopes (`Storefront` and `Rest`) is registered at boot and not again at `rest_api_init`. The container keeps the list, so it is tested without WordPress.
- **Admin includes admin-ajax.php and admin-post.php,** as `is_admin()` does. A Hookable that must not run on AJAX says so in its callback's service, as the inline rule does.
- **Registering lazily was the other way:** every Hookable declares its hooks statically and a closure builds it when one fires. It builds even less, and it costs a second way to declare hooks, `has_action()` on a closure in every test, and filters whose arguments must be declared twice. Scopes keep `register()` as it is.
- **The migration's status is `MigrationStatus`,** a string enum whose values are what `cbuild_migrations` already stores, so no stored state changes. `MigrationState`'s four constants are gone; they were not in the public API.
- **The inline rule is `Install\Migrations\InlineRule::allows()`,** a service `InstallHooks` is given. `InstallHooks::inlineAllowed()` is gone; it was not in the public API either.
- **The asset budget's compression is named where the budget is stated** (Brotli at quality 11; `CLAUDE.md`, `docs/AUDIT.md`, `docs/BENCHMARK.md`), the second half of issue #3.

**Why:** milestone 3 adds about eight Hookables, most of them admin screens and WP-CLI commands. Without scopes a shopper's page would build every one of them and every service they take.
**Status:** Accepted.

### D-068 · How the specs index is stored and read · 2026-11-10

The table is milestone 1's and does not change. These are the choices the repository makes inside it.

- **A measure, a flag and a count go in `number`; a choice goes in `text`.** A measure is stored in its key's unit, as the four-place decimal the domain writes (`Specs::toIndex()`), which is the column's own scale, so nothing is rounded on the way in or out. A flag is 1 or 0 and a count is a whole number; the repository hands both back as whole digits, which is what `Specs::fromIndex()` reads.
- **One row type for the port, `Domain\Spec\IndexedSpec`:** the key, the value as text, the source (`Domain\Spec\Origin`: `field`, `attribute`, `table`) and what in that source. The domain holds them because which source wins is the domain's rule, and comes with the indexer.
- **Replacing a part's specs is two statements,** a `REPLACE INTO` for every row and a `DELETE` for any spec no longer there, with no transaction. `REPLACE` runs the same on MySQL 8.4 and MariaDB 10.11 (the `VALUES()` form of `ON DUPLICATE KEY UPDATE` is deprecated on MySQL). Between the two a reader may see a spec the part has just lost beside its new values. The index is derived, and the cart checks a build again when it is added, so that moment costs nothing a transaction would be worth.
- **A row this version cannot read is passed over,** in the repository as in the domain: a key or a source a newer version wrote, or a value in the wrong column. After a rollback the part keeps every spec this version knows.
- **The plans are checked in the integration suite, not the benchmark group.** The roadmap puts the `EXPLAIN` cases in the benchmark group, which is the unit suite's and has no database. `SpecIndexRepositoryTest::test_every_read_uses_an_index` asks the database for the plan of each statement the repository runs, over 1,800 rows, on every leg of the matrix: the key used, no full scan, no filesort. The two candidate statements are constants of the repository, so the plan asked for is the plan of what runs.
- **One set of cases for the repository and the in-memory index** (`tests/Support/SpecIndexCases.php`), so the fake every service's unit tests use cannot drift from the table.
- **`InlineRule` joins the classes mutation testing leaves to the integration suite** (`infection.json5`). It reads only WordPress (`is_admin()`, `wp_doing_ajax()`, `current_user_can()`), and `InstallHooksTest` proves each branch. D-067 should have listed it.

**Status:** Accepted.

### D-069 · A part's sources: what each returns, and where the winner is chosen · 2026-11-10

- **A source reads; the domain chooses.** `Port\SpecSource::read()` returns, for each part asked about, a `Reading` for each value it can read and an `Unread` for each it holds and cannot. `Domain\Spec\Resolution::of()` takes every source's readings and the shop's `SourceOrder` and keeps one per spec. So which source wins is tested without WordPress at the domain's floors, and a source added later adds no rule.
- **Every typed or stored value is read in one place,** `Reading::from()`: a measure through `MeasureParser` with the separator and unit it was typed in, a choice, a flag (`1`, `yes`, `true`, `0`, `no`, `false`) or a count, then checked by `Specs::with()`. The panel, the import, the CLI and the attribute source will all call it, so 8.25, 8,25 and 8 1/4 are one value at every edge.
- **A disagreement is two readings whose stored text differs.** 8.5 in from the field and 215.9 mm from a chart are the same four-place value in the key's unit and do not disagree. The reading kept is listed with each that says otherwise.
- **Of two readings from one source the first wins,** and the other is a disagreement: two attributes mapped to one spec is a shop's mistake the Catalogue check should show, not an error.
- **The default order is the chart, the field, the attribute** (spec: "the maker's chart, the field or the attribute; the chart by default"). A saved order that is not every source exactly once reads as the default, so a setting from a version with another source cannot leave one out.
- **Nothing is stored about a disagreement or an unread value.** `SpecIndexer::resolve()` gives both without writing, and the Catalogue check will call it when asked. The index table stays as milestone 1 made it.
- **A part no source speaks of ends with no row,** which is also how a deleted product leaves the index: its job runs, the sources find nothing, and the rows go.
- **No `cbuild_spec_sources` filter yet.** The plan expected one. Nothing uses it, and a hook is public from the day it ships. It is added when another plugin has a source to add.

**Status:** Accepted.

### D-070 · The field and attribute sources on WooCommerce · 2026-11-10

- **A part's own specs are one meta value, `_cbuild_specs`:** an array with a value per spec key, each as the index stores it (a point for the decimal, the key's unit). The spec says "one value per spec key"; one array keeps a part's specs in one row of meta and one read. What a person types in the shop's separator or as a fraction is turned into this form by the writer that comes with the panel, so the source never guesses a separator.
- **A variation takes its parent's value spec by spec,** not all or nothing: a variation with its own width and no length has the parent's length. The reading's reference is `parent`, which the panel will show as where the value comes from.
- **A mapped attribute is read from `WC_Product::get_attribute()`,** which gives a variation's own value and a product's terms by name, for a taxonomy and for a product's own attribute alike. A product with several values for a mapped attribute has no single value; the text does not read, and the source reports it as unread, so the Catalogue check can show it.
- **A term's name is read with the shop's decimal separator, then with a point.** Terms are typed by people over years: a French-Canadian shop has "8,25" beside "8.5". The field needs no such fallback, since its writer stores one form.
- **A batch is loaded in one `wc_get_products()` call, and its variations' parents in a second,** through WooCommerce's CRUD, of every type and status. A draft or private part is indexed like any other; whether a part is offered is the kit's question, in milestone 4. `SpecSourcesTest` holds the load to fewer queries than parts.
- **The settings are read defensively** (`Adapter\OptionShopSettings`): whatever the option holds, the answer is a valid setting. The screen that saves it, with its schema, is this milestone's step 7.
- **The settings option exists before its screen.** `docs/SETTINGS.md` said the option arrives in milestone 4; D-026 puts these two keys in milestone 3, since the index cannot be built without them.

**Status:** Accepted. D-089 revises how a mapped attribute is read: by its own key, and from the parent for a variation that has none.

### D-071 · The index's jobs · 2026-11-10

- **One job per product, deduplicated by its arguments, not by the unique flag.** Action Scheduler's unique flag refuses an action while another with the same hook, group and arguments is pending or running (4.1.0 in WooCommerce 11.2.2, `classes/data-stores/ActionScheduler_DBStore.php:218-233`). Its function's comment names the hook and group alone (`functions.php:14`), which is what the plan read; the query compares the arguments too, so two parts saved together would both be queued either way (corrected in the milestone's audit). What the flag gets wrong for a part is the running action: a save while the part's job runs would be dropped. `Queue::enqueueDistinct()` queues unless an action with the same hook and arguments is pending. A running one does not count: it may already have read the product, so a save during it needs a job of its own.
- **The save hooks are WooCommerce's four,** `woocommerce_new_product`, `woocommerce_update_product`, `woocommerce_new_product_variation` and `woocommerce_update_product_variation` (WooCommerce 11.2.2, `includes/data-stores/class-wc-product-data-store-cpt.php:263` and `:398`, `class-wc-product-variation-data-store-cpt.php:174` and `:279`), and for a delete WordPress's `deleted_post` (WordPress 7.1.4, `wp-includes/post.php:3996`), which fires however the product was deleted. The hook does nothing but queue.
- **A variable product's job indexes its variations and drops its own rows.** The parts are the variations; the parent is never one. A product that is gone has no parts, so its job only removes rows.
- **A reindex walks the index's rows first, then the shop's products by page.** The first pass is what removes rows whose product was deleted while the plugin was off. Both passes read the sources again, so a batch run twice writes the same rows. Its place is saved after each batch in `cbuild_reindex_state`, and each batch queues the next plainly, since the unique check counts the action that is running.
- **A delete during the page pass makes the reindex pass once more.** Pages are by offset, so a delete moves every later part back one and a part could be missed. A second pass costs less than keying the catalogue by ID would cost in a query outside WooCommerce's CRUD.
- **A request during a batch wins.** Each request counts the state's `run` up by one. The batch reads the state again before saving; if the run is no longer its own, it leaves the new state alone and queues the next batch, since the request's own unique job was refused while this one ran. The run is a count, not the start time, because two requests can fall in one second.
- **Failures:** a part's job is tried again after 1, 2 and 4 minutes, then thrown so Action Scheduler records it as failed. A reindex batch keeps its place and waits 1, 2, 4 and 8 minutes; the fifth failure in a row stops it with the phase `failed`, which Site Health will show. Each failure is logged with the part or the batch.
- **No migration asks for a first reindex.** Before 0.3.0 no part had a field or a mapped attribute, so there is nothing to index until the shop saves a product or the settings, and each of those queues its own work.
- **`Queue::cancelAll()` passes no arguments and no group.** With a group, `as_unschedule_all_actions()` cancels only actions whose arguments equal the ones given (WooCommerce 11.2.2, `packages/action-scheduler/functions.php:316` to `:328`), so deactivation would have left every part's job waiting. `IndexJobsTest::test_deactivation_cancels_the_parts_jobs_though_they_carry_arguments` failed on the old call. The hooks are the plugin's own, so no other group holds them.
- **The jobs' names are constants on `Service\IndexJobs` and on `Support\Options`.** A service may not use the support layer (deptrac); a unit test holds the two equal.

**Status:** Accepted.

### D-072 · Spec tables: what a row stores, how a part names one, and the seeds · 2026-11-10

- **A table is a post of the type `cbuild_spec_table`:** the title is the maker's name, and the rows are one meta value, `_cbuild_rows`. A row has a size, a value per spec key as the index stores it, a source and the day it was read. A row may hold any spec key, so a chart with heights or wheel limits needs no change here. Only a published table feeds the index; a draft is a chart still being typed.
- **A part names a table and a size in one meta value, `_cbuild_spec_table`:** the table's ID and the size's name. A variation takes its parent's table or size where its own is blank, each on its own, so a variable truck names the chart once and each variation its size.
- **A name that leads nowhere is reported, not dropped.** A table that is gone, binned or a draft is `table_missing`; a size the table has no row for is `size_missing`. The source returns each as an unread value, which the Catalogue check will list.
- **Any change to a table indexes every part again.** The spec says a change re-indexes every part that names the table. Finding those parts needs a query on a serialised meta value outside WooCommerce's CRUD; the reindex is already resumable, runs in the background, and a chart changes a few times a year. It hangs on `wp_after_insert_post` (WordPress 7.1.4, `wp-includes/post.php:6021`), which fires after the post's meta is saved, and for the bin and back; and on `deleted_post` (`:3996`).
- **The post type is the shop's record and nobody's page:** not public, not publicly queryable, out of search, sitemaps and the REST API, with every capability mapped to `manage_woocommerce`. `show_ui` is off until its screen lands in this milestone. `SpecTablesTest` asserts each, with the visitor's request parsed as WordPress parses it (`wp-includes/class-wp.php:376`).
- **The seeds are the spec's table under R1, read again on 10 November 2026.** Independent's own chart gives every figure the spec has. Tactics gives Thunder's 145, 147, 149 and 151 as the spec has them. Rollersnakes gives Venture's five sizes as the spec has them, with one difference: the 5.0's axle is 7.62 in there and 7.6 in the spec's grid, whose rows group makers by axle. The seed holds 7.62, the source's figure. Thunder's 143, 148 and 161 were on neither page read that day; their rows hold the spec's figures, name Warehouse Skateboards as the spec does, and have no day. Both are in the review's For Zach.
- **A seed that has a table is passed over,** whatever state the shop left it in, by the meta `_cbuild_seed`. So the migration run twice, or again after being cut off between two tables, makes each once and overwrites nothing. One deleted for good is not remade on that shop: a migration that is done does not run again.
- **`get_post_meta()` is allowed in `Adapter\PostSpecTables` alone.** The analysis rule keeps products and orders behind WooCommerce's CRUD; a spec table is neither.
- **Uninstall deletes the spec tables first.** Deleting a post fires hooks, and a hook that writes an option or queues an action after those were removed would leave it behind. `UninstallTest` failed on the other order.
- **`MigrationRegistry::VERSION` is 2.**

**Status:** Accepted. D-093 reads Thunder's and Venture's rows from the makers' own charts.

### D-073 · The settings screen · 2026-11-10

- **A tab of WooCommerce's settings, `cbuild`, labelled Builder,** added on `woocommerce_get_settings_pages` (WooCommerce 11.2.2, `includes/admin/class-wc-admin-settings.php:64`). `WC_Settings_Page` is loaded on that screen alone, so the page's class is built inside the filter and nowhere else.
- **WooCommerce checks the capability and the nonce before a page's `save()`** (`class-wc-admin-settings.php:87` and `:91`). `save()` checks both again: it is a public method, and the rule is that the code that writes checks.
- **A save is all or nothing.** `Service\SettingsEditor` reads every line and the order, and stores only when there is no problem. A half-saved mapping would index the shop against lines the owner has not finished. A refused save comes back with what was typed and each problem beside its field, by a code the screen turns into a sentence.
- **No markup is built in JavaScript, so the screen has no script.** The saved lines are drawn with three blank ones after them, and a save adds three more. A line with its attribute and spec both cleared is removed.
- **The attribute is a text field with a list of the shop's attribute taxonomies** (`datalist`), not a select: a product's own attribute, which has no taxonomy, is mapped by its name, and a select could not offer it.
- **Each line is a `fieldset` named "Line N", and each control has a visible label of its own.** The first draft was a table with a header row. At phone width WordPress's admin styles stack a form table's cells, which left the three headers alone above unlabelled controls. Labels above their controls read the same at every width, and need no style sheet of the plugin's.
- **The adapter validates what it writes against `schema/settings.json`** with `rest_validate_value_from_schema()` and throws when it does not fit. The editor has already refused everything the schema would; the schema is the second lock, and the public statement of the option's shape. It allows other keys, so a later version's settings are not refused by an older schema, and has no `schema_version`, since a malformed key reads as its default.
- **`update_option()` fires its action only when the value changes,** so a save that changes nothing queues no reindex. `SettingsPageTest` asserts it.
- **The browser test skips where scripts are off.** WooCommerce disables its Save button until a script sees a change, so its settings cannot be saved by a click without one. The plugin's part, a plain form post, is covered by `SettingsPageTest`.
- **`phpcs.xml` lints `templates/`,** which exists from this step. A template's variables carry the `cbuild_` prefix where they are its own.
- **`SourceOrder::of()` returns null for an order that is not all three once;** `read()` keeps answering the default. The editor needs to tell a wrong order from the default.

**Status:** Accepted.

### D-074 · The Part specs panel · 2026-11-10

- **`Service\SpecWriter` checks a part's own specs, whoever sends them.** It reads each value as the index's sources do (`Reading::from()`) and answers what to store and what was wrong. The panel uses it now; the import and the command line will. What is stored is the index's own text, `8.2500`, so `8.25`, `8,25` and `8 1/4` are one value in every locale and the field source needs no separator.
- **A value that is wrong is not saved, what the part had is kept, and the rest saves,** as the spec has it for the Build tab. The problem goes to WooCommerce's own notices (`WC_Admin_Meta_Boxes::add_error()`), which the product screen prints after its redirect and the variations' save prints in its answer (WooCommerce 11.2.2, `includes/class-wc-ajax.php:3150`). WooCommerce prints them through `wp_kses_post()`, so each is escaped first: it quotes what was typed.
- **The panel shows every spec, in eight groups by kind of part,** each a `details` element that opens when it holds a value. The spec says it shows only the specs the product's kits need. A kit does not exist until milestone 4, which narrows the groups to what a kit's slots ask for; nothing stored changes then.
- **The tab is on variable products as well as simple ones.** The spec names simple products and variations. A variation left blank takes its parent's value, and without the tab the parent's values could only come from an import.
- **A value from somewhere else is a sentence beneath its field,** "In use: 8.5 in, from Independent's chart, size 149.", tied to the field with `aria-describedby`. The spec says it shows read-only; a disabled input is skipped by the keyboard and unread by some screen readers. The sentence is shown whenever the value in use is not the field's own, so a field the shop filled in that another source outranks says so too. The panel asks `SpecIndexer::resolve()`, the indexer's own reading, and decides nothing itself.
- **A maker's chart and a size are one list,** a group of sizes per chart, so a size cannot be chosen that its chart does not have, with no script. Stored, the two stay apart (D-072), and an import may store a chart alone or a size alone; the list then offers "Leave as it is" and stores nothing new unless another choice is made.
- **`SpecTables::all()`** lists the published tables for that field, in two queries, once per request however many variations are drawn. `PartSpecsPanelTest` holds it to that.
- **`Domain\Spec\StoredSpecs` reads what a part stores,** for the two sources and the panel alike. The two sources each had their own copy.
- **A key this version does not know is not taken from the form, and one already stored is left where it is,** as the index does (D-062's rule for a rollback).
- **Both save hooks check `edit_products` themselves,** as the spec says. WooCommerce's two callers of `save_variations()` each check a nonce first (`includes/class-wc-ajax.php:3137`, `includes/admin/class-wc-admin-meta-boxes.php:228`), as the plan found; any code can fire the hooks. A save whose request does not carry the panel's fields (a quick edit, another plugin's save) changes nothing.
- **One style rule, added to WooCommerce's admin style sheet with `wp_add_inline_style()`** on the product screen: the groups' headings get padding. axe failed the browser's own 18 pixel `summary` on target size (WCAG 2.2, 2.5.8). One rule does not earn a built file and a request.
- **The browser test skips where scripts are off,** since WooCommerce's product data box needs one for its tabs and its variations. `PartSpecsPanelTest` covers the plain form post.

**Status:** Accepted.

### D-075 · WooCommerce > Builder, and the spec tables' screen · 2026-11-10

- **The page is the plugin's own, `admin.php?page=cbuild` under WooCommerce, for `manage_woocommerce`,** added on `admin_menu` at 60, after WooCommerce's own entries. The spec puts three things there: spec tables now, the import and the Catalogue check in the steps that follow.
- **The post type keeps `show_ui` off.** D-072 said it would be turned on with the screen. WordPress's own editor for a post type edits a title and custom fields; a chart is rows of figures that must be checked together. The screen is the plugin's, and the post type stays out of the menu, the list tables and quick edit.
- **`Service\SpecTableEditor` saves a chart whole or not at all,** as the settings do (D-073): a chart half saved would give some trucks this year's figures and others last year's. Each figure is read as the index reads it and stored as the index writes it.
- **A chart is a group of fields per size, not a table,** for the reason D-073 found: at phone width a table's headers part from its cells. Saved rows are followed by three blank ones, and a save adds three more. A row with every field cleared is removed. A chart holds 100 sizes at most.
- **The screen shows the six figures the spec names** (axle width, the deck range, height, largest wheel, baseplate holes). A row may hold any spec key (D-072); one the screen does not show is carried through a save in a hidden field, and checked like the rest.
- **A form is handled before the page draws** (`load-{page}`), and a save that passes redirects to the page it leads to, so a reload does not send it twice. One that is refused draws the page in the same request, as typed.
- **Deleting is for good, after a page that asks.** With no screen for a bin there would be no way to bring a binned chart back or to empty the bin. The page that asks is a page, not a script's dialog, so it works with scripts off. Parts that named the chart keep the name; the source reports `table_missing` and the Catalogue check will list them. A seed that is deleted is not remade (D-072).
- **Every form checks `manage_woocommerce` and then its nonce** before it reads a field. The page itself draws nothing for anyone else, though WordPress has already refused them the menu.
- **Two style rules on the page, added with `wp_add_inline_style()`** to WordPress's `common` style sheet, as the panel's one is (D-074).
- **`Reading::typed()` reads what a person typed with the shop's separator, then with a point.** The spec says 8.25, 8,25 and 8 1/4 are the same; in a shop that writes a comma the parser read only the comma. The attribute source already tried both, inline. The panel, the charts and the attribute source now share the one method, and the import will.
- **`SpecTables` gains `update()` and `delete()`.** The adapter refuses a post that is not a spec table in both.

**Status:** Accepted.

### D-076 · The specs sheet: export, and an import that shows before it writes · 2026-11-10

- **The columns are `id`, `sku`, `name`, `spec_table`, `spec_size` and one per spec key, in English in every locale,** as the spec keys are, so a sheet from one shop or one language reads in another. `name` is for the reader and an import passes over it. `spec_table` holds a chart's name, not its ID, which differs from site to site; a name no chart has, or two share, refuses the row.
- **A variable product has a row.** `PartCatalogue::page()` leaves it out, since it is not a part; it names the chart its variations take their sizes from, and holds values they inherit, so `Port\PartSpecs` pages every product.
- **An export holds what each part holds itself,** not what the index resolved. Importing a sheet back then changes nothing, which `SpecImportTest` holds. One exception: a part that names a chart since deleted exports an empty name, and importing that row removes the name.
- **A cell is read as the Part specs panel reads a field,** through `SpecWriter`: a column the file leaves out is left as it is, an empty cell removes the value, and a value takes the shop's separator, a point or a fraction. The plan said the CSV always uses a point. An export does; an import also takes the shop's separator, since a spreadsheet in a comma locale writes `8,25` when it saves, and `Reading::typed()` already reads both.
- **A row with anything wrong in it is not written at all,** unlike the panel, where the rest of the product saves. On the panel a person sees which field failed; in a file of thousands of rows a part half written would pass for one that was done.
- **Parts are matched by `id` or by `sku`, the person's choice, never a mix.** Matched by ID, a row whose `sku` cell is not that product's SKU is refused: a sheet exported from a staging site carries IDs that are other products on the live one. A key in the file twice refuses both rows.
- **The file is read where PHP received it,** after its error code, size, `.csv` ending and `is_uploaded_file()` are checked (`Adapter\RequestUploads`). The plan said it would go through WordPress's own handler; `wp_handle_upload()` moves the file into the uploads folder, where it has an address until it is deleted. It is read with `WP_Filesystem_Direct`, since the functions that read a path also read a URL and are banned. The text must be UTF-8, with or without a byte-order mark, 2 MB and 5,000 rows at most, with commas or semicolons, which is told from the heading row.
- **The dry run is the rows, kept in the option `cbuild_import_state`, not autoloaded.** An option and not a transient: with a persistent object cache a transient lives in the cache, where a flush or an entry over the cache's size limit would lose a waiting import. One import is in hand per shop. A new file takes the place of one that waits; nothing takes the place of one that runs. One that waits is forgotten after an hour.
- **What a file would change is worked out when the page is drawn, and again by each batch that writes,** from the rows and what the shop holds then. Nothing checked an hour ago is trusted.
- **Only the person who sent the file confirms it,** by a token that names that import (the plan's risk: confirming someone else's dry run). Another manager sees it and may put it away. The user's ID is kept while the import waits and dropped when it starts (docs/PRIVACY.md).
- **A confirmed import is written in the background,** by the action `cbuild_import`, 100 rows at a time from a saved place: the demo's host has no command line, and 5,000 products saved in one request would time out. A batch that fails is logged and tried again after 1, 2 and 4 minutes; the fourth failure stops the import, with the parts before it written and the screen saying so. A batch run twice writes the same values; a row it had already written counts the second time as changing nothing.
- **A write saves the product through WooCommerce,** so its index job is queued as for any save, and the import indexes nothing itself.
- **`Service\Csv` is the plugin's own reader and writer,** a state machine over a string. PHP's `fgetcsv()` needs a stream, and `str_getcsv()` does not read a quoted line break across rows. Writing, a cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading apostrophe (OWASP's CSV injection guidance); reading, that apostrophe is taken off again.
- **The export is one request,** built 100 products at a time and sent as an attachment with a byte-order mark, so a spreadsheet reads accents. The benchmark in step 14 measures it at 2,000 parts.
- **The Builder page has tabs,** Spec tables and Import and export, links in a `nav`. The Catalogue check will be the third.
- **The browser test runs the queue itself,** `wp action-scheduler run --hooks=cbuild_import`, and waits for the page to say the import is done: the shop's own requests may have started the job first.

**Status:** Accepted. D-090 moves the rows into an option of their own.

### D-077 · The Catalogue check: what it reads, and what waits for kits · 2026-11-10

- **The check holds no rule.** A part's findings are `FitEngine::standard()`'s, asked of the part with no picks beside it, and a preset's problems are `BuildValidator`'s. A rule added to the engine is then in the check without a line changed here, and the two cannot come to disagree.
- **It reads the sources, not the index.** `SpecIndexer::resolve()` is what the indexer runs, so the check says what the index would hold if it were rebuilt now, and it finds two sources that disagree, which the index does not keep: it stores the winner alone.
- **Kits come through `Port\Kits`, which answers with none until milestone 4** (`Adapter\NoKits`). The spec's list for the check is per kit (parts that lack a spec, presets that no longer fit, slots with nothing in stock), and kits are milestone 4's. Those findings are written and tested now against kits made from documents (`InMemoryKits`, `KitDocuments`); the screen says the shop has no kit yet and what the check will add when it has. What is found without a kit is live now: disagreeing sources, values that cannot be read, a reindex or an import that stopped, and failed jobs.
- **A missing spec is said once per part and spec,** though two rules may each want it; and an assumed spec is a finding of its own (`spec_assumed`), since the part is still offered and the shop may be content with the usual.
- **Failed jobs are counted, to 100, through a new `Port\Queue::failed()`** (the roadmap's "failed jobs found"). The count is Action Scheduler's own, of failed actions in the plugin's group, and the finding links to WooCommerce > Status > Scheduled Actions, where each can be read and run again. Action Scheduler deletes failed actions after its retention period, so the finding clears itself.
- **The check runs when asked, in the request, by a post with a nonce,** and its findings are not stored. A get that read every part would run on every visit to the tab, and a stored result would be out of date the moment a part was saved. The page lists 500 findings and says how many more there are. How long 2,000 parts take is measured in this milestone's benchmark; if that is over the budget, the check moves to the queue.
- **`Domain\Kit\Part` gains `$stock`, the managed count or null for "not managed"** (issue #14's first half, Claude in Cowork's finding 11 of milestone 2). Nothing reads it yet: milestone 4's adapter fills it when it builds a `Part`, and milestone 5's validator checks it against what a build takes.
- **A variation's finding links to its parent's edit screen,** at `#cbuild_part_specs`, since a variation has no screen of its own. A part that is not a product of this shop is named by its ID with no link.

**Status:** Accepted.

### D-078 · `wp cbuild`: what each command does, and when it exits 1 · 2026-11-10

- **A command exits 1 exactly when a script should stop:** the file was not read, a row was not, a batch failed, or the check found something. `WP_CLI::error()` says the summary and exits; what was found is listed before it, so `--format=json` on standard output stays JSON and the sentence goes to standard error.
- **`wp cbuild specs import` without `--dry-run` writes at once.** The spec's "writes nothing until confirmed" is the screen's; at a command line the command is the confirmation and `--dry-run` is the showing first, as the spec's own example has it. A prompt would stop a script.
- **The rows that read are written though others do not,** as on the screen, and the command then exits 1 with the rows it left out. A script that wants all or nothing runs `--dry-run` first and stops on its exit status.
- **An import and a reindex run in the command's own process,** batch after batch through the job's own method, so the place is saved as the queue saves it and a command that is stopped is finished by the queue. The actions the batches queued find nothing left to do. `wp cbuild reindex --background` only queues.
- **A batch that fails stops the command** after the one failure, with the retry left to the queue, which waits a minute; the command does not sleep.
- **A dry run uses the one import a shop has in hand** (D-076) and puts it away when it ends, so it takes the place of a file a manager had waiting on the screen, as another upload would. One that is running refuses the command.
- **A file is a path on the server's disk ending in `.csv`** (`Port\Files`, `Adapter\DiskFiles`): a path with a scheme is refused before anything opens it, since `WP_Filesystem_Direct` reads through PHP's file functions, which follow `phar://` and `https://`. The plan's risk was "a path from the CLI read where it should not be". An export replaces a file that is there, as `wp db export` does, and is written with mode 0644 given outright: `WP_Filesystem_Direct::put_contents()` otherwise reads `FS_CHMOD_FILE`, which only `WP_Filesystem()` defines (`wp-admin/includes/class-wp-filesystem-direct.php:89`, `:170`; `CliTest` found it).
- **The commands are objects handed to `WP_CLI::add_command()`,** not closures, so WP-CLI reads each synopsis from its docblock (`wp help cbuild specs import`, which `CliTest` reads in a real process). `Hooks\CliHooks` is built only when WP-CLI runs (D-067).
- **`Admin\Sentences` holds the check's and the import's sentences,** moved out of the two screens, so the command line and the admin say one thing. The command line has its own words only for a file's path, where the screen speaks of choosing a file.
- **The help text in the docblocks is English,** as WP-CLI's own is: WP-CLI reads it from the source and has no way to translate it. What a command prints goes through the text domain.

**Why:** `docs/STANDARDS.md` asks for a docblock synopsis, examples, clear exit codes and `--format` where a command prints a table. The demo's host has no WP-CLI, so each command is a second way into something the admin already has.
**Status:** Accepted.

### D-079 · The ability `creative-skateboard-builder/catalogue-check` · 2026-11-10

- **It runs the screen's service and says the screen's sentences** (`Service\CatalogueCheck`, `Admin\Sentences`), under the screen's capability, `manage_woocommerce`. It takes no input and holds no rule.
- **It answers with a count and the first 500 findings,** as many as the screen lists, each with its code, IDs, the finding's values and its sentence. `values` is cast to an object so that an empty one is `{}` as JSON, which the output schema says it is.
- **It is shown in REST, and annotated read-only, not destructive, idempotent.** The spec gives abilities to "the shop's own tools and AI assistants", which reach them over REST; the permission callback guards it there as anywhere. WordPress serves a read-only ability by `GET` and refuses `POST` (`wp-includes/rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php:110`). A `GET` here reads every part, as the screen's post does; nothing is cached, since the answer is out of date the moment a part is saved, and only a manager can ask.
- **Its Hookable is built on every request and builds nothing else until the ability is run** (a closure for the check): WordPress fires `wp_abilities_api_init` when something first asks its registry, which can be any kind of request (`wp-includes/abilities-api/class-wp-abilities-registry.php:313`).
- **The category is the plugin's slug,** and milestone 4's `check-build` and `price-build` join it.

**Status:** Accepted.

### D-080 · Site Health: two tests that read no part, and the Info section · 2026-11-10

- **`cbuild_index` and `cbuild_jobs` are direct tests** that cost an option read and one query of Action Scheduler's indexed table each (`Service\Health`). They run when the Site Health page loads and in WordPress's weekly check, so neither reads a part; the Catalogue check, which does, is linked from a failed job's result and is not a test itself. The spec lists the Catalogue check among what Site Health checks: a test that read every part on each visit to the page would be the wrong cost, and the check has nothing kit-shaped to find until milestone 4. Whether its findings get a test of their own is a question for milestone 4's plan.
- **A failed reindex is critical; a failed job is recommended.** The first leaves parts indexed with old figures, shop-wide, until someone starts it again. The second is one part or one batch, already retried, and Action Scheduler clears it after its retention period.
- **A reindex under way with no action pending or running is "stopped part way"** (recommended). `as_has_scheduled_action()` counts pending and running actions (WooCommerce 11.2.2, `packages/action-scheduler/functions.php:406`), so a batch in progress is not mistaken for a stall. It happens when the queue's tables are cleared, or a host kills the request between a batch's save and its next enqueue.
- **A reindex's error text stays in the log** and is not printed on the page: it can hold a query or a path.
- **The Info tab's section** lists the version, the database version, the source order (with its codes as the copied debug text), the number of mapped attributes, the index's state and the failed-job count. Nothing is marked private since nothing is: no name, address or key.
- **The Hookable is built in wp-admin, cron and WP-CLI** (D-067), and its services only when a test runs or the Info tab is drawn.
- **The sibling plugins have no Site Health code,** so this follows WordPress's own filters: `site_status_tests` (`wp-admin/includes/class-wp-site-health.php:3047`) and `debug_information` (`wp-admin/includes/class-wp-debug-data.php:135`).

**Status:** Accepted. D-094 adds the Catalogue check as an asynchronous test.

### D-081 · The specs benchmark runs on real WordPress, and one query a product stays · 2026-11-10

- **`composer test:benchmark:integration` runs `tests/Benchmark` in the integration site with Xdebug off** (`tools/benchmark-integration.sh`). The roadmap puts the 2,000-part reindex in the integration suite; that suite runs with coverage on in CI, which would time Xdebug. The directory is outside the suite's, so the suite's runs and its coverage are as they were. CI runs it on the newest leg, on the site that leg already has up: seconds, and no job of its own.
- **It measures four things at 2,000 parts,** each with WordPress's cache emptied first: the reindex batch by batch, the export, the Catalogue check, and a 5,000-row file read and planned. The roadmap asks for the reindex to be recorded; the other three answer inside a request (D-076, D-077), so each has a ceiling: 10 seconds, a third of PHP's usual limit, on a machine several times slower than the one that measured 0.5 s.
- **Query ceilings are set from what the work is:** one query a product and a page's own (`parts × 1.1 + 60`), and under 400 for a reindex batch of 100. A change that adds a query a part fails the test.
- **The one query a product is WooCommerce's and stays.** Building a `WC_Product` reads its meta with a query of its own (`WC_Data_Store_WP::read_meta()`, WooCommerce 11.2.2, `includes/data-stores/class-wc-data-store-wp.php:83`), which the posts' meta cache does not serve; WooCommerce keeps the answer in the object cache where a shop has a persistent one. `get_post_meta()` on primed IDs would be one query a page, and would read past WooCommerce's CRUD, which the project's rules forbid and which a later product store would break. At 0.15 ms a query it is 0.3 s in 2,000 parts.

**Status:** Accepted.


### D-082 · What the audit changed: a reindex on install, upgrade and activation, and ways back from a stop · 2026-11-10

Found by the milestone's two reviews, each fixed with a test that fails without it (`docs/reviews/M3.md`).

- **Every part is read again after an install, an upgrade's migrations and an activation.** Until the audit nothing indexed a catalogue that was already there: the index filled a part at a time, as parts were saved. `Install\Activator` asks once its migrations are done, and `Hooks\InstallHooks` asks when a run that an admin page or the job finished brought the schema up to date, since an upgrade arrives with no activation. Deactivation cancels the plugin's jobs and hears no save, so activation asks again. The installer reaches the index through `Port\Reindex`, which `Service\IndexJobs` implements, so the Install layer still knows no service. A site activated while WooCommerce is missing has no queue to hold the job and is not asked for; the Catalogue check's button is the way there.
- **"Read every part again" is a button on the Catalogue check,** a post with the tab's nonce for who can manage WooCommerce. The demo's host has no WP-CLI, and the sentences that said "Save the Builder settings to start it again" were wrong: a save that changes nothing fires no hook. Site Health and the check now point at the button.
- **A deleted term asks for a reindex as an edited one does** (`delete_term`, WordPress 7.1.4, `wp-includes/taxonomy.php:2235`): no product is saved when a term it carried goes.
- **A mapped attribute is matched to a taxonomy as WooCommerce reads it:** by the name's slug, then by the slug after `pa_` (`WC_Product::get_attribute()`, WooCommerce 11.2.2, `includes/abstracts/abstract-wc-product.php:2278`). A mapping line that says `Width` reads `pa_width`, so a term of `pa_width` must ask too. `PartCatalogue::reads()` answers, since the slug is WordPress's to make.
- **An import left running with no job is given its job back** when Import and export is opened or a new file is read (`SpecImport::resume()`), and Site Health's jobs test reports an import that failed or stalled. Before, a deactivation mid-import left the screen refusing every file for good.
- **An import by SKU looks each SKU up once,** when the file is read, and keeps the matches in its state. Each batch checks that the product still holds the SKU, so a SKU that moved since is skipped, not written to the wrong part. Before, every batch asked for every SKU again.
- **A file is read to its row limit and no further** (`Csv::parse()`'s `$most`), so a file far over the limit is refused without being parsed whole.
- **Failed jobs are counted from when the last reindex finished,** by when each last ran (`modified`, `ActionScheduler_DBStore.php:627`): that reindex read every part again, and Action Scheduler keeps a failed action for three months. Before, one failure kept Site Health and the check amber long after it was mended.
- **The reindex's and the import's state are read from the table, past the request's cache** (`Adapter\FreshOption`): a long request would otherwise save its stale copy over another process's place.

**Status:** Accepted. D-089 revises the matching of a mapped attribute: a line names one attribute, and `Width` no longer reads `pa_width`. D-095 asks for the reindex a site without WooCommerce was owed.

### D-083 · The product screen's one script is inline · 2026-11-10

- **The Catalogue check's links open the tab or the variation they name.** WooCommerce's product screen opens no tab from the address, so the link used to land on General with the field out of sight. `#cbuild_part_specs` opens the Part specs tab; `#cbuild_variation_<id>` opens the Variations tab and, when the variation is on the page WooCommerce loads first, opens it and scrolls to its fields. A variation on a later page is left to WooCommerce's own paging.
- **It is twenty-odd lines added after WooCommerce's variation script** (`wp_add_inline_script()`), with no data in it and no markup built. `docs/STANDARDS.md` says the admin scripts are TypeScript built by `@wordpress/scripts`; the first entry point and its build land with the builder in milestone 6, and bringing the pipeline forward for this would change what the zip, the budgets and Plugin Check measure in a milestone that planned no script. It moves into the admin bundle when there is one. **This is Zach's standard to keep or bend:** raised under For Zach in the milestone's review.
- **Tested in the browser:** `tests/e2e/check.spec.ts` follows a finding's link and sees the panel; `tests/e2e/part-specs.spec.ts` opens a variation by its address.

**Status:** The links stand. The inline script is superseded by D-086: Zach's word on 10 November 2026 was "whatever is best practice", and the standard's own answer is a built file.

### D-084 · Uninstall forgets Action Scheduler's cached group, and browser tests read numbers in the project's locale · 2026-11-10

- **Uninstall deletes the object cache's entry for the plugin's group** (`wp_cache_delete( 'creative-skateboard-builder', 'action_scheduler_groups' )`). Action Scheduler keeps a group's ID in the object cache for ten minutes (4.1.0 in WooCommerce 11.2.2, `classes/data-stores/ActionScheduler_DBStore.php:15`, `:344`). The uninstaller deletes the group's row with SQL (D-024), so on a persistent cache a reinstall inside ten minutes filed its actions under a row that was gone: they still ran, but nothing found them by group, so deactivation would not cancel them and the Catalogue check would not count their failures. The object-cache leg's `UninstallTest` found it once activation queued a job (D-082): `Failed asserting that two strings are identical. -'1|2|2' +'0|2|2'`.
- **A browser test that reads a formatted number asks for it in the project's locale** (`inLocale()` in `tests/e2e/support/test.ts`). The screens write a value with `number_format_i18n()`, so the `fr-ca` project sees `8,5 in`. The field a person types into keeps the shop's own separator, as WooCommerce's price fields do.

**Status:** Accepted.

### D-085 · The browser run fits its job: one login, a real wait for a form's answer, two engines on a draft · 2026-11-10

Pull request #15's first full run failed in the newest leg. Three causes, none of which a Mac shows:

- **The job ran out of time.** Milestone 3 took the browser suite from 5 tests to 12, in 9 projects on one worker: 108 runs at 20 to 45 seconds each on GitHub's runner, which is about three and a half times slower than the Mac the gates ran on. That is some 37 minutes, after 12 of setup and gates, in a job with a limit of 30.
- **`settings.spec.ts` failed on `webkit-phone`.** Its `save()` waited with `waitForLoadState( 'load' )`, which is already true of the page the form is on, so it waited for nothing. The `goto` that followed could cancel the post before the server had it. The same wait was in `import.spec.ts` and `spec-tables.spec.ts`.
- **`import.spec.ts` timed out on `firefox-desktop`** at the usual 60 seconds. It runs three imports from end to end, each with its job run through wp-env.

What changed:

- **A form's answer is waited for by the next `load` event,** set before the form is sent (`answered()` in `tests/e2e/support/test.ts`).
- **The administrator logs in once for the run,** over HTTP in the global setup, and `login()` gives each test that session's cookies. No spec tests wp-login.php; typing into it before each admin test was about four seconds of every one on the runner.
- **The import's test is marked slow,** which triples its limit.
- **A draft's newest leg runs `chromium-desktop` and `webkit-phone`,** as the oldest leg does. Every project runs when the pull request is marked ready, and on `main`. This revises D-041 in one place: a draft's run no longer includes every browser project. GitHub does not merge a draft, so nothing reaches `main` without all of them.
- **The newest leg has 60 minutes when it runs every project,** and 30 otherwise, as every other leg has.
- **A job that runs out of time keeps its traces.** The artifact was uploaded on failure only, and a cancelled run prints no failure, so the first run left nothing to read but the list of tests.

**Not done:** more than one worker. The specs share one shop (its settings, its one deck, its import), so two at once would break each other; the fix for that is a shop per worker, which is a milestone 6 question, when the builder's own specs arrive.

**Why:** Zach asked on 9 November 2026 that CI not overspend on minutes (D-040). No gate is removed and no floor moves: the same specs run in the same projects before anything merges. **For Zach:** the suite's cost grows with every screen, nine times over. A full run's newest leg is now about 45 billed minutes. Running the wp-admin specs in fewer projects than the builder's would cut that, and would move the audit's floor, so it is his to say.
**Status:** Accepted.

### D-086 · The product screen's script is built TypeScript · 2026-11-10

Zach's answer to the review's first question, on 10 November 2026: "whatever is best practice". `docs/STANDARDS.md` already says it: admin scripts are TypeScript in strict mode, built by `@wordpress/scripts`. The pipeline was all there but a first entry, so D-083's reason for waiting did not hold.

- **`assets/src/admin/part-specs.ts`** replaces the inline script. It is built to `build/admin/part-specs.js` (879 bytes) and enqueued on the product screen alone, in the footer, after `jquery` and `wc-admin-variation-meta-boxes`, with the build's own hash as its version.
- **jQuery is used for two things only,** each WooCommerce's doing: the Variations tab announces its rows with a jQuery event, which the DOM never hears (11.2.2, `assets/js/admin/meta-boxes-product-variation.js:636`), and a row opens with a jQuery animation, whose end only jQuery can promise. Tabs and rows are opened with a native click.
- **`webpack.config.js` names the admin's entry.** `@wordpress/scripts` finds a block's scripts from its `block.json`; a wp-admin script belongs to no block. The file extends the package's own configuration, as its documentation describes, and stays out of the zip.
- **A checkout that was never built has no script,** and the screen works without it. The zip is checked instead: `tools/build-zip.sh` fails when a file `src/` loads from `build/` is not in it (broken on purpose: `The zip would ship without build/admin/part-spec.js, which src/ loads.`).
- **Tests:** 15 in Vitest with a double for the two jQuery calls, StrykerJS at 97.3 % against the 90 % floor; the two browser tests of D-083 run the real thing in every project. `PartSpecsPanelTest` holds the handle, the address, the dependencies, the version and that nothing is inline.

**Status:** Accepted. Supersedes D-083's inline script.

### D-087 · What mutation testing may leave out · 2026-11-10

Zach's answer to the review's second question, on 10 November 2026: "whatever is best practice and modern standards in 2026". Milestone 3 had added 24 entries to `infection.json5`'s list without his word, which D-038 asks for.

- **The rule.** A class is left out of the unit suite's mutation run only when it cannot run without WordPress or WooCommerce loaded: it takes or builds their objects, or does its work through their functions. Such a class is proven in wp-env by the integration suite, with a deliberate break in its milestone's review. A class whose logic needs no more than the translation and number functions is mutated, with shims in `tests/Support/wp-shims.php`. Mutating against the integration suite itself was weighed and left: each mutant would boot WordPress, seconds apiece across thousands of mutants.
- **Three come back in:** `Admin\Labels`, `Admin\Sentences` and `Adapter\NoKits`. The first two map the services' codes to what a shop manager reads, with branches of their own; the third is plain PHP. Their unit tests kill 118 of 118 mutants.
- **Twenty-one stay out,** each under a line in `infection.json5` that says why: the five wp-admin screens, `Cli`, `Abilities`, `Health\SiteHealth`, the twelve adapters over WooCommerce's products and WordPress's options, posts, uploads and filesystem, and `Install\Migrations\InlineRule`.
- **Adding to the list still needs Zach** (D-038), and now needs the entry to meet the rule.

**Status:** Accepted.

### D-088 · A review's screenshots are AVIF · 2026-11-10

Zach asked on 10 November 2026 whether the reviews' images should be ignored by Git, then why they were not smaller and in a modern format. They stay tracked: they are the walkthrough's evidence, the review names them, and Claude in Cowork reads the repository as it is on disk. They never reach the zip (`docs` is in `.distignore`) and start no CI run.

- **`tools/review-images.sh <folder>`** turns each PNG Playwright wrote into AVIF at quality 50 and removes the PNG. A phone's full-page shot is taken at three device pixels; it is kept at two (780 wide), which reads as well. Milestone 3's 24 images went from 7.0 MB to 1.7 MB.
- **It uses `sips`,** which macOS ships, so no tool is added. The walkthrough is taken on the Mac, never in CI.
- **The PNGs pushed before this stay in the branch's history,** 7 MB once. Rewriting a branch under review to drop them would cost more than it saves.

**Status:** Accepted.

### D-089 · A mapped attribute is read by its own key, and a variation takes its parent's · 2026-11-10

Claude in Cowork's review of pull request #15, its one should-fix. `WC_Product_Variation::get_attribute()` answers from the attributes used for variations only (WooCommerce 11.2.2, `includes/class-wc-product-variation.php:140`), so an attribute set on a variable product never reached its variations, while the field and chart sources both fall back to the parent.

- **A variation with no value of its own takes its parent's,** when the parent holds the attribute and does not use it for variations. `Products::parentsOf()` already loads a batch's parents once. An attribute the parent uses for variations is the variation's to answer: one set to "Any" has no single value, and stays unread.
- **The reading says where it came from.** Its reference is the attribute's name after `^` (`Port\SpecSource::PARENT_ATTRIBUTE`), one character since the mapping allows a name of 190 and the index's column holds 191. The panel says "In use: 32 in, from the parent product's Length attribute."
- **A line names one attribute, by the key WooCommerce stores it under** (`sanitize_title()` of the name): `pa_length` is the taxonomy, and `Length` is a product's own attribute of that name. `WC_Product::get_attribute()` alone would answer `Length` from `pa_length` where a product has no attribute of its own by that name (`includes/abstracts/abstract-wc-product.php:2278`), and a line mapping a bolt's own Length would have given every deck with a `pa_length` a bolt length. The source checks the key before it asks.
- **This takes back D-082's `Width` for `pa_width`.** A term of `pa_width` asks for a reindex only when a line names `pa_width`. The settings screen says to choose a taxonomy from the list or type a product's own attribute name.
- **Tests:** `SpecSourcesTest` (the parent's value, "Any", and a line that reads the attribute it names and not a taxonomy of the same name), `PartSpecsPanelTest` and the unit and integration `IndexJobsTest`. Red before the fix: "the deck's Length is the taxonomy, which this line does not name". Without the fallback the parent tests fail; without the check on attributes used for variations the "Any" test fails.

**Status:** Accepted. Revises D-070 and D-082.

### D-090 · An import's rows are stored once · 2026-11-10

Cowork's minor 6. The option `cbuild_import_state` held the whole sheet, and each batch of 100 saved it again: 50 saves of up to 5,000 rows.

- **The rows have an option of their own, `cbuild_import_rows`,** with the import's token and, for an import by SKU, the product each SKU named. It is written when the file is read and removed when the import ends, is put away or is replaced. `cbuild_import_state` keeps the status, the place, the counts and the rows refused, which is what a batch changes.
- **The split is the adapter's** (`Adapter\OptionImportStore`). `Port\ImportStore` and `Service\ImportState` are as they were: the service still loads and saves one import.
- **Rows that are gone or carry another import's token make the import none,** which the screen then clears. The rows are written before the state that says it has them.
- **A request that has read an import's rows does not write or compare them again.** `update_option()` would skip an unchanged value, but only after reading and serializing 5,000 rows to find that out.
- **The write batch stays at 100.** Cowork noted WooCommerce's own importer writes 30 a request and that the benchmark planned and never wrote. It writes now: 1,778 parts in 20 jobs of 100, 706 ms for the slowest on the Mac, so about 2.5 s on CI's runner, against the 10 s a job is allowed. A product save is about 81 queries, WooCommerce's own.
- **What stays large:** a file in which most rows are refused. The 3,000 rows of the benchmark's file that name no product leave 187 kB of refusals in `cbuild_import_state`, saved by each batch as it grows. A real sheet is an export filled in, where refusals are few.
- **A new public item:** the option, in `docs/HOOKS.md`, the snapshot, `docs/PRIVACY.md` and `Options::names()`, so uninstall removes it.

**Status:** Accepted. Revises D-076's "the dry run is the rows, kept in the option `cbuild_import_state`".

### D-091 · A chart's size is matched by its number when no name matches · 2026-11-10

Cowork's minor 7. A spreadsheet opens Venture's size `5.0` as the number 5 and saves it so, and the import refused the row as a size the chart lacks.

- **`Domain\Spec\SpecTable::named()`** gives the row of that name, or failing that the one row whose name is the same plain number: `5`, `5.00` and `05` find `5.0`. A name that is anything but digits and one point is matched exactly, as before.
- **Two rows that are the same number are not guessed at.** A chart with `5.0` and `5.00` refuses `5`, and each still answers to its own name.
- **What is stored is the chart's own name for the row,** so the index and the panel's list find it. `SpecWriter` is the one place a named size is checked, for the panel and the import alike.

**Status:** Accepted.

### D-092 · The rest of Cowork's review of milestone 3 · 2026-11-10

Zach had every minor of milestone 2's reviews fixed, and these follow that.

- **A narrowest deck wider than the widest is refused** (minor 1) where the writers check: `SpecWriter` for the panel and the import, where neither figure is saved and the two stay as they were, and `SpecTableEditor` for a chart's row. The sentence names both figures. Rule R1 would otherwise hide the truck from every deck with nothing to say why.
- **A chart's name is its own** (minor 2): a name another chart has, whatever its case, is refused when a chart is added or renamed. Two charts of one name made the import refuse every row that named either.
- **A command in the foreground queues nothing** (minor 3). `IndexJobs::reindexNow()` and `SpecImport::runNow()` cancel what is queued, then run each batch themselves. Before, `wp cbuild reindex` on 2,000 parts left 22 actions a queue runner could take beside the command.
- **The reindex's first pass asks which of the index's IDs are parts** (minor 4), through `PartCatalogue::partsOf()` as `indexPart()` does. A product that became variable while the plugin was off loses the rows under its own ID, and its variations are indexed.
- **A page of variations asks the sources once** (minor 5), for up to 50 siblings together (`PartSpecsPanel::TOGETHER`); a save empties what was kept.
- **A link from the Catalogue check moves focus** to the first control of the panel or the variation it opens, without scrolling again, and a unit's problem on the settings screen is tied to its select with `aria-invalid` and `aria-describedby` (minor 8).
- **`ContainerTest` lists each scope's Hookables** (minor 9, landed with D-087's commit).
- **Minor 10 is two issues,** for the plans it speaks to: #20 in milestone 4 (a settings save reindexes only when the mapping or the order changed) and #21 in milestone 5 (a stock change queues an index job for every order line).

**Status:** Accepted.

### D-093 · The seeded charts are each maker's own · 2026-11-10

Zach's answer on 10 November 2026 to the two open figures of D-072 (Venture 5.0's axle, and Thunder's 143, 148 and 161): "lets do whatever is best practice for 2026 and standard." A figure a shop's builder decides fit by should come from whoever makes the part, with the page and the day beside it.

- **Thunder publishes a chart,** at https://www.thundertrucks.com/sizing/, as one image a size. The spec took Thunder's sizes from two retailers because the chart is an image; read on 10 November 2026, the images give every size an axle and a deck range. All eight rows are now Thunder's:

  | Size | Axle | Decks |
  | --- | --- | --- |
  | 143 | 7.125 in | 7.4 in and thinner |
  | 145 | 7.62 in | 7.4 to 7.9 in |
  | 147 | 8.0 in | 7.9 to 8.12 in |
  | 148 | 8.25 in | 8.12 to 8.38 in |
  | 149 | 8.5 in | 8.38 to 8.62 in |
  | 151 | 8.75 in | 8.62 to 8.85 in |
  | 161 | 9.125 in | 8.9 to 9.5 in |
  | 181 | 10.0 in | 9.5 in and up |

- **What changed from the seed before.** The 143's axle was 7.0 in, the spec's figure, and is 7.125. The 145's was 7.6 and is 7.62. The 143 and 161 had no deck range and have one. The retailers' ranges for the 145 to 151 were half an inch wide and overlapped; Thunder's are a quarter inch and meet end to end, so rule R1 now offers a Thunder truck to fewer deck widths, the ones its maker names. The 181 is new, from the maker's chart of 2026.
- **Venture publishes one too,** at https://venturetrucks.net/sizes/. Its five sizes are what the seed held from Rollersnakes, the 5.0's 7.62 in axle among them, so the figures stay and the source is now Venture. The spec's grid puts the 5.0 in its 7.6 in row, which groups makers by axle; the row is a grouping, not a figure.
- **Every seeded row now has the day it was read,** and `SpecTablesMigrationTest` holds that each names its maker's own site.
- **`docs/SPEC.md` is not edited.** Its table under R1 and its line "Thunder's chart is an image and Venture publishes none" are behind this; they are Zach's to change.
- **A shop that already has a chart keeps it** (D-072): the seed is passed over where a table exists. 0.3.0 is the first version with charts, so no shop has the old rows.

**Status:** Accepted. Revises D-072's seeds.

### D-094 · The Catalogue check is an asynchronous Site Health test · 2026-11-10

Zach on 10 November 2026, of the review's question whether the spec's third Site Health check should wait for milestone 4: "we should resolve this too". D-080 left it out because a test that reads every part must not hold the Site Health page.

- **`cbuild_catalogue` is an asynchronous test,** WordPress's own answer for a slow one (`wp-admin/includes/class-wp-site-health.php:2941`, as its loopback and background-update tests): the page draws, then asks for the result. At 2,000 parts the check takes half a second (docs/BENCHMARK.md).
- **The page asks over REST,** the form WordPress has used since 5.6 (`has_rest`): `GET cbuild/v1/site-health/catalogue-check`, the plugin's first route. Its `permission_callback` is `view_site_health_checks`, the capability WordPress's own Site Health routes use (`wp-includes/rest-api/endpoints/class-wp-rest-site-health-controller.php:189`). It reads and changes nothing.
- **The weekly check calls it directly** (`async_direct_test`), since a scheduled event cannot sign in (`class-wp-site-health.php:3401`).
- **It reports counts, not findings:** how many things there are to fix and in how many parts, with a link to the check. Recommended when there is one, never critical: a finding keeps a part out of the builder or names a disagreement, and the shop still sells.
- **Background work that failed is left out of its count.** The check lists a failed reindex, import and job; `cbuild_index` and `cbuild_jobs` already report those, and one failure is not shown three times (`Service\Health::catalogue()`).
- **`Health\SiteHealth` is also built on a REST request** (D-067), for the route. `ContainerTest` lists it.
- **Tests:** `HealthTest` for the verdict; `SiteHealthTest` for the test's registration, its results, and the route as a visitor (401), a shop manager (403) and an administrator. With the permission check removed: "Failed asserting that 200 is identical to 401".
- Issue #18 is closed by this.

**Status:** Accepted. Revises D-080.

### D-095 · A reindex owed to a site without WooCommerce is asked for when the queue is there · 2026-11-10

Zach on 10 November 2026, of the review's sixth question: "if this is best practice, do it". It is: a site that repairs itself does not depend on someone finding a button.

- **Activation leaves a mark where it has no queue:** the option `cbuild_reindex_owed`. Without WooCommerce there are no Action Scheduler tables, and the plugin hooks nothing until WooCommerce is back.
- **The first request that finds the queue asks, then removes the mark** (`Hooks\InstallHooks::askWhenOwed()`, on `init`). Asked first: a request that dies between the two asks again, and a reindex asked for twice starts once.
- **A site that owes nothing spends no query on this.** The mark is autoloaded and exists only while owed, and it is looked for in the options WordPress has already loaded (`wp_load_alloptions()`), since `get_option()` on an option that is absent queries the table on every request. `InstallHooksTest::test_a_current_version_costs_no_query_on_any_request` holds it; with `get_option()` in its place: "Failed asserting that 1 is identical to 0".
- **While it is owed,** each request looks for the queue's three tables, three small queries, until they are there.
- **The logic is two static methods on `Install\Activator`,** which mutation testing already leaves out for the reason D-087 gives, so the list of exclusions does not grow.

**Status:** Accepted. Revises D-082's "the button covers it".

### D-096 · A save under a running reindex is not treated as a delete · 2026-11-10

The milestone 3 audit noted that a delete during the reindex's catalogue pass asks for a second pass and a save does not (issue #19). They stay unlike, on purpose.

- **A delete moves a page.** The catalogue pass walks the shop's products a page at a time, so a product removed from a page already walked pulls one from the next page back into it, and that one would be missed. The second pass picks it up.
- **A save moves nothing.** The product keeps its place, and the save's own job indexes it. Whichever of the job and the pass runs last writes the same rows.
- **Telling the reindex of every save would cost a read on each save,** where a save now costs one queue check, and WooCommerce saves a product each time its stock changes. A busy shop could also keep a reindex passing again for as long as it sells.
- **Test:** `IndexJobsTest::test_a_save_under_a_running_reindex_queues_its_own_job_and_asks_for_no_second_pass` holds it. It passed when written, since it pins what the code already did.

**Status:** Accepted.

### D-097 · Decimals are read by the domain's parser, not `wc_format_decimal()` · 2026-11-10

`docs/SPEC.md` (Part specs) and `docs/STANDARDS.md` say a field's decimal is read with `wc_format_decimal()`. The plugin does not call it. Milestone 3's plan noted the difference in its table of installed source, and the audit asked for it here (issue #19).

- **`wc_format_decimal()` cannot read a fraction or say what was wrong.** It strips what it does not know and returns a number (WooCommerce 11.2.2, `includes/wc-formatting-functions.php:290`): run in the integration site on 10 November 2026, `8 1/4` comes back as `814` and `wide` as an empty string, with no reason for either. The spec asks for 8.25, 8,25 and 8 1/4 to be the same, and for a refused value to be named.
- **The domain's parser takes the shop's separator as an argument:** `wc_get_price_decimal_separator()` (`includes/wc-formatting-functions.php:557`), the same setting `wc_format_decimal()` reads. So the shop's separator is honoured, the domain stays free of WordPress, and one parser reads the panel, the charts, the import and the command line.
- **Each field is still sanitised first** with `sanitize_text_field()`.
- `PartSpecsPanelTest`, `SpecTablesScreenTest` and `ImportScreenTest` each save with the separator set to a comma.

**Status:** Accepted. A difference from `docs/SPEC.md`'s wording, not from what it asks for.

### D-098 · The rest of issue #19's minors · 2026-11-10

Milestone 3's audit listed thirteen minors as the first work of milestone 4.

- **The settings screen** prints a problem with the order under the order alone, and the mapping's own problems in one element with one id.
- **The Info tab** shows the index's state in words, with the code as its `debug` value, which is what Site Health copies to the clipboard. The ability's schema descriptions are translated.
- **`SpecImport::summary()`** sorts a plan's rows once, for the screen, the command line and the job.
- **`Domain\Spec\StoredSpecs::kept()`** says what a part's meta holds, and both writers store that. The product screen writes into the product WooCommerce is about to save and the import loads its own, so they stay two callers of one rule. `StoredSpecs::TABLE_MISSING` and `SIZE_MISSING` are the one home of those two codes.
- **The API snapshot** also holds Site Health's test ids, each command's arguments and flags, the CSV's columns and a digest of each ability's output schema. The last three are held by the snapshot alone: their words are too plain to look for in `docs/HOOKS.md`. With `[--dry-run]` renamed in the command's doc comment, `composer api:check` fails on `cli_synopses`.
- **A value the Part specs tab refuses** is named in a notice that is a link to its field. The screen's script opens the tab and the field's group and moves focus there, on load and when the address changes. A variation's notice leads to the variation, since its fields are loaded later and numbered by their place on the page.
- **The index's and the import's services** are built when a part is saved or a job runs. `Hooks\CliHooks` still takes its commands built: WP-CLI reads a command's arguments from the object's doc comments when it is added, and the scope is the command line alone.
- **The EXPLAIN test** hears the repository's own queries as they run, `rowsOf()` among them. With `rowsOf()` reading by a column that has no index: "Failed asserting that null is identical to 'PRIMARY'".
- **The benchmark** runs the check on a catalogue with 200 findings and reads a 2,000-row file matched by SKU (`docs/BENCHMARK.md`).
- **A foreground reindex** leaving its queued action was fixed in milestone 3 by `reindexNow()`.
- **The charts' screen's permission test** sends every action as every role that may not.

**Status:** Accepted.

### D-099 · The kit product type · 2026-11-10

`docs/SPEC.md` (Data model) gives the type, `cbuild_kit`, its class, `KitProduct extends WC_Product`, and its four documents. What it leaves open:

- **The class is `Adapter\KitProduct`.** It is WooCommerce's object, so it belongs with the adapters. `Hooks\KitHooks` answers `product_type_selector` and `woocommerce_product_class` and is handed the class's name by the container, since a Hookable may not name an adapter.
- **No data store of its own.** WooCommerce looks for `product-cbuild_kit` and falls back to the product store (WooCommerce 11.2.2, `includes/class-wc-data-store.php:84`).
- **Each document is JSON text in its meta,** as the spec says, and not a PHP array. WooCommerce's CRUD slashes a string before WordPress unslashes it and does not slash an array, so a backslash in a label survives as text and would be lost in an array. `KitProductTest` saves one.
- **`document()` gives null for anything that is not a JSON object** and checks nothing more. The schema and the domain check a document where it is used (`Adapter\WooKits`, the Build tab), so there is one place that decides a document is good.
- **The price to `view` is the fee; to `edit` it is empty.** WooCommerce's data store writes what `edit` gives (`includes/data-stores/class-wc-product-data-store-cpt.php:790`), so a price an importer or the REST API sets on a kit never reaches `_price` or `_regular_price`. That is what makes a kit unsellable with the plugin off, and `KitProductTest` removes the type's filters and asks WooCommerce. With `edit` answering as WooCommerce's own: "Failed asserting that two strings are identical."
- **`is_purchasable()` is false, through WooCommerce's own filter,** so milestone 5 can answer it for the build it is adding and nothing else can add a kit.
- **The type's name in the list is "Builder kit".** No shop's word is in it.
- **Uninstall removes the four documents with the plugin's other meta** and leaves the products, which WooCommerce then reads as simple products with no price.
- **The plugin's version is 0.4.0 from this commit,** since the type is the milestone's first public item (D-062).

**Status:** Accepted.

### D-100 · How a kit stays out of search · 2026-11-10

`docs/SPEC.md` (SEO and sharing): the kit is kept out of the catalogue, search results, feeds and the sitemaps, and prints no product markup.

- **A kit's catalogue visibility is always `hidden`,** to `view` and to `edit` (`Adapter\KitProduct::get_catalog_visibility()`). WooCommerce's data store writes the terms `exclude-from-catalog` and `exclude-from-search` from it (WooCommerce 11.2.2, `includes/data-stores/class-wc-product-data-store-cpt.php:1061`), and its catalogue pages, its search, WordPress's site search, its feeds, `wc_get_products()` and the Store API all read those terms (`includes/class-wc-query.php:421` and `:937`). So one fact hides the kit everywhere WooCommerce lists products, and a kit set to visible on the product screen, by an import or over REST is saved hidden all the same.
- **"Feeds" is WooCommerce's product feeds,** a category's and the shop's, which run the same query. A feed plugin that builds its own list reads the visibility terms, which are set.
- **WordPress's sitemap does not read visibility,** so `wp_sitemaps_posts_query_args` (WordPress 7.1.4, `wp-includes/sitemaps/providers/class-wp-sitemaps-posts.php:238`) adds a condition to the products' query: not of the type `cbuild_kit`. A condition another plugin added stays.
- **No markup:** `woocommerce_structured_data_product` (`includes/class-wc-structured-data.php:545`) answers an empty array for a kit, and WooCommerce prints nothing for markup with no type. Without it a kit would print an offer at its assembly fee.
- **The two rules that are not WooCommerce's are `Service\KitVisibility`,** plain PHP, unit-tested and mutated.
- **The test site's theme gives products no archive,** so `KitHiddenTest` asks for a product category's page and its feed, which WooCommerce queries as it does the shop's.
- **Deliberate break:** with the visibility WooCommerce's own and the two filters removed, all six of `KitHiddenTest` fail ("Tests: 6, Assertions: 12, Failures: 6").
- **Yoast's sitemap** is D-102's.

**Status:** Accepted.


### D-101 · Which products fill a slot · 2026-11-10

`docs/SPEC.md` (Data model) gives a slot its sources: categories, brands, products and attribute values, as IDs. It does not say how they combine, nor which of the products found is a part. `Adapter\WooKits` decides both, and `WooKitsTest` holds each line.

- **Categories, brands and attributes narrow each other.** A product is found when it is in one of the slot's categories, and of one of its brands, and has one of the values of each attribute, for whichever of the three the slot gives. "Decks" with the brand "Rascals" is the Rascals decks, which is what an owner who picks both means; either alone is all of it. A category holds its sub-categories, as WooCommerce's own queries have it.
- **A named product is added,** whatever it is in, and is not narrowed. It is how an owner adds the one part the terms miss.
- **A variable product stands for its variations.** A variation that sets an attribute to one value is held to that value when the slot asks for values of that attribute; one that takes any value is kept. A named variable product gives all of its variations, and a variation can be named by itself.
- **A part is what a shopper could be shown and could buy:** published (and its parent), not hidden from the catalogue, without a password, with a price, and not a kit. WooCommerce's `is_purchasable()` lets a person who may edit a product buy it unpublished (WooCommerce 11.2.2, `includes/abstracts/abstract-wc-product.php:1777`), so the status is checked here and who is asking changes nothing: the public route and the owner's check read the same list.
- **A part that is sold out is still a part,** marked so. The builder shows it as gone, and the check's "nothing in stock" rule needs to see it.
- **The price is what the shop shows for one,** `wc_get_price_to_display()`, in the currency's minor units, so tax is in or out as the shop's settings have it. The count in stock is given when the shop counts it and does not sell on backorder; otherwise there is no cap to hold a build to (issue #14).
- **A part's brand is its `product_brand` term's slug,** its parent's for a variation, and the first by name when a product has several.
- **A slot holds 2,000 parts at most** and a shop's list 200 kits, each in ID order, so a request's work is bounded whatever the catalogue.
- **A kit is listed when each document passes its schema and the four agree** (`Adapter\KitSchemas`, then `Kit::fromDocuments()`). One that does not is left out of `all()` and is still in `ids()`, which is every kit product not in the bin: what hides kits must hide a broken one too. A draft is a kit, so its owner can check it before it is published.
- **`slotsOf()` asks the same question of one product without the query,** and without the shopper's conditions: the Part specs panel opens the group a product's slot needs before the product is published (issue #16). The panel reads the kits once for a screen of variations; with them read for each: "Failed asserting that 3 is identical to 1."
- **`Adapter\NoKits` is gone,** and the Catalogue check's kit rules now run on the shop's kits (issue #17).

**For Zach's eye:** the first line is a choice an owner will feel. The other reading, categories or brands, would make "Decks" with "Rascals" every deck and every Rascals product.

**Status:** Accepted.

### D-102 · Yoast SEO's sitemap, and the leg that tests it · 2026-11-10

Zach on 10 November 2026, of the plan's question: "sure put free yoast in the test site if we need it".

- **Yoast builds its sitemap from its own query** and never asks WordPress's, so D-100's condition does not reach it. `wpseo_exclude_from_sitemap_by_post_ids` (Yoast SEO 28.8, `inc/sitemaps/class-post-type-sitemap-provider.php:296`) takes the IDs to leave out; `Service\KitVisibility::excluded()` adds every kit product's, from `Port\Kits::ids()`. The kits are read when a sitemap is built and not before. Yoast compares the IDs strictly, so they are whole numbers.
- **The test needs Yoast itself.** `tools/yoast.sh up` downloads Yoast SEO 28.8 from WordPress.org into the integration site's plugins, its zip checked against a pinned SHA-256, and `down` removes it. It is never activated in the site: the suite's bootstrap loads it when `CBUILD_YOAST=1`, and fails if the flag is set without it. `composer test:integration:yoast` does all three and runs `KitYoastTest` alone.
- **In CI it is a seventh, short run of the variants leg** (`tools/integration-variants.sh`), a few seconds on a site that is already up, so it costs no job (D-040). In the ordinary run the test is skipped and says why.
- **Deliberate break:** with the filter unhooked, "Failed asserting that an array does not contain 'http://localhost:8899/?product=complete-skateboard'."
- **Yoast is the one SEO plugin tested.** Another that builds its own sitemap needs its own filter; `Port\Kits::ids()` is there for it.

**Status:** Accepted.

### D-103 · Zach's answers of 10 November 2026, on three questions · 2026-11-10

Asked: whether issue #23 ships as 0.3.1 or with milestone 4; whether three adapters may be left out of the unit suite's mutation run; and D-101's reading of a slot's sources. Zach: "1 and 2 do to best practice 3 is perfect already."

- **Issue #23 ships as 0.3.1.** A fault that drops other plugins' fields on a shop today is fixed where shops are, and does not wait for a milestone that is steps from done. The fix is made on a branch from `main`, released from there, and merged into milestone 4's branch. What needs kits (no panel on a product that fills no slot) is milestone 4's.
- **`Adapter\KitProduct`, `Adapter\KitSchemas` and `Adapter\WooKits` are left out of `infection.json5`,** by D-087's rule: the first is WooCommerce's product object, the second does its work through `rest_validate_value_from_schema()`, the third through WooCommerce's product queries and WordPress's terms. Each is proven in wp-env (`KitProductTest`, `KitHiddenTest`, `KitSchemasTest`, `WooKitsTest`), with its deliberate breaks in D-099 to D-102. `Service\KitVisibility`, which is plain PHP, is mutated.
- **D-101's first line stands:** a slot's categories, brands and attributes narrow each other.

**Status:** Accepted.

### D-104 · 0.3.1: a closed group of Part specs sends nothing · 2026-11-10

Claude in Cowork, checking 0.3.0 on a shop on 10 November 2026, found four products whose edit form posted more than 1,000 variables with the Variations tab open, where PHP's `max_input_vars` is 1,000. PHP keeps the first 1,000 and drops the rest with no error. The panel had added 21 named fields to the product's tab and to each of the 15 variations on a page, on every product, part or not (issue #23). Zach, asked whether the fix is a 0.3.1 or milestone 4's: "do to best practice". A fault that loses a shop's data is fixed where shops are, so it is 0.3.1, made on a branch from `main`. The numbers D-096 to D-103 are milestone 4's, on its branch.

- **A closed group's fields are drawn `disabled`.** A browser sends no disabled field. A group is open when the part has a value in it or one in use from elsewhere, as before, so a part with no specs sends nothing, and one with specs sends its open groups'.
- **The screen's script enables a group's fields when it opens,** in the product's tab and in any variation WooCommerce loads later, by hearing `toggle` on its way down (it does not bubble). The fields stay enabled when the group closes again, so what was typed is still sent. The product screen already needs scripts (D-074); without them a closed group cannot be filled in, and neither can a variation be opened.
- **A group that sends nothing keeps what the part has.** `SpecWriter::draft()` already read a field that was not sent as unchanged; `PartSpecsPanelTest` now holds it.
- **Asking hosts to raise the limit was not weighed:** the plugin must work where it is installed.
- **Not drawing the panel at all on a product that cannot be a part** needs kits to say which products fill a slot, and is milestone 4's (issue #16).
- **The jump to a variation's fields** is checked a frame later: if what has focus is outside the viewport, it is scrolled to the middle. Cowork saw the page end 200 px low once, on a first load.
- **Red runs:** `PartSpecsPanelTest::test_a_closed_group_sends_nothing_so_a_part_with_no_specs_adds_no_field_to_the_form`, "Failed asserting that two arrays are identical"; the script's tests, "TypeError: groups is not a function", 11 failing.
- **Deliberate break:** with the fields drawn enabled, the browser test that reads what the form sends: "Expected - 1, Received + 65".

**Status:** Accepted.

### D-105 · No Part specs fields on a product no kit takes · 2026-11-10

The second half of Claude in Cowork's note on issue #23: "draw no panel where a product can't be a part". It needs kits, so it is milestone 4's, on top of 0.3.1's fix (D-104).

- **Where the shop has kits and none takes a product, a variation draws no fields at all,** and the product's tab holds one sentence in their place: what it means and how to make the product a part. The tab itself stays, so nobody looks for it in vain and the Catalogue check's links still land.
- **"Takes" is `Port\Kits::slotsOf()`** (D-101): named by a slot, or in what its categories, brands and attributes find, published or not.
- **A product that holds a value all the same keeps its fields,** as does one with a value in use from an attribute or a chart: what is stored must be seen to be cleared.
- **Before the shop's first kit every product has its fields,** closed and sending nothing (D-104). There is no telling yet which will be parts, and specs are often filled in first.
- **The cost of the rule:** a product is put in a slot's category, saved, and then filled in. The sentence says so.
- **Red run:** `PartSpecsPanelTest::test_a_product_no_kit_takes_has_no_fields_and_its_tab_says_why`, "a variation draws nothing. Failed asserting that two strings are identical."

**Status:** Accepted.

### D-106 · What the Build tab's save does with what it is sent · 2026-11-10

`docs/SPEC.md` (Admin, The kit's Build tab): each setting is checked against its schema, one that fails isn't saved, the rest saves, and each problem is named with a link to its field. Presets are saved only when the rules accept them. `Service\KitEditor` is that, in plain PHP with no WordPress in it, so the tab and anything else that edits a kit share it and its tests mutate it (225 of 226 mutants killed; the one left is a guard no build reaches). What the spec leaves open:

- **The domain reads a document first, then its schema.** The plan had the schema first. The domain says which field and why (`slots[2].units`, `document_wrong_type`); WordPress's validator says less. A document is saved only when both accept it, so the schema is still the gate, and `document_schema` is the code for one only the schema refuses. `KitSchemasTest` holds the two in agreement.
- **Each wrong slot and each wrong preset is named,** a problem a row. What is wrong with the rows together (the deck not first, the risers after the hardware, a key twice, too many) is named once every row reads. The rules and assembly name the first thing wrong.
- **What was typed in a refused document is kept** in the draft, as the document built from it, so the tab can show it again with the problem beside its field. The measure nobody could read is there as typed.
- **A slot's key.** A row that carries a key the stored kit has keeps it, whatever its label becomes. Any other row is new, and a key the form made up is not taken. A new slot is one of the eight the rules read (deck, trucks, wheels, risers, bearings, hardware, grip, spacers), chosen by name, or another, whose key is made from its label once: lower-case letters, digits and underscores, 32 at most, numbered when another slot has it. The spec has every key made from its label; a French shop's "Planche" would then be a slot no rule reads, and the deck would have no fit at all.
- **A switch that is not sent is off,** as a box left empty is. R7 and R8 have no switch: R7 is the engine's own and a kit cannot turn it off (D-056), and R8 is a note.
- **Measures take the shop's decimal separator and fractions,** as the Part specs fields do, and are written with a point and no zeros they do not need. The fee takes the separator or a point.
- **Presets are held to the slots and rules that will be stored once the save is done:** those sent where they read, else those stored. A new kit whose slots were refused has nothing to hold a preset to, and says so (`presets_without_slots`); one whose rules were refused is held to the usual rules.
- **A preset is refused when it names a part its slot does not take, leaves a required slot empty, names a slot the kit lacks, or holds a part the rules block.** One refused, none saved, since the document is one setting.
- **A part that is sold out does not refuse a preset.** Stock comes and goes; the Catalogue check lists it and the builder handles it (the roadmap's milestone 6).
- **A misfit is said once,** of the part picked later. The validator names it from both sides, the deck and the trucks that do not fit it.
- **R7 at save is a note, not a refusal** (D-062): in each slot that takes more than one unit, how many parts have no set size. A kit is set up before every part is filled in. Slots that take one unit are not asked for their parts.
- **The slots' parts come from `Port\Kits::parts()`,** which takes sources that are not stored yet, and each slot is asked once a save.
- **The documents have names here** (`slots`, `rules`, `presets`, `assembly`), which the schemas' port shares. Where each is stored is `Support\Options`', which a service may not use.

**Status:** Accepted.

### D-107 · The mutation job's time limit is 25 minutes · 2026-11-10

On `main`, `composer infection` mutates everything, which took 11 minutes on 0.3.0's merge and 14 on 0.3.1's, on the same code but for one template and a script. StrykerJS, `npm ci` and the release dry run follow it. The job's limit was 15 minutes, so on 0.3.1's merge (run 42911035398) it was cancelled with nothing wrong, CI ended cancelled, and `release.yml` skipped the release until the job was run again.

- **The limit is 25 minutes.** A limit is there to stop a job that hangs, not one that is slow on a busy runner. GitHub bills what a job uses, not its limit (D-040), so this costs nothing on a run that passes.
- **CI gives Infection every core the runner has** (`CBUILD_INFECTION_THREADS`, from `nproc`). `tools/infection.sh` asked for `--threads=max`, which Infection 0.35.10 reads as every core but one (`src/Resource/Processor/CpuCoresCountProvider.php`, `RESERVED_CPUS`), and the job's log on `main` reads "Threads: 1" for both runs: 5 minutes 34 seconds outside the domain and 2 minutes 41 inside it. Away from CI the script still asks for `max`, so a laptop keeps a core.
- **No floor moves.** The scores asked for are as they were.
- **A pull request's run is not touched by this:** it mutates its changed lines and takes two to three minutes.

**Status:** Accepted.

### D-108 · The Build tab's screen · 2026-11-10

`docs/SPEC.md` (Admin, The kit's Build tab). `Admin\BuildTab` draws the tab and hands what is sent to `Service\KitEditor` (D-106); `templates/admin/build-tab.php` is its one template, and `assets/src/admin/build-tab.ts` its script.

- **Only a kit's screen holds the fields.** WooCommerce prints every tab's panel on every product's screen and shows the ones the type takes. The tab's fields on a simple product would be some 150 more for its form to send, which is issue #23 again. Another product's panel holds one sentence, and its fields arrive once it is saved as a kit.
- **Saved by who may manage the shop** (`manage_woocommerce`, the spec's capability for setting up kits), checked in the save as well as by WooCommerce, which checks `woocommerce_meta_nonce` before it fires the hook (11.2.2, `includes/admin/class-wc-admin-meta-boxes.php:228`). Someone who may only edit products can save the kit's name and leaves its settings as they were.
- **Every value is plain text:** `sanitize_text_field()`, and `sanitize_textarea_field()` for the two that take lines (a slot's help and the returns notice). The documents hold no markup, and the builder escapes them when it prints them.
- **One way to store a document.** `Support\StoredKit` reads and writes a kit's documents on a product through its CRUD. `Adapter\KitProduct` uses it, and so does the tab, which may not reach into the adapters (deptrac).
- **A new kit starts from a complete's seven parts** (deck 1, trucks 2, wheels 4, risers 2 and optional, bearings 8, hardware 8, grip 1) and the seeded rules. Nothing is stored until the kit is saved. The shop's own defaults for new kits are the settings page's, later in this milestone.
- **A slot is added by typing a label into the empty row at the end, and removed by a box on its row.** Neither needs a script, and neither builds markup in the browser. Six presets are always drawn, the empty ones folded shut, and the riser suggestions end with two spare rows.
- **Rows move by Move up and Move down, and by dragging** their handle (jQuery UI's sortable, which WooCommerce's product screen loads). The rows are sent in the order they stand in. After a move, focus stays on the button pressed, or goes to the row's other button at an end; the button with nowhere to go is switched off; and `wp.a11y.speak` says where the row now is, in a sentence the page carries (WCAG 2.5.7, 2.4.3, 4.1.3).
- **A refused section is shown once more as it was typed,** to the person who typed it, with each wrong field marked (`aria-invalid`) and tied to its sentence (`aria-describedby`), and the section's problems listed above it. It is kept in a transient named for the kit and the user, `cbuild_kit_draft_<kit>_<user>`, until the tab is next drawn and an hour at most. A reload after that shows the stored kit. Kept longer, it would stand over what someone else had saved since.
- **A notice's link names its field by the document's own path:** `slots[2].units` is `#cbuild-kit-slots-2-units`. The script opens the tab and every folded group the field is in, and moves focus to it. A path deeper than a field (one ID in a list) leads to the field, and one with no field of its own to its row or its section.
- **Labels stand above their fields.** WooCommerce floats a label into a column 150 pixels wide, which "With no range: the axle warns within (in)" does not fit, less so at phone width or in French. Each slot's settings and each preset fold shut unless they hold something wrong, so the tab is some 5,600 pixels tall and not 11,000.
- **The rules report one thing at a time for the rules and assembly** (D-106), so a second wrong measure is met on the next save.
- **A preset's part that lacks a spec is said as that,** not as a misfit: "lacks a spec that rule R6 reads".
- **WooCommerce's search fields are left out of axe's region in the browser test.** The spec asks for them ("picked with WooCommerce's own search fields"): `wc-category-search`, `wc-taxonomy-term-search` and `wc-product-search`, each returning IDs (11.2.2, `assets/js/admin/wc-enhanced-select.js:250`, `:473`, `:539`). Their widget, selectWoo, fails two axe rules in its own markup: `aria-allowed-attr` on its box and `aria-input-field-name` on a single choice. That markup is WooCommerce's, on every product screen, and not the plugin's to rewrite. The `select` each stands for is the plugin's, has its label, and is checked. Native lists of each slot's parts would pass, at up to 2,000 options a list. Zach, 10 November 2026: "widget out of axe region is fine for now, we can always revisit later if needed."
- **Red runs.** `BuildTabTest::test_a_section_that_fails_is_not_saved_the_rest_is_and_each_problem_links_to_its_field`, "Failed asserting that two arrays are identical", and `build-tab.spec.ts`, "a slot's row moves by its buttons", "expect(locator).toBeFocused() failed".

**Status:** Accepted.

### D-109 · The rest of the settings: the terms a build is sold on · 2026-11-10

`docs/SPEC.md` (Admin, Settings; Cart; Security, The state route). Five keys join `cbuild_settings`: `build_discount`, `fixed_coupons`, `rate_limit`, `trusted_proxy_header` and `kit_defaults`. `Domain\Kit\BuildTerms` reads them, `Service\SettingsEditor` checks what the screen sends, and `Port\ShopSettings::terms()` hands them to whatever needs one.

- **One value object, read leniently.** As with the mapping and the order (D-069, D-073), whatever the option holds reads as a valid setting, each key on its own: a discount of `100` reads as none and leaves the rate beside it as it is. The screen is strict, and the schema (`schema/settings.json`) holds anything else that writes the option to the same shape; a test checks that what the schema refuses, the reader does not take.
- **The discount is text, a percentage with a point and up to two decimals, 0 to 99.99,** and the domain holds it in basis points, which is what `Pricer::quote()` takes. JSON's floats never carry it, as with a kit's fee and its lengths. 100 is refused: a build is not given away by a setting.
- **`fixed_coupons` is `kit` or `parts`,** the spec's default first: a fixed product discount coupon lands on the kit's line alone. Milestone 5's cart reads it and the discount. They are stored and public from here, as the plan said, so the screen is whole in one milestone; until 0.5.0 they change nothing a shopper pays, and the screen says so.
- **The rate limit is a whole number from 10 to 6000 a minute, 120 by default** (the spec's). Below 10, a visitor who changes a pick every few seconds would be stopped; 6000 is a hundred a second from one address, past which a limit is no limit. A shop that wants none is not offered it: the route is public.
- **The proxy header is a header's name and nothing else:** a letter, then up to 63 letters, digits and hyphens, as typed in a server's configuration (`X-Forwarded-For`), not as PHP names it (`HTTP_X_FORWARDED_FOR`). Empty, the default, reads `REMOTE_ADDR`. The screen warns that a header a visitor can send themselves lets them past the limit. The route, later in this milestone, turns the name into the server variable and takes one address from it.
- **`kit_defaults` holds `assembly`, an assembly document,** checked by the same `Assembly::fromDocument()` a kit's own is, and by the same schema properties, which a test holds equal to `schema/kit-assembly.json`'s. The spec's "defaults for new kits" could also cover the slots and the rules. Those start from a complete's seven parts and the seeded rules (D-108), which a shop changes per kit; assembly is the one a shop sets once, since its fee, lead time and returns notice are the shop's policy. The key is an object so that more can join it without a new key.
- **A new kit's Build tab shows the shop's assembly defaults** until the kit is saved with its own. Nothing is copied into a kit until then, and a kit that has its own keeps it when the setting changes.
- **Saved with the mapping and the order, or not at all,** in one write (`ShopSettings::save()` takes the terms as an optional third argument). A caller that sends no terms leaves them as they are.
- **Saving the terms reads no part again.** They feed no spec, and `indexedFrom()` compares only what the index is built from.
- **Each field is a row of WooCommerce's settings table,** drawn by one template (`templates/admin/settings-terms.php`), with its problem beside it. The rate is a text field with `inputmode="numeric"` and not a number field, whose value changes on a scroll and which reports nothing typed when it holds a letter.
- **Red runs.** `SettingsEditorTest::test_fields_left_empty_are_no_discount_no_header_and_no_fee`, "Call to undefined method ...InMemoryShopSettings::terms()", and `SettingsPageTest::test_the_terms_draw_what_is_saved_with_the_shops_separator_and_every_control_named`, "discount: for one control. Failed asserting that 0 is identical to 1."

**Status:** Accepted.

### D-110 · The state service, and a part's stock count held against the build · 2026-11-10

`docs/SPEC.md` (How it fits; How rules run; Security). `Service\BuildState::of()` puts a build's state together, and the state route and both abilities, later in this milestone, call nothing else. Issue #14's second half is in the domain.

- **One service, which decides nothing.** It looks each pick up among its slot's own parts, as `Port\Kits::candidates()` found them, and asks the domain for the rest: `FitEngine::options()` for each slot, `Recommender`, `ChangePlanner`, `BuildValidator` and `Pricer`. Its answer, `Service\State`, holds codes, IDs and amounts in minor units. The words, the names and the photos are the edge's.
- **A pick is an ID, and an ID that is not one of its slot's parts is refused** with the validator's own codes, `part_not_in_slot` or `slot_unknown`, and is not counted, priced or described: the state says nothing of a draft, a private product or one outside the slot. A pick that is not a whole number above 0 is refused without its value.
- **The build the steps count is the picks and the recommendations.** The spec has each later slot start on its recommendation and the total count it from the start (P9), so a slot is held against the picks before it and, where one of those slots has no pick, against the part it starts on: the wheels are held against the trucks the builder shows as chosen. The validator and the pricer are given that same build, so "ready" and the total are of what the shopper sees.
- **A slot starts on a part once every slot the kit requires before it has one.** That is the spec's "after the deck", said without naming the deck: the recommender never picks a deck, so nothing starts until the shopper picks one. A required slot with nothing to start on stops the slots after it from starting, since they would be held against a build with a hole in it. An optional slot gets a part only when the build asks for one, as the recommender has it.
- **The change list is for the first pick, in step order, that differs from the picks sent as `previous`,** planned from the build as it was. A list that breaks nothing is not returned. The state beside a change list is of the picks as sent, the later ones blocked: nothing changes until the shopper accepts (P8).
- **The step is the one asked for when the kit has it;** otherwise the first with nothing picked, or the last when every slot has a pick.
- **Assembly is the shopper's choice where one is sent, and the kit's default where none is.** The discount is the shop's (D-109), and the fee is in the decimals `Port\Kits::decimals()` gives, which are the ones the parts' prices are in. That method is on the kits' port and its adapter, which already reads `wc_get_price_decimals()` for each part's price, so no adapter is added for one function.
- **Issue #14, the second half.** `Part::covers()` and `Option::available()` hold a part's stock count against the quantity a build takes of it (a slot's units over the part's set size, R7). A part the shop holds too few of is not recommended, is not the closest fit in a change list, and the validator refuses it with a code of its own, `part_short`, so the edge can say "1 left, and a build takes 2" and not "sold out". A part that is out of stock is `part_sold_out` as before, said once. A shop that does not count a part, or takes backorders on it, holds enough: the adapter gives no count for it. The number of builds in a cart line is milestone 5's to multiply by.
- **Every kit is read to find one.** `Kits::all()` reads each kit's four documents; a shop has a handful. The benchmark at 500 candidates, later in this milestone, says whether one kit needs a reader of its own.
- **Red runs.** `PicksTest::test_a_part_covers_a_quantity_when_it_is_in_stock_and_the_shop_holds_as_many_or_does_not_count`, "Call to undefined method ...Part::covers()"; `BuildValidatorTest::test_a_part_the_shop_holds_too_few_of_stops_the_build`, "Failed asserting that two arrays are identical"; The service's tests were written with it. Its deliberate break: with a recommendation left out of the build the steps count, four of `BuildStateTest`'s tests fail, the first `test_once_the_deck_is_picked_each_later_slot_shows_what_fits_hides_the_rest_with_why_and_starts_on_a_part`, "Failed asserting that two variables reference the same object".

**Status:** Accepted.

### D-111 · Card data, and how long it is kept · 2026-11-10

`docs/SPEC.md` (Vocabulary, Option; The builder, Cards; Performance). `Port\PartCards` gives what a card shows of a part, and `Adapter\WooPartCards` reads it from WooCommerce and keeps it. The state route, later in this milestone, asks for the cards of the options a step shows and of nothing else.

- **What a card holds:** the name as plain text, the product page's address, the price of one as the shop formats it (`wc_get_price_to_display()` through `wc_price()`, 11.2.2 `includes/wc-product-functions.php:1696`), the availability in the shop's own words and its class (`WC_Product::get_availability()`, which follows the shop's stock display format, `wc_format_stock_for_display()` at `includes/wc-formatting-functions.php:1323`: a shop that hides its counts has them hidden here), and the photo at the catalogue's size with its `srcset` and `sizes`. Fit, and the price as a change from the current pick, are the engine's and the route's.
- **The name is decoded.** WordPress stores a title with its entities written out (`&amp;`). The card's name is plain text, and whatever prints it escapes it once.
- **The photo has no alt text of its own.** It stands beside the part's name in the same card, so a description would be read twice. The attachment's alt text is not read.
- **A variation with no photo shows its parent's,** as WooCommerce does.
- **Kept per part, an hour at most, in the `cbuild` group** (D-066): scalars and arrays alone, an expiry on every entry, a miss answered from the database, and no flush. The key is the part's ID, `card_<id>`. The entry holds the last change of the part's post and of its parent's, so a part saved by anything at all is read again, and a card for each price context it has been asked in, four at most.
- **A price context is what a price and its words depend on beside the part:** the currency and its decimals, the language, whether prices are shown with tax, and the tax rates that apply to this visitor, hashed. The rates stand in for the visitor's address, so no address reaches the cache, and visitors charged the same rates share a card. `cbuild_price_context`, with the filters later in this milestone, lets a plugin that prices by role or by currency add to it.
- **The hooks forget a card at once,** since a stock count moves without the product's post being saved: `woocommerce_update_product` and its variation's, the two `set_stock` and the two `set_stock_status` hooks, `wc_after_products_starting_sales` and `wc_after_products_ending_sales`, and `deleted_post`. `Hooks\CardHooks` names each with its file and line. Forgetting a product forgets its variations too, up to 1,000 of them.
- **The plan said "keyed by the part's last change and a price context".** The last change is in the entry and not in the key, so that a hook can forget a part without knowing every context it was asked in; the effect is the same, and a stale entry is never read.
- **What the hour is for:** a change nothing announces, such as a photo replaced in the media library or a tax rate edited. It shows within the hour.
- **Cost.** A step's 24 cards cost fewer queries than parts when nothing is kept (the posts primed in one, the products loaded in one), and none when all are kept, which `WooPartCardsTest` counts.
- **Red run.** `WooPartCardsTest`'s first run, before the name was decoded: `test_a_card_is_the_parts_name_page_price_stock_and_photo_as_the_shop_shows_them`, "Failed asserting that two strings are identical." Deliberate break: with `woocommerce_product_set_stock` left out of the hooks, `test_the_hooks_are_the_ones_woocommerce_fires` fails, "Failed asserting that false is identical to 10."

**Status:** Accepted.

### D-112 · The state route, its answer and its rate limit · 2026-11-10

`docs/SPEC.md` (How it fits; Security; French and other languages; Performance). `Rest\StateRoute` registers `GET cbuild/v1/kits/<id>/state`. It holds no rule of its own: `Service\BuildState` works the state out (D-110), `Front\StateAnswer` writes it down as `schema/state.json` describes, `Front\Words` turns each code into a sentence, and `Service\RateLimit` counts.

**The answer**

- **One step's options.** Every step says its pick, the part it starts on, and how many parts it offers and hides, counted by reason. Only the step asked for lists its parts, so an answer's size and its card reads follow one slot and not the kit.
- **24 at a time** (spec: Performance), of the parts offered and of those hidden. Parts the shop can supply come before those it cannot (spec: Sold out), each in the engine's order, and the part the step counts is listed wherever it stands. The spec's Show more and its filters come with the page in milestone 6, as arguments added to the five the spec names; nothing here has to change for them.
- **Cards in one call,** for the parts listed, the parts the total counts and the parts a change list names, each once. A part whose product has gone since the index read it keeps its place with no card.
- **A card's price is plain text,** which changes D-111: `Adapter\WooPartCards` kept `wc_price()`'s markup. The route's answer is data, the Interactivity API binds text and not markup, and the page that prints a price escapes it. `Port\Money` writes the total's amounts the same way, through `wc_price()` with its markup taken off, so the symbol, its side and the separators stay the shop's.
- **Money is an amount and a text:** whole minor units for anything that reckons, and the shop's own writing for anything that shows.
- **The sentences name the parts by what they are** ("the trucks' axle", "the deck"), never "this part", so one sentence is right whichever of the pair is the option on show: a shopper who goes back to change the deck sees decks judged against the trucks. For the same reason a hidden count's sentence carries no figure: "9 parts hidden: the trucks would be too narrow for the deck." The spec's example, "9 trucks hidden: too narrow for 8.5″", needs the slot's label made plural and put in lower case, which no language rule the plugin has can do to a label a shop typed. This is a difference from the spec's wording and is in the review for Zach.
- **Bolts and risers are fractions** where they are a whole number of eighths of an inch (1¼ in), and everything else is a decimal, as the spec's Numbers and units asks. A whole number and its fraction are written together as a numeral, with no pattern for a translator: PHPCS refuses a translatable string that is placeholders alone.
- **The schema uses `definitions` and `$ref`** for an option, a card, a message, a problem and an amount, each of which the answer holds in several places. WordPress's validator reads no `$ref` (`wp-includes/rest-api.php`, `rest_validate_value_from_schema()`), so `StateRouteTest` writes each reference out before it validates an answer. The kit's documents have none, and are still validated as they are.
- **Fit labels** ("Fits your 8.25″ deck") and the price as a change from the current pick are the page's, in milestone 6, and will be fields added to an option.

**What the route takes and refuses**

- **The arguments' schema is WordPress's to check:** `picks` and `previous` are objects whose keys match a slot key's pattern and whose values are whole numbers above 0, 20 at most (`maxProperties` and `patternProperties`, `wp-includes/rest-api.php:2180` to `2182`); `step` is a slot key, `assembly` a boolean and `locale` a locale's name. The kit's ID is 1 to 18 digits in the route itself, so a number PHP cannot hold is no route.
- **Then the kit's own slots.** A schema written ahead knows no kit, so the callback refuses a key the kit lacks with WordPress's `rest_invalid_param` and a 400, in `picks`, `previous` or `step`. "No more picks than the kit has slots" follows: each key is one of the kit's, and a query cannot repeat a key. The service still answers such a key with `slot_unknown` in `refused`, for the page in milestone 6, which opens build links made before a slot was removed.
- **A part that is not one of its slot's** is not a refusal of the request: the answer comes with the pick in `refused`, and says nothing of the product.
- **A kit a visitor may not see** is a 404 in the same words as no kit at all: a draft, a private kit, one behind a password, a product that is no kit, and a kit whose documents do not read. `Port\Kits::isPublic()` is new for this; `Service\BuildState` reads any kit that is not in the bin, which the Catalogue check needs. An administrator gets the same 404: the route has one answer for everyone, so nothing it says depends on who asks, and no cache or log holds a draft's parts.
- **`no-store` and `noindex` on every answer,** a refusal included, through `rest_post_dispatch`, since WordPress makes the 400 for a bad argument before the callback runs.
- **The language** is switched to after the limit is passed and switched back in a `finally`. `switch_to_locale()` refuses a language the site has not installed (`wp-includes/class-wp-locale-switcher.php:81`), which is the spec's rule, so the route adds no check of its own.

**The rate limit**

- **A fixed window of a minute.** The count is kept under 40 characters of `hash_hmac( 'sha256', "<minute>|<address>", wp_salt( 'nonce' ) )`. With the minute in the hash the name is new each minute, so nothing links one minute's requests to the next, and with the site's secret as the key the name cannot be made, or checked against a guessed address, by anyone without it. It is kept two minutes, so it outlives its own minute on a server whose clock differs from the cache's and no longer.
- **Where it is kept:** an entry of the `cbuild` group where the site has a persistent object cache (`wp_cache_add()` with the expiry, then `wp_cache_incr()`, which a cache does in one step), and a transient otherwise. A transient is read and then written, so two requests at the same instant may count as one; the limit is a brake on a script and not a meter. A table of the plugin's own was weighed and left: it would be a table added after milestone 1 for a number that lives two minutes. The transient is a write to the options table on each request of the route, which the benchmark in this milestone measures. For Zach at the review.
- **The address** is `REMOTE_ADDR`, read as an address or not at all, unless the shop names a header. Of a header that lists several, the last is read: a proxy appends the address it saw, so the last is the one the shop's own proxy wrote and the ones before it are the visitor's to make up. Reading the first would let anyone past the limit with a header of their own. Behind two proxies the last is the outer proxy, and every visitor shares one count: `docs/SETTINGS.md` says to name the header the outer one sets (`CF-Connecting-IP`).
- **An IPv6 address counts as its /64,** the block one visitor is given; counted singly, one visitor has 18 quintillion counts. An IPv4 address written as IPv6 counts as the IPv4 address.
- **Counted first.** The count is the first thing the callback does, so a request over the limit reads no kit and no part: it costs the count. A request WordPress refuses for its arguments is not counted, and costs less than the count would.
- **Everyone is counted,** signed in or not: the builder's script asks once per pick, debounced, and 120 a minute is two a second.
- **The 429** has the shape of any REST error (`code`, `message`, `data.status`) and `Retry-After` with the seconds left of the minute. Its sentence is in the site's language: the language asked for is not switched to until the limit is passed.

**Tests.** `RateLimitTest`, `WordsTest` and `StateAnswerTest` run without WordPress and are mutated: 225 of 225 mutants killed in `Service\RateLimit` and `Front`. `StateRouteTest` runs in wp-env: every answer is validated against the schema, and the schema is shown to refuse an answer that is not its own. `Rest\StateRoute`, `Adapter\CacheTally` and `Adapter\ShopMoney` cannot run without WordPress; whether they join `infection.json5`'s list is Zach's to say (D-038, D-087).

**Red run.** `StateRouteTest`'s first run: `test_the_answer_is_in_the_language_asked_for_when_the_site_has_it_and_the_sites_own_otherwise`, "Failed asserting that two arrays are identical", with `en_US` beside `fr_CA`: the 404's sentence was made before the switch, which moved the switch to where it is. Deliberate breaks, three at once: with the public check, the limit and the `no-store` header each taken out, `test_a_kit_a_visitor_may_not_see_is_answered_as_one_that_is_not_there` fails ("Failed asserting that 200 is identical to 404", on the draft), as do `test_every_answer_of_the_route_is_kept_out_of_caches_and_out_of_search` ("Failed asserting that null is identical to 'no-store'"), `test_an_address_over_the_limit_is_told_to_wait_and_another_address_is_not`, `test_a_request_over_the_limit_reads_no_kit` and `test_the_address_is_the_connections_own_unless_the_shop_names_a_header` ("Failed asserting that an array contains 429").

**Status:** Accepted.

### D-113 · Five more classes left out of the mutation run · 2026-11-10

Zach's answer on 10 November 2026 to whether `Rest/StateRoute.php`, `Adapter/CacheTally.php`, `Adapter/ShopMoney.php`, `Adapter/WooPartCards.php` and `Admin/BuildTab.php` may join `infection.json5`'s list (D-038): "for whats still open: do whatever is best practice". The practice is D-087's rule, applied as it stands: a class is left out only when it cannot run without WordPress or WooCommerce loaded.

- **All five meet it.** The route takes WordPress's REST request and gives its response, and switches its locale. `CacheTally` is the object cache and transients. `ShopMoney` is `wc_price()`. `WooPartCards` reads WooCommerce's products and the object cache. `BuildTab` is a wp-admin screen, as the five screens already on the list are.
- **Each is proven in wp-env** by the integration suite, with a deliberate break on record: the route's three in D-112 and the cards' in D-111. The Build tab had none on record, so one was run for this decision: with its capability check taken out, `BuildTabTest::test_only_who_may_manage_the_shop_saves_a_kit` fails, "Failed asserting that two arrays are identical." `CacheTally` and `ShopMoney` are reached through the route's tests: the limit's 429s, the transient's name and expiry, and the total's "$97.00".
- **What needs no WordPress stays in the run:** `Service\RateLimit`, `Front\Words` and `Front\StateAnswer`, where the route's logic is, at 225 of 225 mutants killed. The route was written thin so that this is where the list ends.
- **The rule is unchanged,** and adding to the list still needs Zach.

**Status:** Accepted.

### D-114 · The five filters · 2026-11-10

`docs/SPEC.md` (Pricing, stock, assembly and returns; French and other languages; Performance) names five filters and what each is for, and leaves their arguments to the build.

- **`cbuild_part_available` takes a part out; it does not mark it sold out.** `( bool $available, int $id, WC_Product $part )`, asked in `Adapter\WooKits` where a slot's parts are found, after WooCommerce's own tests (published, visible, purchasable), so it is asked only of a part that would otherwise be offered. A part ruled out is no candidate of any slot: it is not offered, not recommended, not a preset's valid pick, and a request that names it gets `part_not_in_slot`, as for any product that is not the slot's. The other reading, a part shown as sold out, would have the card say "In stock" in WooCommerce's words beside an option that cannot be picked; a plugin that wants a part shown as sold out sets its stock status, which WooCommerce and the builder both read. It has no kit or slot argument: a part's sources are read per slot, and a plugin that knows a part is unavailable knows it of the part. For Zach at the review.
- **`cbuild_price_context` filters the list that is hashed,** `( array $context )`, in `Adapter\WooPartCards`. `StateRouteTest` shows why it is needed: a plugin that filters `woocommerce_product_get_price` for some shoppers and says nothing here has its shoppers shown the card kept for the others. `docs/HOOKS.md` says so, and says to add nothing that names a person: the context is hashed and the hash is all that is kept, but a context per person would also be a card per person.
- **The owner's words: three filters, one field name.** `cbuild_slot_label( $label, $slot, $kit )` and `cbuild_preset_label( $name, $position, $kit )` are the two the spec names for labels. `cbuild_kit_text( $text, $field, $kit, $of )` takes the rest: a slot's help, a preset's description, the lead time and the returns notice, told apart by `$field`. A preset has no key, so it is named by its position, which the Build tab's order sets.
- **An answer of the wrong type is ignored** and the owner's words stand, so a plugin's mistake does not put "Array" or nothing in front of a shopper. An answer is not escaped or cut: the route's answer is plain text and the page escapes it.
- **The answer gained `texts` and `presets`.** The filters "the answer runs through" needed the words to be in the answer, and the page in milestone 6 needs both. A preset is its name, its description and its picks; its picture, its total and whether it holds a sold-out part are the page's to ask for.
- **A label a plugin answers is the label everywhere in the answer:** the step's, the change list's, and the one a problem's sentence names its slot by. Each slot's label is asked for once an answer.
- **Unit tests answer a filter** through `$GLOBALS['cbuild_test_filters']`, which the `apply_filters()` shim in `tests/Support/wp-shims.php` reads, so `Front\StateAnswer` stays in the mutation run with its filters: 190 of 190 mutants killed in `Front`.

**Red run.** `StateAnswerTest::test_the_owners_own_words_are_in_the_answer_as_typed`, "InvalidDocument: presets_slot_not_filled": the test's presets left required slots empty, which the domain refuses. Deliberate breaks: with `cbuild_part_available` not asked, `StateRouteTest::test_a_plugin_that_rules_a_part_out_takes_it_from_the_route` fails, "Failed asserting that two arrays are identical"; with the context's answer not used, `test_a_plugin_that_prices_some_shoppers_apart_says_so_and_each_gets_their_own_card` fails, "Failed asserting that two strings are identical."

**Status:** Accepted.

### D-115 · The abilities that check and price a build · 2026-11-10

`docs/ROADMAP.md` (milestone 4): "read-only and give the route's answers, as Creative Reviews registers its own". `Abilities\CheckBuildAbility` and `Abilities\PriceBuildAbility`, over `Abilities\BuildAbility`.

- **Parts of the route's answer, by the route's classes.** Each asks `Service\BuildState` and has `Front\StateAnswer` write the answer, then keeps the keys it is for: `kit`, `ready`, `refused` and `problems`, or `kit` and `total`. Nothing is worked out a second time, so there is nothing to drift. `BuildAbilitiesTest` holds each to the route for five builds, each with assembly on, off and left out.
- **Open to whoever may run an ability** (`__return_true`), as Creative Reviews' read-only abilities are (`creative-reviews-for-woocommerce/src/Abilities/Abilities.php:119`) and as the route is open to whoever asks. WordPress serves an ability over REST to a signed-in user alone. What it says is what the kit's page says, and a kit a visitor may not see is `cbuild_kit_not_found` to everyone, an administrator included, through the same `Port\Kits::isPublic()`. The deliberate break below is why that check is in the ability and not left to the service: the service reads drafts.
- **No rate limit.** An ability is run by code on the site or by a signed-in user through WordPress's own route; the limit is for the route anyone can reach. For Zach at the review, with the limit's other questions.
- **One class to an ability.** `composer api:check` reads each ability's `outputSchema()` from the class that registers it, so the two are two classes over a shared one. The output schemas are written out in PHP, with no `$ref`, since WordPress holds an ability's answer against its schema with a validator that reads none; `BuildAbilitiesTest` shows an answer with a blocked pair and a refused pick passing.
- **A slot key the kit lacks is not a refusal here,** where it is a 400 on the route: an ability's input schema is fixed when it is registered and knows no kit, and the answer's `refused` already says `slot_unknown`.
- **Both stay out of the mutation run** with the rest of `Abilities`, which the list has held since D-087.

**Deliberate breaks.** With the public check taken out, `BuildAbilitiesTest::test_a_kit_a_visitor_may_not_see_is_not_there_for_an_ability_either` fails, "Failed asserting that Array (...) is an instance of class \"WP_Error\""; with `assembly` not passed on, `test_what_an_ability_says_of_a_build_is_what_the_state_route_says_of_it` fails, "Failed asserting that two arrays are identical." The tests passed on their first run, so there is no red run to quote beside these.

**Status:** Accepted.
