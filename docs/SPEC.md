# Creative Skateboard Builder: research and build spec

Oct 22, 2026, updated Nov 9, 2026 · Zach Atkinson

Build a WooCommerce plugin of our own that sells a complete made from parts the shop already stocks: the shopper picks a deck, sees only the trucks, wheels, bearings, hardware and grip that fit it, watches the total as they go, and orders it assembled. Each part stays its own line in the cart and the order, so stock, tax and refunds work as WooCommerce means them to. Built for reuse, like Creative Locator, Creative Stockroom and Creative Search: Skatehouse is the first shop, and any skate shop the agency builds can run it.

## What changed on 9 Nov

The UX deep dive, Zach's audit list and the plugin's new name reshaped the plan. Milestones 1 to 5 build what they did before, with the additions below; the builder's front end is now two milestones.

| What changed | What it means for the build |
| --- | --- |
| The name: Creative Skateboard Builder | Zach's call: Creative Builder read as generic. The slug, text domain and namespace follow the new name; the short `cbuild_` prefix stays (D-001) |
| The UX deep dive (6 Nov): sixteen skate shops' builders walked on a desktop and a phone, car configurators studied, 3D costed | Its patterns are in The builder, numbered P1 to P12 in `docs/UX-RESEARCH.md`. The board's layered 2D picture is in 1.0. A 3D view that loads on a tap, and graphics shoppers upload themselves, are modules after 1.0 |
| Nine milestones instead of eight | The builder's front end (6) and the picture (7) are milestones of their own; orders move to 8 and hardening to 9. `docs/ROADMAP.md` has each in full |
| The audit matrix has twelve dimensions | Zach added SEO, privacy and data handling, and upgrades and compatibility for code that builds on the plugin; resilience, and UX and content, were proposed with this brief and approved. The audit runs through every milestone, as `docs/AUDIT.md` sets out |
| The floors move to PHP 8.5, WordPress 7.0 and WooCommerce 11.2 | PHP and WordPress as Creative Search's D-023. WooCommerce 11.2 is the first release with the Store API validation filters the cart relies on. Open question 8 |
| Four new sections | SEO and sharing; Privacy and data handling; and, under Security, performance and compatibility, Upgrades and the public API, and Resilience |
| The brief around this spec | `CLAUDE.md`, `docs/PROJECT.md`, `docs/STANDARDS.md`, `docs/AUDIT.md`, `docs/ROADMAP.md`, `docs/UX-RESEARCH.md` and `docs/DECISIONS.md` |
| The attribute mapping and which source wins are shop-wide (milestone 1's review, 9 Nov) | The specs index holds one value per part and spec, so what feeds a spec and which source wins when two disagree can't differ by kit. Both move from the kit's Build tab to WooCommerce > Settings > Builder. Kits keep their rule switches and tolerances, which apply when fit is checked, not when parts are indexed (D-026) |

## What changed since 22 Oct

The demo moved on in the two weeks after this spec was written (checked 6 Nov 2026). Each row is now part of the build, and the lines it touches further down are corrected. The owner hasn't answered questions 13 and 17 to 19 yet, so Skatehouse's setup still waits (Open questions); the milestones don't.

| What changed on the demo | What the builder does about it |
| --- | --- |
| skatehouse-core was split into the agency's plugins on 27 Oct. Skatehouse Core 2.x keeps only the site's own settings and copy | Skatehouse's hooks (rule settings, copy, the "Make it a complete" link) are answered by Skatehouse Core 2.x, and delivery by Creative Checkout (next row) |
| Creative Checkout owns the delivery dates, the cart estimate and the free-shipping meter. The owner's transit days are in: 2 business days to Southern Ontario, 4 to 8 to the rest of Canada | Creative Checkout answers `cbuild_arrival_estimate`, as it answers Creative Shipment Tracking's `ctracking_expected_delivery`, adding the assembly lead time the filter passes. The builder's free-shipping line uses the meter's rule and words, so the builder and the cart drawer never disagree. A Creative Checkout release (ours) adds the lead time wherever a cart or order holds an assembled build, Apple Pay's and Google Pay's sheets included, and an assembly step to "What happens next" |
| Stripe replaced WooPayments on 30 Oct. Its Apple Pay and Google Pay buttons sit above the checkout's steps, on the cart and checkout only | Skatehouse's own checks pay for a build through Stripe's test mode on the demo, by card and by Apple Pay, with Zach paying, as in the 4 Nov dry run. The plugin's CI has no Stripe keys, so milestone 5's checkout tests pay with one of WooCommerce's offline methods |
| Spec attributes were filled in from the descriptions on 6 Nov: Length (`pa_length`) on 13 of the 160 decks, Wheelbase (`pa_wheelbase`) on 19, and Sheet size (`pa_sheet-size`) on the grip sheets and strips | The attribute mapping reads them. R6 checks a deck's length where it has one and skips the check where it doesn't. The grip slot picks parts by Sheet size (What Skatehouse needs) |
| Every product and variation has a SKU since 6 Nov: BRAND-TYPE-STYLE plus the size or colour (Katie at 7.75″ is YEA-DK-109-7750) | The kit gets one in the same scheme, and each part keeps its own on its order line, so a label tool's per-item tracking numbers match. The assembly ticket prints them, and specs import by SKU |
| Mature Content (ours) blurs flagged products and designs on cards, in search suggestions and on their pages | A flagged part blurs on its card in a step, in the parts picture and in the review. The builder lets plugins add classes to a part's markup (`cbuild_part_classes`); a Mature Content release (ours) adds its flag class there and the builder's image selectors to `mcw_image_selectors` |
| Creative Restock (29 Oct) puts Notify me on sold-out sizes and cards | A sold-out part stays in its slot after those in stock, can't be picked, and links to its page's Notify me |
| Creative Shipment Tracking lists each parcel's items and the arrival date on its tracking page (1.12, 6 Nov) | Its "In this parcel" list shows a build under the kit's name with its parts beneath it: a Tracking release, by Claude Code |
| Creative Reviews (2 Nov) asks for reviews 10 days after an order is marked Completed | The request asks about the parts, never the kit |
| Creative Schema's product markup (6 Nov), and Yoast set up on 27 Oct | The kit prints no product markup and stays out of the sitemap, search results and catalogue, since its own price is only the assembly fee. Creative Search suggests the Build a complete page by its title |
| WordPress 7.1.4, WooCommerce 11.2.2 and Kadence Blocks 3.9.0 on the demo. Creative Checkout keeps the cart drawer in step where WooCommerce's Mini-Cart drops a read of the cart | wp-env's newest pins move to these. Milestone 5's drawer test holds the Mini-Cart's first read back 6 seconds, as the site's own end-to-end test does |
| Creative Loyalty (ours, Phase 3) and dealer accounts (Phase 4) are planned | Loyalty reads the order's lines. A points line under the builder's total comes through `cbuild_summary_notes` when Creative Loyalty adds one. Whether dealers see the builder is open question 7 |

## Goal and scope

**Name:** Creative Skateboard Builder (Zach's call, 9 Nov; D-001). Plugin slug and text domain `creative-skateboard-builder`, namespace `CreativeBranding\SkateboardBuilder`. The short prefix `cbuild_` stays on tables, options, meta, hooks and actions, and on the WP-CLI command `wp cbuild`.

**What it does**

- Adds a **kit** product type: a product made of **slots** (deck, trucks, wheels, risers, bearings, hardware, grip), each filled by one **part** the shop already sells. The kit says which products can fill each slot, which slots are optional, and which presets to offer.
- Checks every pick against **compatibility rules** that read the parts' **specs** (a deck's width, a truck's axle width and height, a bolt's length). A part that doesn't fit is shown as unavailable, with the reason; a part that fits but needs care (big wheels without risers) gets a warning.
- Shows a **builder** on the kit's page or wherever the Builder block goes: one step per slot, a **picture** of the board as it's built, the running total with assembly in it from the first step, what each pick changes, presets to start from, and a shareable link. It works with a keyboard, a screen reader and a phone, and without JavaScript.
- Adds a **build** to the cart as one parent line (the kit, carrying the assembly fee) with one child line per part, so each part keeps its own price, tax class, stock and refund. The Cart block, the Mini-Cart and a classic checkout all show the build grouped, and nothing in it can be changed piece by piece.
- Carries the build into the order, the emails and the admin, and prints an **assembly ticket** for the workshop.

**Who it's for.** Skatehouse first: one kit, the complete skateboard, built from 160 deck variations, wheels, hardware and grip, plus the trucks and bearings the shop will add. Then any skate shop Creative Branding builds. The kit model underneath (slots, parts, rules) isn't tied to skateboards, so another kind of kit could follow one day, but 1.0's rules, spec tables and words are for skateboards. The owner sets up kits, slots and presets once; a shop manager only ever fills in part specs.

**Requirements.** PHP 8.5 or later, WordPress 7.0 or later and WooCommerce 11.2 or later (open question 8: Creative Search moved to the same PHP and WordPress floors on 21 Oct, and WooCommerce 11.2 brought the Store API validation filters the cart relies on), and MySQL 8.0 or MariaDB 10.6 or later, as WordPress itself asks. Tested against the oldest pair, WordPress 7.0 with WooCommerce 11.2.0, and the newest, pinned in `.wp-env.json`: WordPress 7.1.4 with WooCommerce 11.2.2, as on the demo on 6 Nov, all on PHP 8.5, on MySQL 8.4 and MariaDB 10.11. No external service, no API key, and no outbound request but the agency's update check.

**Non-goals for 1.0.** No 3D view (the layered 2D picture is in 1.0; a 3D view that loads on a tap is a module after it); no graphics uploaded by shoppers (a module after 1.0, once the owner answers the questions in `docs/UX-RESEARCH.md`); no quantity choices inside a slot (one part per slot, in the set the shop sells); no dealer pricing (Phase 4 adds it through hooks); no builds made of other builds; no editing a build inside the cart (the cart links back to the builder instead); no theme template overrides. Nothing that only Skatehouse needs: Skatehouse's rules and copy go in Skatehouse Core 2.x and the kit's settings.

### Working with Claude Code

- The same shape as Creative Stockroom and Creative Search: milestones in order, each ending in green gates, the audit written up, a commit and a tag. Nothing is merged that lowers coverage or the mutation score.
- The brief around this spec:
  - `CLAUDE.md`: commands, rules, quirks.
  - `docs/PROJECT.md`: the whole Skatehouse project and the agency's plugins.
  - `docs/STANDARDS.md`: how the code is written.
  - `docs/AUDIT.md`: the twelve dimensions and how each milestone is audited.
  - `docs/ROADMAP.md`: each milestone in full, and what comes after 1.0.
  - `docs/UX-RESEARCH.md`: the deep dive.
  - `docs/DECISIONS.md`: the decisions so far.
- The agency's audit runs through the whole build:
  - a plan with the risks under each dimension, before a milestone's code;
  - every gate, on every commit;
  - two reviewer agents at the end of each milestone;
  - Claude in Cowork's review;
  - a full audit before each release.
- Tooling follows Creative Search (its D-021 and D-023):
  - **Composer:** PHPUnit 13 for unit tests; PHPUnit 9.6 for the WordPress test suite, in `tools/integration/`; Infection 0.35; PHPCS with WordPress-Extra, WordPress-Docs, WordPress-VIP-Go and PHPCompatibilityWP; PHPStan 2 at level 9, with level 10 on the domain; deptrac.
  - **npm:** `@wordpress/scripts`, TypeScript, Vitest, StrykerJS, Playwright with axe, Lighthouse CI.
  - `.wp-env.json` pins WordPress and WooCommerce.
- **Conventions:**
  - PSR-4 under `src/`, one class per concern.
  - Repositories own the tables, services own the rules, and the domain layer has no WordPress in it.
  - Nothing in the plugin's root file but the bootstrap.
  - The public API goes in `docs/HOOKS.md` as it's added, settings in `docs/SETTINGS.md`, stored data in `docs/PRIVACY.md`, and a `CHANGELOG.md` entry per milestone.
- Decisions Claude Code makes on its own go in `docs/DECISIONS.md` with the reason. Decisions that belong to the owner (the assembly fee, whether assembled builds can be returned) are settings with the default this spec names.

## The bar to clear

Eleven skate shops run a complete builder; most give advice rather than checking fit, and none works without JavaScript. Pages opened 22 October 2026.

| Shop | Fit | Pre-picked | Assembly | Total and savings | Sold out, saving |
| --- | --- | --- | --- | --- | --- |
| [Warehouse Skateboards](https://www.warehouseskateboards.com/build-a-complete-skateboard) | Not checked; advice on each step (which contradicts itself on wheel sizes) | Nothing | $6, or "Do Not Assemble"; grip only $3; assembled boards can't be returned | Cart subtotal only | Suggests shock pads against wheelbite |
| [Skate Warehouse](https://www.skatewarehouse.com/buildcomplete.html) | Shows only parts that fit the deck | Presets and pro setups | Free with a deck; $20 to cancel once assembled; not returnable | Says you save, no amount | Backordered parts outlined |
| [CCS](https://shop.ccs.com/skateboards/complete-skateboards) and [Daddies](https://www.daddiesboardshop.com/products/flip-razor-skateboard-complete-8-50) | Suggested truck size, with a warning when it's unavailable | Good, Better, Best and Custom; house parts at +$0, upgrades shown as +$ | Separate yes or no for assembly and grip | Price change per option, no total | CCS has a Share button |
| [SkatePro](https://www.skatepro.ca/en/84-51441.htm) | Hides parts that don't fit, and says when an earlier change removes a later pick | Not stated | $27.95 in Canada; not refundable once started | Totals on team rider builds | Names sold-out parts and blocks the cart until they're swapped; shareable `?id=` links, edit, delete, restart |
| [NHS Skate Direct](https://nhsskatedirect.ca/pages/board-builder) | "Compatibility filters" | Packages | Free | Not visible | Ships to Canada from California |
| [Zumiez](https://www.zumiez.com/skateboard-complete-builder.html) | Not visible | Nothing; six parts required | Not assembled | Flat $80 or $100 for parts; savings shown only at the end | Wishlist needs sign-in |
| [Hammer, Toronto](https://www.hammertoronto.com/collections/build-your-board) | By size bucket, chosen first | Free Jessup grip | Free in store, about 30 minutes | 10% off | Runs in a bundle app |
| [Eastern Boarder](https://easternboarder.com/pages/board-builder) | By size tier, chosen first | Not stated | Free, optional | 10% off | Not stated |
| [Orchard](https://orchardshop.com/pages/complete-builder) | Advice only | Packages | Free, a cart checkbox | $15 off with a code | Not stated |
| [SoCal Skateshop](https://socalskateshop.com/custom-complete.html) | Not visible | Not visible | Not visible | Not visible | Any part can come first |
| [Tactics](https://www.tactics.com/skateboardbuilder) | The builder link lands on the skateboards category |  |  |  |  |

The WooCommerce plugins that sell a build. Woo's own Product Bundles and Composite Products both document a skateboard as their example.

| Plugin | Cart and order | Price | Stock | HPOS, blocks | Cost and limits |
| --- | --- | --- | --- | --- | --- |
| [Composite Products](https://woocommerce.com/document/composite-products/composite-products-use-case-kit-builder/) | Parent line with child lines | Base plus parts, optional % off | Each part reduces its own | Yes; Cart, Checkout, Mini-Cart | $149 a year; can't show each variation as its own option |
| [Product Bundles](https://woocommerce.com/document/bundles/bundles-use-case-complex-products) | Parent line with child lines | Fixed, or parts with % off | Each part | Yes, all three | $79 a year; contents fixed, no choices |
| [YITH Composite](https://yithemes.com/themes/plugins/yith-woocommerce-composite-products/) | Parts added as ordinary products | Composite price, or parts on top | Each part | HPOS yes; blocks not stated | No REST support |
| [WPC Composite](https://wordpress.org/plugins/wpc-composite-products/) | Parts as their own lines | Base, parts, or both, % off | Each part | HPOS not stated; Cart block fixes | Free; $29 for the stepped layout |
| [Mix and Match](https://woocommerce.com/products/woocommerce-mix-and-match-products/) | Container with child lines, quantities locked | Fixed, or base plus items | Each child | Yes, both | $89 a year; built for choosing quantities |
| [Product Add-Ons](https://woocommerce.com/products/product-add-ons/) | One line, add-ons as meta | Flat or % | None for options | Yes | $79 a year; no stock, no rules |
| [BuildKit](https://wordpress.org/plugins/woo-product-builder/) | Stepped "PC builder" | Summed preview, extra fee | Hides sold-out | HPOS yes; blocks not stated | Free; Pro adds attribute rules |

**Where 1.0 beats them**

- **It checks fit from specs.** Rules compare a deck's width with a truck's published range, wheels with truck height, bolts with risers. The shops mostly give advice, and the plugins list compatible options by hand.
- **It passes an accessibility audit and works without JavaScript.** None of the eleven builders had a no-JavaScript version.
- **Builds stay whole on every cart.** The parts are grouped and locked in the Cart block, the Mini-Cart and a classic checkout, enforced on the server as well as shown in the page.
- **Each part keeps its own stock,** including the Stockroom's printed decks, and the workshop gets an assembly ticket.
- **It shows the board as it's built,** to scale, in the page's own HTML. Three of the sixteen builders in the deep dive show the chosen parts together at all.
- **No licence per site.** The agency ships it on every shop it builds.

**Build or buy.** Composite Products is the closest fit, at $149 a year per site. We build our own for four reasons:

1. Fit is checked against specs, not against lists of options that go together. Those lists don't scale to 160 deck variations and every truck.
2. Each deck width shows as its own option. Composite can't list variations that way.
3. The accessibility bar and the no-JavaScript version.
4. One codebase for every shop the agency builds.

## Quality bar

Creative Skateboard Builder is held to the same measured standard as Creative Search, judged on the agency's twelve-dimension matrix.

- `docs/AUDIT.md` gives each dimension's checks, the evidence a review needs and when the audit runs.
- `docs/STANDARDS.md` gives the standards the code is written to.
- A milestone isn't done while a gate is red or a dimension fails.

**The matrix, applied to the builder**

| Dimension | What it means here |
| --- | --- |
| DRY and SOLID | One fit engine, in PHP, used by the state route, the cart validation, the no-JavaScript form, WP-CLI and the abilities; the browser never decides fit or price. One template for the builder, rendered by the server and re-rendered by the Interactivity API. The domain has no WordPress in it; the catalogue, stock, prices, cache and clock come in through interfaces |
| Anti-patterns | No SQL outside repositories, no logic in hooks, no stock written by the plugin, no price taken from the browser, no unbounded queries, no work on the request that saves a product, no template overrides, nothing private of WooCommerce's |
| Security | Nonces and capabilities on every admin action; every add to cart validated on the server, slot by slot; share links carry IDs only and are re-validated; saved builds readable only by their owner; prepared SQL; input sanitised at the edge, output escaped late; the state route public, read-only and rate-limited; composed pictures named by hash; a pinned and audited supply chain |
| Privacy and data handling | A data inventory in `docs/PRIVACY.md`; personal data only in saved builds; an exporter and an eraser; no cookies of its own and no third-party requests; addresses kept only as salted hashes for the rate limit's minute |
| Accessibility | WCAG 2.2 AA: a stepped form after WAI's multi-page pattern; native radio buttons; a status region; an error summary; focus to each step's heading; distinct button names; 24 px targets; the phone bar never covering focus; a described picture; reduced motion honoured; the whole builder without JavaScript; every admin screen keyboard-complete |
| Internationalisation | Every string translatable with context; the domain returns codes, not English; no concatenated sentences; plurals through `_n()`; prices through WooCommerce's formatters; inches and millimetres per locale; logical CSS; French shipped and right to left tested |
| SEO | The clean builder page indexable and fast, with its options in the HTML; builder state reached by forms, its pages `noindex` and canonical to the clean page; the kit out of sitemaps, search and feeds, with no product markup; shared builds unfurl with their picture |
| Performance | The budgets below, measured in CI on a generated catalogue, and the page's Core Web Vitals in the lab |
| Testing | Unit tests for the domain; integration tests for everything that touches WordPress and WooCommerce; browser tests for the builder and all three carts; contract tests between the server and the script; visual snapshots; mutation testing to prove the tests bite. A bug fixed is a test added first |
| Upgrades and compatibility | Upgrading from every earlier version with no manual step; migrations and reindexes that recover when interrupted; a public API (hooks, REST, abilities, block attributes, settings and meta keys) that stays stable from 1.0 and is deprecated, never renamed; the platform matrix below |
| Resilience and error handling | The builder never takes its page down; it falls back to the form when the route fails; every use of a build re-checks parts that changed; jobs retry, and failures reach Site Health and the log |
| UX and content | The deep dive's patterns and Baymard's guidelines, checked screen by screen; plain copy with no em dashes or superlatives; motion that confirms picks and keeps the shopper oriented, and none under reduced motion |

**CI gates**

| Gate | Floor | How it's checked |
| --- | --- | --- |
| Static analysis | PHPStan 2 at level 9 with the WordPress and WooCommerce stubs, strict, deprecation and banned-call rules, and level 10 on `src/Domain`; zero baseline | `composer analyse` |
| Architecture | deptrac: the domain depends on nothing; WordPress only in adapters and repositories | `composer arch` |
| Coding standard | Every WordPress standard in full: WPCS (WordPress-Extra, WordPress-Docs, WordPress-VIP-Go, zero warnings) with PHPCompatibilityWP for 8.5; the JavaScript and CSS standards through @wordpress/eslint-plugin, @wordpress/stylelint-config and @wordpress/prettier-config; TypeScript strict with no errors; the inline documentation and accessibility coding standards; the block editor standards (block.json, @wordpress/scripts, the Interactivity API, no jQuery); the REST API handbook's conventions; the Plugin Handbook's security practices and the Plugin Directory guidelines; `declare(strict_types=1)` in every file | `composer lint`, `npm run check` |
| Unit tests | 100 % line and branch coverage of `src/Domain`; 95 % line coverage overall | PHPUnit 13: pcov for lines, Xdebug's path coverage for the domain's branches |
| Mutation testing | Infection MSI 100 % on `src/Domain`, 95 % on the rest; StrykerJS 90 % on the builder's store | `composer infection`, `npm run mutate:js` |
| Contracts | The public API matches `docs/HOOKS.md` and its committed snapshot; every state route answer validates against its schema, and the script's types are generated from it | `composer api:check`, the integration suite |
| Integration tests | Every hook, route, ability, admin action and WP-CLI command against real WordPress and WooCommerce in wp-env: the Cart and Checkout blocks and the classic checkout; HPOS on, off and syncing; multisite; a persistent object cache; MySQL 8.4 and MariaDB 10.11; the oldest and newest supported versions | PHPUnit 9.6 with the WordPress test suite |
| Upgrades | Upgrade from every tagged release; a migration and a reindex stopped halfway and run again, ending as an uninterrupted run would | The integration suite |
| Browser tests | The builder, the Cart block, the Mini-Cart and a classic checkout on Chromium, WebKit and Firefox, at phone and desktop widths, with and without JavaScript, in fr_CA and right to left; visual snapshots on three themes, dark and light; the state route forced to fail | Playwright |
| Accessibility | axe clean on the builder, every cart and every admin screen; keyboard-only, reflow, forced colours, reduced motion, target sizes and text spacing tested; one manual pass per release with VoiceOver on Safari and NVDA on Firefox, recorded in `docs/A11Y.md` | Playwright + axe, and the manual script |
| Performance | p95 server time for the state route under 80 ms with 500 candidate parts; add to cart of a 7-part build under 300 ms; the builder's script under 15 kB and its styles under 10 kB compressed, no framework; LCP 2.5 s or less, CLS under 0.1 and TBT under 200 ms on Lighthouse's mobile profile; a pick's own selected state painted within 100 ms on a 4× slowed CPU | Benchmarks, `npm run budget`, Lighthouse CI and Playwright, failing the build when a budget is missed |
| SEO | Robots meta, canonical, sitemaps, markup and share tags as SEO and sharing says | Playwright |
| Privacy | The exporter and eraser; no cookie of the plugin's own; no request to another host; no personal data in logs | The integration and browser suites |
| WordPress.org fitness | Plugin Check with zero errors or warnings; a valid readme; uninstall removes every table, option, product meta, scheduled action and composed picture the plugin made, and keeps the order records | CI |
| Dependencies | `composer audit` and `npm audit` clean; a secrets scan; every action pinned to a commit SHA; a CycloneDX SBOM and a SHA-256 with each release | CI |
| Documentation | Every public API item in `docs/HOOKS.md`, every setting in `docs/SETTINGS.md`, stored data in `docs/PRIVACY.md`, every decision in `docs/DECISIONS.md`, a changelog entry and a review per milestone | Reviewed at each milestone |
| Compatibility | No PHP deprecations on 8.5; no console errors with Kadence, Storefront and Twenty Twenty-Five; no conflict with the agency's other plugins or Yoast SEO; a weekly canary on WordPress trunk and WooCommerce's pre-release that opens an issue when red | The wp-env matrix and the canary |

## Vocabulary

Classes, tables, settings and the docs use these words, and no others, for these things.

| Word | Meaning |
| --- | --- |
| Kit | A product of the `cbuild_kit` type: the thing the shopper builds, such as a complete skateboard. It has slots, presets and assembly settings, and a price of its own: the assembly fee, often $0. |
| Slot | One place in a kit where a part goes: deck, trucks, wheels, risers, bearings, hardware, grip. Risers come before hardware, since bolt length follows riser thickness (R4). Each has a key, a label, an order, whether it's required, the products allowed in it, and how many units of a part one build takes. |
| Part | A product or variation that can fill a slot: one width of a deck, a pair of trucks in one size, a set of four wheels. |
| Option | A part as the builder shows it in a slot: name, photo, price, stock and fit. |
| Spec | One measured fact about a part that rules read: deck width 8.25 in, axle width 8.5 in, deck range 8.375 to 8.6 in, truck height 55 mm, wheel diameter 54 mm, riser thickness 0.25 in, bolt length 1 in, bearing bore 8 mm, grip width and length, deck length, hole pattern. Each spec has one unit, and specs are never parsed from a product's name while the shop runs. |
| Spec source | Where a spec's value comes from: a WooCommerce attribute (a deck's Width), a field on the part, or a spec table (a truck maker's size chart, which maps a size to its axle width and deck range). |
| Rule | A test between two slots' specs, with a severity: block (the pair can't be built) or warn (it can, with a note). Each rule has a message and a source. |
| Fit | What the rules say about one option, given the picks so far: fits, a warning with its message, or blocked with its reason. |
| Build | One set of picks for a kit: a part for each required slot, any optional ones, and assembly on or off. In the cart it becomes a parent line with a child line per part. |
| Preset | A ready build the owner sets up (up to six: street, park, cruiser; value and pro; a team rider's setup) that a shopper starts from and can change. |
| Assembly | The shop putting the build together, grip included: on or off per build, the default the owner's, with a fee and a lead time. |
| Build link | A URL that rebuilds a build from its picks, for sharing and for the cart's "Edit build" link. It holds ids only. |
| Saved build | A build kept in a signed-in customer's account, under My builds. |
| Assembly ticket | The printable sheet for the workshop: the order, the parts by slot with their specs, and any notes. |
| Picture | The board drawn as it's built: the chosen parts' layer images stacked to scale in the page, and the same layout as one composed image for carts, emails and share cards. A part with no layer image shows its labelled product photo instead. |
| Layer image | A part's photo shot to the agency's template (one camera height and lens, a fixed scale, nose up, a transparent background), used only by the picture. |
| Change list | What a changed earlier pick does to later ones: the picks kept, the picks that no longer fit with the closest part that does, and an undo. |
| Recommendation | The part a later slot starts on once the deck is picked: the kit's default when it fits and is in stock, otherwise the fitting, in-stock part nearest the middle of the slot's prices. Labelled, never hidden, never a deck. |

## How it fits

WooCommerce keeps doing what it does: products, prices, stock, the cart, the checkout, the order. The plugin adds a product type, a builder, a fit engine and the glue that keeps a build together in the cart and the order. It never writes stock or prices of its own.

- **The kit's page** renders the builder on the server: the steps, each slot's options with their fit for the picks so far (from a build link, a preset or nothing), and the total. A Builder block can also show a kit on any page, such as Skatehouse's existing /build-a-complete/ page.
- **As the shopper picks,** the builder's Interactivity API store calls `GET /wp-json/cbuild/v1/kits/{id}/state?picks=…`, debounced. The fit engine runs over the slot's candidate parts from the specs index and returns the options with their fit, how many were hidden and why, any change list, the picture's layers and the total, and the same template the server rendered re-renders from that answer. Without JavaScript, each step is a form that sends the picks in the URL, and the server renders the next state.
- **Adding to the cart** posts the build to the Store API's `cart/add-item` route with the build as item data, then sends WooCommerce's `wc-blocks_added_to_cart` event, so the Cart block and the Mini-Cart update as they would for any product. Without JavaScript it posts to WooCommerce's own add-to-cart handler. Either way the server validates the whole build (slots, rules, stock), adds the kit as the parent line and a child line per part.
- **In the cart,** children follow their parent: same build quantity, removed together, never changed alone. The Cart block shows them grouped through Store API data and its JavaScript filters, the Mini-Cart through each part's item data and a CSS class, and a classic cart or checkout (Shop Kit's) through WooCommerce's PHP filters.
- **At checkout and after,** each line becomes an order item carrying the build's keys. Emails, the account pages and the admin show the build grouped. WooCommerce reduces and restores stock part by part. The admin's order screen prints the assembly ticket.
- **Stock** is read from WooCommerce when the builder asks. (Since 0.4.0 the state route reads it from the parts table, a row no more than two hours old and deleted when WooCommerce says the part moved; the cart reads WooCommerce itself. D-116, for Zach.) Creative Stockroom already keeps a printed deck's stock right in WooCommerce, so the builder needs no Stockroom code; a filter lets any plugin rule a part out.
- **Specs** live in an index table, rebuilt in the background (Action Scheduler) whenever a part, its attributes or a spec table change.
- **The picture** is laid out by the domain from the parts' specs and drawn twice from that one layout: as layers in the page, and as one composed image, made in the background, for the cart, the emails and share cards.
- **Abilities.** The same engine answers WordPress's Abilities API (check a build, price a build, run the Catalogue check), so the shop's own tools and AI assistants get the answers the builder gives, under the same permissions.
- **The owner** sets up a kit on its product's Build tab, fills in specs on each part's Part specs panel (or imports them), keeps truck size charts under WooCommerce > Builder > Spec tables, and runs the Catalogue check to find parts with missing specs.
- **Skatehouse's own behaviour** (an extra rule, its copy) comes in through the plugin's hooks from Skatehouse Core 2.x, the site's own plugin since the 27 Oct split. The plugin knows skateboards, through its rules and spec tables, but nothing about any one shop.

### How a build travels

The drawing in the doc, as text. Steps 1 and 5 and the specs index are Creative Skateboard Builder's own work; the rest is WooCommerce, through the plugin's hooks.

1. **The builder.** A step per slot, on the kit's page or in the Builder block. Each pick asks the state route for every option's fit, which it reads from the **specs index** (a row per part and spec, rebuilt in the background).
2. **Add to cart.** Through the Store API, or WooCommerce's own form without JavaScript. The plugin checks the whole build again: slots, rules and stock.
3. **The cart.** The kit as a parent line with its fee, and a child line per part. Kept together on the server, and grouped in all three carts.
4. **Checkout and the order.** Each line becomes an order item carrying the build's keys, and the parent keeps a snapshot of the parts and their specs. **Stock** is reserved, reduced and restored part by part, by WooCommerce, line by line.
5. **The workshop.** The order's Build box prints the assembly ticket. Emails and My account show the build grouped.

The browser never decides fit or price. The server checks a build as the shopper picks, again when it's added to the cart, and again each time the cart loads.

## Data model

Three tables (two until 0.4.0, D-116), product meta on the kit and the parts, and keys on cart and order items. Everything the plugin stores is either the owner's settings or rebuildable from WooCommerce.

**The kit**, a product of type `cbuild_kit` (class `KitProduct extends WC_Product`). It is virtual (the parts ship), can't be bought outside the builder, and its price is the assembly fee, read from its assembly settings rather than WooCommerce's price fields and set to $0 by the cart when the shopper turns assembly off. Its settings are product meta, each a JSON document carrying a `schema_version` and validated against a JSON Schema in the plugin's `schema/` folder, which ships with it. Validation runs at the edge through WordPress's own `rest_validate_value_from_schema()`, with no library added, and the domain's value objects check their own invariants:

| Meta | Holds |
| --- | --- |
| `_cbuild_slots` | The slots in order: key, label, help text, required or optional, units per build, which products may fill it (categories, brands, products, attribute values), a default part |
| `_cbuild_rules` | Which rules run for this kit, and their settings (R1's tolerances, R3's riser suggestions, R6's length check). They apply when fit is checked, never when parts are indexed; what feeds a spec and which source wins are the shop's settings (D-026) |
| `_cbuild_presets` | Ready builds: name, a line of description, and a pick per slot |
| `_cbuild_assembly` | Default on or off, the fee, the lead time, and the returns notice shown before checkout |
| `_cbuild_picture` | The picture's settings (milestone 7): the slots' layer order, and any anchor the kit moves, such as where the trucks sit on a shape with no wheelbase |

**Part specs.** A shop manager fills them in on each part's Part specs panel (simple products and variations), or imports them; they're stored as `_cbuild_specs` meta, one value per spec key. Two other sources feed the same specs: an attribute mapping, set once for the shop at WooCommerce > Settings > Builder (the deck's `deck_width` comes from the `pa_width` attribute, in inches), and **spec tables**, a post type `cbuild_spec_table` holding a maker's size chart (Independent 149: axle 8.5 in, decks 8.375 to 8.6 in, height 55 mm, wheels up to 56 mm). A truck variation names its table and size, and inherits the rest. Which source wins where two disagree is a shop-wide setting as well, so the index holds one value per part and spec whichever kit reads it (D-026). From milestone 7 a part can also carry a **layer image** for the picture, an attachment ID in `_cbuild_layer`, set on the same panel.

**The specs index**, table `{prefix}cbuild_specs`: one row per part and spec, with the value as a number (`DECIMAL(10,4)`) or a short text, where it came from, and when. Keyed on the part and the spec, indexed on spec and value so a slot's candidates come back in one query. Derived: rebuilt in the background from the three sources, never edited.

**Saved builds**, table `{prefix}cbuild_builds`: id, a UUID, the customer's user id, the kit, the picks as JSON, assembly on or off, a name the customer gives it, created and updated times. Indexed on the user. Shared links aren't stored: the picks travel in the URL as ids and are validated again on every use.

**Parts as a build reads them**, table `{prefix}cbuild_parts` (added in milestone 4, D-116): one row per product or variation and price context, with its parent, whether it may be sold, its brand, its price in minor units, whether it is in stock and how many, a variation's attribute values and its specs as JSON, and when it was read as UTC seconds. Keyed on the part and the context, indexed on the parent and the context. Derived: a row is deleted when its part moves and read from WooCommerce again by the next request that needs it, and no row is believed for more than two hours.

**Cart items.** The parent carries `cbuild` (the build's UUID, the kit, the picks, assembly) and `cbuild_children` (the child cart keys). Each child carries `cbuild_parent` (the parent's cart key) and `cbuild_slot`. The UUID keeps two identical builds from merging into one line, which the Store API would otherwise do.

**Order items.** Hidden keys: `_cbuild_id`, `_cbuild_role` (kit or part), `_cbuild_parent`, `_cbuild_children`, `_cbuild_slot`, and on the parent `_cbuild_snapshot`, the picks with each part's specs at the time of purchase. Visible on the parent, for the customer and the workshop: Assembly (yes or no) and the build in a line.

**Options and jobs.** `cbuild_settings` for shop-wide defaults; `cbuild_db_version` and `cbuild_migrations` (each migration's state and cursor, so an interrupted one resumes); Action Scheduler actions `cbuild_index_part` (one part), `cbuild_reindex` (everything, 100 parts at a time, from a saved cursor), `cbuild_compose_picture` (one build's composed image) and `cbuild_prune_pictures` (daily: composed images unused for 30 days, and the folder kept under its size cap).

**Composed pictures** are files in `wp-content/uploads/creative-skateboard-builder/pictures/`, named by a hash of their layers, layout version and size, never by anything a visitor sends, and never in the Media Library. They show only products' public images.

## Compatibility rules

Eight numbered rules, each with its source: six fit tests between two slots (R1 to R6), a quantity check when the kit is saved (R7), and a note that isn't a test (R8). A fit test reads specs from two slots and returns fits, warn (the build can go ahead, with a note) or block (it can't). Where sources disagree, the maker's own figures win unless the shop's settings say otherwise (D-026), and the kit can loosen a rule with a tolerance.

**How rules run.**

- The engine checks every option in a slot against the picks already made, in step order.
- Each slot reports how many parts it hid and why, grouped by rule ("9 trucks hidden: too narrow for 8.5″").
- When an earlier pick changes so that later picks no longer fit, the engine returns a **change list**: the picks kept, each pick that no longer fits with the closest part that does (the nearest spec, then the same brand, then the nearest price, in stock), and an undo that restores the build as it was. Nothing changes until the shopper accepts (The builder). SkatePro removes the later pick and says why; Porsche's configurator asks first, which is what this does.
- After the deck, each later slot starts on its **recommendation**: the kit's default part when it fits and is in stock, otherwise the fitting, in-stock part nearest the middle of the slot's prices. The deck is never picked for the shopper.
- A part missing a spec that one of its rules requires isn't offered, and the Catalogue check lists it, so nothing is sold on a guess. Three specs aren't required: a deck with no hole pattern counts as new school (R2), an axle with no diameter counts as 8 mm (R5), and a deck with no length skips the length check (R6). The Catalogue check flags each so it can be filled in.

**R1. Deck width and truck size (block).**

- Reads: the deck's `deck_width`; the truck's `deck_min`, `deck_max` and `axle_width`, all in inches.
- It fits when `deck_min ≤ deck_width ≤ deck_max`, the maker's published range.
- For a truck with no published range: an axle within 0.25 in of the deck fits, within 0.5 in warns, and beyond that blocks.
- Axle widths are compared, never size numbers. Indy 149 and Thunder 149 are both 8.5 in, while Thunder 151 matches Indy 159.
- Tests: an 8.0 in deck with Indy 139 fits. An 8.5 in deck with Indy 144 is blocked by Independent's own range of 8.2 to 8.375 in, though one retailer gives 8.0 to 8.5.

Seed spec tables. Each maker's figures come from its own size chart, read on 10 November 2026: Independent's from its site, Thunder's and Venture's from the chart each publishes as images (D-093).

| Axle | Independent, with its deck range | Thunder, with its deck range | Venture, with its deck range |
| --- | --- | --- | --- |
| 6.9 in | 109: 6.25–7.6 |  |  |
| 7.125 in |  | 143: up to 7.4 |  |
| 7.6 in | 129: 7.4–7.8 |  |  |
| 7.62 in |  | 145: 7.4–7.9 | 5.0: up to 7.9 |
| 8.0 in | 139: 7.8–8.2 | 147: 7.9–8.12 | 5.2: 7.9–8.1 |
| 8.25 in | 144: 8.2–8.375 | 148: 8.12–8.38 | 5.6: 8.1–8.3 |
| 8.375 in | 146: 8.125–8.625 |  |  |
| 8.5 in | 149: 8.375–8.6 | 149: 8.38–8.62 | 5.8: 8.4–8.6 |
| 8.625 in | 151: 8.375–8.875 |  |  |
| 8.75 in | 159: 8.6–9.0 | 151: 8.62–8.85 | 6.1: 8.62 and up |
| 9.0 in | 166: 8.5–9.5 |  |  |
| 9.125 in | 169: 9.0–9.5 | 161: 8.9–9.5 |  |
| 10.0 in | 215: 9.5–10.5 | 181: 9.5 and up |  |

**R2. Mounting holes (block).**

- Reads: the deck's `hole_pattern` and the truck's `baseplate`, each new school, old school or both. New school is 2.125 × 1.625 in; old school is 2.5 × 1.625 in.
- It fits when the baseplate takes the deck's pattern. Six-hole baseplates (Indy 159 and 169 non-forged, and Indy 215) take both.
- A deck with no pattern given counts as new school, and the Catalogue check flags it.
- Tests: an old-school-only deck with Indy 144 is blocked; with Indy 159 Standard it fits.

**R3. Wheels, truck height and risers (warn).**

- Reads: the wheel's `wheel_diameter` (mm), the truck's `max_wheel` (mm) or height class, and the riser's `riser_thickness` (in).
- Each truck has a limit: the largest wheel it takes without risers. The maker's figure comes first: Indy Standard, 55 mm tall, takes wheels of 56 mm and under; Indy Mid, 52 mm tall, takes 53 mm and under.
- Without a maker's figure, the truck's height class gives the limit, from a kit setting seeded at low 53 mm, mid 56 mm and high 56 mm: the upper ends of the ranges the sources give (low 50 to 53 mm, mid 53 to 56 mm, high 56 mm and up). A truck not labelled high or low counts as high. The Catalogue check lists trucks using a class limit, so the maker's figure can be filled in. Milestone 2 checks the seeds against the sources below and records any change in `docs/DECISIONS.md`.
- A wheel over the limit warns and suggests a riser by how far over it is: 1 to 2 mm over, ¼ in; 3 to 6 mm over, ½ in. More than 6 mm over, the warning says no riser covers it and suggests taller trucks. The warning clears once a riser at least as thick as suggested is picked. The table is a kit setting, from Hammer's guide measured from the truck's limit (Hammer's own figures are for a 56 mm limit: none up to 56 mm, ¼ in for 57 to 58 mm, ½ in for 59 to 62 mm).
- Tests: Indy Standard with 56 mm wheels gives no warning. With 58 mm wheels and no riser, it warns and suggests ¼ in; with a ¼ in riser it fits. Indy Mid with 54 mm wheels warns and suggests ¼ in. Indy Standard with 63 mm wheels warns that no riser covers it.
- Sources put the largest wheel without risers at 54 mm (SkatePro), 56 mm (Hammer, Independent) or 57 mm (Switch).

**R4. Bolts and risers (block below, warn above).**

- Reads: the hardware's `bolt_length` and the riser's `riser_thickness`, both in inches, 0 with no riser.
- The shortest bolt allowed is 7/8 in plus the riser; the usual length is 1 in plus the riser.
- Shorter than the minimum blocks. More than ¼ in longer than the usual length warns that the bolts will stand proud.
- The published table: no riser, 7/8 to 1 in; ⅛ in, 1 to 1⅛ in; ¼ in, 1¼ in; ½ in, 1½ in.
- Tests: a ¼ in riser with 1 in bolts is blocked; with 1¼ in bolts it fits. No riser with 1½ in bolts warns.

**R5. Bearings (block).**

- Reads: the bearing's `bearing_bore` and the truck's `axle_diameter`, in mm; the axle is 8 mm unless a part says otherwise.
- It fits when they're equal. The standard 608 bearing has an 8 mm bore, 22 mm outside and 7 mm width; Bones also sells 7 mm-bore bearings, which are blocked on an 8 mm axle.
- Bearings with `spacers_built_in`, such as Bones Race Reds, block a separate spacers slot, where a kit has one.

**R6. Grip (block).**

- Reads: the grip's `grip_width` and `grip_length`; the deck's `deck_width` and `deck_length`, all in inches.
- It fits when the sheet is at least as wide and as long as the deck. 9 × 33 in is the usual sheet; 10 in sheets cover decks up to 10 in wide.
- Tests: a 9.25 in deck with a 9 in sheet is blocked; a 34 in deck with a 33 in sheet is blocked.

**R7. Quantities (checked when the kit is saved).**

- A complete takes 1 deck, 2 trucks, 4 matching wheels, 8 bearings, risers in pairs, one set of 8 bolts and nuts, and 1 grip sheet.
- Each slot says how many units a build takes, and each part says how many units it holds (`set_size`: trucks as a pair is 2, wheels 4, bearings 8, hardware 8, risers 2). A slot that takes one unit, as the deck and the grip do, takes one of any part and needs no `set_size` (D-064).
- The build adds as many of the part as it takes to cover the slot. Wheels can't be mixed, since a slot holds one part.

**R8. Wheelbase and deck length (no rule).** These change how a board turns, not whether parts fit. The deck's option shows them when they're known.

## The builder

One step per slot, in the kit's order, then a review. The deck comes first, every later step offers only what fits, and every step shows the picture and the running total. A pick never leaves the shopper guessing whether it fits. The research behind this section is `docs/UX-RESEARCH.md`; its patterns are numbered there, P1 to P12, and each milestone's review checks them.

**Ways in**

- The kit's page or the Builder block: blank, or from a preset.
- A deck's product page: a "Make it a complete" link opens the builder with that deck and width picked, the way CCS and Daddies do it. On Skatehouse, Skatehouse Core 2.x places the link.
- A build link or a saved build, rebuilt and checked again on arrival.
- No deck in mind: the deck step opens with "Start with a width", a size filter first, as Hammer and Eastern Boarder do.

**Presets.**

- Up to six tiles at the start (street, park, cruiser; value and pro; a team rider's setup), four to six once the shop's parts are in. Each shows its picture, its total and a line on who it's for.
- A preset fills every slot, and the shopper can change any part after (P3).
- A preset holding a sold-out part says so on its tile.

**A step**

- **Where the shopper is.** The heading says it: "Step 2 of 7: Trucks". A progress list above shows the steps as done, current or to come, and done steps link back with their picks kept.
- **Risers only when needed.** The risers step appears only when the wheels need risers, with "No risers" as a choice (P2).
- **History.** With JavaScript, each step change adds a history entry carrying the same URL the no-JavaScript form makes. The browser's Back and Forward move between steps with every pick kept, and a reload opens the same step. Baymard found [59% of sites break at least one thing shoppers expect of Back](https://baymard.com/research-articles/back-button-expectations) (2020).
- **Cards.** Each option is a card with a native radio button and:
  - a photo and the name;
  - the price as a change from the current pick ("+$12.00", "Same price" or "Included", P4);
  - stock;
  - a fit label ("Fits your 8.25″ deck").

  Each card's control has a name of its own ("Choose Independent 149 trucks"), never one of seven buttons called "Add" (P11).
- **Recommendations.** After the deck, each later slot starts on its recommendation, labelled "Recommended for your 8.25″ deck", and the total counts it from the start (P9). Nothing is picked that the shopper can't see and change, and the deck is never picked for them.
- **Hidden parts.** Options that don't fit stay out of the main list but not out of sight: "9 trucks hidden: too narrow for 8.5″" opens to show them with their reasons. Nothing vanishes silently (P1).
- **Filters.** Long lists get filters (brand, size) above them, and in a drawer on phones.
- **Sold out.** Sold-out parts show after those in stock and can't be picked; each links to its page's Notify me. A preset or link that holds one names it, and the build can't go to the cart until it's swapped (as SkatePro does).

**A change that breaks later picks.** A wider deck or bigger wheels can leave later picks that no longer fit. The builder never changes them silently (P8):

- A panel names what's kept, what no longer fits, and the closest part that does, with "Use these" and "Undo".
- Without JavaScript, the same panel is a step of its own, with both as buttons.
- Focus moves to the panel's heading, and the status region says how many parts changed.

**The total**

- Assembly is in the total from the first step ("Assembly: free", or its fee), so no cost appears late (P5).
- A summary bar shows the total, the parts so far and any build discount.
  - On desktop it sits beside the steps.
  - On phones it's a bar at the bottom: a small picture of the board, the total, and the next step on its button ("Next: Wheels"). Tapping the total opens the parts list above the bar, and tapping the picture shows it full size, so neither scrolls out of reach (W7).
  - It never covers the control in focus.
- Under the total, one line covers delivery:
  - the shipping to the shopper's province from WooCommerce's shipping zones, or how far the build is from free shipping, in the words of Creative Checkout's cart meter where it's on ("Spend $41.00 more for free shipping to Ontario.");
  - an arrival date worked out from the shop's cut-off, the assembly lead time and the transit days ("Arrives by Thu, Nov 19, assembled").
- The date comes from a filter the shop answers, `cbuild_arrival_estimate`. On Skatehouse, Creative Checkout answers it from its cut-off, holidays and each zone's business days (What changed since 22 Oct). With no answer, the line shows the shipping alone.
- The shipping words come through a second filter, `cbuild_delivery_line`, which Creative Checkout answers from its cart meter, so the builder and the cart drawer never disagree. With no answer, the builder words the line itself from WooCommerce's shipping zones.
- Baymard found that [40% of shoppers who abandon a cart cite extra costs](https://baymard.com/lists/cart-abandonment-rate), and [48% of checkouts give no delivery date](https://baymard.com/research-articles/current-state-of-checkout-ux) (2025).
- Other plugins add lines under the total through `cbuild_summary_notes`. Creative Loyalty can show the points a build earns. A signed-in dealer is told the builder shows retail prices (open question 7).

**The picture**

The board as it's built (P6). It heads every step and the review, and is redrawn with each pick.

- **Layered, from milestone 7.** Each part that has a layer image is drawn to scale:
  - the deck, scaled by its width and length;
  - the trucks, placed by its wheelbase, or by the shape's usual one;
  - then the wheels;
  - the grip, as a strip along the edge.
- **One layout, drawn twice.** The domain lays the board out from the specs. The page stacks the layers as images. A composed image of the same layout, made in the background, serves the cart, the emails and share cards.
- **Where a part has no layer image yet,** the picture shows its product photo instead, labelled with its slot, brand and size ("Wheels: Retna, 54 mm"). This is the "What's in the box" layout: Baymard found [63% of test users missed bundled items when there was no picture of them](https://baymard.com/blog/included-accessories-image) (2019).
- **Who ships what.** Milestone 6 ships the labelled layout, and milestone 7 adds the layers. Skatehouse's deck cut-outs come from today's photos. Trucks, wheels, risers and grip are shot to the template in `docs/PICTURE.md` as they arrive.
- **A written description.** The board has one written description in the page's language ("An 8.25″ Rascals deck with Independent 149 trucks, 54 mm Retna wheels and black grip"). It changes with the build, and the status region announces the change once.
- **The largest paint.** The deck layer is the page's largest paint: an explicit size, `fetchpriority="high"`, and AVIF or WebP at the size shown. A pick swaps one layer in place.
- **Ready for 3D.** Both renderers sit behind one interface, so a 3D view that loads on a tap can be added as a module after 1.0 (`docs/ROADMAP.md`).

**Review and add to cart**

- The review lists the build by slot, each part with a fit tick and a Change link (P7).
- It also shows assembly on or off with its fee and lead time, the arrival date, the total, and the returns notice the owner writes, all before Add to cart.
- The build then goes into the cart as one line with its parts under it (P10).
- Copy link makes a build link, shareable without an account (P12). Save build keeps it under My builds for a signed-in customer.
- After adding, the cart line carries an "Edit build" link that reopens the builder. Saving there replaces the build in the cart.

**Motion.** Motion confirms a pick and keeps the shopper oriented:

- the chosen card settles;
- the part slides into place in the picture;
- steps cross over with a view transition where the browser has one.

Each animation lasts 150 to 250 ms, eases out, and moves only `transform` and `opacity`. None blocks a tap or loops. Under reduced motion, nothing moves.

**Keyboard and screen readers**, after WAI's multi-page form pattern:

- Native radio buttons in a fieldset per slot, so the arrow keys move between options.
- Focus moves to the new step's heading on each change. The page title and the heading both say the step.
- Fit changes, the picture's description and the total are announced through a polite status region that's in the page from the start, once per pick.
- A blocking problem on Next or Add to cart shows an error summary that takes focus and links to the slot, and the page title gets "Error:" first.
- 24 px targets, a visible focus ring everywhere, and no motion when the shopper asks for reduced motion.

**Without JavaScript**

- Each step is a form whose picks travel in the URL (a GET), so the server renders the next step and every state can be bookmarked.
- The change panel and the review are server-rendered steps too.
- Add to cart posts to WooCommerce's own add-to-cart handler, which takes no nonce, so a cached page never goes stale (see Security).

**Speed**

- The first step and the picture are rendered on the server.
- The state route is called once per pick, 150 ms after the last one.
- A pick paints its own selected state at once; the total follows when the answer comes.
- Photos load lazily at the sizes the cards show, except the picture's first layers.

## Cart, checkout and orders

A build is a parent line (the kit) with a child line per part, the model Woo's own Product Bundles and Composite Products use ([Bundles data](https://woocommerce.com/document/bundles/bundles-data-structures-storage/), [Composite data](https://woocommerce.com/document/composite-products/composite-products-data-structures-storage/)). Child lines are what let WooCommerce reserve, reduce and restore stock, apply each part's tax class, and refund part by part. A build kept as meta on one line would skip all of that.

**Adding a build.** The same three steps whichever way it arrives, the Store API or the classic form:

1. **Validate** the whole build in `woocommerce_add_to_cart_validation`, which the Store API also runs ([migrated hooks](https://developer.woocommerce.com/docs/block-development/reference/hooks/migrated-hooks/)), and in `woocommerce_store_api_validate_add_to_cart` for a first add through the Store API ([advisory](https://developer.woocommerce.com/2026/09/24/new-cart-validation-filter/)). That covers slots, rules and stock, counting each part across every line already in the cart.
2. **Attach the build** in `woocommerce_add_cart_item_data`, which runs in `WC_Cart::add_to_cart()` and in the Store API's cart controller alike ([source](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_add_cart_item_data)), with the build's UUID so two identical builds don't merge.
3. **Add the children** on `woocommerce_add_to_cart`, which the Store API also fires ([source](https://wp-kama.com/plugin/woocommerce/function/CartController::add_to_cart)).

**Keeping it together.** The server enforces this; the page only reflects it.

- The parent's quantity sets the children's, through `woocommerce_cart_item_set_quantity`.
- Removing the parent removes the children (`woocommerce_remove_cart_item` fires before the line goes). Removing a child by any route removes the whole build, guarded so the two don't call each other forever.
- Children's quantities are locked in the Store API by `woocommerce_store_api_product_quantity_editable` returning false ([source](https://wp-kama.com/plugin/woocommerce/function/QuantityLimits)). The parent's maximum is what the scarcest part allows.
- A change that would break that is refused with a `WP_Error` from `woocommerce_store_api_cart_item_quantity_validation`: a child changed alone, or a parent raised past a part's stock.
- `woocommerce_check_cart_items` drops orphaned children and re-checks every build, since stock or specs may have changed since it was added.

**Showing it**

| Where | How |
| --- | --- |
| Cart block | Each item gets its role, build id, parent key and slot through `woocommerce_store_api_register_endpoint_data()` on the cart item schema ([docs](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/)). Then `registerCheckoutFilters` indents children (`cartItemClass`), labels them ("Deck: …", `itemName`) and hides their remove links (`showRemoveItemLink`) ([filters](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/filters-in-cart-and-checkout/cart-line-items/)) |
| Mini-Cart | WooCommerce's Interactivity API Mini-Cart, the default since 10.4 ([announcement](https://developer.woocommerce.com/2025/12/10/woocommerce-10-4-the-interactivity-api-mini-cart-goes-live/)) and the only one in 11.2. On the demo (22 October 2026) it refreshed from the Store API and opened its drawer on `wc-blocks_added_to_cart`, which its markup listens for on the document, so the builder sends that event after each add. It hides and locks a line's quantity when `quantity_limits.editable` is false, and it prints each line's `item_data`: the plugin puts the part's slot and build there through `woocommerce_get_item_data`, with a `cbuild-part` class that CSS uses to indent the line (`.wc-block-cart-items__row:has(.cbuild-part)`); milestone 5 checks the class survives in all three carts. The line filters above reach it only where `wc-blocks-checkout` is loaded (see Compatibility), so a part keeps its Remove link there, and removing it removes the whole build. |
| Classic cart and Shop Kit's checkout | `woocommerce_cart_item_name`, `woocommerce_cart_item_class`, `woocommerce_cart_item_quantity` and `woocommerce_cart_item_remove_link`. None of these run in the blocks ([hook alternatives](https://developer.woocommerce.com/docs/block-development/reference/hooks/hook-alternatives/)), so both sets are needed |

**The kit's thumbnail.** In every cart, the kit's line shows the build's composed picture once it's made, through the Store API's cart item images and `woocommerce_cart_item_thumbnail` (both confirmed in the installed source in milestone 7), and the deck's photo until then. Every part sits under it as its own line with its own photo.

**Orders**

- `woocommerce_checkout_create_order_line_item` copies the build's keys onto each order item; the Store API's checkout calls the same method ([source](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_checkout_create_order_line_item)). Once the order is saved, the cart keys are turned into order item ids.
- `woocommerce_order_item_name` labels the parts in emails, the account pages, the admin's order rows and the order confirmation ([source](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_order_item_name)).
- Underscore keys are hidden from customers. The parent shows Assembly and the build in a line, for the customer and the workshop. Since WooCommerce 11.0, hidden keys can't be added through the admin's Add meta form ([advisory](https://developer.woocommerce.com/2026/08/18/reserved-order-item-meta-admin/)).
- Adding a build to an order by hand in the admin isn't in 1.0. Changing a build's lines in the admin shows a warning.
- HPOS: CRUD methods only, and the plugin declares compatibility with `custom_order_tables` and `cart_checkout_blocks` ([recipe](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/)). Where the installed WooCommerce still has the `product_block_editor` feature, the plugin declares itself not compatible, since the kit's settings live in the classic product screen. WooCommerce retired that editor's beta in 2026, so milestone 1 checks the source and records what it found.
- The picture goes with the build: the composed image in emails, with its description as the alt text, and the layers in the order's Build box and on the assembly ticket.

## Pricing, stock, assembly and returns

**Pricing**

- Each part is charged at its own live price, so sales flow through, and the parent line carries the assembly fee or $0. This is the model Composite Products uses for its skateboard ([kit builder](https://woocommerce.com/document/composite-products/composite-products-use-case-kit-builder/)).
- Every price is worked out on the server, and nothing the browser sends is trusted. Every part needs a price, since WooCommerce won't sell a product without one ([Bundles FAQ](https://woocommerce.com/document/bundles/bundles-faq/)).
- An optional **build discount** (the owner's call, off by default) takes a percentage off the parts' lines in `woocommerce_before_calculate_totals`. The cart shows each part's own price struck through.
- Coupons follow the parent to its parts through `woocommerce_coupon_is_valid_for_product` ([source](https://wp-kama.com/plugin/woocommerce/function/WC_Coupon::is_valid_for_product)). A fixed-product coupon would apply to every part ([coupons](https://woocommerce.com/document/coupon-management/)), so by default it applies to the kit line only; that's a setting.
- Revenue sits on the parts; the parent is credited only its fee. Reporting tools that count a bundle's parts and the bundle twice ([Metorik](https://help.metorik.com/article/77-product-bundles-support)) can read the role on each line to tell them apart.
- At most one line is $0: the parent, when assembly is free. Bundles warns that $0 lines trip some carriers, Canada Post among them ([compatibility](https://woocommerce.com/document/bundles/bundles-compatibility-and-integrations/)), so the integration tests run the shop's own shipping methods.

**Stock**

- The plugin never writes stock. Reservation, reduction, restoring stock on a failed or cancelled order ([advisory](https://developer.woocommerce.com/2026/07/17/failed-order-stock-update/)) and restocking on a refund all run per line, in WooCommerce ([reduce](https://hooks.wbcomdesigns.com/?p=33660), [reserve](https://wp-kama.com/plugin/woocommerce/function/ReserveStock::reserve_stock_for_order), [restock](https://wp-kama.com/plugin/woocommerce/function/wc_restock_refunded_items)).
- What the builder offers is worked out when it's asked, never synced. Imports and bulk edits that skip WooCommerce's functions can't leave it stale. (Since 0.4.0, D-116: the state route's options come from the parts table, so a change that fires no WooCommerce hook shows within two hours, or at once after `cbuild_parts_changed`. What goes to the cart is read from WooCommerce. For Zach.)
- Creative Stockroom keeps a printed deck's stock right in WooCommerce, so it needs nothing from the builder. The `cbuild_part_available` filter lets any plugin rule a part out.
- If a part sells out between the builder and the checkout, WooCommerce's own checks stop the order, and the cart line's Edit build link takes the shopper back to swap it.

**Assembly**

- Assembly is on or off for each build, with the owner choosing the default. Skatehouse's proposal says free, by the team.
- The fee sits on the parent line. A lead time, in the owner's words, shows in the builder and in the order email.
- Grip is applied as part of assembly.
- The **assembly ticket** prints from the order screen with a print stylesheet, no PDF. It lists the order number, the customer, each slot's part with the specs that matter (deck width, truck size, wheel size, riser, bolt length), any notes, and a tick box per step.

**Shipping.** The parent is virtual, and the parts carry their own weights. A filter on `woocommerce_cart_shipping_packages` can keep a build in one package for shops that split packages.

**Returns**

- Whether an assembled build can be returned is the owner's call. Warehouse Skateboards, Skate Warehouse and SkatePro say no. The owner's returns notice shows on the review step and in the order email.
- Refunds work line by line with Restock refunded items ([refunds](https://woocommerce.com/document/woocommerce-refunds/)). That box covers a whole refund, so restocking unused parts without the grip already applied takes two refunds. The admin's help text says so.

## Admin

Everything sits in WooCommerce's own screens, built from WordPress's admin markup, so it looks and works like the rest of wp-admin. Every action checks a capability and a nonce, every screen works from the keyboard and passes axe, and nothing is saved until it's validated.

| Capability | Can |
| --- | --- |
| `manage_woocommerce` (administrators and shop managers) | Set up kits, spec tables and settings; import specs; run the Catalogue check |
| `edit_products` | Fill in part specs |
| `edit_shop_orders` | See a build on an order; print assembly tickets |

**The kit's Build tab.** A product data tab on kit products (`woocommerce_product_data_tabs`, `woocommerce_product_data_panels`), saved in `woocommerce_admin_process_product_object` so the settings go through the product's own CRUD.

- **Slots:** a row each, with its label, help text, required or optional, units per build, what may fill it (categories, brands, products, attribute values, picked with WooCommerce's own search fields) and a default part. A slot's key is made from its label once and never changes, since builds and orders store it. Rows move with Move up and Move down buttons as well as by dragging, as WCAG 2.2 asks (2.5.7).
- **Rules:** R1 to R8, each with a switch and its settings: R1's tolerances, R3's riser suggestions and R6's length check. The attribute mapping and which source wins when two disagree are the shop's, under Settings (D-026).
- **Presets:** a name, a line of description and a pick per slot, saved only when the rules accept them.
- **Assembly:** on or off by default, the fee, the lead time and the returns notice.
- **Saving:** each setting is checked against its schema. One that fails isn't saved, the rest of the product saves as usual, and the notice after saving lists each problem with a link to its field.

**Part specs.** A Part specs panel on simple products, and inside each variation (`woocommerce_product_after_variable_attributes`, saved in `woocommerce_admin_process_variation_object`).

- It shows only the specs the product's kits need: a deck's width, length and hole pattern; a truck's size chart and size, or its own figures.
- Each field shows its unit and takes the shop's decimal separator (read with `wc_format_decimal()`) or a fraction, so 8.25, 8,25 and 8 1/4 are the same.
- A variation left blank takes its parent's value. A value from an attribute or a spec table shows read-only with where it comes from: "From the Width attribute", "From Independent's chart, size 149".
- Saving queues `cbuild_index_part` for the part, so the builder sees the change within moments without slowing the save.
- Both save hooks fire just before WooCommerce writes the object, so the specs go in with the same save (checked in 11.2.1): `woocommerce_admin_process_product_object` gets the product, and `woocommerce_admin_process_variation_object` the variation and its row. WooCommerce's variation save checks no nonce of its own, so the plugin checks `edit_products` before reading its fields.

**WooCommerce > Builder**

- **Spec tables** (`cbuild_spec_table`): a maker's size chart, a row per size with its axle width, deck range, height, largest wheel without risers and baseplate holes. Independent, Thunder and Venture are created by a migration from the table under R1, each row with its source and the date it was read, so an upgraded site gets them as a new one does. Changing a table re-indexes every part that names it.
- **Catalogue check:** for each kit, the parts that can fill a slot but lack a spec one of their rules needs (and so aren't offered), parts whose sources disagree (the Width attribute says 8.25 in, the field 8.5 in), presets that no longer fit or hold a sold-out part, and slots with nothing in stock. Each row links to the field that fixes it. It runs when asked, in the admin or as `wp cbuild check`, which exits with an error for CI or a monitor.
- **Import and export:** specs as CSV, a row per part, keyed on the product or variation ID, or on the SKU where a shop uses them (Skatehouse does since 6 Nov). An import first shows what it would change and any rows it can't read, and writes nothing until confirmed. The same runs as `wp cbuild specs import <file> --dry-run` and `wp cbuild specs export`.
- **Settings**, at WooCommerce > Settings > Builder through WooCommerce's settings API: defaults for new kits, the attribute mapping (which attribute feeds which spec, in which unit), which source wins where two disagree (the maker's chart, the field or the attribute; the chart by default), the build discount, how coupons reach the parts, and the state route's rate limit.

**The picture's images**, from milestone 7.

- A Layer image field on each part's Part specs panel (simple products and variations) takes a transparent PNG or WebP from the Media Library, previews it on the template at the part's size, and checks its transparency and size.
- On the kit's Build tab, a Picture section sets the slots' layer order and any anchor the kit needs to move.
- The Catalogue check lists the parts without a layer image, so the owner sees what the picture still lacks.

**On orders**

- A **Build box** on the order screen, registered for `wc_get_page_screen_id( 'shop-order' )` so it shows with HPOS on or off ([recipe](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/)). It lists the build by slot with each part's specs as sold (from `_cbuild_snapshot`) and a Print assembly ticket button.
- If the order's lines no longer match the build (a part removed or a quantity changed in the admin, which can also drop the build's meta, as [Bundles warns](https://woocommerce.com/document/bundles/bundles-faq/)), the box says so before anyone prints a ticket.
- The orders list gets an Assembly column on both the HPOS and the older list, and a bulk action prints the tickets for every order picked, one build to a page.

## French and other languages

Skatehouse's French version is built and switched off (owner question 10), so the builder ships ready for French and works in any language a shop runs.

- **Strings.** Every string goes through `__()`, `_x()` where a word needs context ("Deck" the slot, "deck" in a sentence) and `_n()` for anything counted. Sentences are never built from pieces: `sprintf()` takes numbered placeholders and a translator comment, so a translator can reorder them (`Step %1$d of %2$d: %3$s`).
- **The domain speaks in codes.** Rules, the change list and the Catalogue check return message codes and values, never English; the words are added at the edge, in the page's language.
- **The browser gets its words from the server.** The builder's script module reads its strings from the page's markup and from `wp_interactivity_state()`, and fit messages arrive from the state route already translated, so the script carries no strings of its own.
- **The state route answers in the page's language.** The builder sends the page's locale, and the route switches to it only when it's a language the site has installed.
- **Numbers and units.** Measurements go through `number_format_i18n()` and a translatable unit pattern, `_x( '%s in', 'inches', 'creative-skateboard-builder' )`, which French makes "%s po". Each spec says how it's written, the way skaters say it: deck widths as decimals (8.25 in, 8,25 po), bolts and risers as fractions (1¼ in). Prices go through `wc_price()` with the shop's own separators.
- **The owner's own words** (slot labels, help text, preset names, the lead time and the returns notice) are content, not code. Each passes through a filter, `cbuild_slot_label`, `cbuild_preset_label` or `cbuild_kit_text`, that a translation plugin or Skatehouse French can answer.
- **Orders and emails.** The order stores keys and numbers, and the labels on its lines are drawn when shown (through `woocommerce_order_item_get_formatted_meta_data`), so an email reads in the language WooCommerce sends it in and the order screen in the admin's own.
- **Loading.** The text domain loads on `init` from the plugin's `languages/` folder, since WordPress 6.7 warns about translations loaded earlier. French ships as `creative-skateboard-builder-fr_CA`: the .po, the .mo and the .l10n.php file WordPress reads fastest.
- **Layout.** Logical CSS properties throughout, so a right-to-left language mirrors without extra CSS. French runs longer than English, so the cards, the progress list and the total bar are tested in French at phone width. The picture doesn't mirror (a board is the same board either way), but its labels do.
- **Checks.** `wp i18n make-pot` runs in CI, and WPCS's I18n sniffs fail a string without the text domain or a placeholder without a translator comment. The browser suite runs once in fr\_CA and once right to left.

## SEO and sharing

The builder page can bring the shop search traffic of its own ("build a complete skateboard"), and the builder can make a near-endless number of URLs. Both are handled.

- **The clean page is indexable and fast.**
  - Skatehouse's /build-a-complete/ answers 200 with its own canonical.
  - It has one H1, an intro, and the first step's options in the HTML, with names, prices and links to each part's own page.
  - Its Core Web Vitals are budgets (Quality bar).
- **Builder state is reached by forms, not links.** Picks, filters, Show more and presets are buttons in GET forms, so crawlers that don't submit forms never enter the builder's states.
  - Every state travels in the `cbuild[…]` query parameters, written in slot order, so one build has one URL.
  - A page carrying them gets `noindex, follow` through `wp_robots` and a canonical link to the clean page. The step and Change links on it carry `rel="nofollow"`.
  - That covers build links that arrive from outside. Google's advice for faceted URLs, a `Disallow` line in robots.txt ([faceted navigation](https://developers.google.com/crawling/docs/faceted-navigation)), isn't used: it would also stop the crawlers that respect it, X's among them, from showing a shared build's card.
- **Answers are honest.** A build link naming parts that are gone still opens the builder, says which part is gone, and offers the closest fit, under `noindex`.
- **The kit stays out of search.** It's kept out of:
  - the catalogue, search results and feeds;
  - the sitemaps: WordPress's own (through `wp_sitemaps_posts_query_args`) and Yoast's.

  It prints no product markup, since its own price is only the assembly fee.
- **Shared builds unfurl as themselves.**
  - A build link's page carries Open Graph and X card tags with the build's composed picture, its name ("An 8.25″ complete: Rascals, Independent 149, Retna 54 mm") and its total.
  - `og:url` is the build link, so the card shows that build, while `rel=canonical` stays the clean page, so search engines index one page.
  - With Yoast or another SEO plugin active, the tags go through its filters. With none, the plugin prints them itself.
- Structured data for the builder page itself (a price range from the presets) is an idea for after 1.0, once Google's rules for configurable products are checked.

## Privacy and data handling

The builder needs almost nothing personal, and keeps it that way. `docs/PRIVACY.md` lists every piece of data the plugin stores, why, for how long, who sees it, and how it's exported and erased. A pull request that stores something new updates it.

- **Personal data in 1.0 is saved builds only:** the customer's user ID, the picks, the name they give the build, and when it was saved.
  - WordPress's privacy tools cover them, with a personal data exporter and eraser.
  - Deleting an account deletes its saved builds.
- **Orders** keep the build's keys and a snapshot of the parts and their specs, which name no one. The order's own personal data stays WooCommerce's.
- **The rate limit** keeps a salted hash of each address for its one-minute window, never the address itself.
- **Share links** carry part IDs, the step and assembly on or off, never a customer, an order or a saved build's ID.
- **No cookies of its own, and nothing sent anywhere.**
  - The builder keeps its state in the URL and the page.
  - It sends no request to another host and loads nothing from one.
  - It fires DOM events (`cbuild:pick`, `cbuild:added`) that a shop's own analytics can listen to, under the shop's own consent.
- **Logs** hold part, kit and order IDs, never names, emails or addresses.
- **Policy text.** The plugin suggests a paragraph for the shop's privacy policy through `wp_add_privacy_policy_content()`, in English and French.
- **Privacy by default,** as Quebec's Law 25 asks and PIPEDA's principles expect: nothing that identifies or profiles a shopper is on unless the shop turns it on.
- **After 1.0,** the graphic module adds uploaded artwork. It stays private and out of the Media Library, only staff see it, and it's deleted on the retention the owner sets (`docs/UX-RESEARCH.md`).

## Security, performance and compatibility

**Security.** `docs/SECURITY.md` is walked through before each tag.

- **The state route** is public and read-only: a GET with an explicit `permission_callback`, a schema for its arguments (the kit's slot keys to integer IDs, no more picks than the kit has slots), and a rate limit per address, 120 a minute by default, answered with 429 and `Retry-After`. The address is `REMOTE_ADDR` unless the shop names a trusted proxy header in settings, and it's kept only as a salted hash for its minute. It sends `Cache-Control: no-store`, since stock moves.
- **It shows only what the shop shows:** published, visible, purchasable parts; prices as that shopper would see them; stock in the shop's own stock display format, so a shop that hides its counts doesn't leak them.
- **Nothing from the browser is trusted.** A build link, a preset or a form carries IDs only. On every use each is checked (it exists, it's published, it may fill that slot, it's in stock, the rules accept it), and prices are always the server's.
- **Adding to the cart** goes through WooCommerce. The Store API needs its own nonce, which the builder takes from the Store API's response, never from the page. Without JavaScript the form posts to WooCommerce's add-to-cart handler, which takes no nonce, like WooCommerce's own buttons: a nonce in a page served from a cache goes stale within a day.
- **Saved builds** are read and changed only by the customer who owns them, checked on every request, and every change carries a nonce. Signed-in pages aren't served from a page cache, so those nonces stay fresh.
- **Admin:** a capability and a nonce on every action (the table under Admin); input sanitised where it arrives and output escaped where it's printed; SQL prepared, and only in repositories; a cell that starts with =, +, - or @ is neutralised when specs are exported, so a spreadsheet can't run it.
- **Generated files.** Composed pictures are made only for builds that pass validation, from a capped queue, and named by a hash of their layers and size, never by anything a visitor sends.
- **Supply chain and updates.** Lock files committed, `composer audit` and `npm audit` clean, a secrets scan, every GitHub Action pinned to a commit SHA, and a CycloneDX SBOM with each release. The updater reads only the agency's release manifest, over HTTPS, and checks the zip's SHA-256 before installing it.
- **Switched off safely.** The kit keeps its fee in `_cbuild_assembly`, never in WooCommerce's price fields. With the plugin switched off, WooCommerce treats a kit as a simple product with no price, which it won't sell, and the parts stay ordinary products. Orders keep every line, so their records still read correctly; only the grouping and the ticket go.
- **Uninstall** removes the tables (saved builds with them), options, scheduled actions, product meta, spec tables and composed pictures, and keeps order item meta, which is part of the shop's order records.

**Performance.** The budgets are under Quality bar.

- The fit engine reads only the specs index, so deciding fit never loads a product.
- Card data (name, photo, price, stock) is loaded only for the options a step shows, with their posts and meta primed in one query each. It's cached per part in the object cache, keyed by the part's last change and a price context the `cbuild_price_context` filter can extend (Phase 4's dealer prices), and WooCommerce's product, variation and stock hooks clear it.
- Prices come from the functions the cart uses (`wc_get_price_to_display()`), never from WooCommerce's lookup table, so a sale or a dealer price is the same in the builder as in the cart.
- A slot shows 24 options at a time, with Show more (a button in the step's form without JavaScript).
- Nothing loads on pages without a builder: the block's script module and styles are declared in `block.json` and load only where it renders, and a kit's page enqueues them only for its own form.
- The specs index is rebuilt by Action Scheduler, 100 parts at a time, never during the request that saves a product.
- The budgets hold without a persistent object cache, and run faster with one.
- The picture's deck layer is the page's largest paint, sized and prioritised. Composed pictures are made in Action Scheduler, so no page waits on one.
- A pick paints its own selected state before the state route answers, so the interaction stays within its budget on a slow phone.

**Compatibility**

- **Where the builder renders.** On a kit's page it takes the place of the add-to-cart form, through `woocommerce_cbuild_kit_add_to_cart`, the action WooCommerce calls for each product type's form. That covers classic themes, Shop Kit's product templates and block themes' Add to Cart form block. The browser tests run Storefront and Twenty Twenty-Five; Shop Kit can't run in CI, so Claude in Cowork checks its templates and checkout with a build in them. The Builder block puts a kit on any page.
- **Shop Kit's checkout** replaces WooCommerce's checkout templates and lets other plugins override only those it lists (`flux_allowed_template_overrides`), so the builder changes the checkout through WooCommerce's filters only, never templates. Its order summary is checked with a build in it, by Claude in Cowork.
- **The Mini-Cart**, tested on the demo on 22 October 2026 (WooCommerce 11.2.1). After an add through the Store API it showed nothing until WooCommerce's documented `wc-blocks_added_to_cart` event was sent on `document.body`; then it fetched the cart once, showed the new count and opened its drawer. jQuery's `added_to_cart` works too, since the Mini-Cart bridges it to the same event. WooCommerce's own Interactivity API cart store is private (locked with WooCommerce's "private store" warning), and an open pull request renames its actions, so the builder neither calls it nor sends its internal `wc-blocks_store_sync_required` event.
- **Cart line filters in the Mini-Cart.** It applies `itemName`, `cartItemClass` and `showRemoveItemLink` only when WooCommerce's `wc-blocks-checkout` script is on the page, and on Skatehouse's home page it isn't. Loading it brings React and WooCommerce's block data scripts with it, about 140 kB compressed on the demo's cart page, so 1.0 doesn't load it for the Mini-Cart (open question 6); item data and CSS group a build there instead.
- **Express payment buttons** (Apple Pay and Google Pay through WooPayments or Stripe) can add a product to the cart around the builder. They're hidden on kit pages where those plugins allow it, and validation refuses a kit that arrives without a build either way. On Skatehouse they're Stripe's since 30 Oct, on the cart and checkout only.
- **The agency's plugins.** Creative Stockroom needs nothing (Stock). The kit stays out of Creative Search's results, as it stays out of the catalogue, and the Build a complete page is suggested by its title; a slot's own filters don't call Search. Skatehouse Core 2.x brings Skatehouse's rule settings, copy and the "Make it a complete" link through the builder's hooks. Creative Checkout, Mature Content, Creative Shipment Tracking and Creative Reviews meet the builder as What changed since 22 Oct says.
- **Everything else** is in the Quality bar's matrix: PHP 8.5; Kadence, Storefront and Twenty Twenty-Five; page caches (the kit's page caches, the state route doesn't); a persistent object cache; multisite, activated per site; Yoast SEO; and the oldest and newest supported WordPress and WooCommerce.

**Upgrades and the public API**

- **What's public.** Each of these is listed in `docs/HOOKS.md` with the version it arrived in:
  - the hooks (`cbuild_*`);
  - the REST routes and their response shapes (`cbuild/v1`);
  - the abilities;
  - the block's name and attributes;
  - the WP-CLI commands;
  - the settings keys and option names;
  - the product and order item meta keys, and the JSON document schemas;
  - the documented CSS classes and custom properties;
  - the DOM events;
  - the state route's arguments: `picks[<slot key>]`, `previous[<slot key>]` (for the change list), `step`, `assembly` and `locale`;
  - the build link's format: `cbuild[<slot key>]=<part ID>`, with `cbuild[step]`, `cbuild[assembly]` and `cbuild[preset]`. A link made by any 1.x release opens in every later one.

  The Interactivity API store is private (locked), so nothing can come to depend on it.
- **From 1.0, nothing public breaks in a minor or patch release.**
  - REST responses only gain fields. A removal or rename means a `v2` namespace, with `v1` kept until the next major release.
  - A hook is never renamed or removed. It's deprecated with `apply_filters_deprecated()` or `do_action_deprecated()`, and a function with `_deprecated_function()`. Deprecated items are kept until the next major release, and for at least six months.
  - Settings and meta keys are migrated, never renamed in place.
  - Block attributes keep working through the block's `deprecated` entries.
  - A snapshot test fails on any change to the public API that has no changelog entry.
- **Before 1.0,** the API can still change between milestones. Every change is in the changelog and `HOOKS.md`, since Creative Checkout's and Mature Content's releases build on the hooks as they land.
- **Upgrades need no manual step.**
  - Migrations run on load when `cbuild_db_version` is behind.
  - Each is small, ordered and idempotent, and recorded in `cbuild_migrations`.
  - Each takes a lock that expires, so two requests never run one twice and a crashed one never blocks.
  - Long ones run in Action Scheduler batches from a saved cursor.
  - The shop keeps selling while they run.
- **Interruptions recover.** A migration or reindex stopped halfway resumes from its cursor and ends exactly as an uninterrupted run would, with no duplicate rows. This is tested.
- **Rolling back one minor release leaves a working shop.** Within a major release, migrations only add columns, keys and documents; they never drop or rename them.

**Resilience**

- **The builder never takes its page down.** Its render is a boundary: it logs an error and shows the shopper "This builder isn't available right now". An admin sees why.
- **Without WooCommerce, or below the floors,** the plugin hooks nothing and says so in the admin.
- **The page degrades rather than breaks.**
  - If the state route fails, the form submits and the server renders the next step.
  - A slow answer shows a pending state after 300 ms.
  - A stale answer is dropped.
- **Everything that can drift is re-checked on use.** A part may be deleted, unpublished, sold out, repriced or re-specced after a build was shared, saved or carted. The shopper is told which part changed and is offered the closest fit.
- **Failures are found.** Jobs are idempotent and retried with backoff. A failure goes to WooCommerce's log under `creative-skateboard-builder`, and shows in Site Health and the Catalogue check.
- **Site Health** checks the specs index, failed jobs and the Catalogue check, and its Info tab lists the plugin's versions and settings.

## Testing

The floors are under Quality bar; this is what the suites cover. The test cases under Compatibility rules are written first, before each rule's code.

**Unit, with no WordPress**

- Each rule with the cases this spec gives, and every boundary: both ends of a range, each tolerance, a missing spec (the part isn't offered), unit conversions and fractions.
- The engine: a later pick removed when an earlier one changes, with its reason; quantities from set sizes (R7); presets checked.
- Prices: the build discount rounded per line to the shop's decimals, a currency without decimals, a $0 parent.
- The change list and its closest fits; recommendations; the picture's layout at each width, length and wheelbase.

**Integration, in wp-env with the WordPress test suite**

- A build added through the Store API's `cart/add-item` and through the classic handler, and every refusal with its message.
- Keeping it together: quantity changes, removal from either end without a loop, a child changed alone refused, orphans dropped, the parent's maximum.
- Both checkouts (the Store API's and `WC_Checkout`) writing order items with the build's keys and snapshot; children mapped to item IDs; stock reduced per part, restored on a failed order and restocked on a refund.
- HPOS on, off and syncing; each coupon type with the coupon setting both ways; parts in different tax classes, with prices shown with and without tax; shipping with a $0 parent through the shop's own methods.
- The index jobs; the state route's schema refusals, its 429 and its budget; every admin action with and without its capability and nonce; every WP-CLI command; uninstall.
- **The invariant.** Across random builds over a generated catalogue, whatever the state route offers, the cart's validation accepts, and whatever it blocks, validation refuses.
- The abilities giving the state route's answers; the exporter, the eraser and account deletion; a migration and a reindex stopped halfway and run again; the composed picture with GD and with Imagick, and its pruning; multisite; a persistent object cache; MySQL 8.4 and MariaDB 10.11.

**Browser, in Playwright on Chromium, WebKit and Firefox at phone and desktop widths**

- Every way in: blank, a preset, a deck's link, a build link and a saved build. A later pick removed, a sold-out part in a preset, the review, Edit build replacing the build in the cart, and Back and Forward between steps with every pick kept.
- The build grouped and locked in the Cart block, the Mini-Cart and a classic checkout (Storefront's in CI; Shop Kit's is checked by Claude in Cowork).
- The whole flow again with JavaScript off, once in French and once right to left.
- A change that breaks later picks, accepted and undone; the picture's description after each pick; the phone bar never covering focus; the state route forced to fail; the SEO tags on the clean page and on a build link; visual snapshots of the main states on three themes, dark and light.

**Accessibility**

- axe on every step, the error summary, the three carts and every admin screen.
- A whole build by keyboard alone, from the first step to a placed order; focus on each step's heading; the status region's announcements read from the page; 24 px targets measured; reflow at 320 px wide (WCAG 1.4.10); forced colours; text spacing (WCAG 1.4.12); no animation running under reduced motion.
- The manual VoiceOver and NVDA script each release, in `docs/A11Y.md`.

**Also**

- Infection's mutation floors, and StrykerJS's on the builder's store.
- The contract tests: every state route answer against its schema, and the public API against its snapshot.
- The asset budgets, and Lighthouse CI on the builder page.
- Benchmarks on a generated catalogue of 2,000 parts with 500 candidates in one slot, cold and warm, with and without a persistent object cache.
- An upgrade test from every tagged release, through the `schema_version` and `cbuild_db_version` migrations.
- Once the kit is live, a build-and-checkout test in Skatehouse's own browser suite, the one that runs against the demo.

## What Skatehouse needs before the builder can sell

The plugin can be built and tested on a generated catalogue now. Selling completes on Skatehouse needs the shop's own parts and a few of the owner's decisions.

| Slot or task | Today | Needed |
| --- | --- | --- |
| Trucks | None stocked; the Trucks category is empty | The brands and sizes the owner will carry (owner question 13). Independent, Thunder and Venture charts come seeded; another brand needs its chart added. Each needs its `set_size`: 1 sold singly, 2 as a pair |
| Bearings | None stocked | The same question. Each needs its bore (8 mm for a 608), whether it has spacers built in, and its `set_size` (8 for a set) |
| Decks | Widths from `pa_width`; since 6 Nov, Length on 13 of the 160 (L'ÉTAT and Baitshop) and Wheelbase on 19, copied from their descriptions; no hole patterns | A missing pattern counts as new school and is flagged (R2). R6 checks a deck's length where it has one and skips the check where it doesn't, so lengths can be filled in over time |
| Wheels | Retna and Blotto, 52 to 58 mm, diameters from `pa_diameter` | A `set_size` of 4 on each set, filled in with the specs import (milestone 3). A 58 mm set warns with standard-height trucks and suggests a ¼ in riser |
| Risers | None stocked | The riser slot stays off until they are, and R3 only warns. Each will need its `set_size` (2 for a pair) |
| Hardware | Philly's, in 7/8, 1 and 1¼ in | Enough for no riser up to ¼ in risers. ½ in risers would need 1½ in bolts (R4). Each pack needs its `set_size` (8) |
| Grip | Three 9 × 33 in sheets (Graffic Grip's Blank and Cam Man, Mumber-Fur's; only Cam Man in stock on 6 Nov), with Mumber-Fur's strips in the same category; Sheet size on the sheets and strips since 6 Nov | The grip slot takes the category's parts whose Sheet size covers a deck (the 9 × 33 in sheets, never the strips), so a new sheet joins without editing the kit. A deck over 9 in wide can't take a 9 in sheet (R6) |
| Fingerboards | Fingerboard decks, trucks and completes in their own categories | The slots draw from the skateboard categories only, so these never show |
| Picture | Product photos only | Deck cut-outs from today's photos (agency); trucks, wheels, risers and grip shot to `docs/PICTURE.md` as they arrive (the owner's stock, the agency's template). Until then the picture shows labelled product photos |
| Owner's decisions | Not yet made | Assembly free or a fee, and whether a build can come unassembled (17); returns of assembled completes (18); a build discount (19) |
| Presets | None | Four to six from what's in stock: we suggest, the owner approves |

**Setting it up**, once 1.0 is tagged and the parts are in: create the Complete skateboard kit in the Completes category; import the part specs and run the Catalogue check until it's clean; put the Builder block on /build-a-complete/; Skatehouse Core 2.x adds "Make it a complete" to deck pages; and Skatehouse's browser suite gains a build-and-checkout test.

## Milestones for Claude Code

Nine milestones, in order.

- Each starts with a plan: the risks under each of the twelve dimensions.
- Each ends with every CI gate green, the audit written into `docs/reviews/M<n>.md` and its pull request, a changelog entry, a commit and a tag (0.1.0 to 1.0.0).
- `docs/ROADMAP.md` has each one in full: what it builds, the tests that must pass, where the audit looks hardest, and what comes after 1.0.
- The cart comes before the builder's front end. Keeping a build together on every route is the riskiest part, and the cart can be driven through the Store API before the builder exists.

| # | Milestone | Done when |
| --- | --- | --- |
| 1 | Foundation | The plugin boots from a bootstrap-only root file into `src/`. The migration framework, the updater and the logger are in place. CI runs every gate, and wp-env pins the oldest and newest supported WordPress and WooCommerce. HPOS and blocks compatibility are declared. Activation, upgrade and uninstall are in place, and the `docs/` files are started |
| 2 | Domain | Specs, units, R1 to R8, the fit engine with its hidden counts, the change list, recommendations, quantities, prices and the picture's layout live in `src/Domain` with no WordPress in it. Every test in this spec passes, at 100 % coverage and MSI |
| 3 | Specs | The specs index and its resumable jobs, the attribute mapping, the seeded spec tables, the Part specs panel, CSV import and export with the `wp cbuild specs` commands, the Catalogue check and Site Health |
| 4 | Kits | The kit product type; the Build tab with schema validation and up to six presets; the state route within its budget and behind its rate limit; the abilities; the kit kept out of search and sitemaps |
| 5 | Cart and checkout | A build added through the Store API and the classic handler and kept together on every route. It shows grouped in the Cart block, the Mini-Cart and a classic checkout (Shop Kit's is checked by Claude in Cowork). Pricing carries assembly from the first step, the discount and coupons, and both checkouts write the order items |
| 6 | The builder | Server-rendered steps from one template, the Interactivity API store and the no-JavaScript forms. Presets, every way in, the change panel, the phone bar, the labelled parts picture, the review, history, the delivery line, Edit build, the Builder block, motion, and the SEO rules for builder URLs |
| 7 | The picture | Layer images and the kit's picture settings; the layered picture in the page; the composed image, made in the background, for the cart thumbnail and share cards; `docs/PICTURE.md` |
| 8 | Orders and after | Order display and emails with the picture, the Build box, the assembly ticket and its bulk print, the orders list column, saved builds under My builds, the privacy exporter and eraser, and the refund help text |
| 9 | Hardening and 1.0 | French, the manual accessibility pass, the benchmarks and Lighthouse on the generated catalogue, the security and privacy walk-throughs, the public API frozen, a clean Plugin Check, and the full audit in `docs/AUDIT-1.0.0.md`. The docs are complete and the release is tagged 1.0.0 |

Skatehouse's own setup follows 1.0, as What Skatehouse needs describes, alongside the companion releases.

## Open questions

**For Zach.** When each answer is needed:

- Before milestone 1: questions 2 and 3; 8, since the floors set the toolchain; and 9, since the picture's layout is built in milestone 2. Question 1, the name, is answered.
- By milestone 5: questions 5 and 6.
- By milestone 6: question 4.
- Before Skatehouse's setup: question 7.

Each suggestion is written into `docs/DECISIONS.md` as a proposed decision, so Claude Code starts from it. Zach's answer turns it into an accepted one.

| # | Question | Our suggestion |
| --- | --- | --- |
| 1 | The name | Answered: Creative Skateboard Builder, Zach's call on 9 Nov. Creative Builder read as generic. The slug, text domain and namespace follow the new name, and the short `cbuild_` prefix stays (D-001) |
| 2 | Build our own rather than buy Composite Products at $149 a year per site | Build; the reasons are under The bar to clear (D-002) |
| 3 | Repository and licence | A private repository like Creative Search's, licensed GPL-2.0-or-later as WordPress plugins are, released through the agency's releases repository and updater (D-003) |
| 4 | Where the builder lives on Skatehouse | The Builder block on /build-a-complete/, which is already in the menu, with the kit's own page pointing there (D-008) |
| 5 | The cart count: one per build, or one per part | One per build, as Bundles and Mix and Match count theirs (D-009). One filter does it everywhere: `woocommerce_cart_contents_count` sets the header count and the Store API's `items_count`. Once anything is hooked there, WooCommerce has the Mini-Cart show `items_count` instead of adding up the lines (checked in 11.2.1) |
| 6 | Load WooCommerce's checkout script on every page, so the Mini-Cart can indent a build's parts and hide their Remove links | No: it adds about 140 kB compressed to every page. Item data and CSS group the parts, and removing any part removes the whole build (D-010) |
| 7 | Dealer accounts (Phase 4): does a signed-in dealer see the builder, and at which prices? | Retail prices only in 1.0, with a line telling a signed-in dealer the builder is for retail orders. Revisit with the owner's trade terms (D-011) |
| 8 | The floors: PHP 8.5, WordPress 7.0 and WooCommerce 11.2 | Yes. The PHP and WordPress floors match Creative Search's D-023, Zach's call on 21 Oct: the agency's shops run WordPress 7 on PHP 8.5, and the current test tools need PHP 8.4 or later. WooCommerce 11.2 is the first release with the Store API filters that refuse a part's quantity changed on its own and validate a build's first add; the demo runs 11.2.2 (D-004) |
| 9 | The picture in 1.0; 3D and shoppers' own graphics after | Yes: the layered 2D picture in 1.0. 3D on a tap comes once trucks are stocked and the owner pays for models, and graphics once the owner answers the seven questions in `docs/UX-RESEARCH.md` (D-006, D-007) |

**For the owner**, already in [Skatehouse: questions for the owner](https://claude.ai/artifact/NtszoXMTEDsu9GRekKyGbH):

- 13 (trucks and bearings), 17 (assembly), 18 (returns of assembled completes) and 19 (a build discount), none answered by 9 Nov.
- The seven questions on shoppers' own graphics (`docs/UX-RESEARCH.md`), which only the graphic module waits on.
- The presets come later: once trucks and bearings are in, we suggest four to six for the owner to approve.

## Sources

Opened 22 October 2026. WooCommerce's source is cited from mirrors (wp-kama.com and hooks.wbcomdesigns.com, which show 11.2.1), since the research tools couldn't open GitHub.

**Skate shops' builders.** Linked in The bar to clear, plus Warehouse Skateboards' [assembly options](https://www.warehouseskateboards.com/assembly-options), [policies](https://www.warehouseskateboards.com/policies) and [grip option](https://www.warehouseskateboards.com/grip-option); Skate Warehouse's [build guide](https://www.skatewarehouse.com/news/articles/how-to-build-a-skateboard.html) and [order information](https://www.skatewarehouse.com/SupportCenter/OrderInfo/); SkatePro's [US builder](https://www.skatepro.com/en-us/build-a-skateboard.htm); Hammer's [in-store assembly](https://www.hammertoronto.com/products/assemble-complete-board-in-store); and a [UX case study of Zumiez's builder](https://www.behance.net/gallery/159688635/Zumiez-Build-a-Custom-Skateboard-Case-Study), not by Zumiez.

**Fit rules**

- R1: [Independent's trucks](https://independenttrucks.com/trucks); Thunder's from [its own chart](https://www.thundertrucks.com/sizing/) and Venture's from [its own](https://venturetrucks.net/sizes/), each published as images (D-093); the tolerance from [Warehouse's trucks step](https://www.warehouseskateboards.com/skateboard-trucks?bac=1) and [Skate Warehouse's trucks guide](https://www.skatewarehouse.com/news/articles/skateboard_trucks_buying_guide.html); the disagreeing range from [NHS](https://nhsskatedirect.com/products/stage-11-polished-standard-skateboard-trucks-independent).
- R2: [Riding Boards](https://www.ridingboards.com/?p=2738) for the patterns; [NHS's Indy 215](https://nhsskatedirect.com/products/stage-11-215-polished-skateboard-trucks-independent) for six-hole baseplates.
- R3: [NHS's Indy Mid](https://nhsskatedirect.com/products/polished-mid-independent-skateboard-trucks); [Warehouse's wheels step](https://www.warehouseskateboards.com/skateboard-wheels?bac=1); [Thunder's truck styles](https://www.thundertrucks.com/truck-styles/); [Skate Warehouse UK](https://www.skatewarehouse.co.uk/news/low-vs-high-skateboard-trucks/); [Hammer's guide](https://www.hammertoronto.com/pages/beginner-guide-to-choosing-a-skateboard) for the riser table; [SkatePro's risers](https://www.skatepro.ca/c390.htm); [Switch](https://www.switchnow.ca/pages/skateboard-buying-guide).
- R4: [Warehouse's hardware guide](https://www.warehouseskateboards.com/help/Skateboard-Hardware-Buying-Guide) and [SkatePro's](https://www.skatepro.com/en-us/a798.htm).
- R5: [Sector 9](https://sector9.com/blogs/blog/skateboard-bearing-sizes-what-you-need-to-know); Bones' [7 mm bearings](https://bonesbearings.com/bones-reg-swiss-bearings-7mm-16-pack) and [Race Reds](https://bonesbearings.com/bones-reg-race-reds-reg-skateboard-bearings-8-pack).
- R6: [Warehouse's gripping guide](https://www.warehouseskateboards.com/help/How-to-Grip-a-Skateboard); Mob's [9 in](https://nhsskatedirect.com/products/9in-x-33in-creature-riot1394-hallucination-sheet-mob-skateboard-grip-tape) and [10 in](https://nhsskatedirect.com/products/10in-x-33in-thrasher-metal-sheet-mob-skateboard-grip-tape) sheets; [Longboards USA](https://longboardsusa.com/products/black-longboard-skateboard-10x-33-griptape-sheet-with-deck).
- R7: [Tactics on assembling a skateboard](https://www.tactics.com/info/assembling-a-skateboard).
- R8: [Concrete Waves](https://concretewaves.com/?p=9409); [SkateboardersHQ](https://skateboardershq.com/skateboard-wheelbase-guide/).

**WooCommerce**

- Extensions: Composite Products' [kit builder](https://woocommerce.com/document/composite-products/composite-products-use-case-kit-builder/), [data structures](https://woocommerce.com/document/composite-products/composite-products-data-structures-storage/) and [FAQ](https://woocommerce.com/document/composite-products/composite-products-faq/); Product Bundles' [complex products](https://woocommerce.com/document/bundles/bundles-use-case-complex-products), [data structures](https://woocommerce.com/document/bundles/bundles-data-structures-storage/), [FAQ](https://woocommerce.com/document/bundles/bundles-faq/) and [compatibility](https://woocommerce.com/document/bundles/bundles-compatibility-and-integrations/); Mix and Match's [Store API reference](https://woocommerce.com/document/woocommerce-mix-and-match-products/mix-and-match-store-api-reference/).
- Developer docs: [extending the Store API](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/), [cart line item filters](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/filters-in-cart-and-checkout/cart-line-items/), [migrated hooks](https://developer.woocommerce.com/docs/block-development/reference/hooks/migrated-hooks/), [hook alternatives](https://developer.woocommerce.com/docs/block-development/reference/hooks/hook-alternatives/), the [HPOS recipe book](https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/) and the [blocks extensibility FAQ](https://developer.woocommerce.com/2023/11/06/faq-extending-cart-and-checkout-blocks/).
- Announcements: [the Interactivity API Mini-Cart in 10.4](https://developer.woocommerce.com/2025/12/10/woocommerce-10-4-the-interactivity-api-mini-cart-goes-live/), [stock restored on failed orders](https://developer.woocommerce.com/2026/07/17/failed-order-stock-update/), [hidden order item meta in the admin](https://developer.woocommerce.com/2026/08/18/reserved-order-item-meta-admin/) and [the new cart validation filter](https://developer.woocommerce.com/2026/09/24/new-cart-validation-filter/); and a [discussion of item meta missing from the new Mini-Cart](https://github.com/woocommerce/woocommerce/discussions/59850).
- Guides: [coupons](https://woocommerce.com/document/coupon-management/), [refunds](https://woocommerce.com/document/woocommerce-refunds/), and [Metorik on bundles](https://help.metorik.com/article/77-product-bundles-support).
- Source: [`woocommerce_add_cart_item_data`](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_add_cart_item_data), [`CartController::add_to_cart`](https://wp-kama.com/plugin/woocommerce/function/CartController::add_to_cart), [`QuantityLimits`](https://wp-kama.com/plugin/woocommerce/function/QuantityLimits), [`woocommerce_cart_item_set_quantity`](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_cart_item_set_quantity), [`WC_Cart::remove_cart_item`](https://wp-kama.com/plugin/woocommerce/function/WC_Cart::remove_cart_item), [`woocommerce_checkout_create_order_line_item`](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_checkout_create_order_line_item), [`OrderController::update_line_items_from_cart`](https://wp-kama.com/plugin/woocommerce/function/OrderController::update_line_items_from_cart), [`woocommerce_order_item_name`](https://wp-kama.com/plugin/woocommerce/hook/woocommerce_order_item_name), [`WC_Coupon::is_valid_for_product`](https://wp-kama.com/plugin/woocommerce/function/WC_Coupon::is_valid_for_product), [`ReserveStock::reserve_stock_for_order`](https://wp-kama.com/plugin/woocommerce/function/ReserveStock::reserve_stock_for_order), [`wc_reduce_stock_levels`](https://hooks.wbcomdesigns.com/?p=33660) and [`wc_restock_refunded_items`](https://wp-kama.com/plugin/woocommerce/function/wc_restock_refunded_items).
- Checked 22 October 2026: WooCommerce's [DOM events](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/dom-events/); the cart store's [locked status](https://github.com/woocommerce/woocommerce/issues/68885) and [renamed actions](https://github.com/woocommerce/woocommerce/pull/69038); [WooCommerce PayPal Payments](https://github.com/woocommerce/woocommerce-paypal-payments/pull/4796) on the Mini-Cart in WooCommerce 11, which never loads the `wc/store/cart` store; the Cart block's [cart-updated action](https://github.com/woocommerce/woocommerce/pull/69788), which doesn't reach the Mini-Cart; and the source of [`MiniCart`](https://wp-kama.com/plugin/woocommerce/function/MiniCart), [`BlocksSharedState`](https://wp-kama.com/plugin/woocommerce/function/BlocksSharedState), [`CartSchema::get_item_response`](https://wp-kama.com/plugin/woocommerce/function/CartSchema::get_item_response), [`WC_Cart::get_cart_contents_count`](https://wp-kama.com/plugin/woocommerce/function/WC_Cart::get_cart_contents_count), [`WC_Meta_Box_Product_Data::save`](https://wp-kama.com/plugin/woocommerce/function/WC_Meta_Box_Product_Data::save) and [`save_variations`](https://wp-kama.com/plugin/woocommerce/function/WC_Meta_Box_Product_Data::save_variations). The Mini-Cart was also tested on the demo itself, as a guest, removing each test item after.

**Accessibility.** WAI's [multi-page forms](https://www.w3.org/WAI/tutorials/forms/multi-page/) and [notifications](https://www.w3.org/WAI/tutorials/forms/notifications/); the APG's [radio group](https://www.w3.org/WAI/ARIA/apg/patterns/radio/), [tabs](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/), [listbox](https://www.w3.org/WAI/ARIA/apg/patterns/listbox/) and [read me first](https://www.w3.org/WAI/ARIA/apg/practices/read-me-first/); WCAG 2.2's [status messages](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html), [F110](https://www.w3.org/WAI/WCAG22/Techniques/failures/F110.html) and [C43](https://www.w3.org/WAI/WCAG22/Techniques/css/C43); MDN on [live regions](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Guides/Live_regions); GOV.UK's [error summary](https://design-system.service.gov.uk/components/error-summary/); and [Gatsby's user testing of focus after a route change](https://v2.gatsbyjs.com/blog/2019-07-11-user-testing-accessible-client-routing).

**Added 9 November 2026.** [Creative Skateboard Builder: UX deep dive](https://claude.ai/artifact/447cwcRifAicW4B7BRMgwo) (6 Nov), with its own sources; Google's [faceted navigation](https://developers.google.com/crawling/docs/faceted-navigation) guidance; WordPress's [client-side navigation](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/core-concepts/client-side-navigation/) and [Interactivity API changes in 7.0](https://make.wordpress.org/core/2026/02/23/changes-to-the-interactivity-api-in-wordpress-7-0/); the [Abilities API](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/); WooCommerce's [PHP 8.1 requirement from 11.6](https://developer.woocommerce.com/2026/09/29/php-8-1-requirement/) and its [compatibility practices](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility/); WordPress core's [PHPUnit 10 and 11 ticket](https://core.trac.wordpress.org/ticket/62004), still open; and Creative Search's `docs/DECISIONS.md` (D-021, D-022, D-023).
