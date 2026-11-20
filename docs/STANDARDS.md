# Engineering standards

How Creative Skateboard Builder's code is written: the industry standards for each language and platform it uses, and the agency's house rules on top of them. `AUDIT.md` judges the code against these; `SPEC.md` says what the code must do. Where this file and the spec disagree, the spec wins and `DECISIONS.md` records the difference.

These follow the agency's other Claude Code builds (Creative Search, Stockroom, Restock, Shipment Tracking and Reviews). Where a sibling repo already solved something, do it the same way unless this file says otherwise.

## Sources, in order

When two sources disagree, the one higher on this list wins.

1. **WordPress:** the [Plugin Handbook](https://developer.wordpress.org/plugins/), the [Coding Standards handbook](https://developer.wordpress.org/coding-standards/) (PHP, JavaScript, CSS, HTML, accessibility, inline documentation), the [Block Editor handbook](https://developer.wordpress.org/block-editor/) (block.json, script modules, the [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)), the [REST API handbook](https://developer.wordpress.org/rest-api/) and the [Abilities API](https://developer.wordpress.org/apis/abilities/).
2. **WooCommerce:** the [extension best practices](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility/), the [HPOS recipe book](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/), [extending the Store API](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/) and the Cart and Checkout blocks' [filters](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/filters-in-cart-and-checkout/cart-line-items/), and the developer advisories.
3. **The web:**
   - [WCAG 2.2](https://www.w3.org/TR/WCAG22/) AA and the [ARIA Authoring Practices](https://www.w3.org/WAI/ARIA/apg/)
   - [OWASP ASVS 5.0](https://owasp.org/www-project-application-security-verification-standard/) at level 2
   - Google Search Central, including [faceted navigation](https://developers.google.com/crawling/docs/faceted-navigation)
   - [Core Web Vitals](https://web.dev/articles/vitals)
4. **PHP and tooling:**
   - [PSR-4](https://www.php-fig.org/psr/psr-4/) autoloading. The WordPress Coding Standards win over PSR-12 for formatting.
   - [Semantic Versioning](https://semver.org/)
   - [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
5. **The agency's house rules**, here and in `CLAUDE.md`.

When a source is unclear or the installed code differs from its docs, read the source installed by wp-env, do what it does, and record it in `DECISIONS.md` with the file and line.

## Layout

The starting layout. Claude Code may refine it in milestone 1 with a `DECISIONS.md` entry; deptrac enforces whatever is settled.

```
creative-skateboard-builder.php  Bootstrap only: headers, constants, the autoloader, Plugin::boot()
uninstall.php             Calls Install\Uninstaller
src/
  Domain/                 Pure PHP. Specs, units, slots, parts, kit settings, rules R1–R8, the fit engine,
                          the change list, the recommender, quantities, pricing, the picture's layout
  Port/                   Interfaces the services need: Catalogue, Stock, Prices, Clock, Cache, Logger, SpecSource
  Service/                Use cases joining the domain to the ports: State, BuildValidator, CartBuilds,
                          SpecIndexer, CatalogueCheck, SpecImport, Pictures
  Adapter/                WordPress and WooCommerce implementations of the ports
  Repository/             The plugin's own tables. The only code that touches $wpdb, with Install/
  Install/                Schema, migrations, activation, uninstall
  Hooks/                  Hookables: attach WordPress and WooCommerce hooks to services. No logic
  Rest/  Abilities/  Cli/ The state route, the abilities, wp cbuild
  Block/  Front/          The Builder block; the builder's markup, its SEO tags
  Cart/  Order/           Cart lines (Store API, blocks, classic); order items, emails, the Build box, the ticket
  Admin/                  The Build tab, Part specs, Spec tables, the Catalogue check, settings
  Privacy/  Health/       Exporter, eraser and policy text; Site Health
  Updates/                The agency's updater, as in Creative Search
  Support/                Small helpers with no domain knowledge
templates/                Server-side markup for the builder, rendered through the Interactivity API's directives
assets/src/               TypeScript and SCSS: the builder's store, the Cart block filters, the block's editor, admin
tests/                    Unit/, Integration/, e2e/, js/, Fixtures/, Support/
tools/                    gates.sh, integration/ (PHPUnit 9.6), build, release, i18n and copy checks
```

**Layers** (deptrac, the `deptrac/deptrac` package):

- `Domain` depends on nothing.
- `Port` depends on `Domain`.
- `Service` depends on `Domain` and `Port`.
- `Adapter` and `Repository` implement ports and may use WordPress.
- The presentation layers depend on services and the domain, never on adapters or repositories directly. The container wires the adapters in.

**No template overrides in 1.0.** A template a theme copies becomes an API we would have to version. Themes restyle the builder through its documented classes and custom properties; plugins change its content through filters.

## PHP

- PHP 8.5 is the floor (as Creative Search, D-023). `composer.json` pins `platform.php` to `8.5.99`, so no dependency can need more than the floor.
- `declare( strict_types=1 );` in every file. Every file under `src/` outside the domain stops when requested directly (`if ( ! defined( 'ABSPATH' ) ) { exit; }`, after its `use` lines); gate 17 holds both. Classes are `final` unless built to be extended. Value objects are `readonly`. Enums stand in for string constants. Use typed constants, `#[\Override]` and property hooks where they make the code clearer, and PHP 8.5's pipe operator where it reads better.
- No globals, no static state outside the bootstrap and the container, no service locator inside the domain.
- Errors are typed exceptions in the domain, turned into `WP_Error` or notices at the edges. No `@` suppression, and no catch that hides an error.
- **Naming:**
  - Classes, files and namespaces follow PSR-4 (WPCS's file name sniffs are off, as in the family).
  - Methods are camelCase inside the namespace. WPCS's snake_case rule is relaxed for methods only, as in the family.
  - Hooks, options, meta keys, tables, actions and any global function are snake_case with the `cbuild_` prefix.
- **Docs:** WordPress's inline documentation standard. Every public hook and public method gets a docblock with `@since`. Generics go in PHPStan's syntax (`list<Part>`, `array<string, Spec>`).
- **Static analysis:** PHPStan 2 at level 9 with `szepeviktor/phpstan-wordpress`, the WordPress and WooCommerce stubs, strict rules, deprecation rules and `spaze/phpstan-disallowed-calls` for the banned functions. A second config runs `src/Domain` at level 10. No baseline. A `@phpstan-ignore` needs the error's identifier and a reason.
- **Coding standard:** PHPCS with WordPress-Extra, WordPress-Docs, WordPress-VIP-Go and PHPCompatibilityWP (`testVersion 8.5-`, `minimum_supported_wp_version 7.0`), at zero warnings. Start from Creative Search's `phpcs.xml`, swapping in this plugin's prefixes and text domain.
- **Dependencies:** none at run time beyond Composer's autoloader. If a package becomes necessary, ask first. If Zach agrees, prefix it with Strauss and record it.

## WordPress

- **Hooks:** each feature is a Hookable class registered by the container, which names the scopes its hooks can fire in (`Scope`: everywhere, admin, WP-CLI, cron, REST, storefront). The plugin builds a Hookable only on a request in one of them (D-067). `Everywhere` is for a job's handler and a hook that fires on any request, and needs a comment saying which. Callbacks are one line into a service. Priorities are named constants where they matter.
- **Security:**
  - Sanitise on input (`sanitize_*`, `absint`, `wc_format_decimal`) and escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses` with a named allowlist).
  - Nonces and capabilities on every state change. Nonces prove intent, never identity.
  - `$wpdb->prepare()` with `%i` for identifiers.
  - Redirects through `wp_safe_redirect()`.
- **REST:**
  - Routes are namespaced `cbuild/v1`.
  - Every route has an explicit `permission_callback` and an `args` schema with types, limits and enums.
  - Responses come from `rest_ensure_response()` with a published JSON Schema, and errors are `WP_Error` with a code and a status.
- **Blocks:**
  - `block.json` uses apiVersion 3. The block is dynamic (`render.php`).
  - `viewScriptModule` for the front end, with `supports.interactivity` on.
  - Block supports (spacing, colour, typography) so themes can style it.
  - `deprecated` entries whenever an attribute changes.
- **Script modules:** register them with `wp_register_script_module()` and the block metadata, and build them with `@wordpress/scripts --experimental-modules`.
- **Assets:** loaded only where a builder renders. No inline scripts carrying data.
- **Options and storage:**
  - One settings option, autoloaded only if read on every request.
  - Large or rarely read data is never autoloaded.
  - Transients are for caches that can vanish, never for state.
- **Object cache** (D-066), so the plugin behaves the same on Redis, Valkey, Memcached, LiteSpeed's cache, APCu, a file or SQLite cache, and none:
  - Only the `wp_cache_*` functions, in the `cbuild` group, with an expiry on every entry. No Redis or Memcached call of the plugin's own.
  - A miss is always answered from the database. Nothing is correct only because an entry was there, and nothing is correct only because it was gone by the next request.
  - What is cached is scalars and arrays of them: no object, closure or resource, since each cache serialises its own way.
  - An entry goes out of date by its key changing (a part's last change, the index's generation), never by a flush. `wp_cache_flush()` and `wp_cache_flush_runtime()` are banned (gate 2): they empty every other plugin's entries too.
  - `wp_cache_flush_group()` and the `*_multiple` functions only behind `wp_cache_supports()`, with a path that works where the cache has neither.
  - No global group: on a network, each site's entries are its own.
- **Background work:** Action Scheduler, in the `creative-skateboard-builder` group. Actions are unique where duplicates would waste work, and idempotent always. No WP-Cron for anything that must run.
- **Settings:** through WooCommerce's settings API (WooCommerce > Settings > Builder), with every option described in `docs/SETTINGS.md`.
- **Internationalisation:** the spec's French section. The text domain loads on `init`. Translations ship as `.po`, `.mo` and `.l10n.php`.
- **Privacy:** an exporter, an eraser and policy text through the core privacy API.
- **Site Health:** `site_status_tests` and `debug_information`.
- **WP-CLI:** every command has a docblock synopsis and examples, clear exit codes and `--format` where it prints tables. The demo host has no WP-CLI, so anything the shop needs also exists in the admin.
- **Activation, upgrade and uninstall:**
  - Activation creates the schema and seeds the spec tables.
  - Migrations run on load when `cbuild_db_version` is behind, with the guarantees in `AUDIT.md` (Upgrades).
  - `uninstall.php` removes everything but the order records.
  - Multisite activates per site.
- **Headers:**
  - `Requires at least: 7.0`, `Requires PHP: 8.5` and `Requires Plugins: woocommerce`.
  - `WC requires at least: 11.2` and `WC tested up to`, kept current.
  - The text domain and the update URI, as the family's plugins carry them.

## WooCommerce

- **CRUD only:** `wc_get_product()`, `WC_Order`, order item methods. Never `get_post_meta()` on orders, never SQL on WooCommerce's tables.
- **Declare compatibility** through `FeaturesUtil::declare_compatibility()`:
  - `custom_order_tables` true;
  - `cart_checkout_blocks` true;
  - `product_block_editor` false, only if the installed WooCommerce still has that feature. Its beta was retired in 2026: check `FeaturesController` on the floor and record what you find.
- **The product type:** `KitProduct extends WC_Product`, registered through `product_type_selector` and `woocommerce_product_class`. Its settings go through the product's own CRUD on save.
- **Cart and checkout:**
  - The Store API through its extension points: `woocommerce_store_api_register_endpoint_data()` and `ExtendSchema`.
  - The blocks through `registerCheckoutFilters`.
  - The Mini-Cart through item data, CSS and the documented `wc-blocks_added_to_cart` event.
  - Classic carts and checkouts through their PHP filters.
  - Nothing private, internal or undocumented. Where the spec names a hook, confirm it in the installed source first.
- **Prices, stock and logs:**
  - Prices come from `wc_get_price_to_display()` and are formatted with `wc_price()`.
  - Decimals are read with `wc_format_decimal()`.
  - Stock is WooCommerce's alone.
  - Logs go to `wc_get_logger()` with the source `creative-skateboard-builder`.
- **Version policy:** the floor is WooCommerce 11.2, the first release with the Store API validation filters the cart relies on (D-004). CI pins the oldest and the newest. The floor moves only with a decision, in the changelog.

## Front end: HTML, CSS, TypeScript, the Interactivity API

**HTML**

- Semantic and valid:
  - landmarks, one H1 per page, headings in order;
  - `fieldset` and `legend` per slot;
  - real `<button>`s for actions and links for navigation;
  - images with `alt`, `width`, `height`, `decoding` and the right `loading`.
- No inline event handlers. Inline `style` is used only to set the builder's custom properties.

**CSS**

- The block's stylesheet uses `@wordpress/stylelint-config` (SCSS) and Prettier.
- Logical properties throughout.
- Classes follow BEM under the `cbuild-` prefix. Base rules sit inside `:where()`, so themes override them without fighting specificity.
- Every colour, size and font is a `--cbuild-*` custom property that falls back to the theme's presets (`--wp--preset--*`).
- Container queries lay out the cards, so the builder works in a narrow column as well as full width.
- `:focus-visible` rings, `forced-colors` support and `prefers-reduced-motion`.
- No `!important`, no IDs in selectors, nothing styled outside the builder's root.

**TypeScript**

- The builder's store, the Cart block filters and the admin scripts are TypeScript in `strict` mode, built by `@wordpress/scripts` and type-checked by `tsc --noEmit` in `npm run check`.
- The state route's response types are generated from its JSON Schema (`json-schema-to-typescript`), so the store and the server can't drift.
- Lint with `@wordpress/eslint-plugin` (flat config) and format with `@wordpress/prettier-config`.
- No jQuery and no framework.
- The `@wordpress/*` packages are externals, provided by WordPress at run time, as Creative Search's ESLint config sets out.

**The Interactivity API**

- One store, `creative-skateboard-builder`, private (`lock: true`). Other code listens to the builder's DOM events instead.
- State starts on the server with `wp_interactivity_state()`.
- The markup is one template with directives. The server renders it, and the client re-renders the same template from the state route's answer: `data-wp-each` for option lists, `data-wp-bind` and `data-wp-text` for the rest. Nothing builds markup in JavaScript.
- Requests to the state route are debounced 150 ms. They carry a sequence number and an `AbortController`, so a stale answer is dropped. A failure hands over to the form's own submit.
- Steps push the same URL the no-JavaScript form makes. Back and Forward re-render from the URL.
- Focus and announcements follow the spec.
- The router (`@wordpress/interactivity-router`) was considered for step changes. It fetches whole pages, which costs a full page render (0.7 to 1.1 s on the demo's host without a page cache), so the state route's JSON drives every step change instead. If a benchmark shows the router is fast enough, that's a decision for `DECISIONS.md`.
- **Motion:**
  - Same-document view transitions (`document.startViewTransition()`) between steps and for the picture's layers, where the browser supports them.
  - CSS transitions on `transform` and `opacity` only.
  - Nothing under reduced motion.

**Browsers:** WordPress's own list (`@wordpress/browserslist-config`). Anything outside it still works without JavaScript.

## Testing

| Suite | Tool | Notes |
| --- | --- | --- |
| Unit | PHPUnit 13 | No WordPress. The domain must need no shim to run. Coverage: pcov for lines; branch coverage of `src/Domain` needs Xdebug in coverage mode (`--path-coverage`), which `composer test:coverage` uses for the domain |
| Integration | PHPUnit 9.6 with the Yoast polyfills in `tools/integration/`, its own Composer project | The WordPress core test suite still calls an API PHPUnit 10 removed (Creative Search D-021; WordPress core ticket [#62004](https://core.trac.wordpress.org/ticket/62004) is open). Integration tests use `set_up()`/`tear_down()` and no PHPUnit 10+ attributes. Register the plugin's Composer loader behind PHPUnit 9.6's (Search D-023) |
| PHP mutation | Infection 0.35 | `infection-domain.json5` at 100 %, `infection.json5` at 95 % |
| JavaScript unit | Vitest, jsdom | The store's actions and derived state |
| JavaScript mutation | StrykerJS with the Vitest runner | 90 % on the store |
| Browser | Playwright, `@axe-core/playwright` | Chromium, WebKit and Firefox at phone and desktop widths; projects for JavaScript off, fr_CA, right to left and visual snapshots. Firefox on macOS 27 needs the `CFFIXED_USER_HOME` workaround (Search D-022) |
| Lab performance | Lighthouse CI | The builder page on wp-env, median of three |
| Benchmarks | PHPUnit's `benchmark` group | The server budgets, on a generated catalogue |

- Test names say the behaviour: `test_an_8_5_inch_deck_blocks_indy_144_trucks`.
- Every fixture catalogue is generated from a seed, and the seed is printed on failure.
- No test sends mail outside wp-env's catcher or calls the network.
- Test addresses are `shopper+N@example.com`. Nothing in the repository uses a real person's address.

## Git, versions and releases

- **Branches:** `main` is protected. Each milestone works on its own branch (`m6-builder`) and lands through one pull request, using the template in `.github/`.
- **Commits:** an area and an imperative summary (`Cart: keep a build's parts with their parent`), with D-numbers where a decision is involved, as the family's repositories do. Small, each green.
- **Versions:** milestones tag `v0.<n>.0`; milestone 10 tags `v1.0.0` (D-236). Semantic Versioning from 1.0, with the public API rules in `AUDIT.md`. The version lives in the plugin header, `readme.txt`'s stable tag, `package.json` and a constant, kept in step by the build script, and in each block's `block.json`, which WordPress writes on the block's stylesheet and script as their `ver`: gate 16 holds it to the constant.
- **Changelog:** `CHANGELOG.md` in Keep a Changelog's sections (Added, Changed, Deprecated, Removed, Fixed, Security), one entry per milestone. `readme.txt`'s `== Changelog ==` is written by hand for shop owners, a line or two per release in plain words, and is what the update's "View details" popup and the release's notes show (D-053).
- **Releases:** `.github/workflows/release.yml`, copied from Creative Search and kept the same:
  - It runs on a version tag once CI has passed on that commit.
  - It builds the zip and writes `info.json` with the SHA-256.
  - It attaches a CycloneDX SBOM.
  - It publishes to `zachatkinson/creative-releases` (the `RELEASES_TOKEN` secret).
  - Shops update through `Updates\Updater`, which `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter turns off.
  - The zip anyone installs is the release's, built from the tag.
- **CI hygiene:**
  - Every action is pinned to a commit SHA, and Dependabot keeps them and the lock files current.
  - Jobs that can't affect each other run in parallel, unless running them in one job costs fewer billed minutes for the same gates (D-040): a job is billed to the whole minute, so a few seconds of work rides in a job that already exists, and the matrix variants share one wp-env.
  - A pull request is opened as a draft and takes the lighter run; marking it ready starts the full one (D-041). Pushes are batched, since each is a run.
  - Every job has a time limit, and no checkout keeps its token.
  - Dependabot opens one grouped pull request per ecosystem a month; security updates are not delayed.
  - The weekly canary runs on a schedule.

## Documentation

| File | Holds | Kept |
| --- | --- | --- |
| `README.md` | For developers: what it is, how to run it, how to test it | Every milestone |
| `readme.txt` | WordPress.org format: description, FAQ, changelog, privacy | Every release |
| `docs/HOOKS.md` | The public API, each item with since, signature, where it runs and an example, as Creative Search's `HOOKS.md` | As each item lands |
| `docs/SETTINGS.md` | Every setting: key, default, who can change it, what it does | As each lands |
| `docs/DECISIONS.md` | Every decision Claude Code makes, numbered, dated, with its reason | As made |
| `docs/SECURITY.md` | The walk-through before each tag | Each tag |
| `docs/PRIVACY.md` | The data inventory | Whenever storage changes |
| `docs/A11Y.md` | The manual screen reader script and its results | Each release |
| `docs/BENCHMARK.md` | Every budget's measurements | Each milestone |
| `docs/PICTURE.md` | The shot template for part photos (milestone 7) | When it changes |
| `docs/reviews/M<n>.md`, `docs/AUDIT-<version>.md` | The audits | Per milestone and release |

Comments in code say why, not what. A workaround names the issue it works around and when to remove it.

## Copy: the words shoppers and admins read

- Plain words, sentence case, active voice. Say what happened and what to do next ("These trucks are too narrow for an 8.5″ deck. Pick a pair from 8.5″ up.").
- **Never:** em dashes, superlatives, exclamation marks, or templated constructions ("Not just X, but Y", "Whether you're X or Y"). Never pressure ("Hurry up!").
- Numbers as numerals with their unit, written the way skaters write them: 8.25″ decks, 1¼″ bolts, 54 mm wheels. Units go through the translatable patterns.
- Button names say exactly what they do and to what ("Choose Independent 149 trucks", "Next: Wheels", "Add the build to the cart").
- English strings are Canadian English, as the shop is ("catalogue", "colour"). French is reviewed by a person before release.
- The grep gate fails on an em dash or a word from the banned list (in `tools/copy-words.txt`) in the POT file. The review reads the rest.
