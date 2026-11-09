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
