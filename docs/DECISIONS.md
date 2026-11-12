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

### D-116 · What the builder needs of a part is kept in a table of its own · 2026-11-10

The spec's budget is 80 ms at p95 for the state route with 500 candidate parts, held "without a persistent object cache". The first run of `StateRouteBenchmarkTest` (2,000 parts in a kit, 500 of them trucks) took 489 ms at p95. Timed piece by piece, 440 ms of it was `Adapter\WooKits::parts()` loading every candidate of every slot as a WooCommerce product, to learn whether it may be sold, its price and its stock. No budget holds while a request loads 2,000 products, with or without an object cache: with every product already cached, 500 still took 106 ms.

The first answer kept each slot's list in a transient (commit `ae04b23`). It held the budget, but one change to one product threw every list away, and the next request read all 2,000 products again: 435 ms. Asked which of leaving it, refreshing in the background or a table was best practice, I recommended the table; Zach said "yes, add the table", and "go" to the shape shown (CLAUDE.md: a table after milestone 1 needs him). The transient lists, `Service\PartRows` and the option `cbuild_parts_mark` are gone; none was ever released.

- **The table, `{prefix}cbuild_parts`.** One row per product or variation and price context: its parent, whether it may be sold, its brand, the price of one as the shop shows it in minor units, whether it is in stock, how many the shop holds where it counts them, a variation's own attribute values, its specs as the index held them, and when it was read. Keyed on the part and the context, indexed on the parent and the context. Made by the migration `schema_2`; the schema's version is 3.
- **One column more than Zach saw: `specs`.** The shape he said "go" to had no specs; they were to come from the index on each request. Reading and building 2,000 parts' specs from the index was 8 ms and a second query, and the cold run stood at 88 ms. With the specs in the row it is one read. They are a copy of the index's, written when the row is, and the index's own jobs forget the row. For Zach at the review.
- **`read_at` is UTC seconds, not a `datetime`.** The Clock's timestamp goes in and comes out as it is, with no conversion in SQL that the database's time zone could shift.
- **A request reads rows, and only what is missing or old from the shop.** `Port\Kits::offered()` finds a slot's product IDs by term (`get_objects_in_term()`, with a hierarchical term's children), reads their rows and their variations' in `Repository\PartFactsRepository`, and reads from WooCommerce only the IDs with no row or an old one, writing those back. A product that moved costs the next request that one product.
- **What is found by term is not yet known to be sold.** The term lookup returns drafts and hidden products too. Each gets a row all the same, marked unsellable, and so does an ID that is no product at all, so it is not asked of the shop again on every request.
- **A row is believed for an hour, and up to an hour more.** Its age limit is an hour plus as many minutes as the ID leaves when divided by 60, so a catalogue read at once does not fall due at once. A change nothing announces (a price changed straight in the database) shows within that time.
- **Kept per price context,** the one a card is kept under (D-111, `WooPartCards::context()` for both): the currency, the language, the tax display and the tax rates that apply, and whatever a plugin adds with `cbuild_price_context`. No address and no user is in it. The first request in a new context reads every part of the kit once: 470 ms at 2,000 parts, on one request, per context. The benchmark prints it and does not hold it to the budget.
- **What deletes rows** (`Hooks\CardHooks`), each found in the source wp-env installed, with the files and lines in the class: everything that already forgets a card (a product or variation saved, its stock or stock status set, a sale starting or ending, a post deleted); a product or variation made; a product's terms set; a product's status moving by any road (`transition_post_status`); the index written by its job. Each deletes the rows of that product, its parent and its variations, in every context. A brand or attribute term edited or deleted, and a whole reindex, empty the table: a term's slug is in every row that names it.
- **`cbuild_parts_changed` is new public API,** an action a plugin does when its answer to `cbuild_part_available` changes, or a price it sets, with no product saved. With a list of IDs it deletes those parts' rows; with none, all. `composer api:check` lists the actions the plugin listens for (`listened_actions`), beside those it does.
- **The recommender takes options already worked out.** `Domain\Fit\Recommender::among()` is `recommend()` without the engine's second pass over the slot, which `BuildState` had already made.
- **One kit is read, not the shop's list.** `Kits::kit()` reads the kit asked for; `BuildState` read all of them, up to 200, to find one.
- **The Catalogue check, the Build tab and a kit's save still read the shop itself** (`Kits::parts()`). They are the owner's, asked for by hand, and must show what is there now.
- **For milestone 5.** Adding to the cart must read the parts picked from WooCommerce itself, not from rows: seven products, loaded once, and the price charged is then the price of that moment. An issue in milestone 5's list says so.
- **No class joins the mutation run's exclusions.** The repository is in `src/Repository`, out by D-087's rule since milestone 3, and `Adapter\WooKits` since D-103; both are held by `PartsTableTest` in the integration site. `BuildState`, `CardHooks` and the recommender are mutated, and every mutant is killed.

**Measured** (Apple M1 Max, wp-env, Xdebug off, no persistent object cache): cold, p95 58 ms and 42 queries; warm, p95 41 ms and 15 queries; the first request after one part moves, p95 71 ms. docs/BENCHMARK.md has the run.

**Red run.** `StateRouteBenchmarkTest::test_the_state_route_answers_within_80_ms_at_p95_with_500_candidates_in_a_slot`: "The state route, cold. Failed asserting that 489.333745 is less than 80.0."

**Deliberate break.** With `Hooks\CardHooks` deleting no row, the tests of `PartsTableTest` that move a part fail, the first `test_one_part_that_moves_is_read_again_and_no_other`: "the one that moved. Failed asserting that two arrays are identical." Among the rest, "a price is saved": "Failed asserting that 6000 is identical to 7500."

- **What the spec said and what this changes** (the spec reviewer's should-fix 4). Spec, How it fits: "Stock is read from WooCommerce when the builder asks." Spec, Stock: "What the builder offers is worked out when it's asked, never synced. Imports and bulk edits that skip WooCommerce's functions can't leave it stale." With the table, what the state route offers comes from rows: a change WooCommerce announces deletes the row at once, and one it does not (a price or a stock count written straight to the database) shows within two hours, or at once after `cbuild_parts_changed`. What is charged and what goes to the cart is read from WooCommerce itself (issue #26). Zach said yes to the table; he has not been asked about these two sentences, and the spec now carries a note beside each. For Zach at the review.
- **Rows are deleted once a request** (D-119).

**Status:** Accepted. The table is Zach's word of 10 November; the `specs` column, the first request in a price context being outside the budget, and the spec's two sentences on stock are for him at the review.

### D-117 · axe's target-size is quarantined in a variation's Part specs, and the headings are measured there instead · 2026-11-10

Issue #22. `part-specs.spec.ts`, "a variation names its size in a maker's chart", has failed twice on axe's `target-size` for the Deck group's heading: in CI on `main`'s merge of milestone 3 (webkit-phone, "smallest space is 261.2px by 20.6px") and once locally in a full two-project run (chromium-phone, "281px by 23px"). Both name as covering it the row's attribute select, which is about 1,000 px above it in the settled page, and the next group's heading, which starts where it ends. The heading is 34 px high.

Tried, and none showed it: the test alone, 12 times; three full two-project runs; jQuery's animations slowed to 6 s; the row's box clipped 20 px into the heading, as a slide would leave it; a scan at eight moments of the row's slide; a scan while the page scrolls. So it is not the layout as a shopkeeper meets it, and I cannot yet say what it is.

CLAUDE.md: a flaky test is fixed or quarantined with an issue the same day. Quarantined, as narrowly as it goes:

- `target-size` alone is left out of axe's two scans of a variation's Part specs. Every other rule still runs there, and `target-size` still runs on the product's own Part specs tab and everywhere else.
- In its place the test measures what the rule is for: every group heading in the variation at least 24 px by 24 px, and none overlapping the one before.

Issue #22 stays open in milestone 4's list until the cause is found and the rule is back.

**Deliberate break.** With the padding above and below a heading taken out of the panel's rule, the measurement fails in chromium-phone: "Expected: >= 24. Received: 18.1875".

**Status:** Accepted. For Zach at the review: the quarantine, and whether it may stand past this milestone.

### D-118 · The audit's two blockers: a kit made on the product screen is hidden, and an ability needs someone signed in · 2026-11-10

Both were found by the auditor reading the source wp-env installed, and both were confirmed by a test that failed before anything was changed.

- **A kit is hidden by every save.** D-100 overrode the kit's `get_catalog_visibility()`, and WooCommerce's data store writes the two visibility terms from it. But on an update it writes them only when visibility is among the product's changes (WooCommerce 11.2.2, `includes/data-stores/class-wc-product-data-store-cpt.php:1039`), and what is read of a kit is already hidden, so no setter records a change. A product made a kit on the product screen, the only way an owner has, kept the terms of a visible product and stayed in the catalogue, in search and in feeds at its assembly fee. `Adapter\KitProduct::save()` now puts visibility among the changes outright, and `set_catalog_visibility()` takes nothing but hidden. D-100's tests made their kits with `new KitProduct()`, a create, which WooCommerce forces the terms for; the new test builds the kit on an existing post, as `WC_Meta_Box_Product_Data::save()` does (`includes/admin/meta-boxes/class-wc-meta-box-product-data.php:337`).
- **The two build abilities answer to someone signed in.** D-115 left them open (`__return_true`) and said WordPress serves an ability over REST "to a signed-in user". It does not: the run route asks the ability's own callback and nothing else (WordPress 7.1.4, `wp-includes/rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php:143`). So a visitor could ask `check-build` and `price-build` as often as they liked, which is the state route with no limit. The callback is now `is_user_logged_in`, which makes D-115's sentence true. Code on the site that runs an ability with no user is refused as well; the other road, leaving them open and counting them against the route's limit, is Zach's to choose at the review. Creative Reviews' read-only abilities are open in the same way (`creative-reviews-for-woocommerce/src/Abilities/Abilities.php:119`); that is another repository's, and is for Zach.

Three of the audit run's failures were the tests' or the tools' own, fixed with these: `StateRouteTest` leaned on a route another test had registered and on Site Health being an administrator's, which on a network it is not; `BuildTabTest` flushed the whole object cache between a save and the next screen, which on a site with a persistent one takes what the person typed with it; `tools/zip-lifecycle.sh` expected two tables after an install. `tools/infection.sh` gives Infection 1 GB, since 128 MB ran out printing a class of a thousand lines.

**Red runs.** `KitHiddenTest::test_a_product_made_a_kit_on_the_product_screen_is_hidden_by_that_save`: "exclude-from-catalog. Failed asserting that false is true." `BuildAbilitiesTest::test_a_visitor_who_is_not_signed_in_runs_neither`: "Failed asserting that 200 is identical to 401."

**Deliberate breaks.** With `KitProduct::save()` taken out, the first fails as above. With the callback `__return_true` again, the second fails as above.

**Status:** Accepted. Signed in or counted is for Zach at the review.

### D-119 · The audit's should-fixes of milestone 4 · 2026-11-10

Found by the `auditor` and `spec-reviewer` agents on the diff, each held by a test.

- **A product's save deletes its rows once, and another post costs nothing** (auditor 6). `Hooks\CardHooks` deleted rows on every `set_object_terms` and `deleted_post`, of any post type: a select and a delete for each taxonomy a product's save sets, and two queries a taxonomy for a page or a post. `Adapter\WooKits::forget()` and `Adapter\WooPartCards::forget()` now only note the IDs, and `settle()` deletes once, on `shutdown` or before the same request reads a row or a card. The hooks look at the post's type first.
- **A batch of the reindex deletes its own parts' rows** (auditor 7). `CardHooks` emptied the whole table after every batch of 100, so between two batches every request read the whole shop again, 470 ms at 2,000 parts. `Service\IndexJobs` now tells `Port\Kits` of the parts each batch indexed, from the queue and from WP-CLI alike. A brand or attribute term edited still empties the table.
- **Slots are not saved beside presets they break** (auditor 3). The Build tab saved the slots while the presets sent with them were refused, and the stored presets and the new slots made a kit that did not read: a 404 to every shopper, and a notice that said only that the presets were not saved. `Service\KitEditor` holds slots that would be saved without their presets against the presets that stay, and refuses them with the new code `slots_leave_presets`.
- **A kit that does not read is said** (auditor 11, spec reviewer 3). The Catalogue check lists it under the new code `kit_unread`, with a link to its Build tab. `Service\BuildState` writes a line to the log when a caller asks for a kit that does not read, and when a build cannot be priced, each at most once an hour (`Port\Logger::hourly()`, a transient `cbuild_logged_<what>`), with the kit's ID and nothing of the request. Site Health is not told; the check and the log are. For Zach if he wants a Site Health test as well.
- **The limit's counts take 1,024 rows at most** (auditor 4). Without a persistent object cache each address and minute was a transient of its own, never read again, so two rows of the options table stayed for each until WordPress's daily clean, and addresses that keep changing filled the table. `Adapter\CacheTally` now keeps the counts in 1,024 transients at most, each holding the hashes that fall to it and when each ends, and a row lasts no longer than its last count. A request costs two writes or four. With an object cache nothing changes. A table would be simpler and is Zach's to ask for (D-112).
- **The route's query count is held** (auditor 8): the same warm and cold for a step of 30 parts and of 60, the limit's own writes left out since their number moves with the second.
- **Each row's buttons and fold are named with the row** (auditor 9), by `aria-labelledby` on the button and the row's legend: "Move up Slot: Trucks". Revised by D-120: the buttons carry the legend's words as hidden text.
- **`composer api:check` holds the route's arguments, the error codes and what each ability takes** (spec reviewer 1). `Rest\StateRoute::arguments()` is the route's arguments, read by `tools/api-surface.php` as the abilities' schemas are.
- **A site on 0.3.1 gains the table** (spec reviewer 2): `MigrationTest` starts from that release's version and states. No code changed.

Left for Zach, since each is his under CLAUDE.md: the route's prices where tax depends on where the shopper is (auditor 5), and the first request in a price context being outside the budget (spec reviewer 5). Both are in the review.

**Red runs and deliberate breaks.**
- `KitEditorTest::test_slots_that_the_presets_left_in_force_cannot_live_with_are_not_saved`, before the fix: the problems lacked "slots slots_leave_presets slots".
- `PartsTableTest::test_a_products_save_deletes_its_rows_once_and_another_posts_terms_cost_nothing`, with `forget()` deleting at once: "nothing while the request runs. Failed asserting that two arrays are identical."
- `CacheTallyTest::test_each_key_is_counted_on_its_own_and_the_rows_are_bounded_however_many_keys`, with a row for each key: "Failed asserting that 3002 is equal to 1024 or is less than 1024."
- `IndexJobsTest`, `BuildStateTest`, `CatalogueCheckTest`, `BuildTabTest` and `StateRouteTest` each gained a case written with its fix; mutation testing holds the three services' cases.

**Status:** Accepted.


### D-120 · A row's buttons are named with hidden text, and a percentage is read with one cast · 2026-11-11

Two failures of the audit's second full run, both in what D-119 and the settings added.

- **The buttons.** D-119 named each row's Move up and Move down with `aria-labelledby` on the button and the row's legend. In Firefox, the page crashed when axe read the Build tab after a row had moved ("Error: frame.evaluate: Page crashed", `firefox-desktop` and `firefox-phone`). It crashed the same with the button named from a span inside it and the legend, so the reference to the legend is the cause, not the button naming itself. The buttons now hold the legend's words in a `screen-reader-text` span, as WordPress's own list tables name their row actions: the name is the same, "Move up Slot: Trucks", and nothing is looked up when a row moves. The fold keeps `aria-labelledby`: it crashed nothing, and a `summary` is not moved by a press.
- **The percentage.** `BuildTerms::discountFrom()` cast the whole part and the places apart and added them. Take either cast away and PHP adds the numeric strings to the same number, so no test could tell, and only static analysis caught the two mutants (D-063 allows none unlisted). It now joins the two strings and casts once; without that cast the method returns a string and every case fails.

**Red runs.** `build-tab.spec.ts` "a slot's row moves by its buttons from the keyboard, and the kit keeps the order", Firefox: "Error: frame.evaluate: Page crashed". `composer infection`: "Caught by static analysis and by no test: CastInt src/Domain/Kit/BuildTerms.php, 2 (allowed 0), at line(s) 124, 124."

**Deliberate breaks.** With `aria-labelledby` back on the buttons, the Firefox test crashes as above. `BuildTabTest::test_each_rows_buttons_and_fold_are_named_with_the_row` holds the markup, and fails when a button is named from the legend again.

**Status:** Accepted.

### D-121 · Zach's answers of 11 November 2026, on the audit's three questions · 2026-11-11

Asked before the pull request opened, since each is his under `CLAUDE.md`.

- **The two build abilities stay signed in** (D-118). "thats fine as is. keep it signed in". The other road, open and counted against the route's limit, is not taken.
- **Tax by where the shopper is, is deferred to milestone 5** (auditor 5, a should-fix). "defer is gine". Until then the state route prices for the shop's base address where a shop's tax depends on the shopper's. Milestone 5's cart knows the customer, and the route reads the same one then. An issue in milestone 5's list holds it.
- **The first request in a price context, 470 ms against the 80 ms budget** (spec reviewer 5). "whatever is best practice". The practice is that no visitor pays to fill a cache: the rows are read in the background and a request finds them there. That is owed before the first shopper's request, which is milestone 6's builder, so 0.4.0 ships as measured and an issue in milestone 6's list has the reindex job fill the default price context's rows. The benchmark holds every other request to the budget now, and will hold this one when the job fills it. This reading of "best practice" is mine.

**Status:** Accepted.

### D-122 · Claude in Cowork's review of milestone 4's pull request · 2026-11-11

One blocker, one should-fix and four minors, on pull request #30. Each fix has a test that failed without it.

- **The API snapshot is read in one order on every disk** (blocker). `tools/api-surface.php` listed a directory's files in the disk's order, which APFS and CI's ext4 give differently, and `ability_schemas` is compared in order: the first full CI run failed on the order alone, with every hash the same. The list is sorted, and the snapshot rewritten in that order. Since the job stopped there, the dependency audits, the benchmarks and the PHP 7.4 parse had not run on the commit; the merge waits for a full run with them green.
- **A Build tab post that was cut short saves nothing** (should-fix). PHP keeps the first `max_input_vars` fields of a form and drops the rest with no error (D-104), and `Service\KitEditor` reads a field that is not sent as a choice: a rule off, a slot or a preset gone. The panel's last field is now a hidden one, `cbuild_kit[end]`, and a post that arrives without it is refused whole: nothing is written, a notice says only part of the form reached the server and names `max_input_vars`, and what did arrive is kept for an hour as a refused section is, so the tab shows it as typed. D-104 fixed the Part specs panel in the plugin and did not ask a host to raise the limit; here the fields are the kit itself, so there is nothing to leave out, and the notice names the setting because that is the one thing that lets the save through.
- **A slot the kit does not have is refused before the state is worked out** (minor 1). `Rest\StateRoute` reads the kit, checks the keys named and answers 400; the state service is not built for it. A kit that does not read is still left to the service, which logs it (D-119).
- **The Build tab is drawn for who can save it** (minor 2): neither the tab nor its panel for a user without `manage_woocommerce`, who could edit the product and see fields that saved nothing.
- **Each slot's Attribute values fold is named with its row** (minor 3), by `aria-labelledby` as the slot's own fold is. The buttons keep their hidden text (D-120).
- **`docs/SPEC.md`'s truck table under R1 is the seeds'** (minor 4): each maker's own chart, as D-093 seeded them, with Thunder's 181 and the ranges of its 143 and 161. Cowork said this needed no answer from Zach.
- **Noted, not changed:** a read of the parts table that races a save can write its row after the save's delete. The row is then as old as that read, and the two-hour age the rows are read with bounds it, as it bounds D-116's other gaps.

**Red runs.**
- CI run 42969051989, `composer api:check`: "The public API differs from tests/Fixtures/api-surface.json", on `ability_schemas` alone.
- `BuildTabTest::test_a_post_cut_short_saves_nothing_says_why_and_keeps_what_arrived`: "nothing is saved. Failed asserting that two arrays are identical."
- `BuildTabTest::test_the_tab_is_drawn_only_for_who_can_save_it`: "Failed asserting that an array does not have the key 'cbuild_build'."
- `BuildTabTest::test_each_rows_buttons_and_fold_are_named_with_the_row`: "more. Failed asserting that 0 is identical to 1."
- `StateRouteTest::test_a_slot_the_kit_does_not_have_is_refused_before_the_state_is_worked_out`: "LogicException: The state was worked out for a slot the kit does not have."

**Deliberate breaks.** With the sort commented out, `composer api:check` fails on this Mac as it did in CI. With the end field ignored, the capability checks removed and the fold's attribute taken out, the three `BuildTabTest` cases fail as above. With the route's check moved back after the state, the route's case fails as above.

**Status:** Accepted.

### D-123 · The second full CI run of milestone 4: apt that hangs, and a benchmark decided by one slow request · 2026-11-11

Neither is a fault of the plugin's code; both cost billed minutes.

- **Playwright's system packages are installed in tries that end.** On run 43057146618 the newest and oldest legs sat on `npx playwright install-deps` for 15 and 19 minutes, and again on the re-run: the runners could not resolve Ubuntu's mirrors ("Temporary failure resolving 'azure.archive.ubuntu.com'", and the same for `archive.ubuntu.com` and `security.ubuntu.com`), and apt waits for as long as the job has. From outside, the Azure mirror failed for a while and the other two answered throughout. Each of the two install steps now runs up to three times, each try stopped by `timeout` after three minutes (five where the browsers are downloaded too). A hang costs nine minutes at most, not the job's sixty, and a short outage is ridden out. Pointing apt at the main mirror, which Zach found suggested, is not added: the runners failed to resolve that one as well. It stays on issue #32 with caching the packages.
- **The benchmark's request after a part moves is measured 40 times, as the cold and warm runs are.** On the re-run the newest leg failed with "The state route, after one part moves. Failed asserting that 81.024766 is less than 80.0." The cold and warm runs passed at 65.8 and 45.6 ms. That case took 20 samples, so its 95th percentile was the second slowest request, and two slow ones on a shared runner decided it; cold and warm take 40. It takes 40 now, from an address of its own, since the three runs together are more than one address may ask in a minute, which the first try at this found out with a 429. It also prints its median and its queries. The budget is not moved: 80 ms at p95.
- **Checked before settling on that:** whether the code had slowed. It had not. The route as it was before D-122's change, and the whole tree at the commit `docs/BENCHMARK.md`'s figures came from, both measure the same on this Mac today (cold median 68 ms) as the current code. The Mac is slower today than when the table was taken, which the benchmark's page now says.
- **For Zach:** the budget is held on GitHub's shared runners with about 14 ms to spare when cold. If it fails there again on a run where the code has not changed, how a time budget is held on hardware nobody controls is his to decide; it is not decided here.

**Status:** Accepted.

### D-124 · The state route's time budget is held on a known machine, and CI holds its queries · 2026-11-11

The third full CI run of milestone 4 failed where the second had passed: "The state route, cold. Failed asserting that 83.695002 is less than 80.0", median 73 ms. The run before it, of the same code, measured 65.8 ms at p95 and a median of 60. GitHub's shared runners moved by a fifth in an hour, and a budget with 14 ms to spare fails on that at random, each time for the price of a run.

Zach was asked how CI should hold the budget, as D-123 left to him, and chose on 11 November 2026: "Split it (Recommended)".

- **The queries are held everywhere:** 42 at most for a cold request, 15 warm, 37 after a part moves. They do not move with the machine, and a change that makes the route slower by doing more work shows in them.
- **In CI the time is held to 120 ms,** which a gross slowdown still fails. `ci.yml` sets `CBUILD_BENCHMARK_SHARED=1` on the benchmark's step, `tools/benchmark-integration.sh` passes it into the container, and the test reads it.
- **The 80 ms budget is held on the Mac the figures in `docs/BENCHMARK.md` come from,** by `composer test:benchmark:integration` in `/audit-milestone`'s local run before each milestone's pull request, and its numbers go into the review. The skill's list now names the command.
- **Nothing else moves.** The spec's budget is 80 ms. The fit engine's benchmark and milestone 3's are as they were.

**Red run.** CI run 43069596131, as above. **Deliberate break:** with the cold count set to 41, "The state route's queries, cold. Failed asserting that 42 is equal to 41 or is less than 41."

**Status:** Accepted.

### D-125 · StrykerJS copies only what its tests read, and the admin scripts' mutants are killed · 2026-11-11

Issue #31, which Zach agreed on 11 November 2026 as milestone 5's first commit ("makes sense, lets go with your plan.").

- **The sandbox leaves out what Vitest never reads:** Composer's packages, `tmp`, `docs`, `build`, `languages`, `test-results` and the PHP and browser test folders (`ignorePatterns`). The warnings about PHP_CodeSniffer's HTML fixtures are gone, and a run takes 23 seconds where it took 36.
- **`tools/mutate-js.sh` unsets `GITHUB_STEP_SUMMARY`.** Vitest 5.1's GitHub reporter writes a job summary on every run, and Stryker runs Vitest once a mutant, so the summary page held a red report per killed mutant. The clean suite's report, from `npm run check`, is the one that stays.
- **The score is 99.65 %, from 92.78 %,** against the floor of 90: 282 killed, 3 timed out, 1 survived. Twenty survivors and the one uncovered mutant are gone, by tests where the mutant was a different program and by simpler code where it was not:
  - tests for the frame-later `settle()` in both scripts, `preventScroll: true`, the rows counted without what else a list holds, the name said without the space around it, a row whose other button is gone, the `^` of the address pattern, the tab's own prefix as where `find()` stops, `wp` loaded without `a11y`, a window with no frames, and each script starting itself when the page loads it. `groups()` had survived because every earlier test left its listener on the same document; its test now starts the script on a page of its own;
  - a group is opened by `group.open = true`, not `setAttribute( 'open', '' )`, which any string satisfied; the groups a field is folded into are found by `contains()`, not by walking parents through a guard that never tripped; and `move()` reads the list's data without asking whether a parent element is an HTML one, which it always is.
- **One mutant is the same program, and stays:** `assets/src/admin/part-specs.ts:131`, `field( hash ) ?? ''` with any other string that is no element's ID. Both ask the page for an element it does not have.
- **`ignoreStatic` is still not set.** It would take 71 mutants out of the score to save part of 23 seconds.

**Red run.** `bash tools/mutate-js.sh` before the tests: 20 survived and 1 not covered, among them "[Survived] CallExpression assets/src/admin/build-tab.ts:91:2 - settle( doc.defaultView, control );" and "[Survived] CallExpression assets/src/admin/part-specs.ts:190:2 - groups( win.document );".

**Status:** Accepted.

### D-126 · CI does not cache wp-env's folder or apt's packages, and apt falls back to Ubuntu's own mirror · 2026-11-11

Issue #32, which Zach agreed to try on 11 November 2026 ("sure your suggestion sounds good"), to keep only if it saved time.

- **`~/.wp-env` is not cached, and no run was spent finding out.** wp-env 12's own source says what a restored folder would do (`node_modules/@wordpress/env/lib/runtime/docker/index.js:105` and `:203`, `lib/download-sources.js:103`):
  - restored with its `wp-env-cache.json`, the configuration's hash matches, so wp-env skips the downloads and also skips installing WordPress, on a runner whose database is new. The site would not be there;
  - restored without that file, wp-env downloads every zip again and replaces each folder, so the cache saves nothing and costs its restore and its save of several hundred megabytes a leg.
  The step's time (about 90 seconds for one site, 175 for two, in run 43074042593) is the Docker images, the zips and the installs, and none of them can be kept by a cache of that folder. Caching the images was ruled out on the issue already.
- **apt's packages are not cached.** `npx playwright install-deps` asks apt for whatever the runner's image lacks that week, and apt needs its index from the mirrors before it will install anything, which is the step that hung. A cache of `.deb` files would be keyed on an image that changes weekly, and the action that does it is a tool these docs do not name.
- **After a try fails, apt is pointed at `archive.ubuntu.com`.** The hosted runners use Azure's mirror; on 11 November it failed from outside while Ubuntu's own answered. The line Zach found runs only between tries, so a healthy run is as it was, and a run where Azure's mirror alone is down gets its second try from the other. D-123's tries and timeouts stay.
- **Not shown by a forced failure,** as the issue asked: that would cost a full run to watch one line of `sed`. It is read on the first run where a try fails, and the warning says so.

**Status:** Accepted.

### D-127 · A save that moves no source of the index queues no job · 2026-11-11

Issue #21, from Claude in Cowork's review of milestone 3: once builds are bought, every order line saves its product, and every save queued `cbuild_index_part` to read the three sources and write the same rows back.

- **What a stock change fires, in WooCommerce 11.2.2.** `wc_update_product_stock()` writes the count with a query of its own and then saves the product (`includes/wc-stock-functions.php:58` to `:64`). The save has nothing changed, or `stock_status` alone when the last one sells, and `woocommerce_update_product` fires all the same, with the ID and the product and no word of what changed (`includes/data-stores/class-wc-product-data-store-cpt.php:398`; a variation's at `class-wc-product-variation-data-store-cpt.php:279`).
- **So what a save changes is noted before it, and asked after.** `Service\SavedSources` is told, within the request:
  - by `woocommerce_before_product_object_save` (`includes/abstracts/abstract-wc-product.php:1559`), the properties the save changes;
  - by `added_post_meta`, `updated_post_meta` and `deleted_post_meta` (WordPress 7.1.4, `wp-includes/meta.php:168`, `:353`, `:550`), a write of `_cbuild_specs` or `_cbuild_spec_table`, which a product's changes do not list;
  - by `set_object_terms` (`wp-includes/taxonomy.php:3007`), an attribute's terms changed past WooCommerce and followed by a save.
- **The job is skipped only when nothing but selling moved:** every changed property is one of `stock_quantity`, `stock_status`, `total_sales` and `date_modified`, no spec document was written, and no `pa_` taxonomy's terms changed. Anything else queues the job as before: a property not on that list, whatever it is; a save nothing was noted of; a new product. The list names what is beside the point, not what is a source, so a property WooCommerce adds later errs towards the job.
- **A save that changes nothing queues nothing either,** which is what an order's save is. One test saved a variable product unchanged to queue its job; it now renames it first.
- **The cost on a request that saves no product:** the three meta hooks and the terms hook each compare a string and return. Nothing is built for them, and nothing is kept for a post that is never asked about but the two flags.
- **The parts table is not touched by this.** Its rows are dropped by `Hooks\CardHooks` when stock or price moves (D-116), as before.

**Red run.** "IndexJobsTest::test_a_stock_change_queues_no_job_and_a_change_to_a_source_still_does: stock reduced as an order reduces it. Failed asserting that two arrays are identical." at `tests/Integration/IndexJobsTest.php:119`, with one job waiting for the part. **Deliberate break:** `IndexJobs::updated()` queuing whatever the save changed fails the same line, and the unit test's "stock alone".

**Status:** Accepted.

### D-128 · How a build reaches the cart · 2026-11-11

Milestone 5's first step: the keys' shapes, and where each route's request is judged. One service, `Service\BuildCart`, answers every hook, so the Store API and the form cannot disagree.

- **The cart item keys are the spec's, and `cbuild` holds two things more.** `schema_version`, as every document the plugin stores has, so a line an older or a newer version wrote is recognised and not sold on a guess. And `quantities`, slot key to how many of the part one build takes, fixed when the build is judged: the parts' lines are added from it, and the parent's quantity will multiply it, without the kit being read and the fit engine run again for a number already decided.
- **The Store API's build is judged in `woocommerce_store_api_add_to_cart_data`, not in the two validation hooks the spec names.** In WooCommerce 11.2.2 the cart controller checks `is_purchasable()` before it applies either (`src/StoreApi/Utilities/CartController.php:273`), and a kit is not purchasable. `woocommerce_store_api_add_to_cart_data` is applied with the request before the controller is called (`src/StoreApi/Routes/V1/CartAddItem.php:118`), so the build is judged there, a refusal is the route's own `RouteException` with the code, and a build that is taken goes into the line's `cart_item_data`. `woocommerce_add_to_cart_validation` still runs after, and passes a kit whose build was taken that way.
- **The form's build is judged in `woocommerce_add_to_cart_validation`,** as the spec says (`includes/class-wc-form-handler.php:1088`), and attached in `woocommerce_add_cart_item_data` (`includes/class-wc-cart.php:1331`). Both ask the service, which answers the second from what it said to the first, so a build is judged once and gets one identifier.
- **A kit is purchasable only while a build of it is taken and its parts are not yet added.** `woocommerce_is_purchasable` answers true for a kit in that window, and for the product object of a cart line the cart has priced. Outside it a kit is refused by WooCommerce itself, whoever adds it: another plugin's `WC_Cart::add_to_cart()`, an express button, a link with `?add-to-cart=`. A line that comes back from the session is asked through `woocommerce_cart_item_is_purchasable` (`includes/class-wc-cart-session.php:198`): a kit's line with a `cbuild` that reads.
- **A kit's line that arrives with a `cbuild` the service did not take in that request is removed** when `woocommerce_add_to_cart` fires. The key is public and anyone can write one; only the service's own verdict counts.
- **The parts are added on `woocommerce_add_to_cart`** at priority 10, before WooCommerce's own totals at 20 (`includes/class-wc-cart.php:133`), which save the session with `cbuild_children` in place. If WooCommerce will not add one, every line of the build is taken out and the add fails with `cbuild_not_added`: a `RouteException`, which the Store API answers with and which `WC_Cart::add_to_cart()` turns into its notice, being an `Exception` (`:1484` is inside its `try`).
- **The form's `cbuild` field is read without a nonce.** WooCommerce's add-to-cart form carries none, for a page cache's sake, and the field holds only IDs, each of which is checked against the shop. It is read from the post alone: a build is never added by a link.
- **The kit's line is priced by `set_price()` on the line's product object,** on `woocommerce_add_cart_item` and `woocommerce_get_cart_item_from_session`, at the assembly fee or nothing. The kit product's own price is not changed, and a kit read anywhere else still shows its fee.
- **Mutation testing.** `Cart\CartHooks`, `Adapter\WooCart` and `Adapter\WpUuids` cannot run without WooCommerce and WordPress, and the integration suite proves them (`CartAddTest`). The pattern `Hooks` in `infection.json5` had matched `Cart/CartHooks.php` by its name; it is now `Hooks/`, the folder it was written for. Whether the three are named in the list is Zach's (D-038), and is asked at the review. Infection makes no mutant in a file no unit test loads, so the floor is the same either way.

**Red run.** "CartAddTest::test_a_part_woocommerce_will_not_add_takes_the_whole_build_out_and_fails_the_add: Failed asserting that 500 is identical to 400." at `tests/Integration/CartAddTest.php:337`: the refusal was a plain exception wherever `WC()->is_store_api_request()` was false. **Deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-129 · How a build in the cart is kept whole · 2026-11-11

Milestone 5's second step. `Service\BuildLines` holds the rules, and `Cart\LineHooks` tells it of each change and holds none.

- **The parts follow the kit's line on `woocommerce_after_cart_item_quantity_update`, not on `woocommerce_cart_item_set_quantity`,** which the spec names. In WooCommerce 11.2.2 `WC_Cart::set_quantity()` fires the first, works out the totals, and then fires the second (`includes/class-wc-cart.php:1562`, `:1565`, `:1576`). On the second the totals would be worked out before the parts moved, and again after. On the first they are right the first time.
- **A part's line set alone is set back,** whatever set it. The carts are also told not to offer it: `woocommerce_store_api_product_quantity_editable` is false for a part (`src/StoreApi/Utilities/QuantityLimits.php:309`), and `woocommerce_store_api_cart_item_quantity_validation` (`:211`, since 11.2.0) and `woocommerce_update_cart_validation` (`includes/class-wc-form-handler.php:859`) refuse it as `cbuild_part_locked`. A part set to the quantity it must already have is let through, since the classic cart posts every line.
- **A kit's line's maximum is at least 1.** Where a part is sold out the limit is none, and a maximum of none would have the Store API cut the line's quantity to nothing with no word of why. The check below says why instead.
- **Removal goes by who names the kit's line, not by its list.** Every line whose `cbuild_parent` is the kit's key goes, so a build whose `cbuild_children` has drifted still leaves nothing behind. The hook is `woocommerce_remove_cart_item` (`includes/class-wc-cart.php:1509`), while the line is still there to be read.
- **Undo restores the build or nothing.** On `woocommerce_cart_item_restored` (`:1536`) the kit's line and each part are restored from what the cart removed in the session; if the build is not whole after, what came back is removed again. A restored kit's line is priced again on `woocommerce_restore_cart_item` (`:1532`), since WooCommerce reads its product afresh.
- **The service's own changes fire the same hooks,** so it answers none while one of its changes is under way. The flag is cleared in a `finally`: a cart that throws part way through does not leave the next change unanswered.
- **`woocommerce_check_cart_items` runs more often than the spec supposes.** Beside the cart page and both checkouts, the Store API runs it for every cart response, to fill the cart's `errors` (`src/StoreApi/Utilities/CartController.php:531`, from `CartSchema::get_item_response()`). So a build is judged again on each of those: its kit read, each part read from WooCommerce, the validator run. A cart with no build in it is passed by after one look at each line's keys. The cost is measured with the add's benchmark, later in this milestone.
- **A build that has come apart is removed; one that is whole and no longer sellable stays.** The first cannot be mended by the shopper. The second can, from the builder, and the spec keeps it for the Edit build link. Both are error notices, so neither is paid for unseen: the classic checkout stops on any error notice, and the Store API's checkout answers 409 with them.
- **A bug this step found in the last.** `Adapter\WooCart::lines()` read `cbuild_children` with a helper that keeps only string keys, so every kit's line was read as having no parts. Nothing asked until the cart was looked over, which then took every build for a broken one.

**Red run.** With `Cart\LineHooks` registered: "CartAddTest::test_the_store_api_adds_the_kit_with_its_build_and_a_line_for_each_part: Failed asserting that actual size 0 matches expected size 1." at `tests/Integration/CartAddTest.php:38`, and two more of that class. **Deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-130 · What a build costs in the cart, and what a coupon reaches · 2026-11-11

Milestone 5's third step. `Cart\PriceHooks` reads WooCommerce's objects; the two rules are `Service\BuildCart::charged()` and `::couponTakes()`.

- **The discount is taken on `woocommerce_before_calculate_totals`** (`includes/class-wc-cart.php:1605`), as the spec says, at priority 999. The price discounted is the one the line's product gives then, so a sale price, and a price another plugin sets on the line at an ordinary priority, are what it comes off.
- **It is taken once.** WooCommerce works the totals out many times in a request on the same product objects, and a price set on one stays set. The class remembers, per object, the price it found and the price it gave: where the object still says what it was given, the discount is worked from what was found. Where it says anything else, someone has priced it again, and that is the price discounted.
- **Rounding is the domain's:** `Pricer::discounted()`, half up to the minor unit, on the unit price, which is how the builder's total takes it.
- **The price struck through.** The classic cart and the Mini-Cart's template get the part's own price before the discounted one, through `woocommerce_cart_item_price` (`templates/cart/cart.php:140`), shown with or without tax as the cart shows prices. The Store API gives a line's `prices.regular_price` beside its `prices.price`, and the blocks strike the first through themselves. For a part that is also on sale the blocks strike its regular price, not its sale price: the spec says its own price, and the figure charged is right in both.
- **What "coupons follow the parent" is read as.** A part's line is taken by a coupon that would take the part as a product of its own, or that takes the kit. So a coupon for the kit alone reaches every part, and a coupon for decks still reaches a deck in a build. A coupon that names the kit as excluded, or a category the kit is in, reaches none of the build's lines. This is how Product Bundles has a bundled item inherit from its container. The other reading, that a part answers only as its kit does, would stop a shopper's coupon for a brand from working once the part is in a build. Zach kept this reading on 12 November 2026.
- **A fixed product coupon is the exception,** as the spec says: with `fixed_coupons` at `kit` no part's line takes it, whatever it names, so its amount comes off the kit's line once for each build; at `parts` it follows the rule above, and comes off each part too. Where assembly is free the kit's line costs nothing, and WooCommerce takes nothing off nothing: under `kit` such a coupon then discounts nothing of the build.
- **A fixed cart coupon is WooCommerce's to spread,** over every line, as on any cart.
- **The count** (D-009) subtracts each part's line whose kit's line is in the cart with a build, on `woocommerce_cart_contents_count` (`includes/class-wc-cart.php:738`), which the Store API's `items_count` reads.
- **Tax needs nothing of the plugin.** Each part is its own line and product, so WooCommerce taxes it in its own class on its discounted price, and the fee in the kit's.

**Deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-131 · A build on the order · 2026-11-11

Milestone 5's orders step. `Order\OrderHooks` writes; what is written of the parts' specs is `Service\BuildCart::snapshot()`.

- **One hook serves both checkouts.** `woocommerce_checkout_create_order_line_item` (`includes/class-wc-checkout.php:612`) is fired by `WC_Checkout::create_order_line_items()`, which the Store API's `OrderController::update_line_items_from_cart()` calls too (`src/StoreApi/Utilities/OrderController.php:845`). Confirmed in the source, as the spec says.
- **The tie is by the build's identifier, not by cart keys.** The spec says the cart keys are turned into order item IDs once the order is saved. An order item does not keep its cart key, so each item is given `_cbuild_id`, the build's UUID, as it is made, and once the items have IDs the parts with a kit's identifier are that kit's. Nothing is held in memory between the two hooks, so it is right whichever request finishes the order.
- **When the tie is made.** `woocommerce_checkout_order_created` (`includes/class-wc-checkout.php:522`) for the classic checkout. For the Store API, `woocommerce_store_api_checkout_update_order_meta` (`src/StoreApi/Routes/V1/Checkout.php:871`) and `woocommerce_store_api_checkout_order_processed` (`:708`).
- **The Store API makes an order's items more than once.** Since WooCommerce 10.8 the draft is made when the order is placed (`Checkout.php:807`), and kept where the payment is refused. On the next try every line item is removed and made again, with new IDs. The tie is therefore made on each of the hooks above, from the items as they are then; `OrderTest` refuses a payment, changes the cart and places the order, and the build is tied to the new items.
- **Asking again saves nothing.** An item is saved only where its `_cbuild_parent` or `_cbuild_children` differs from what is right, so a hook that fires twice costs a read.
- **`_cbuild_parent` is stored as the ID's digits.** `WC_Data::update_meta_data()` takes a string or an array, and item meta reads back from the database as a string in any case.
- **The snapshot holds each slot's label and each part's name** beside the spec's picks and specs. The order's emails and the assembly ticket are read long after, when a slot may be renamed or a product deleted, and the snapshot is what they can still trust. The label is the one `cbuild_slot_label` gave the shopper.
- **A part the shop can no longer read at that moment** is kept in the snapshot with no specs. The cart's check has already stopped an order for such a build; this is for a plugin that places an order past it, and it must not be the reason an order fails.
- **The two visible lines are written in the shop's language as the order is placed,** names and values both, as WooCommerce stores a variation's attributes: an order is a record of what the customer was shown.
- **The order screen.** It lists every item meta key not named in `woocommerce_hidden_order_itemmeta` (`src/Internal/Utilities/OrderItemMetaUtil.php:30`), underscore or not, and offers each for editing. The plugin's six are added there; the snapshot is an array, and would print as nothing a manager could read or safely change.
- **A part's name** is given with its slot on `woocommerce_order_item_name` (`templates/order/order-details-item.php:33`, `templates/emails/email-order-items.php:92`, `includes/admin/meta-boxes/views/html-order-item.php:30`). Grouping the items on those pages, and the ticket, are milestone 8's.
- **Stock is WooCommerce's.** Each part is an ordinary item of its product: reduced when the order is held or paid, given back when it fails (`includes/wc-stock-functions.php:162`, since 11.0), and restocked by a refund. The kit's item manages no stock. `OrderTest` walks all three and the plugin has no code for any.

**Red run.** Before `Order\OrderHooks`: "OrderTest::test_the_classic_checkout_writes_the_build_to_the_order: Failed asserting that actual size 0 matches expected size 1." **Deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-132 · A build shown in the three carts, and what a page pays for it · 2026-11-11

Milestone 5's display step. `Cart\ShowHooks` gives each cart what it reads; nothing in it keeps a build whole, which is `Cart\LineHooks`'s work on the server (D-129).

- **The Cart and Checkout blocks** read `role`, `build`, `parent`, `slot` and `name` from the Store API's `extensions`, and `assets/src/cart/lines.ts` hands them to `registerCheckoutFilters`. The script decides nothing and builds no markup: a part's name with its slot's label is composed on the server, so no string is translated in JavaScript.
- **A locked quantity is WooCommerce's to draw.** With `quantity_limits.editable` false the Cart block shows the number in a field nobody can change and no buttons; the Mini-Cart hides the buttons and makes the field read-only (`src/Blocks/BlockTypes/MiniCartProductsTableBlock.php:179`, `:191`, `:200`). The spec says the Mini-Cart "hides and locks" the quantity: it hides the buttons and locks the field. A classic cart gets the number alone, through `woocommerce_cart_item_quantity`.
- **The Mini-Cart prints a line's `display`, or its `value`, as HTML** (`assets/client/blocks/woocommerce/mini-cart.js`), cut to 15 words. The class comes through the Store API's `wp_kses_post` whole, and the browser test finds it in the drawer.
- **The drawer does not open by itself here.** The spec records that on the demo it opened on `wc-blocks_added_to_cart`. Whether it does is the Mini-Cart block's own setting, off by default, so the browser test waits for the count to follow the event and then opens the drawer as a shopper would.
- **The indent is a clear border.** The first try set a padding, and on Storefront that made a part's cell narrower than its kit's: the theme's own padding was the larger, and the plugin's replaced it. A transparent border at the start of the cell adds to whatever the theme has. The browser tests compare a part's cell with its kit's in each cart, and failed on the padding.
- **The styles go in the page, not in a file.** Four selectors and one declaration. Many a shop has the Mini-Cart in its header, so a file would be a request on every page.
- **Nothing reaches a page whose cart holds no build.** The smoke test of milestone 1 holds every shop page to nothing of the plugin's, and the spec's Performance section says nothing loads on pages without a builder. A build gets into a cart from the builder's page alone, which brings its own styles in milestone 6; on any other page the cart is known as the page is drawn. So the styles, and the Cart block's script, are added only where the cart holds a build then. The smoke test is unchanged and passes.
- **What that costs.** A shop whose page cache serves one stored page to shoppers with and without a cart would show a build's parts unindented in the Mini-Cart. WooCommerce sets `woocommerce_items_in_cart` so that caches pass such shoppers by, and the build is whole and locked either way: the server does that.
- **The script comes with the Cart or Checkout block alone,** on `render_block_woocommerce/cart` and `/checkout`. It needs `wc-blocks-checkout`, which those blocks load anyway; a classic cart page never gets it (D-010).
- **The Mini-Cart's test adds a second build without leaving the page.** The first is in the cart as the page is drawn, as it is for a shopper coming from the builder. The read the event asks for is held six seconds, as Skatehouse's own test holds it, and the count and the drawer follow.
- **A race the test had, fixed the day it showed.** One right-to-left run sent the event before the Mini-Cart's script had begun to listen, and the count stayed at 1. The count on the page is the server's, so it is no sign the script is ready. The test now sends the event until it is heard; the held read is still one. For milestone 6: the builder sends the event after a shopper's click, long after the page has started, so it does not meet this.
- **A classic cart is tested on Storefront without switching the site's theme.** A must-use plugin in `tests/e2e/mu-plugins` gives a browser that sends its cookie Storefront and two shortcode pages as the shop's cart and checkout, for that request alone, so the other projects' tests run beside it on the block theme. It is a test fixture and is in no zip.
- **A fixture this step corrected.** The Build tab's test kit holds slots alone, and a kit with no rules or presets document does not read: the state route answers 404 for it. Nothing asked until a kit had to be bought. The cart's fixture kit holds all four documents.

**Red run.** With `Cart\ShowHooks` out of the container's hooks: "CartShowTest::test_the_store_api_says_what_each_line_is_of_a_build: Undefined property: stdClass::$creative-skateboard-builder" at `tests/Integration/CartShowTest.php:76`, and two more of that class. **Deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-133 · Shipping, express pay and the delivery filters · 2026-11-11

Milestone 5's steps 10 and 11.

- **A kit's line at nothing needs no code to ship.** The kit is virtual (D-099), so `WC_Cart::get_items_needing_shipping()` leaves it out of every package, and no shipping method is handed a line that costs nothing. `CartShippingTest` takes a build with free assembly through flat rate, free shipping and local pickup, and places the order. The spec's worry is a carrier's plugin that reads the cart itself; that cannot run in CI, and is Claude in Cowork's to check on the demo, as the plan said.
- **One package, by default for a build the shop assembles.** The spec says a filter "can keep a build in one package for shops that split packages", and leaves the default open. An assembled build leaves the shop as one board, so its parts in two packages would be priced as two shipments that are never made. A build that is not assembled is loose parts, and a shop that splits packages (by vendor, by warehouse) has its reasons for where each goes. So the default is the build's `assembly`, and `cbuild_build_in_one_package` changes it either way. Zach kept this default on 12 November 2026.
- **How they are put together.** `Service\BuildPackages` moves a build's parts into the package that holds the first of them in step order, works out again the cost of each package it touched, and drops one a move emptied. It makes no package and leaves every other key of one alone. It runs at priority 100, after a shop's own split, and only where there is more than one package.
- **Express pay.** Neither plugin is in wp-env, so each one's public source was read on 11 November 2026 (`develop` branch): WooCommerce Stripe Gateway's `includes/payment-methods/class-wc-stripe-express-checkout-helper.php` (`should_show_ece_on_product_pages()` applies `wc_stripe_hide_payment_request_on_product_page( $hide, $post )`; `supported_product_types()` applies `wc_stripe_payment_request_supported_types`) and WooPayments' `includes/express-checkout/class-wc-payments-express-checkout-button-helper.php` (`is_product_supported()` applies `wcpay_payment_request_is_product_supported( $is_supported, $product )`; `supported_product_types()` applies `wcpay_payment_request_supported_types`). Both list the product types their buttons take, and a kit's type is in neither list, so the buttons are already not offered on a kit's page. The plugin answers the four filters all the same, for a shop whose code adds the type. The tests apply each filter as the plugin does; the buttons themselves are Claude in Cowork's to check on the demo.
- **The delivery filters' shape.** The spec names `cbuild_arrival_estimate` and `cbuild_delivery_line` and not their arguments. Each is given an empty string and one array: the kit, whether the build is assembled, the lead time in the owner's words, and the total in minor units with its decimals. An array, so that a later milestone can add to it without changing the filter's arity. The shopper's address is not in it: the plugin that answers knows its own customer, and the builder holds no address. An answer is shown as plain text.
- **They are asked by `Front\Delivery`, which nothing calls yet.** The plan put the filters in this milestone and their line in milestone 6's summary. The state route's answer and its schema are unchanged in 0.5.0.
- **The lead time and the returns notice go on the order,** in the kit's item's snapshot, as the shopper read them (after `cbuild_kit_text`) and only for a build the shop assembles, since both are about assembly. Milestone 8's emails read them there, whatever the kit says by then.

**Red runs and deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-134 · The cart takes what the route offers, and what a build costs a cart request · 2026-11-11

- **The invariant goes through the real cart.** Milestone 2 proved in the domain that what the engine offers the validator accepts. `CartInvariantTest` proves it through WooCommerce: 70 real products made from a generated catalogue, the public state route asked what it offers at each step, and the Store API's add asked to take it. Thirty seeded walks end in a build the cart takes; thirty more put one part the route had ruled out into a build, and the cart refuses each with the route's own reason. The seed is in each failure's message.
- **The add's budget.** The spec gives adding a 7-part build 300 ms. `CartBenchmarkTest` times 40 adds by the Store API, each from an empty object cache, and holds the 95th to it. On CI's shared runner the ceiling is 450 ms and the query count holds the rest, as the state route's does (D-124).
- **A build's parts are read together.** The first run of the benchmark showed what the time hid: 132 queries for an add and 84 for a cart read with a build in it, against 10 for the same seven products with no build. Each part was read from the shop alone (`Kits::picked( Slot, int )`), and a cart read asks for a build's parts three times: twice for the kit's line's quantity limit and once for the check before paying (D-129). `picked()` now takes a build's slots and IDs and reads them in one go. An add asks 54 queries and a cart read 30, and both are ceilings in the benchmark. Nothing is kept between requests or within one: issue #26's reason for reading the shop itself stands, and stock can move inside a request that places an order.
- **What the check on every cart response costs** (owed by D-129): about 17 ms and 20 queries more than the same cart with no build, on the machine in `docs/BENCHMARK.md`, with no persistent object cache. Seven of the 20 are each slot's "which products are in these categories", which a shop's object cache answers.

**Red runs and deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-135 · The state route prices for the shopper the cart will charge (#27) · 2026-11-11

- **What was wrong.** WooCommerce loads a customer for a page and for the Store API, and for no other REST request (`includes/class-woocommerce.php:347`). So on `cbuild/v1/kits/<id>/state` there was none, `WC_Tax::get_tax_location()` (`includes/class-wc-tax.php:469`) fell back to the shop's default address, and a shop that shows prices with tax, taxed by where the shopper is, showed a shopper abroad a total the cart then changed. The auditor found it in milestone 4, and Zach deferred it to this one (D-121).
- **The fix.** The route calls `wc_load_cart()` before it prices, through `Port\Cart::shopper()`, where no customer is loaded. It is WooCommerce's own function for this (`includes/wc-core-functions.php:2539`). The price context already keys what is kept by the tax rates that apply (D-111, D-116), so nothing else changes: shoppers charged the same rates share rows and cards, and no address is kept.
- **A visitor nobody knows** has no session cookie. WooCommerce makes a session object for the request, stores nothing for it and sets no cookie, and the price is the shop's default as before. `CartPriceTest` counts the sessions table before and after.
- **The route was never cacheable** (`Cache-Control: no-store`, D-112), so answering by the session costs no cache.
- **Not done: the two build abilities.** `check-build` and `price-build` answer a signed-in user through the Abilities API, also with no customer loaded, and still price for the shop's default address. They are read by tools and not shown beside a cart, and loading a cart session there, where the caller may be WP-CLI or an agent, is a choice of its own. Listed for the review.
- **Its cost is not in the benchmark:** the benchmark dispatches in a process that has a customer already. The route's query ceilings are unchanged (42, 15, 37).

**Red run and deliberate break** are in the commit's message.

**Status:** Accepted.

### D-136 · Milestone 4's listed minors, and the issues carried into the audit (#29, #22, #25) · 2026-11-11

Issue #29 lists ten minors from milestone 4's review. What was done with each:

- **Site Health's Info section does not list the five new settings.** Fixed: the section lists them, the proxy header by its name, which is never an address. `SiteHealthTest` failed first on the section's keys.
- **Two adapters reach a service's constant across a layer.** Already gone: no file under `src/Adapter` names a class of `src/Service`.
- **`get_objects_in_term()` asked with no bound.** Stays. The function takes no limit, and a bound put on it by another query would cut a category's products at an arbitrary one before the slot's own rule chose among them. The cart no longer pays for it seven times three on a read (D-134); the state route reads it once a slot.
- **Two `!important` in the Build tab's styles.** Stay: the padding answers a rule of WooCommerce's that is itself `!important` (`.woocommerce_options_panel p.form-field`), and the width answers the width select2 writes on its container's own `style`. Nothing less wins over either.
- **The schema's `additionalProperties: false`.** Stays until the builder's script reads the answer in milestone 6: the strict schema is what makes the generated types exact and the route's tests catch a key nobody declared. Whether to loosen it before 1.0 is a question for that milestone's plan.
- **`ready: true` when a refused pick was replaced by the default.** Stays: the schema says `problems` is empty exactly when `ready` is true, and the answer names the replaced pick under `refused`. What the builder shows a shopper whose pick was replaced is milestone 6's.
- **Rows of a price context nobody asks for again, and a password set by `wp_update_post()` alone.** Stay, bounded by the two hours a row is read within, as the review said.
- **`Admin\BuildTab` is over 1,000 lines.** Stays. Its tests hold its behaviour, and the split is a change with no behaviour to test; it is better made beside the next change to the tab than alone in a cart milestone.
- **The route's benchmark on a persistent object cache.** Not run; the integration suite runs on Redis (`composer test:integration:object-cache`), the benchmark does not yet.
- **The change list has no undo.** Milestone 6's, as listed.

**#22** (axe's `target-size` on a variation's Part specs, seen twice under load) stays quarantined as D-117 left it: the rule is left out of two scans and the test measures the headings itself. The cause is still not found.

**#25**: three mutants are left, each the same program as the original as far as can be shown (a cast of an array key PHP makes a number again, one row more read past the row that already says too many, and the branch for a server without mbstring). They are inside the floor and go to Zach at the review.

**Status:** Accepted.

### D-137 · The reviews of milestone 5: Zach's four answers, and the cart's four fixes · 2026-11-11

The spec reviewer found 4 should-fix and 4 minor, and the auditor 9 and 12, with no blocker. Zach answered four questions on 11 November 2026:

1. **Order again puts a build back whole.** WooCommerce's button refused the kit and left each part in the cart as a line of its own (failure W9), and the spec says nothing of it.
2. **The two build abilities price for the signed-in user as the route does** (D-135's open item).
3. **The hook classes that hold no logic are named in the mutation exclusions,** under D-087's rule, once their price, tie and snapshot logic is in services (D-128's question, D-038).
4. **The add's benchmark keeps 450 ms as CI's ceiling,** as the state route's (D-124, D-134).

What this commit does:

- **Order again.** WooCommerce fills the cart from an order's items itself and writes the lines without adding them (`includes/class-wc-cart-session.php:632` to `:731`), so no add hook runs. `Cart\AgainHooks` answers three of its hooks. On `woocommerce_order_again_cart_item_data` the kit's build is judged again by `BuildCart::consider()` from what the order's snapshot asks for (`Service\BuildAgain::asked()`), as the shop is now, and given a new identifier; each part's line is marked with its build. On `woocommerce_add_to_cart_validation` a line of a refused build is left out. On `woocommerce_ordered_again`, which hands over the cart's lines by reference, `BuildAgain::whole()` says which builds have a line for every slot: those have their lines tied to one another, and every line of any other is taken out, since WooCommerce leaves a sold-out or deleted product out by itself. The shopper is told once. The marker `cbuild_again` is on a line only while WooCommerce fills the cart and is never stored. WooCommerce's own count of items it could not add stays true.
- **A coupon's own exclusions.** D-130 let a coupon that takes the kit take its parts. A coupon for the whole cart takes the kit, so one that left out decks, a category or sale items took them all the same once they were in a build. `couponTakes()` has a fifth fact, whether the coupon leaves the part itself out, read as WooCommerce reads it (`includes/class-wc-coupon.php:1045` to `:1058`). D-130's reading otherwise stands, and is still Zach's at the review.
- **The classic mini-cart's prices.** A cart read back from the session works its totals out again only when it changed (`class-wc-cart-session.php:302`), so each part showed at the shop's full price beside a subtotal with the discount in it. The price is given on `woocommerce_cart_loaded_from_session` as well.
- **The check's sentences.** A build that stops the order is in the cart, so nothing is said of adding. The first notice names the kit and what to do; the problems follow. The Edit link is milestone 6's, and the sentence will offer it then.
- **Three browser tests found WooCommerce's own Save button and saved notice by their English words.** The local site has since been given WooCommerce's French and Hebrew language packs, and the tests failed in `fr-ca` and `rtl` there, though not in CI's fresh site. They find both by their markup now, and the terms test starts from a shop's defaults whatever an earlier run left.

**Red runs and deliberate breaks** are in the commit's message.

**Status:** Accepted.

### D-138 · The reviews of milestone 5, the rest: logic in services, the snapshot's schema, the shopper with no cart · 2026-11-11

The second batch of the reviews' findings (D-137 has the first, and Zach's four answers).

- **The hook classes hold no logic, and are named.** `infection.json5` left out anything with "Hooks" in its path, which took `Cart\*Hooks` and `Order\OrderHooks` with it unsaid. The entry is now `Hooks/`, the directory. What those classes decided moved into services, each at 100 % MSI: a line's price asked for more than once (`BuildCart::repriced()`), an order's ties and its snapshot (`Service\OrderBuilds`). Under Zach's third answer the eight hook classes, `Adapter\WooCart`, `Adapter\WpUuids` and `Front\Delivery` are then named in the exclusions with their reasons: each takes WooCommerce's cart, products, coupons, order items or packages, or does its work through WordPress's functions (D-087, D-038). `Cart\Refusals` needs only the translation functions, so it is not left out: `RefusalsTest` mutates it at 100 %.
- **The order's snapshot has a schema and a version of its own.** `schema/order-snapshot.json` describes `_cbuild_snapshot`, `OrderTest` validates a real order's against it, and `OrderBuilds::SNAPSHOT_VERSION` is 1. It was the cart's build's version before, so a change to the cart's shape would have renumbered every order's record.
- **The abilities price as the route does** (Zach's second answer). `Abilities\BuildAbility` asks for the shopper before it asks the state's service, so `price-build` and the route give a signed-in user the same total where tax goes by address. D-135's "Not done" is done.
- **The shopper is found without a cart.** D-135 called `wc_load_cart()`. That makes a `WC_Cart`, and a cart reads itself from the session when the request shuts down, to set its cookies (`includes/class-wc-cart-session.php:95` and `:400`); PHP runs that before it sends the answer. Every state request of a shopper with a build in the cart would have paid for reading and checking that cart, on the route the 80 ms budget is about. `Adapter\WooCart::shopper()` now makes the session and the customer as `wc_load_cart()` does (`includes/class-woocommerce.php:1228` and `:1245`) and no cart, and does not queue the customer's save, since the route changes nothing of them. Held by `CartPriceTest::test_the_route_finds_the_shopper_and_makes_no_cart_for_the_request` and by a benchmark: finding the shopper costs the one query that reads the session (38 against 37).
- **Refusals are tested by code and by sentence, by both routes:** an unpublished kit's 404, each of `slot_unknown`, `part_not_in_slot`, `part_short` and `part_blocked`, `cbuild_no_price`, and WooCommerce's ajax add. The Store API's message is HTML, as WooCommerce's own are, so a quotation mark in it is an entity; the Cart block decodes it.
- **Axe reads the three carts and the classic checkout's table** in every project with a script. With scripts off the server draws the same classic tables, and axe, a script, cannot run.
- **The classic mini-cart widget.** Its rows have a filter of their own, `woocommerce_mini_cart_item_class`, which the plugin now answers as it answers the cart's, with the styles enqueued where the widget is drawn.
- **Composer's scripts have 30 minutes.** The integration suite with coverage passed Composer's default of 300 seconds at 402 tests and was stopped mid-run with every test so far passing. `composer.json` sets `process-timeout` to 1800; each CI job keeps its own limit in minutes.
- **Minors.** A part's quantity in the order's Build line goes through `number_format_i18n()`. An order item made with no cart reads none. `ContainerTest` holds the two meta keys the index follows. `docs/BENCHMARK.md` says seven parts.

Listed, not done:

- **The kit's line has the kit's own thumbnail.** The deck's photo there, with the fallback where a deck has none, is milestone 7's: the build's picture is drawn then, and the cart's line takes it.
- **A cart with two builds** is held by `CartInvariantTest` and has no query ceiling of its own; the read's ceiling is for one build, and each build adds its parts' one reading (D-134).
- **A build added from a page with no build in the cart** has its styles from the next page on (D-132). The builder page is milestone 6's, and its plan carries this.
- **The state schema's `additionalProperties: false`** is asked in milestone 6's plan.

**Red runs and deliberate breaks** are in the commit's message and the review's findings table.

**Status:** Accepted.

### D-139 · Claude in Cowork's review of pull request #33: WooCommerce 11.3.0, the address only where a price needs it, a coupon on the order, the SKU · 2026-11-11

Claude in Cowork read pull request #33 at `916d82e`: no blocker, four should-fixes and five minors. All nine are done.

- **The newest leg is WooCommerce 11.3.0** (S1). The demo updated itself to 11.3.0, and CI's newest leg was 11.2.2, so the cart was tested on neither version a shop runs it on today. `.wp-env.json` and `.wp-env.tests.json` pin 11.3.0, and D-004's "newest" reads 7.1.4 with 11.3.0 from here. The oldest leg stays 7.0 with 11.2.0, and the floor stays 11.2. `php-stubs/woocommerce-stubs` and `tools/phpstan-bootstrap.php`'s `WC_VERSION` stay at 11.2.2: static analysis reads the floor's API, which is the one the code may rely on.
- **A part's line names its parent to the Store API** (S1). WooCommerce 11.3.0 gives a cart item a `parent_item_key`, answered by the filter `woocommerce_store_api_cart_item_parent_item_key` (`src/StoreApi/Schemas/V1/CartItemSchema.php:132`), and counts a product's quantity in the cart from lines with no parent. `Cart\ShowHooks::parent()` answers a part's `cbuild_parent` and leaves every other line as it came. On 11.2 the filter is never asked.
- **The address reaches a price only where a shown price can depend on it** (S2). D-135 put the rates for the shopper's address in the price context on every shop with tax on. On a shop that enters and shows prices without tax, as the demo does with 17 standard rates over 13 provinces and territories, that kept up to 13 sets of the same rows and read the session for nothing. `Adapter\WooPartCards::byAddress()` is true where prices are shown with tax, or entered with tax while `woocommerce_adjust_non_base_location_prices` is answered false (`includes/wc-product-functions.php:1575`). Only then does the context hold the shopper's rates, and only then does `Adapter\WooCart::shopper()` make the session and the customer. Otherwise the context holds the shop's base rates, the same for everyone.
- **A coupon worked out again on the order follows the kit** (S3). `WC_Discounts` hands `woocommerce_coupon_is_valid_for_product` a `WC_Order_Item_Product` where it works from an order (`includes/class-wc-discounts.php:115`), as it does when a manager adds or removes a coupon (`includes/abstracts/abstract-wc-order.php:1705`, `:1915`). `Cart\PriceHooks::coupon()` read the cart's keys alone, so on an order a kit-only coupon's share came off the parts and the total moved after payment. It now finds a part's kit by the order item's `_cbuild_parent`, or by `_cbuild_id` and `_cbuild_role` where the tie is not yet written, and answers as it does in the cart.
- **The snapshot keeps each part's SKU** (S4): `sku` on each pick, a string, empty where the part had none, required by `schema/order-snapshot.json`. The snapshot's version stays 1: no released version wrote one, since 0.5.0 is the first.
- **Picks are capped before anything is read** (M1). `Service\BuildCart::consider()` refuses more picks than `Slots::MAX`, and a key that is not a slot key by `Fields::isKey()`, before the kit or any product is read.
- **Words for people, the snapshot for code** (M2), and **`_cbuild_id` is the tie** (M5): both said in `docs/HOOKS.md`. Milestone 8 ties copied items again from `_cbuild_id` and `_cbuild_role`.
- **Shipping packages keep their keys** (M3). `Service\BuildPackages::together()` renumbered the packages it returned. WooCommerce keeps the method chosen for a package under the package's key (`chosen_shipping_methods`), so a package after one a move emptied would have taken the emptied one's method.
- **A kit cannot be added to an order by hand** (M4), the first of the two roads the review offered. `Order\OrderHooks::byHand()` answers `woocommerce_ajax_add_order_item_validation` (`includes/class-wc-ajax.php:1292`) with the code `cbuild_kit_by_hand` and a sentence. `WC_Order::add_product()` called by other code has no such filter, so milestone 8's Build box and ticket still read a kit's item with no `_cbuild_id` as a line of no build; its plan carries that.

Carried from the review, not for this milestone:

- **PayPal Payments' buttons on a kit's page** are looked at in milestone 6, with the builder's page (the review read 4.2.2).
- **Geolocation.** Where the shop's default customer address is Geolocate and a shown price goes by address, a guest's first state request costs WooCommerce's GeoIP lookup, which the benchmark does not include. Milestone 6's plan measures it.

Zach's, answered apart from this: the coupon edges (D-130), the package default (D-133), and how the demo takes WooCommerce's updates.

**Red runs and deliberate breaks** are in the commit's message and the review's table.

**Status:** Accepted.

### D-140 · The index job writes the parts table's rows, so no visitor pays for a reindex (#28) · 2026-11-12

Issue #28, from the spec-reviewer on milestone 4, with Zach's answer of 11 November read in D-121: no visitor pays to fill a cache.

- **`Port\Kits::warm()`.** After a part's own job or a batch of the reindex has indexed its parts and forgotten their rows, it reads those products from the shop and writes their rows. `Adapter\WooKits::warm()` passes over a product no kit's slot could offer, so a shop with 10,000 products and 300 parts writes 300 rows.
- **For the job's own price context,** which is the shop's default: a background request has no shopper, so the currency, the language and the tax rates are the shop's. A visitor shown other prices (another language, a currency plugin's, other rates where a shown price goes by address) still pays once for their context. That is one request in each context, and writing every context from the job would mean knowing them all, which only a visit tells.
- **Deleted first, then written.** The rows forgotten in the request are deleted at once, for every context, before the new ones are written. Left to the request's end, as `forget()` leaves them, the delete would take the new rows with it.
- **Held by the benchmark.** The first request after a reindex of 2,000 parts is held to the budget and to the cold run's queries: 58 ms and 37, where it was about 490 ms and 101. The reindex goes from 0.84 s to 3.85 s in all, in the background, a batch 130 ms at its slowest (`docs/BENCHMARK.md`).

**Red runs:** `IndexJobsTest::test_a_variable_products_job_indexes_its_variations_and_not_itself` and `PartsTableTest::test_the_job_that_indexes_a_part_writes_its_row_and_the_next_visitor_loads_no_product`, each "Failed asserting that two arrays are identical." With the delete left to the request's end, the second fails the same way.

**Status:** Accepted.

### D-141 · Where the test shop's front page spends its largest paint, and what the builder page is measured on (#7) · 2026-11-12

Issue #7, owed before the builder page exists. Measured on 12 November 2026 with `npm run lhci` (Lighthouse 13.6.0, mobile profile, three runs, WordPress 7.1.4, WooCommerce 11.3.0, Twenty Twenty-Five, the plugin active).

- **The numbers.** Largest paint 2,283, 2,290 and 2,323 ms; first paint 2,203 to 2,260 ms; the server's answer 103 to 216 ms; blocking time 0.
- **The largest paint is text,** the site title in the footer (`h2.wp-block-site-title`), painted with the first paint. Nothing on the page is fetched for it.
- **What delays it is the first paint, and that is the simulated network.** Lighthouse's phone has 150 ms of latency and 1.6 Mbit/s. Before anything paints, the page waits for what blocks rendering: jQuery and jQuery Migrate in the head (36 kB, which WooCommerce's classic scripts ask for), `woocommerce.css` and `woocommerce-smallscreen.css` (14 kB, of which the page uses under 1 %), and six more small stylesheets of WooCommerce's blocks and the theme. 31 requests and 205 kB in all, 54 kB of it the theme's font. Lighthouse puts about a second of the 2.1 s on the blocking requests.
- **None of it is the plugin's, and none of it is wp-env's.** A shop on Twenty Twenty-Five with WooCommerce would measure the same on this profile. It is the theme's and WooCommerce's, and a shop's own theme and caching decide it. So the budget is about what the builder adds to a baseline the plugin does not set.
- **What the builder may add.** About 200 ms before 2,500. The builder's styles must not block: they are small and in the page. Its script is a module, which never blocks. The picture's first photo becomes the largest paint, so it is in the HTML with its size, `fetchpriority="high"` and no lazy loading, where the browser finds it while the blocking requests are still loading, and it is held under what that connection carries in the time (about 30 kB).
- **What the builder page is measured on:** a page holding the Builder block on Twenty Twenty-Five, at its clean address, with a kit whose parts have photos, made by the browser fixture. The kit's own page is measured by the browser tests, not by Lighthouse.
- **Five runs, not three,** from the commit that points Lighthouse at the builder page. The three runs here are 40 ms apart, but D-032 saw one in three over the budget on a busy machine, and a median of five does not move with one slow run. It costs CI about a minute.

The budget is unchanged. Changing it, or what it is measured against, stays Zach's.

**Status:** Accepted.

### D-142 · The build link: the spec's four parameters, the builder's own under an underscore, and four words no slot is keyed with · 2026-11-12

`Front\BuildLink` reads and writes a build as query parameters. It is the one place that does, for the page, the no-JavaScript forms, the browser's history, Copy link and the cart's Edit build.

- **The spec's format is the public one:** `cbuild[<slot key>]`, `cbuild[step]`, `cbuild[assembly]` and `cbuild[preset]`.
- **What the forms need beside it goes under keys that start with an underscore:** `_was`, `_brand`, `_size`, `_shown` and `_replace`. A slot's key starts with a letter (`Fields::isKey()`), so these can never be read as a pick, and new ones can be added without touching any kit. They are documented as the builder's own and are left out of a shared link (`BuildLink::shared()`), which carries part IDs, the step and assembly, as the spec's Privacy section has it.
- **`step`, `assembly`, `preset` and `review` are refused as slot keys** (`Slots::KEPT`, `document_bad_key`). The spec's three share the picks' own array, and the review is a step that is no slot's. Without this a kit with a slot keyed `step` would have links no one could read. A kit saved by 0.4.0 or 0.5.0 with such a key would stop reading; none exists outside a test site, and the eight keys the rules know are not among them.
- **One build, one address.** A link is written in the kit's slot order whatever order it arrived in, an ID only as PHP writes a whole number (`012` and `1e1` are no IDs), and a slot the kit does not have is left out.
- **It decides nothing.** A link is read into IDs, keys and flags, capped at the most slots a kit can have. Whether an ID is a part of its slot is the state's to say, each time the link is opened.
- **The version is 0.6.0** from this commit, the first with something public.

**Status:** Accepted.

### D-143 · What the state answer gains for the builder: a card's price change, sizes, filters, the box and the notes · 2026-11-12

The builder's template shows nothing the server did not work out. This is what the answer lacked for it.

- **The price on a card is the change to the total** (spec: A step, P4), against the part the step counts now, which is the pick or the recommendation. It is what the build charges: the build discount off each price, rounded as a line's is, times as many as the build takes. The domain works it out (`Pricer::change()`), so the card and the cart cannot differ.
- **The four words.** `Included` is the part the total already counts, and only that part. `Free` is any other part that costs no more, a part priced at nothing among them. `+$12.00` and `−$5.00` are the rest, the second with a minus sign. The spec gives the three words and no rule for them; this is the reading that never shows two parts as included. A part with no set size has no quantity, so no price in a build, and says nothing.
- **With nothing counted, the change is the whole price,** written `+$60.00`. A deck is never picked for the shopper, so the deck step starts this way.
- **A size is the spec a shopper names a part by:** a deck's width, a truck's axle, a wheel's diameter, a riser's thickness, a bolt's length, each by its slot's usual key. Bearings, grip and spacers have none, and neither has a slot keyed with a word of the owner's. The filter's value is the number and its unit (`8.25in`), which the address can carry.
- **Filters are of the parts the step offers,** and their counts do not move when one is set, so a shopper sees what the other values hold. A list of one value is no filter. The part the step counts stays listed when a filter leaves it out: it is the checked radio, and a form that did not hold it would drop the pick.
- **Brands are read by name in one query,** with WordPress's `suppress_filter`. Without it WooCommerce's `wc_change_term_counts` (`includes/wc-term-functions.php:640`) read each term's meta and wrote the transient `wc_term_counts` from a visitor's GET: seven queries, two of them writes. The test holds it to one.
- **The review is a step** (`State::REVIEW`), honoured whatever the build holds. A build that is not ready shows what it lacks there.
- **The picture is the box for now** (milestone 6): the parts the total counts, each with its photo and its label. The sentence lists their names with WordPress's own list pattern (`%l`), so a language orders and joins them its own way.
- **`cbuild_summary_notes`** takes sentences and gives six at most, as plain text. The route's answers are `no-store`, so a note about the signed-in customer is never served to another.
- **The published schema stops closing its objects.** The spec says a response only gains fields, and a shop's code that checked an answer against 0.5.0's schema would have refused 0.6.0's. The plugin's own contract test adds the closing back before it validates, so a field the schema does not describe still fails. This is the answer I suggested to the plan's one question; turning it back is one line in the test and seventeen in the schema.
- **Not yet here:** the delivery line worded from WooCommerce's shipping zones where no plugin answers (the plan's step 10), and the route's own arguments for the filters, which arrive with the template that sends them.

**Status:** Accepted. Zach kept the schema open on 12 November 2026.

### D-144 · The builder on the server: one renderer for the kit's page and the block, one per page, and the view as the builder's own · 2026-11-12

`Front\BuilderPage` renders the builder; `Front\Builder` decides what an address shows; `Front\BuilderView` words it for the template. The kit's page and the Builder block both call the renderer.

- **The kit's page shows the builder where WooCommerce asks a product type for its add-to-cart area:** `woocommerce_cbuild_kit_add_to_cart`, fired by `woocommerce_template_single_add_to_cart()` (WooCommerce 11.3.0, `includes/wc-template-functions.php:2174`) and by the Add to Cart form block (`src/Blocks/BlockTypes/AddToCartForm.php:227`). One hook covers classic and block themes.
- **The block's render is a callback, not block.json's `render` file.** `register_block_type()` merges its arguments over the metadata, so the callback is the renderer's own method. A render file would have to fetch the renderer from a global, and the plugin has no service locator (STANDARDS). The block declares `supports.interactivity`, so WordPress processes its directives after the render; the kit's page processes its own with `wp_interactivity_process_directives()`.
- **One builder a page.** The store's state is the page's, so a second builder would show the first one's build. The block says `multiple: false`, and the renderer renders only the first one asked for, the kit's page included.
- **The view is the builder's own document.** Every sentence, address and yes or no the template binds is worked out once, in PHP, so the template holds no rule and the script will hold no word. The template's fixed words are in the view too, so the store can re-render a step from the route's answer alone. Its fields may change in any release; what other code may rely on is the route's answer (HOOKS).
- **`0` in a build link means a slot left empty by choice.** A slot the kit does not require starts on the part that fits best, so without a word for "none" a shopper who cleared the risers would have them back on the next page. The link writes `0` only for a slot with no pick, and a required slot ignores it.
- **Risers are a step only when they matter:** when the kit requires them, the build holds some, or the wheels need them. A shopper is not asked about a part most builds go without. Asked for a step that is no step, the builder shows the next one. (As amended by D-146: step 3 left out the wheels.)
- **A step past a required slot with nothing in it stops there,** with the reason linked in the error summary and `Error:` in the title (W8). A slot with nothing counted is one where nothing the shop has fits, so going on would hide why the cart is out of reach.
- **The renderer is a boundary** (spec: Resilience). What a render throws is logged with the kit and the message, never the request, and the shopper reads one sentence in the builder's place (the spec's, from D-146). The buffers the template opened are closed, so no half builder reaches the page.
- **A request that shows no builder builds none of its services.** `PageHooks` takes a closure for the renderer, as the state route does, and `ContainerTest` holds the builder's services to that.
- **A list in an answer is read as a list.** The view read the answer through `Cast::map()`, which keeps string keys only, so every list in it read as empty: the steps' labels fell back to their keys, and the total's lines, the picture and the problems were missing. The page's integration test caught it; `Cast::items()` reads a list.
- **0.5.0 is not published.** Its tag's CI run failed on the Firefox wait that #35 fixes, so `release.yml` held it. Zach chose to let 0.6.0 carry 0.5.0's changes rather than tag a 0.5.1 (#35).

**Status:** Accepted.

### D-145 · The demo takes WooCommerce's updates as they come, and the test sites follow · 2026-11-12

Milestone 5's review found the demo on WooCommerce 11.3.0 while the newest test leg was 11.2.2, and 11.3.0 changed what a Store API cart item says (M5, S1). Zach answered how the demo takes updates: automatically.

- **The test sites follow the demo, not the other way.** `.wp-env.json` and `.wp-env.tests.json` pin the newest WooCommerce release, and the oldest leg the one before it (`npm run env:oldest`). When WooCommerce ships a release, the pins move to it in the next milestone's first commit, with its integration and browser suites run, before any code leans on it. A release that changes something the plugin reads is a fix of its own, as 0.3.1 was.
- **Where a release lands mid-milestone,** the plan's step that checks hooks in the source (`/plan-milestone`, step 3) is run again against it.
- **Dependabot's alert on `deepmerge`** (a dev dependency under `@wordpress/scripts`, no fixed release) stays open until a fix ships, Zach's answer of the same day. The weekly canary reports it; nothing that ships reaches it.

**Status:** Accepted.

### D-146 · The no-JavaScript flow against the spec: risers when the wheels need them, Use these and Undo as buttons, the builder's address, and the failure's words · 2026-11-12

Milestone 6's step 4 walked the flow end to end in a browser with JavaScript off (`tests/e2e/builder.spec.ts`), against the spec's Ways in and Without JavaScript. Four things in step 3 did not match it.

- **Risers when the wheels need them** (spec: A step, "The risers step appears only when the wheels need risers, with 'No risers' as a choice"). Step 3 showed risers when the kit required them or the build held some. The state recommends a riser when the wheels need one, so that held while the shopper kept the riser, but a shopper who chose No risers lost the step, and the reason with it. The step now stays while a part the build counts carries `wheel_needs_riser`.
- **Wheels that now need risers stop on that step.** The wheels step is drawn before its pick, so its Next named Bearings. Picking 58 mm wheels made risers a step, and the form went past it: the browser test reached the cart with a riser the shopper never saw. A step asked for past risers now lands on risers while the wheels need them and the shopper has neither picked them nor chosen No risers. Sending that step's form puts its choice in the address, and the shopper goes on. It is no error: a riser is recommended, to be seen.
- **Use these and Undo are buttons** (spec: Without JavaScript, "with both as buttons"; SEO and sharing, every change of state a button in a GET form). They were links with `nofollow`. Each is now a form with the build as hidden fields, the page's own query carried as the step's form carries it. The progress list's and the review's Change links stay links with `nofollow`, as the spec has them.
- **`cbuild_builder_url`** (the plan's public API; D-008). `Front\BuilderUrl::of()` is the kit's own page unless the shop answers with another address on the site; an address on another host, or no address, is ignored (`wp_validate_redirect()`), so the filter cannot become an open redirect. Where it names another page, the kit's page sends the visitor there on `template_redirect` with the `cbuild` query they came with, by a 302, so a shop can move its builder without browsers holding the old answer. A deck's "Make it a complete" link is that address with the deck in it, which HOOKS writes out; Skatehouse Core 2.x places it.
- **The failure's words are the spec's:** "This builder isn't available right now." A user with `manage_woocommerce` also reads the error's message, escaped, under it ("An admin sees why"). A signed-in page is not served from a page cache, so no visitor is shown it.

**For Zach.** `Front\BuilderPage`, `Front\PageHooks` and `Front\BuilderUrl` run only with WordPress loaded (the Interactivity API, `register_block_type()`, `template_redirect`, `wp_validate_redirect()`), so no unit test reaches them and Infection, which mutates covered code only, generates no mutant for them. They are proven by `BuilderPageTest` in wp-env, each fix with a deliberate break in its commit. Listing them in `infection.json5`'s excludes beside `Front/Delivery.php` says so where the floor is read. That narrows the floor's reach, which is Zach's (D-038, D-128): he left it to best practice on 12 November 2026, and they are listed, with why, as every other class that needs WordPress is (D-087).

**Status:** Accepted.

### D-147 · A build in the address: robots and the canonical through WordPress's filters and Yoast's, and no canonical where Yoast prints none · 2026-11-12

Milestone 6's step 5, against the spec's SEO and sharing ("A page carrying them gets `noindex, follow` through `wp_robots` and a canonical link to the clean page"). `Front\SeoHooks`, in the Storefront scope, does it.

- **Which pages.** A singular page holding a builder: a kit's own page, or a post with the Builder block. A `cbuild` query is enough, whatever it holds, since an address that reads as no build is still not the clean page. A page with no builder is left alone.
- **WordPress.** `wp_robots` at 20 drops `index` and `nofollow` and sets `noindex` and `follow`. `get_canonical_url` at 20 takes `cbuild` out. WordPress's own canonical is the permalink with no query already, so this cleans one another filter wrote with the build in it.
- **Yoast SEO 28.8, read in the zip `tools/yoast.sh` pins.** It merges its robots into `wp_robots` at `PHP_INT_MAX - 10` (`src/integrations/front-end/wp-robots-integration.php`). Where a theme or plugin takes `wp_robots` out of the head, it prints its own tag (`src/integrations/front-end-integration.php:432`) from `wpseo_robots_array` (`src/presentations/indexable-presentation.php:373`), so the plugin answers that filter too. Taking the answer away fails both of `BuilderYoastTest`'s tests.
- **No `wpseo_canonical`.** Yoast prints no canonical on a page whose robots say `noindex` (`src/presenters/canonical-presenter.php:38`), before its filter is asked, so a hook there would never run on a build's page. Yoast does this so the two never disagree, and Google reads `noindex` with a canonical as mixed signals. The plugin keeps Yoast's behaviour rather than printing a link tag of its own beside Yoast's head. The spec's canonical is met without Yoast, and with it the page is out of the index and its `og:url`, which a shared build's card reads, is the clean page.
- **The test visits several pages in one request.** Yoast keeps the current page's tags for the request (`src/memoizers/meta-tags-context-memoizer.php:167`), so `BuilderYoastTest` clears that between visits. Before it did, the build's page read the clean page's tags, and a deliberate break of either Yoast hook passed; with it, the break fails.

**For Zach.** With Yoast active, a build's page has no canonical link, where the spec asks for one to the clean page. It has `noindex, follow` and the clean `og:url`. This follows Yoast and Google's guidance; printing one would put a second head tag beside Yoast's.

`Front\SeoHooks` runs only with WordPress loaded, so Infection generates no mutant for it, as for D-146's three. Listing it in `infection.json5`'s excludes beside them narrows the floor's reach, which is Zach's (D-038): he left it to best practice on 12 November 2026, and it is listed, with why, as D-087 has every class that needs WordPress.

**Status:** Accepted.

### D-148 · The store shows the server's view: the state route takes a build link and answers with what the page would draw · 2026-11-12

Milestone 6's step 6. The spec has the store call the state route with its five arguments and "the same template the server rendered re-renders from that answer" (How it fits; Upgrades and the public API). Two things stood in the way. The template draws a view, `Front\BuilderView`'s 700 lines and 47 strings, not the answer. And a build link holds more than the five arguments carry: a slot left empty by choice, the step's filters, how many options are shown, a preset and the review.

Zach chose, on 12 November 2026, between the server sending the view, a TypeScript copy of the view with a test that the two agree, and the script handling picks alone with page loads for the rest. **The server sends the view.**

- **Two arguments, added.** `cbuild[…]` is the build link exactly as a page's address holds it, read by `Front\BuildLink::read()` as the page reads it, so the route and the no-JavaScript form cannot read one link two ways. It takes the place of `picks`, `previous`, `step` and `assembly`, and beside any of them is refused, so that no request says two builds. `page` is the post the builder is on: used for the view's addresses when a visitor may see it and its Builder block, at any depth, sells this kit, and otherwise the addresses go to `cbuild_builder_url`'s page. An ID from the browser is checked as every ID is; it never names a page the visitor could not open.
- **The answer** is `Front\Builder::show()`'s, the one the page's first render uses, with `view` added. So the first render and every later one come from one service, as the plan has it. `view` is described in `schema/state.json` as an object and marked the builder's own, liable to change in any release; the five arguments' answer is 0.4.0's and has none. The route's arguments and rate limit are as before.
- **The store** (`assets/src/builder/view.ts`) is locked and holds the view and whether a request is out. Its moves are in `navigate.ts`, which Vitest tests with no WordPress: a pick waits 150 ms for the next (spec: Performance), a step change asks at once and cancels a waiting pick, a request a later one overtakes is aborted and its answer dropped, and anything but a view is a failure. A failure does what the page does without JavaScript: the form is sent with the button pressed in it, the link followed, or, after Back, the page reloaded (spec: Resilience).
- **History.** A step change is an entry at the address the answer says the view is at, which the server may have moved to another step (risers, a required slot). A pick replaces the current entry. Back and Forward ask for the address the browser is at and make none. A pick the shopper moves on from within 150 ms is not written into the entry they leave.
- **No strings.** Every word is in the view. The script is 2.9 kB before compression, and `@wordpress/interactivity` is WordPress's own module.
- **The block** names the store as its `viewScriptModule`; the kit's own page, which has no block, enqueues the same module. `wp_interactivity_config()` gives the store the route's address, the page and the page's language.

**Status:** Accepted.

### D-149 · Focus, the status region and the page's title after each move · 2026-11-12

Milestone 6's step 7, against the spec's Keyboard and screen readers: "Focus moves to the new step's heading on each change. The page title and the heading both say the step"; fit changes, the description and the total "announced through a polite status region that's in the page from the start, once per pick"; a blocking problem "shows an error summary that takes focus ... and the page title gets "Error:" first"; and, for the change panel, "Focus moves to the panel's heading, and the status region says how many parts changed".

- **Focus after a step change,** a link or Back: the error summary where it is shown, the change panel's heading where the panel is, and the step's heading otherwise. Each has `tabindex="-1"`. Focus moves in the frame after the view is set, once the Interactivity API has drawn it. A pick moves no focus: the shopper is on the radio.
- **The status region** says the view's `status` after a pick, the build in one sentence and its total, and after the change panel opens, how many parts changed. After any other step change it is emptied, since the focused heading is read and saying the step twice is noise. The region is in the server's markup from the start, empty.
- **The page's title** is the step before the page's own, through one translatable pattern, `%1$s - %2$s`, so that a language may order them its own way. The view's `title` already says "Error:" first. The server writes it through `pre_get_document_title` at 20, after an SEO plugin's at 15, for a page with a build in its address, and the store after each move, with what goes before and after the step given through `wp_interactivity_config()`. The clean page keeps its own title: it is the one search engines index, and its title is the shop's to write.
- **Its own title, asked once.** The builder asks WordPress for the page's own title with its hook standing aside (`Front\PageTitle::base()`). Yoast SEO 28.8 answers WordPress's filter by asking WordPress again (`src/integrations/front-end-integration.php:282`, `src/presenters/title-presenter.php:40`), so the hook runs inside itself. It builds the title from the page's own each time and never from what it is handed, so the step is said once; `BuilderYoastTest` asks both ways.
- **One view a request.** `BuilderPage` works the view out once for each kit, address and page, so the title, asked for in the head, and the builder, rendered in the body, are the same and cost one state.

**Status:** Accepted.


### D-150 · Filters, Show more and the hidden parts are buttons and a disclosure in the step's own form · 2026-11-12

Milestone 6's step 8, against the spec's A step ("Long lists get filters (brand, size) above them"; parts that don't fit are "hidden behind a disclosure that says how many and why") and Performance ("A slot shows 24 options at a time, with Show more").

- **Buttons in the step's form,** not links or a form of their own. A filter, its clear and Show more are submit buttons named `cbuild[_brand]`, `cbuild[_size]` and `cbuild[_shown]`, so a pick the shopper has made and not yet sent travels with them, without JavaScript as with it. Each pressed filter is a hidden field too, so a pick or Show more keeps it. A filter starts the list again at 24.
- **`_on`, the step the form is on.** A step with filters or Show more carries `cbuild[_on]`, its own slot. Pressed alone, a filter lands on that step; with Next, which names the next step as `cbuild[step]`, the link drops the filters and Show more, so a deck's width never filters the trucks. A hidden `cbuild[step]` would have done the first, but the form would then send `step` twice and leave the second for the server to pick, and it would still carry the filters on. A step with neither leaves `_on` out, so its address stays as it was. An address the server writes has no `_on`: its filters are the step's it names.
- **Which filters.** A group shows only where the step's parts have two values or more of it. The deck's widths come first ("Start with a width"); every other step lists brands, then its size, named for its slot (axle width, diameter, thickness, length). Each button says how many parts it lists and is `aria-pressed` where it is on; its group's clear shows only while one is.
- **Show more** asks for the next 24, and says only what is left: the answer's `more` asks for a whole page, and the button is capped at how many match. The count beside it says how many are listed of how many match.
- **The hidden parts** are a `<details>` under the list: one reason said in its summary where every hidden part shares it, or the count with each reason listed; then each part, linked to its page, with what about it does not fit.
- **One render a request.** `BuilderPageTest`'s pages are rendered once a test: the Interactivity API keeps one state a request and merges a second render's lists into the first's, which a real page, with one builder, never does.

**Status:** Accepted.

### D-151 · Preset tiles: each preset worked out by the one engine, and a sold-out part named · 2026-11-12

Milestone 6's step 8, against the spec's Presets ("Up to six tiles at the start ... Each shows its picture, its total and a line on who it's for"; "A preset holding a sold-out part says so on its tile") and A step, Sold out ("A preset or link that holds one names it, and the build can't go to the cart until it's swapped").

- **One engine.** `BuildState` checks and prices each of the kit's presets as a build of its own (`Service\PresetState`), with the validator and the pricer every build goes through, the kit's own assembly setting, and the parts the state has already read. No second price is worked out anywhere. With six presets and 2,000 parts, the state route's p95 went from 68.2 to 70.2 ms cold and stayed at 47 ms warm, with no query more (`StateRouteBenchmarkTest` now holds six).
- **The answer** gives each preset its `total`, whether it is `ready`, and its `problems` in the build's own shape with the part's `name`. A part the shop no longer offers in its slot is a problem too, `part_not_in_slot`. `schema/state.json` writes that problem as a definition of its own, `presetProblem`, since WordPress's validator takes no `allOf` (`wp-includes/rest-api.php:2251`).
- **Tiles at the start alone:** on the first step with nothing picked, which a review asked for with nothing picked stops at too. A build begun, or one that began as a preset, is the shopper's own.
- **A tile is a GET form** whose fields are the preset's part IDs, `step=review` and `preset`, as a build link writes them, so a preset starts on the review, where the whole build and its total are, and every part is checked again on arrival. Its button is "Start from Street", a name of its own (P11).
- **A sold-out part is named,** on the tile and in the review's error summary, as "Wheels: Builder test wheels 56 is sold out." (`Words::named()`), with the error's link to its step; the review's Add to cart stays disabled until it is swapped. A problem about no part keeps its sentence.
- **The tile's picture** waits for step 11, where the picture is drawn; the tile holds the total, the line and any note until then.

**Status:** Accepted.

### D-152 · A pick that opens the change panel is a step: its own history entry, focus on the panel's heading · 2026-11-12

Milestone 6's step 9, against the spec's A change that breaks later picks ("Focus moves to the panel's heading, and the status region says how many parts changed") and History ("each step change adds a history entry").

- **What went wrong.** With JavaScript, a pick is sent to the route once the shopper pauses (D-148). A wider deck answered with the change panel then, before Next: the panel took the place of the step under the radio the shopper was on, so focus fell to the page's body, the title still said the step, and the panel took the deck step's history entry, so Back skipped past the step.
- **The store says what a view is.** The navigator still decides nothing about the view (it reads its address alone), but `show` now answers how history treats the view as shown. The store turns a pick whose answer opens the panel, where none was shown, into a step: a new entry, the title, and focus on the panel's heading once it is drawn. Back leaves the panel for the deck step as the shopper left it, as it does without JavaScript, where the panel comes from Next. A pick on a panel already shown stays a pick.
- **Use these and Undo** are the panel's GET forms, sent through the store as any step's form is, and move focus to the step they land on.

**Status:** Accepted.

### D-153 · The phone bar's view now, its markup with the styles · 2026-11-12

Milestone 6's step 10, against the spec's The total ("On phones it's a bar at the bottom: a small picture of the board, the total, and the next step on its button ... It never covers the control in focus", W7). The summary, the delivery line and the notes came with steps 3 and 4; what step 10 adds is the bar.

- **The view** (`bar`): the total, the parts list's label, and the button that moves the build on, the step's own Next or the review's Add to cart, disabled where the build cannot go to the cart, with no button on the change panel, whose Use these and Undo are forms of their own. The button names its form by ID (`BuilderView::STEP_FORM`, `REVIEW_FORM`), which the step's and the review's forms now carry, so it sends the same form with JavaScript or without.
- **The markup waits for the styles, in step 13.** The bar is the summary shown another way at another width: beside the steps on a desktop, at the foot of the screen on a phone. Without the stylesheet that hides one of the two at each width, every page would show the total twice and two buttons with one name, which a screen reader hears and P11 forbids. So the bar's template, the stylesheet that places it, the rule that keeps it off the control in focus (`scroll-padding-bottom`) and its tests at phone width land together. The small picture on it is step 11's.

**Status:** Accepted.

### D-154 · The review says everything before Add to cart, and the picture's first photo is the largest paint · 2026-11-12

Milestone 6's step 11, against the spec's Review and add to cart ("assembly on or off with its fee and lead time, the arrival date, the total, and the returns notice the owner writes, all before Add to cart") and The picture ("The largest paint ... an explicit size, `fetchpriority="high"`").

- **The review** says assembly with its fee, its lead time, the arrival date where the shop answers `cbuild_arrival_estimate`, the total and the returns notice, in that order, all before Add to cart. Each is the summary's own sentence, so the two cannot disagree. The summary still sits beside the steps; in the page's order it comes after the review, which is why the review says them itself.
- **The picture, for now,** is milestone 6's labelled "What is in the box" layout. Its first photo, the deck's, loads at once with `fetchpriority="high"` and its size given; the rest load lazily. Milestone 7's deck layer takes over as the largest paint.
- **A preset's tile** shows its first slot's part's photo, the deck's, lazily, from the one cards call the answer already makes (the answer's `image` on each preset, `null` where the part has none). Milestone 7 draws each preset's board instead. This closes what D-151 left for this step.

**Status:** Accepted.

### D-155 · Add to cart through the Store API, with its nonce asked for at the add · 2026-11-12

Milestone 6's step 12a, against the spec's Review and add to cart ("With JavaScript, Add to cart uses the Store API's `cart/add-item` and then sends `wc-blocks_added_to_cart`, so the Mini-Cart updates") and Privacy (the DOM events "a shop's own analytics can listen to, under the shop's own consent").

- **The nonce.** The Store API takes a write only with a `Nonce` header made for the session (`wc_store_api`, `src/StoreApi/Routes/V1/AbstractCartRoute.php:326-349`). A nonce printed in the page would be stale on any page a cache keeps, and the builder's pages are exactly the ones a shop caches. So the add first reads the cart (`GET wc/store/v1/cart`), whose answer carries a fresh `Nonce` header (`:179`), and posts with it. One extra request at the moment the shopper adds, which no cache can spoil.
- **Anything but a line added falls back.** A cart read or an add that fails, a missing nonce, or a network error sends the review's form as it is sent without JavaScript, to WooCommerce's own handler, which adds the build or says why as a notice. The shopper never sees a failure the server did not explain.
- **What the page does after.** It sends `wc-blocks_added_to_cart` on `document.body`, bubbling and cancelable, as WooCommerce's own add buttons do; the Mini-Cart listens on the document (`src/Blocks/BlockTypes/MiniCart.php:404`, `:407`). It then says "The build is in your cart." in the status region, shows the same sentence with a link to the cart under the review, and moves focus there. The next view the builder shows forgets the add.
- **The events.** `cbuild:pick` and `cbuild:added` go on the builder's own element and bubble, with IDs alone (docs/HOOKS.md, DOM events). The plugin listens to neither and sends nothing anywhere.

**Status:** Accepted.

### D-156 · Copy link: the build's own address in a field, and a button where the browser can copy · 2026-11-12

Milestone 6's step 12b, against the spec's Review and add to cart ("Copy link makes a build link, shareable without an account", P12) and How a build travels.

- **The link is the view's own address,** the one the page's history holds: the kit's builder page with the build in `cbuild[…]` and the step. It names parts by ID and nothing of the shopper (spec: Share links), and opens the same build for anyone, signed in or not. Nothing is stored to make it.
- **A field without JavaScript, a button with it.** A button that copies needs the Clipboard API, so without JavaScript it would do nothing. The review therefore always holds the link in a read-only field labelled "Link to this build", which any shopper can select and copy; the Copy link button beside it is `hidden` until the store finds `navigator.clipboard.writeText`. A copy says "Link copied." in the status region.
- **A refused copy** (no secure page, or a browser that asks the shopper first and is told no) focuses the field and selects the link, so the shopper's own copy takes it.
- **Not the Web Share API.** `navigator.share` would be a second way to do the one thing the spec asks for, Copy link, with its own support to test on each engine. A shop that wants a share sheet can add one beside the field.

**Status:** Accepted.

### D-157 · Edit build: a link on the kit's line, and an add that replaces · 2026-11-12

Milestone 6's step 12c, against the spec's Review and add to cart ("After adding, the cart line carries an "Edit build" link that reopens the builder. Saving there replaces the build in the cart") and the plan's risk line ("Edit build replaces by adding first").

- **One link for three carts.** The kit's line gets one item data entry whose `display` is the link. Checked in WooCommerce 11.3.0: the Store API passes each item data value through `wp_kses_post` (`src/StoreApi/Schemas/V1/CartItemSchema.php:258`); the Cart and Checkout blocks and the Mini-Cart print it through `sanitizeHTML` with `a`, `href` and `class` allowed (`assets/client/blocks/cart.js`, `mini-cart-contents.js`); a classic cart prints it through `wp_kses_post( wpautop() )` (`templates/cart/cart-item-data.php:24`). No script and no template override.
- **What the link holds.** The kit's builder (`BuilderUrl::of`, so `cbuild_builder_url` holds), at the review, with the build's part IDs, assembly, and `_replace`, the build's own `id`, a random UUID that names nothing outside the shopper's own cart. The review's form sends it on as `cbuild[replace]`, by the Store API with JavaScript and by WooCommerce's handler without.
- **Add first, then take out.** `BuildCart` remembers which build a build taken in the request replaces, and takes the old one out (its parts, then its kit's line) only after every part of the new one is in the cart. A build the cart refuses, or a part WooCommerce will not add, leaves the old build as it was: the shopper never ends up with neither. A `replace` naming nothing in this cart takes nothing out, so a forged one can do no more than an add can.
- **The shared link drops the builder's own keys.** Step 12b's field held the view's own address, which, on a page opened from Edit build, carries `_replace`, and on any page may carry `_was`, `_brand`, `_size` and `_shown`. The spec's Share links "carry part IDs, the step and assembly on or off, never a customer, an order or a saved build's ID". The review's `link` is now made from the picks, the review, assembly and the preset alone, and Copy link copies it.
- **Quantity.** The edited build goes in at the quantity the review sends, which is 1. A shopper who had 3 of a build and edits it has 1 after; the cart's own quantity field sets it back. Carrying the quantity through the link is for Zach to decide; this is what stands until he does.

**Status:** Accepted.

### D-158 · The builder's page brings the carts' line styles · 2026-11-12

Milestone 6's step 12d, closing what D-132 left for this milestone ("A build added from a page with no build in the cart has its styles from the next page on").

- **The gap.** `Cart\ShowHooks` puts its few inline rules in a page only when the cart holds a build as the page is drawn, so that a shop page gets nothing of the plugin's (spec: Performance). With step 12a, a shopper's first build reaches the cart from the builder's page without a page load, and the Mini-Cart in that page's header would draw its parts unindented until the next page.
- **The fix.** The builder's page asks for the styles whenever it renders a builder, through `ShowHooks::styles()`, the same handle with no file and the same rules. It is one `<style>` element of about 300 bytes, on pages that already load the builder's own script and styles; a page without a builder still gets nothing, and the smoke test that holds it to that is unchanged.
- **Front calls Cart directly.** Both are in deptrac's hooks layer, so no new hook or public API is needed for it.

**Status:** Accepted.

### D-159 · The builder's stylesheet, and how a theme restyles it · 2026-11-12

Milestone 6's step 13, its first part, against docs/STANDARDS.md's CSS rules and the plan's accessibility line (forced colours, reflow, text spacing, targets).

- **Where it is built and loaded.** `assets/src/builder/style.scss`, imported by the block's editor script, which wp-scripts extracts to `build/builder/style-index.css` with its right-to-left twin. block.json names it as the block's `style`, so WordPress loads it wherever the block renders, and in the editor's preview. The kit's own page has no block, so `BuilderPage` enqueues the same handle. The budget tool's comment named `style-view.css`, which a script module's build would only make with a stylesheet imported by the module itself; it measures every stylesheet either way. 1,871 B compressed, of 10 kB.
- **Stylelint's class pattern.** `@wordpress/stylelint-config` allows only hyphenated classes, and the standards ask for BEM. `.stylelintrc.json` now allows BEM under `cbuild-` (and WordPress's own `screen-reader-text`) and nothing else, which is narrower than the rule it replaces: a class of another prefix is now an error. Its two blank-line rules take Stylelint's own `first-nested` exception, since Prettier, which owns formatting here, takes out the blank line before the first rule of a block and the two would otherwise never agree.
- **Two rules outside `:where()`.** `.cbuild [hidden] { display: none }`, because every piece the builder hides is hidden by the attribute and a `display` of the builder's or a theme's would show it; and `.cbuild .screen-reader-text`, so the status region stays off screen in a theme that defines no such class.
- **Colour without knowing the theme.** Every colour is the theme's preset or the text's own colour mixed with transparency, so the builder reads on a dark theme as on a light one without a dark mode of its own. The one colour of its own, the red of a part that does not fit, is only ever a border beside words that say so, so its contrast on a dark ground costs nothing.
- **Targets.** Buttons are at least 2.75rem (44 px) each way; radios and the links that act as controls at least 1.5rem (24 px), WCAG 2.2's 2.5.8. A link inside a sentence is exempt and left as text.
- **The custom properties are public.** Sixteen `--cbuild-*` properties, listed in docs/HOOKS.md. The API surface tool now reads every `--cbuild-*` a stylesheet defines, so one cannot be renamed or dropped without the snapshot, HOOKS and the changelog. Motion's two come with motion.
- **What the tests hold.** The target and forced-colour tests fail on the page without the stylesheet; the reflow and text-spacing tests pass on the bare page and hold the styles to not breaking it. Forced colours run in Chromium only, the one engine Playwright can put in that mode.

**Status:** Accepted.

### D-160 · The phone bar: sticky, one of each button, and focus kept clear · 2026-11-12

Milestone 6's step 13, its second part, against the spec's The total ("On phones it's a bar at the bottom: a small picture of the board, the total, and the next step on its button ... It never covers the control in focus"), W7 and P11. D-153 made the bar's view and left its markup for the styles.

- **Sticky, not fixed.** The bar is the builder's last element, `position: sticky` at the foot of the screen. It stays in reach while the shopper is in the builder and stops where the builder ends, so it never sits over the theme's footer or its links, which a fixed bar would cover for the rest of the page.
- **Which shows follows the screen, not the builder.** The bar shows below a screen 48rem wide, and the summary beside the steps everywhere else, each hidden by a rule outside `:where()`, so a theme's `display` on a `div` or an `aside` cannot show both. A bar is a thing of the screen's foot, so it follows the screen; the layout of the cards still follows the builder's own width.
- **One button of each name (P11).** On a phone the step's own Next and the review's Add to cart are hidden, and the bar's are the only ones; a test holds every visible button in the builder to a name of its own at both widths. The bar has two buttons, one per form, because a button's `form` is set by its attribute and cannot be bound; the view says which is shown (`onStep`, `onReview`).
- **The small picture** is, for now, the picture's first photo, which links to the picture (`#cbuild-picture`, which takes focus). Milestone 7 draws the board.
- **The parts list** is a disclosure on the total, opening above the bar, from the summary's own rows, so it works without JavaScript and leaves the bar's button where the thumb is.
- **Focus kept clear: the page's padding.** The first try gave the builder's controls a `scroll-margin`, and the browser test found a preset's button 11 px under the bar: a browser scrolls a focused control only when it is outside the scrolling box's padding, so the padding has to be the page's. `:root:has(.cbuild .cbuild-bar:not([hidden]))` gets `scroll-padding-block-end` below 48rem: the one rule outside the builder's root, which the spec's own words require, and only while a bar is shown. The bar is `border-box`, so its height is the property's and the padding clears it.
- **And the script, for Firefox.** Firefox scrolls a focused control only once it leaves the screen, whatever the padding, so a button partly under the bar stayed there. The store's `reveal`, on `focusin`, scrolls a control the bar overlaps into view, which honours the padding. Without JavaScript in Firefox a control can be partly under the bar: WCAG 2.4.11 (not entirely hidden) still holds there, the spec's stricter "never covers" does not. Chromium and WebKit need no script.

**Status:** Accepted.

### D-161 · Motion: three movements, the browser's view transition, nothing under reduced motion · 2026-11-12

Milestone 6's step 13, its third part, against the spec's Motion (150 to 250 ms, easing out, `transform` and `opacity` only, nothing that blocks a tap or loops, nothing under reduced motion).

- **Three movements, no more.** The chosen card settles (scale 0.97 to its size), a part new to the picture slides up into place, and a step crosses over to the next. Each is 200 ms by default, with a decelerating curve, `cubic-bezier(0.2, 0, 0, 1)`, as the two custom properties `--cbuild-duration` and `--cbuild-ease`.
- **The browser's view transition, not a script's animation.** The store's `show` wraps the change of view in `document.startViewTransition` where the browser has it and the shopper allows motion, and changes the view at once otherwise, so a browser without the API loses nothing but the crossing. The update waits one task for the Interactivity API's render, which runs on a microtask, before the browser takes the new picture. Each picture item is `view-transition-name: match-element` with the class `cbuild-part`, so a part kept moves with the view and a new one arrives by `cbuild-arrive`.
- **Timing on the page's root.** The view transition's pseudo-elements hang off `:root`, not `.cbuild`, so their rules read the two properties with the same values as fallbacks. A theme that changes them sets them on `:root` too; HOOKS says so.
- **Nothing in the way of a tap.** `::view-transition` takes no pointer events, so a tap during a crossing reaches the page beneath.
- **Under reduced motion,** every rule is inside `@media (prefers-reduced-motion: no-preference)`, and the store asks `matchMedia` before starting a transition. A browser test watches the page frame by frame and finds no animation and no transition.
- **The `transform` and `opacity` rule** is held by the browser test for the builder's own keyframes. The browser's own view transition groups animate their box's size as well as its place; between the old and new view the boxes are the same size, so only `transform` changes, but the test does not hold the browser's keyframes to the rule. It does hold every animation, the browser's included, to 150 to 250 ms and one iteration.
- **The card settles on load as well** when a card is checked as the page opens (a build link): one 200 ms settle, which the spec's bounds allow, and simpler than marking the shopper's own pick.

**Status:** Accepted.

### D-162 · Visual snapshots in Playwright's container, three themes by cookie, and the bar's ground on a dark classic theme · 2026-11-12

Milestone 6's step 13, its last part, against docs/AUDIT.md ("Visual snapshots of the builder's main states on Kadence, Storefront and Twenty Twenty-Five, in dark and light ... Run in CI's Linux container only; baselines change only on purpose") and the roadmap's Snapshots line.

- **Playwright's own image, locally and in CI.** `tools/visual.sh` runs the `visual` project in `mcr.microsoft.com/playwright:v<version>-noble`, the version read from the installed `@playwright/test`, on the host's network, so it reaches wp-env's site. A baseline made on a Mac is then the picture CI takes: the same Chromium, fonts and renderer. A run on the bare runner or on macOS would compare different fonts. The `visual` project exists only where the script sets `CBUILD_VISUAL`, so `npm run test:e2e` never runs it by accident. CI runs the script on the newest leg where every browser project runs. Pulling the image costs about a minute there.
- **The site made ready on the host.** The container has no wp-env, so the plugin, the default theme and the shop's fixture moved from Playwright's global setup into `tools/e2e-site.sh`. The global setup calls it, and the script calls it before the container starts and tells the container's setup to skip it (`CBUILD_E2E_SITE_READY`).
- **Three themes without switching the site's.** A test mu-plugin, `cbuild-e2e-theme.php`, reads two cookies for that request alone: the theme (through the `stylesheet` and `template` filters) and dark colours. Dark is each theme's own: Twenty Twenty-Five's Evening style; a dark palette through Kadence's `kadence_palette_option` (`inc/components/options/component.php:5015`); and dark defaults through Storefront's `storefront_setting_default_values`, since Storefront answers each `theme_mod_` itself with its defaults where the site set none (`inc/customizer/class-storefront-customizer.php:90`). Cookies, not a header, because a header on a cross-origin request forces a preflight (tests/e2e/support/test.ts).
- **What is pictured.** The builder's own box with the theme's ground behind it, at its start and at a full build's review, at 1280 and 390 pixels: 24 pictures. The viewport is made as tall as the page first, because a box taller than the screen is pictured in pieces and a theme's fixed bar is drawn into the middle of it. Motion is reduced, fonts loaded and photos decoded first. A second run against the first baselines matched all 24.
- **What they found: the bar's ground.** On Storefront's and Kadence's dark colours the phone bar was white with the theme's light text on it, and its total all but unreadable. `--cbuild-ground` fell back to `Canvas`, which is white unless the page declares a dark `color-scheme`, which classic themes do not. Where the theme names no base colour, the ground is now `contrast-color(currentcolor)`, so a dark theme's bar is dark, and the bar's words are `--cbuild-on-ground`, the builder's own text. Chromium, WebKit and Firefox at the versions Playwright 1.65 runs support `contrast-color()`. A browser that does not gets `Canvas` and `CanvasText` together, behind `@supports`, which always read. A browser test runs axe's contrast rule on the bar, closed and open, on all three themes' dark colours.

**Status:** Accepted.

### D-163 · The phone bar among a theme's layout: overflow that stops a sticky box, and a theme's own bar at the foot · 2026-11-12

Found by step 13's snapshots (D-162) and then measured on Storefront at 390 pixels: halfway down the builder, the bar's top was at 2,004 px on a 700 px screen, and every point of it was under something else.

- **Why it did not stick.** Storefront gives `body` and `.site` `overflow-x: hidden`. A box with `hidden` on one axis is a scroll container, its other axis computed to `auto`, even when it never scrolls, and a sticky box sticks within its nearest scroll container. `.site` grows with the page, so the bar stayed at the builder's end.
- **The fix, in the store, for any theme.** On start and on each resize, while the bar is shown, each ancestor of the builder whose computed overflow is `hidden` on an axis gets `clip` on that axis, inline. `clip` clips the same and is not a scroll container, so the bar sticks. `hidden` also made a block formatting context, which `clip` does not, so a box whose display is `block` gets `flow-root`, which is the same box with that context: floats it held stay held. A box that scrolls (`auto`, `scroll`) and the page's root are left alone.
- **Not in the stylesheet.** CSS cannot ask what an ancestor's overflow is, and `clip` on every ancestor would cut what a theme lets out sideways on purpose, such as Twenty Twenty-Five's full-width blocks, which reach past their parent with negative margins.
- **A theme's own bar at the foot.** Once the builder's bar stuck, Storefront's handheld bar, `position: fixed` at the bottom with a z-index of 9999, covered it. The store measures the tallest fixed box outside the builder that reaches the screen's bottom edge and is under half the screen tall, and sets its height as `--cbuild-bar-offset` on the page's root. The bar's `inset-block-end` and the page's scroll padding read it, so the bar and the control in focus clear both bars. A theme may set the property itself.
- **Without JavaScript,** on a theme like Storefront the bar stays at the builder's end, and its buttons are there to reach. The spec's "bar at the bottom" then holds only with JavaScript on such a theme.
- **Tests.** A browser test scrolls halfway down the builder on each theme and holds the bar wholly on screen with nothing of the theme's over it. It failed on Storefront before the fix (`onScreen: false`, `covered: 6`), and again (`covered: 6`) with the measurement broken on purpose. Vitest covers the module's cases.

**Status:** Accepted.
