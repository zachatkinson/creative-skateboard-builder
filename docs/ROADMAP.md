# Roadmap

The whole Creative Skateboard Builder project: ten milestones to 1.0 (D-236), then what follows it. `SPEC.md` says what each part must do; this file says what each milestone builds, what proves it, and where its audit looks hardest. The milestones are in order.

Don't start one until Zach approves the last. Milestones 1 to 9 are tagged `v0.1.0` to `v0.9.0`; milestone 10 is `v1.0.0`.

**Every milestone runs the same way** (`AUDIT.md`):

1. `/plan-milestone`: the plan, with the risks under each of the twelve dimensions, written into `docs/reviews/M<n>.md` before any code. Questions for Zach go first.
2. Build, test first where the spec gives cases. `/verify` before every commit.
3. `/audit-milestone`: the two reviewer agents, every blocker and should-fix fixed with a test that fails without it, and the review written up.
4. A draft pull request using the template, with a `CHANGELOG.md` entry, marked ready once the gates pass locally (D-041).
5. Zach sends the summary to Claude in Cowork for the independent review. Its fixes land, Claude merges once the full CI run is green, and the next milestone's `/plan-milestone` tags the merge (`v0.<n>.0`; `v1.0.0` for milestone 10).

## Contents

- [Milestone 1: Foundation (0.1.0)](#milestone-1-foundation-010)
  - [Builds](#builds)
  - [Tests that must pass](#tests-that-must-pass)
- [Milestone 2: Domain (0.2.0)](#milestone-2-domain-020)
  - [Tests that must pass](#tests-that-must-pass-1)
- [Milestone 3: Specs (0.3.0)](#milestone-3-specs-030)
  - [Builds](#builds-1)
  - [Tests that must pass](#tests-that-must-pass-2)
- [Milestone 4: Kits (0.4.0)](#milestone-4-kits-040)
  - [Builds](#builds-2)
  - [Tests that must pass](#tests-that-must-pass-3)
- [Milestone 5: Cart and checkout (0.5.0)](#milestone-5-cart-and-checkout-050)
  - [Tests that must pass](#tests-that-must-pass-4)
- [Milestone 6: The builder (0.6.0)](#milestone-6-the-builder-060)
  - [Tests that must pass](#tests-that-must-pass-5)
- [Milestone 7: The picture (0.7.0)](#milestone-7-the-picture-070)
  - [Builds](#builds-3)
  - [Tests that must pass](#tests-that-must-pass-6)
- [Milestone 8: Orders and after (0.8.0)](#milestone-8-orders-and-after-080)
  - [Tests that must pass](#tests-that-must-pass-7)
- [Milestone 9: Hardening (0.9.0)](#milestone-9-hardening-090)
  - [Builds and checks](#builds-and-checks)
- [Milestone 10: The configurator and 1.0 (1.0.0)](#milestone-10-the-configurator-and-10-100)
  - [Tests that must pass](#tests-that-must-pass-8)
- [Milestone 11: The motion (1.1.0)](#milestone-11-the-motion-110)
  - [Tests that must pass](#tests-that-must-pass-9)
- [After 1.0](#after-10)
  - [Companion releases](#companion-releases)
  - [Skatehouse's setup](#skatehouses-setup)
  - [Module A: your own graphic](#module-a-your-own-graphic)
  - [Module B: 3D on a tap](#module-b-3d-on-a-tap)
  - [Later ideas, not planned](#later-ideas-not-planned)

---

## Milestone 1: Foundation (0.1.0)

### Builds

- The repository as `CLAUDE.md` describes it, set up like Creative Search's:
  - Composer, with PHPUnit 9.6 in `tools/integration/`;
  - npm and TypeScript;
  - PHPCS, PHPStan (two configs) and deptrac;
  - Infection and StrykerJS;
  - Playwright with axe, and Lighthouse CI;
  - the `tools/` scripts.
- `creative-skateboard-builder.php` with the bootstrap only.
- `Plugin`, `Container` and `Hookable`, as in the agency's other plugins.
- The PSR-4 layout and the deptrac layers in `STANDARDS.md`.
- **Activation, deactivation and uninstall.** Activation creates the two tables and seeds nothing yet. Uninstall removes everything the plugin made.
- **The migration framework.** It is built first, so every later change rides it:
  - versioned, ordered, idempotent steps;
  - `cbuild_db_version` and `cbuild_migrations`;
  - a lock that expires;
  - Action Scheduler batches with a saved cursor for long steps;
  - a no-op first migration that proves the path.
- **Platform plumbing:**
  - the WooCommerce check and the floors' admin notice;
  - compatibility declared: `custom_order_tables` and `cart_checkout_blocks` true; `product_block_editor` false only if the installed WooCommerce still has it;
  - the logger (`wc_get_logger()`, source `creative-skateboard-builder`) and the Clock;
  - the updater (`Updates\`, as in Creative Search), with `CBUILD_DISABLE_UPDATES` and `cbuild_updates_enabled`.
- **CI with every job wired, even where a suite is still empty:**
  - static checks and JavaScript;
  - mutation and benchmarks;
  - integration on both legs (newest and oldest) with the HPOS, multisite and object cache variants;
  - browser tests and Plugin Check;
  - the weekly canary.
- `release.yml` and Dependabot.
- wp-env on ports 8898 and 8899, newest and oldest.
- `docs/` started: HOOKS, SETTINGS, SECURITY, PRIVACY, A11Y, BENCHMARK, `reviews/M1.md`; `readme.txt`; `CHANGELOG.md`.

### Tests that must pass

- **Lifecycle:** activation, deactivation and uninstall on a single site and on multisite.
- **Migrations:**
  - a migration runs once;
  - two requests at once run it once;
  - a crashed run's lock expires;
  - a batch run stopped halfway resumes and matches an uninterrupted run.
- **Snapshots and gates:**
  - the public API snapshot (empty, committed);
  - deptrac;
  - Plugin Check on the built zip;
  - a browser smoke test: the plugin active on the shop's pages with no console errors and no asset of the plugin's loaded.

**Done when** every gate is green on both legs, and the milestone's zip installs on a clean WordPress, upgrades over itself and uninstalls clean.

**Where the audit looks hardest:** upgrades and compatibility (the migration framework, the declarations); security (the updater checks the zip's SHA-256 and fetches over HTTPS only); anti-patterns (nothing but the bootstrap at the root); testing (every gate really runs and really fails when broken: one deliberate break per gate).

---

## Milestone 2: Domain (0.2.0)

**Builds**, all in `src/Domain`, with no WordPress:

- **The model.** Specs and their units (inches, millimetres and fractions, parsed at the edge, never from names). Slots, parts and the kit's settings as value objects, built from the JSON documents after schema validation.
- **Rules R1 to R8**, each with its severity, its source and a message code with values.
- **The fit engine**: fit per option given the picks, with the counts hidden per slot, grouped by rule.
- **The change list**: the picks kept, the picks that no longer fit with the closest part that does (the nearest spec, then the same brand, then the nearest price, in stock), and the undo.
- **Recommendations**: the kit's default if it fits and is in stock, otherwise the fitting, in-stock part nearest the middle of the slot's prices. Never a deck.
- **Quantities** (R7).
- **Pricing**: parts at the prices passed in, the assembly fee, and the build discount rounded per line to the shop's decimals.
- **The build validator** the cart will use.
- **The picture's layout**: from the specs to each layer's scale and position. The deck by its width and length, the trucks by the wheelbase or the shape's usual one, the wheels at the axles.

### Tests that must pass

- Every case under Compatibility rules, written first, and every boundary: both ends of a range, each tolerance, a missing spec, unit conversions and fractions.
- The change list, the recommendations and the layout, case by case.
- The invariant, over seeded random builds on a generated catalogue: what the engine offers, the validator accepts.
- 100 % line and branch coverage, 100 % MSI, and PHPStan level 10 on the domain.

**Done when** the domain runs its whole suite with WordPress not loaded at all, and deptrac shows nothing leaving the domain.

**Where the audit looks hardest:**

- **DRY and SOLID:** one engine, small classes, interfaces for the outside.
- **Internationalisation:** codes and values, no English in the domain.
- **Testing:** mutation at 100 %.
- **Performance:** the engine's work grows linearly with options times rules. Benchmark 500 candidates.

---

## Milestone 3: Specs (0.3.0)

### Builds

- The specs index table and its repository.
- The jobs: `cbuild_index_part` and `cbuild_reindex` (100 parts a batch, from a saved cursor), unique and idempotent.
- The attribute mapping.
- The seeded spec tables (Independent, Thunder and Venture, each row with its source and the date it was read), as `cbuild_spec_table` posts made by a migration, so upgraded sites get them too.
- The Part specs panel for simple products and variations:
  - units shown;
  - decimals in the shop's separator, or fractions;
  - inherited values read-only, with where they come from.
- **CSV import and export**, in the admin and as `wp cbuild specs import|export`:
  - keyed on ID or SKU;
  - a dry run first;
  - formula-safe export.
- The Catalogue check, in the admin and as `wp cbuild check`, which exits non-zero.
- The ability `creative-skateboard-builder/catalogue-check`, for those who can manage WooCommerce.
- Site Health tests: the index up to date, failed jobs.

### Tests that must pass

- **Indexing:** from each source, which source wins, disagreements flagged.
- **Jobs:** idempotent; a reindex stopped halfway resumes.
- **Imports:** bad rows, encodings, SKU against ID, the dry run writing nothing.
- **Exports:** the formula cells neutralised.
- **Permissions:** every admin action with and without its capability and nonce.
- **WP-CLI:** each command and its exit codes.
- **Benchmarks:** the query plans use the index (EXPLAIN in the benchmark group), and the 2,000-part rebuild time is recorded.

**Done when** a generated catalogue of 2,000 parts indexes in the background without slowing a product save, and the Catalogue check reports it right.

**Where the audit looks hardest:** performance (batch sizes, query plans); upgrades (resumable reindex); security (import and export); accessibility (the panels by keyboard and screen reader, axe on each); resilience (a failed job reaches Site Health and the check).

---

## Milestone 4: Kits (0.4.0)

### Builds

- **The kit product type** (`KitProduct`). It is virtual and can't be bought outside the builder. It's kept out of the catalogue, search, feeds and sitemaps (WordPress's and Yoast's), and prints no product markup.
- **The Build tab**, with schema validation and a notice that links each problem to its field:
  - slots, with move up and down as well as drag;
  - rules;
  - up to six presets, each with its description (four to six is the aim once the shop's parts are in);
  - assembly.
- **The state route**, `GET /cbuild/v1/kits/{id}/state`:
  - **Request:** its argument schema; the locale switch.
  - **Answer:** each option's fit, the hidden counts, the change list when the request carries the previous picks, the recommendations, and the total with assembly in it.
  - **Rules:** the rate limit with salted hashes, 429 and `Retry-After`; `no-store`; a published JSON Schema.
  - **Budget:** within 80 ms at p95.
- **The abilities** `creative-skateboard-builder/check-build` and `creative-skateboard-builder/price-build`. They are read-only and give the route's answers, as Creative Reviews registers its own.
- The filters the route's answer runs through: `cbuild_part_available`, `cbuild_price_context`, and the owner's own words through `cbuild_slot_label`, `cbuild_preset_label` and `cbuild_kit_text`.
- The settings page under WooCommerce > Settings > Builder.

### Tests that must pass

- **The kit:** saves and loads through CRUD.
- **The Build tab:** each validation error.
- **Presets:** a preset that breaks a rule is refused.
- **The route:** its schema refusals, its 429, its header, its locale, and its budget at 500 candidates.
- **Contracts:** each answer validated against the schema; the abilities agreeing with the route on the same builds.
- **SEO:** the kit absent from the sitemaps and markup.
- **API:** the public API snapshot updated (route, abilities, settings keys) with its changelog entries.

**Done when** the route answers the generated catalogue within budget, and a preset or kit that breaks a rule can't be saved.

**Where the audit looks hardest:** security (a public route); performance (its budget); upgrades and compatibility (the `v1` contract and settings keys are public from here); DRY (the route and the abilities share one service); privacy (the rate limiter keeps no address); SEO (the kit stays out).

---

## Milestone 5: Cart and checkout (0.5.0)

**Builds**, everything under Cart, checkout and orders and Pricing, stock, assembly and returns:

- **Adding a build:**
  - validate in both hooks;
  - attach the build with its UUID;
  - add the children.
- **Keeping it together:**
  - quantities locked;
  - removal from either end;
  - orphans dropped;
  - every build re-checked on load.
- **Showing it:**
  - Store API item data, and the Cart block filters (a small script, on the cart and checkout pages only);
  - the Mini-Cart's item data, class and `wc-blocks_added_to_cart`;
  - the classic cart and Shop Kit's checkout through their PHP filters.
- **Prices and coupons:** parts at live prices; the assembly fee on the parent, in the total from the first step; the build discount; coupons.
- **Counts and lines:** one cart count per build (D-009).
- **Orders:** both checkouts write the order items, and cart keys are mapped to item IDs. Stock is left to WooCommerce, part by part.
- **Express pay:** buttons hidden on kit pages, and a bare kit refused.
- **Delivery data:** `cbuild_arrival_estimate` and `cbuild_delivery_line`, and the returns notice.

### Tests that must pass

- The spec's integration list:
  - every refusal with its message;
  - HPOS on, off and syncing;
  - each coupon type with the setting both ways;
  - tax classes, with prices shown with and without tax;
  - a $0 parent through the shop's own shipping methods.
- The invariant, now through the real cart.
- **Browser:**
  - an add through the Store API, then the Mini-Cart's drawer refreshing (with its first read held back 6 seconds, as Skatehouse's own test does);
  - the build grouped and locked in the Cart block;
  - an order placed with an offline method on a classic checkout (Storefront).

Shop Kit can't run in CI, so Claude in Cowork checks its checkout with a build in it, on a local copy of the demo or on the demo if Zach installs the milestone's zip there.

**Done when** no route through any cart can split, change or orphan a build, and both checkouts write the same order items.

**Where the audit looks hardest:** security (validation on the server, no browser prices); resilience (a part sold out or deleted between the builder and checkout); upgrades (order item meta keys are forever); compatibility (three carts, HPOS, the blocks); testing (the invariant).

---

## Milestone 6: The builder (0.6.0)

**Builds**, everything under The builder except the layered picture:

- **The Builder block:** `block.json` apiVersion 3, `render.php`, `supports.interactivity`, a kit attribute, and an editor preview. The kit's own page renders through `woocommerce_cbuild_kit_add_to_cart`.
- **Rendering:**
  - one template with directives, rendered by the server and re-rendered by the private `creative-skateboard-builder` store from the state route's answer;
  - the no-JavaScript GET forms with the `cbuild[…]` parameters in slot order;
  - history entries with the same URLs.
- **Accessibility mechanics:** focus to each step's heading, the status region and the error summary.
- **Ways in and presets:** every way in, and the preset tiles.
- **A step:**
  - cards with "+$", "Free" or "Included", fit labels and distinct names, and `cbuild_part_classes` on each card;
  - the hidden-parts disclosure with its reasons;
  - filters and the phone drawer;
  - sold-out parts linking to Notify me;
  - the recommendations, labelled.
- **The change panel**, with "Use these" and "Undo"; a step of its own without JavaScript.
- **The total and delivery:**
  - the summary bar beside the steps, and the phone bar with "Next: X", whose total opens the parts list;
  - assembly in the total from step one;
  - the delivery line;
  - `cbuild_summary_notes`.
- **The picture, for now:** the labelled "What's in the box" picture, with its written description.
- **The review**, with fit ticks, Change links, assembly, the date, the total and the returns notice.
- **Getting it to the cart:**
  - Add to cart through the Store API, then the event;
  - Copy link (the Clipboard API, with a selectable field when it isn't there);
  - Edit build from the cart, replacing the build.
- **Motion:** view transitions between steps, a settling card, nothing under reduced motion.
- **SEO:** state pages `noindex, follow` with a canonical to the clean page; `rel="nofollow"` on step links; the clean page's content in the HTML.

### Tests that must pass

- **Browser, on Chromium, WebKit and Firefox at both widths:**
  - every way in built so far (saved builds arrive in milestone 8);
  - a change accepted and undone;
  - a sold-out part in a preset;
  - Back, Forward and reload;
  - JavaScript off, fr_CA and right to left;
  - the state route forced to fail, and the build still finishes.
- **Accessibility:**
  - axe on every state;
  - a keyboard-only build to a placed order;
  - focus after each step;
  - the status region's text;
  - targets, reflow, forced colours, text spacing and reduced motion.
- **Speed:**
  - a pick paints within 100 ms on a 4× slowed CPU;
  - Lighthouse CI on the builder page;
  - the asset budgets.
- **Snapshots:** visual on Kadence, Storefront and Twenty Twenty-Five, dark and light.
- **The store:** StrykerJS at 90 %; its types generated from the route's schema.
- **Content:** the SEO tags; the copy gate.

**Done when** a shopper can build, change, share and buy a board on a phone and a desktop with or without JavaScript, by keyboard, mouse, touch or screen reader, within every budget.

**Where the audit looks hardest:** accessibility and UX and content (Baymard's configurator guidelines and P1 to P12, screen by screen, with the walkthrough screenshots); performance (interaction, largest paint, bundle size); SEO (state URLs); internationalisation (French at phone width); resilience (the fallbacks); DRY (one template, no markup in JavaScript).

---

## Milestone 7: The picture (0.7.0)

### Builds

- **The renderer interface**, and its two renderers, both from the domain's layout:
  - **In the page:** layered `<picture>` elements with explicit sizes, AVIF and WebP sources, the deck at `fetchpriority="high"`, and `cbuild_part_classes` on each layer. The written description is updated and announced once per pick, and a layer is swapped with a view transition.
  - **Composed:** GD by default, Imagick when the host has it; JPEG for email and WebP elsewhere; made by `cbuild_compose_picture` in Action Scheduler; cached by hash in `uploads/creative-skateboard-builder/pictures/`; pruned by `cbuild_prune_pictures`; the generation queue capped.
- **The admin:**
  - the Layer image field on parts, with a preview on the template and checks for transparency and size;
  - the kit's Picture section (layer order and anchors);
  - the Catalogue check's list of parts without layers.
- **Where the composed picture goes:**
  - the kit's cart thumbnail, in the Store API and the classic cart;
  - share cards (`og:image`, `og:url` as the build link, the build's name and total), through Yoast's filters when it's active and the plugin's own tags when no SEO plugin is.
- **The fallback:** parts without layer images keep milestone 6's labelled photos within the same picture.
- `docs/PICTURE.md`, the shot template for the agency's photographer: camera height and lens, scale, the nose up, the background, and the export settings for a transparent master.

### Tests that must pass

- **Layout:** in the domain (from milestone 2), now drawn by both renderers alike; GD and Imagick agreeing within a set tolerance.
- **Caching and limits:** the cache keys, the pruning, and the queue cap.
- **Accessibility:** the description right in English and French, announced once.
- **Snapshots:** visual of the picture at three widths.
- **Speed:** the deck layer as the largest paint, within 2.5 s in the lab; no page request waiting on a composition.
- **Share cards:** the tags with and without Yoast.

**Done when** the picture matches every build to scale in the page, in the cart and in a share card, and still shows a labelled picture wherever layer images are missing.

**Where the audit looks hardest:** performance (the largest paint, and generation cost); security (generated files, the queue); accessibility (the description); SEO (share cards); resilience (missing layers, an image library missing on the host); UX (the picture changes with every pick, and its motion helps).

---

## Milestone 8: Orders and after (0.8.0)

**Builds**, everything under Orders, Admin's On orders and Privacy:

- order display and emails, plain and HTML, with the composed picture and its alt text;
- the Build box on the order screen (HPOS and posts), from the snapshot, with its mismatch warning;
- the assembly ticket and its bulk print, with the layered picture;
- the orders list's Assembly column;
- **saved builds** under My builds:
  - save, rename, delete and open, with nonces;
  - owner-only;
  - re-checked on opening;
- the personal data exporter and eraser;
- account deletion clearing saved builds;
- the suggested privacy policy text;
- the refund help text.

### Tests that must pass

- **Emails** in English and French.
- **Print:** the ticket's print styles.
- **Saved builds:** opening one as a way into the builder; IDOR tests, with another customer's ID and a guessed UUID.
- **Privacy:** the exporter and eraser; account deletion.
- **Platform:** HPOS on, off and syncing.
- **Uninstall:** it keeps order item meta and removes saved builds.

**Done when** the workshop can print a correct ticket for every build order, and a customer can keep, reopen and erase their builds.

**Where the audit looks hardest:** privacy (the inventory, the tools); security (saved builds); internationalisation (emails in the customer's language); upgrades (order data is forever); accessibility (My builds and the admin screens).

---

## Milestone 9: Hardening (0.9.0)

Planned as 1.0.0. On 18 November 2026 Zach moved 1.0.0 to milestone 10, the configurator, so the checks a person makes on the screens, and the API freeze, are made on the front end that ships (D-236). The items marked "moved" are milestone 10's now.

### Builds and checks

- **French:** complete (`.po`, `.mo`, `.l10n.php`). Read by a person: moved.
- **Accessibility:** the manual pass in `docs/A11Y.md`, with VoiceOver and NVDA, by someone who didn't write the code. Moved.
- **Benchmarks** on the generated catalogue (2,000 parts, 500 candidates in one slot; cold and warm; with and without a persistent object cache) and Lighthouse, recorded in `docs/BENCHMARK.md`.
- **Walk-throughs:** security (`docs/SECURITY.md`) and privacy (`docs/PRIVACY.md`).
- **The public API ready to freeze:** `docs/HOOKS.md` complete, the snapshot committed. The freeze: moved.
- **Upgrades:** tested from every 0.x tag; the canary green; a clean Plugin Check; `readme.txt` complete.
- **The full-codebase audit** in `docs/AUDIT-0.9.0.md`, every dimension, every open item named. Milestone 10 writes `docs/AUDIT-1.0.0.md`.
- **A hallway test:** Zach and three people who haven't seen the builder, on their own phones. Moved.
- **The release:** tagged 0.9.0 and published.

**Done when** the audit has no blocker or should-fix open, and Zach approves the release.

---

## Milestone 10: The configurator and 1.0 (1.0.0)

**Builds**, everything in `docs/SPEC-CONFIGURATOR.md` but its Motion section:

- **The layout:**
  - the kit page's full-screen configurator, with the stage, the panel, the step rail and the footer;
  - the block theme's `single-product-cbuild-kit` template, and the classic themes' full-width placement (D-238);
  - the tablet and phone layouts and the phone's sheet and bar;
  - the Builder block at any width;
  - "Back to the shop".
- **Finding a part:**
  - one card per look (variations that share a photo), with sizes picked by the build, grip and hardware included, or chosen after the pick where nothing decides them (D-240);
  - the deck's width first, with no All, and the width guide (`cbuild_width_guide`; D-241, D-242);
  - the order and Sort (D-243);
  - 30 at a time, with the route's `from` argument (D-244);
  - the sold-out fold;
  - the filter chips from the step's own data, with true counts (D-247);
  - search, through `csearch_ranked_ids` or the builder's own (D-248);
  - the tools by the step's size.
- **The steps that appear only when needed:**
  - bearings, skipped for wheels with `bearings_included` (a new spec key);
  - risers, from R3 as built, with "Needs ¼″ risers" on wheel cards, the bolts following R4, Add risers at the review, and over-limit wheels hidden when the kit has no riser that clears them with bolts long enough for it (D-245, D-246).
- **Ways in:** "Build a complete with this deck" (`cbuild_start_button`, D-253); rider builds with the composed picture or a rider photo (D-252).
- **A saved build that has changed:** prices kept at save; the "Your build has changed" panel with the closest replacements and the prices then and now (D-254).
- **The admin screens:** `bearings_included` in Part specs, and Sold in sets opened for parts in a slot of more than one unit (#61); presets with an image, and unchanged presets naming a hidden part no longer reported as not saved (#62).
- **The Build tab's Layout section:** stage side, board size and search (`_cbuild_layout`, D-255).
- **The route's additions:**
  - `from`, `q` and `sort`;
  - the looks and their sizes;
  - chip groups with true counts;
  - `bearings_included`;
  - the changed-build answer.

  The schema only gains; the types are regenerated.
- **Moved from milestone 9 (D-236):**
  - the manual accessibility pass with VoiceOver and NVDA, by someone who didn't write the code;
  - French held by the checks and Cowork's walk in French, not read by a person (D-260);
  - the hallway test, Zach and three people on their own phones, one of them an iPhone, with the route's rate limit checked;
  - the public API frozen, `docs/HOOKS.md` final with the 1.0 snapshot;
  - the full-codebase audit in `docs/AUDIT-1.0.0.md`.
- **CI:** the newest leg split into parallel jobs, with the ruleset's required checks updated to match (D-258).
- **The release:** tagged 1.0.0 and published.

### Tests that must pass

- **The spec's cases, in the domain first:**
  - a look's size picked by the deck's width, by R1, R3 and R4;
  - a wheel's size, and a choice you can't see, chosen after the pick;
  - variations that share a photo making one look;
  - grip's sheet and hardware's nearest length picked by the build;
  - `bearings_included` filling the bearings slot;
  - the closest replacement against the rest of the build;
  - chip counts with the other groups applied.
  - 100 % lines, branches and MSI in `src/Domain`, as always.
- **The route:**
  - `from` pages without gaps or repeats;
  - search through a stubbed `csearch_ranked_ids`, and without it;
  - the 500 cap;
  - its p95 under 80 ms at 500 candidates with search and chips (`composer test:benchmark:integration`).
- **Browser, on Chromium, WebKit and Firefox at desktop, tablet and phone widths:**
  - every way in;
  - the width question;
  - search with and without results, the nearest width offered;
  - Show 30 more and the sold-out fold;
  - chips;
  - the bearings step skipped;
  - the risers step and the bolts following, on the test kit's 54 mm and 56 mm wheels, with bolts long enough for a ½″ riser and without;
  - Add risers at the review;
  - a changed saved build, sold out and repriced;
  - Back, Forward and reload keeping a step's filters, search and place;
  - JavaScript off, fr_CA and right to left;
  - the route forced to fail.
- **Themes:** Twenty Twenty-Five (the block template), Storefront (classic) and the suite's Shop Kit stand-in (a page builder's product template), each with one h1; Kadence checked by Cowork on the demo.
- **Accessibility:**
  - axe on every state;
  - the keyboard to the first deck in at most eight presses;
  - focus after a pick, Show more and the changed-build panel;
  - targets, 200 % zoom, 320 px, forced colours, text spacing;
  - the bar never over the control in focus.
- **Speed:**
  - Lighthouse CI: largest paint under 2.5 s mobile, no layout shift;
  - a pick paints within 100 ms on a 4× slowed CPU;
  - the asset budgets.
- **Snapshots:** visual on Twenty Twenty-Five, Storefront and Kadence's stand-in, dark and light, at three widths, rebaselined on purpose.
- **CI:** a full run in about 40 minutes, with every project, upgrade and snapshot the newest leg ran before (D-258).
- **Upgrades:** from every 0.x release to 1.0.0 (`npm run upgrade:all`), saved builds from 0.8.0 and 0.9.0 opening with no prices kept.

**Done when** a shopper can find a part in a step of 500 as quickly as in one of 20, build, change, save, reopen a changed build, share and buy a board on a phone, a tablet and a desktop with or without JavaScript, by keyboard, touch or screen reader, within every budget, and the 1.0 checks moved from milestone 9 are done, with Zach's approval of the release.

**Where the audit looks hardest:**

- UX and content: Baymard's product-list and configurator research, P1 to P12, screen by screen, with the walkthrough;
- accessibility: the rail, the chips, the sheet, the manual pass;
- performance: the route's paging and search at 500, the largest paint on the stage;
- upgrades and the API: the schema only gains, the freeze, the old saved builds;
- internationalisation: French at phone width, sizes and the width guide;
- resilience: without Creative Search, a failing route, a theme with no block template;
- privacy: nothing stored on the device, the search not logged by the builder.

---

## Milestone 11: The motion (1.1.0)

**Builds**, the Motion section of `docs/SPEC-CONFIGURATOR.md`:

- the ten moments in its table, through view transitions and the Web Animations API, each cut short by a new pick;
- reduced motion and the Motion setting's subtle as cross-fades (D-256);
- the counting total (D-257);
- Add to cart aiming at the Mini-Cart's button.

### Tests that must pass

- **Each moment:** its end state as a visual baseline; its duration, and its fade under reduced motion and subtle, read from the animation's own timing in the browser test.
- **Speed:**
  - a pick still paints within 100 ms on a 4× slowed CPU;
  - Interaction to Next Paint under 200 ms;
  - no layout shift;
  - a performance trace of each moment in CI;
  - the script budget.
- **Accessibility:**
  - the total said once;
  - the counting frames hidden;
  - nothing moves under reduced motion;
  - focus never lost in a transition.
- **Engines:** Firefox's view transitions without types; a browser without view transitions changes at once.

**Done when** every pick, swap and step change shows the board being built within its time, and a shopper who asked for less motion sees fades alone.

**Where the audit looks hardest:** performance (the main thread during a moment), accessibility (reduced motion, the counting total), UX (motion that explains and never waits).

---

## After 1.0

### Companion releases

Each is a small release of its own, once the hooks it needs are in a tagged build:

| Plugin | Who builds it | What it does with the builder |
| --- | --- | --- |
| Creative Checkout | Claude in Cowork | Answers `cbuild_arrival_estimate` and `cbuild_delivery_line`; adds the assembly lead time wherever a cart or order holds an assembled build, Apple Pay's and Google Pay's sheets included; adds an assembly step to "What happens next" |
| Mature Content | Claude in Cowork | Blurs a flagged part on its card, in the picture's layers and in the review, through `cbuild_part_classes` and `mcw_image_selectors`; from 1.3.0 that includes the review's thumbnails (#132) |
| Creative Shipment Tracking | Claude Code | Shows a build under the kit's name in "In this parcel" |
| Creative Reviews | Claude Code | Leaves the kit out of review requests. Its `creviews_should_send_request` (1.0.3) decides per order, so this needs a per-line filter in a Reviews release. The ratings shown in each step (#137) need nothing from it: its reviews are WooCommerce's own, read through the product |
| Creative Loyalty | Claude Code, from its own brief (Phase 3) | Points per part line; a points line under the builder's total through `cbuild_summary_notes` |

Creative Schema needs no release: a kit prints no product markup, and from 1.3.0 a browser test holds the kit's page to carrying none, so Schema's markup can't reach it unnoticed.

### Skatehouse's setup

Following What Skatehouse needs in the spec:

- the kit in the Completes category;
- the part specs imported, and the Catalogue check clean;
- the Builder block on /build-a-complete/;
- "Build a complete with this deck", placed by the plugin from 1.0.0 (D-253), so Skatehouse Core needs no button of its own;
- the presets approved by the owner;
- the deck cut-outs, then trucks, wheels, risers and grip shot to `docs/PICTURE.md` as they arrive;
- a build-and-checkout test in Skatehouse's own end-to-end suite.

One build is then paid for on the demo through Stripe's test mode, by card and by Apple Pay, with Zach paying.

### Module A: your own graphic

Waits on the owner's seven answers (`UX-RESEARCH.md`). It gets its own brief and milestones:

- **A1. Uploads.** Private storage, re-encoding, limits, retention and the privacy tools.
- **A2. The editor and its checks.**
  - Place, scale and rotate by buttons and by drag.
  - Fit to deck and fill to edges.
  - Live resolution at print size.
  - The template per shape and width.
- **A3. Proof, rights and approval.**
  - The proof, and the shopper's two confirmations.
  - The owner's approval or refusal (with a refund) in the admin.
  - The print file at full resolution with bleed, in the supplier's format.
- **A4. Hardening.** As milestone 9, for the module.

The deck layer of the picture already takes any image, so the shopper sees their graphic on their board with no change to the picture.

### Module B: 3D on a tap

Replaced by milestone 14's 3D view, generated from each part's sizes with no model files, on three.js (Zach, 24 Nov 2026; `docs/research/plan-after-1-2.md`). Kept below for its history.

Waits on trucks being stocked and the owner paying for models (US$1,400 to $10,400). It builds on what 1.0 already has:

- A third renderer behind the picture's interface.
- **Assets:**
  - deck meshes generated per shape family from width, length, wheelbase, nose, tail and concave, with the graphic laid on;
  - one truck model per brand line, scaled to axle width;
  - one wheel model per shape.
- **Loading:** model-viewer, loaded only when the shopper taps "See it in 3D".
- **Controls:** keyboard and buttons as well as drag, no auto-rotate, the written description kept, French strings.

### Later ideas, not planned

- **Dealer prices** (Phase 4, with the wholesale build), through the price context the card cache is already keyed on, once the owner's trade terms arrive (open question 7).
- **Structured data for the builder page** (a price range from the presets), once Google's rules for configurable products are checked.
- **A "Surprise me" preset** that picks a random build that fits and is in stock.
- **A fit helper** that answers fit questions from the plugin's own compatibility data. It needs an AI provider, a monthly cost and a privacy position; it has no budget (Zach, 24 Nov 2026).
- **Presets side by side.**
