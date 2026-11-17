# The project around Creative Skateboard Builder

The whole picture, so the builder fits the shop it's built for and the plugins it sits beside. The builder knows skateboards (its rules and spec tables) but no one shop: nothing in its code knows about Skatehouse beyond what a kit's settings and other plugins' hooks tell it. Everything here is context. The rules for the code are in `SPEC.md`, `STANDARDS.md` and `AUDIT.md`.

## Who

- **Creative Branding** is Zach Atkinson's agency in Canada. It builds WooCommerce shops and the plugins they run on, and ships those plugins on every shop it builds. Zach approves each milestone, installs releases on the demo, and makes any test payment himself.
- **Skatehouse Distribution** is the client: a skate distributor in Barrie, Ontario, selling to shoppers across Canada and the US, and to dealers (skate shops) at trade prices. A separate business owner makes the business calls: assembly, returns, dealer terms, which parts to stock. Those go through the owner questions doc, never into code as guesses.
- **Claude Code** builds the agency's larger plugins in their own repositories under `~/web-projects/`: Creative Search, Stockroom, Restock, Shipment Tracking, Reviews, and now Creative Skateboard Builder.
- **Claude in Cowork** does the rest:
  - writes the specs and briefs;
  - reviews each milestone and checks it on the demo;
  - builds the agency's smaller plugins;
  - keeps the project's documents current.

## The shop

| | |
| --- | --- |
| Live site today | skatehousedistribution.com, the shop being replaced |
| The new shop | Built on the demo at zachatkinson.com/demos/skate, moving to the live address once Phases 1 to 4 are done |
| Platform on the demo | WordPress 7.1.4, WooCommerce 11.2.2 with HPOS, PHP 8.5 |
| Theme | Kadence, Kadence Pro and Kadence Shop Kit. Shop Kit's checkout (Flux) is WooCommerce's classic checkout, kept on purpose. Kadence Blocks 3.9.0; WooCommerce's Interactivity API Mini-Cart in the header |
| Payments | Stripe (cards, Apple Pay, Google Pay) and PayPal, in test mode until launch |
| Around it | Yoast SEO, WP Super Cache (page cache), MaxMind geolocation; WooCommerce Brands; English (Canada), with French built and switched off until Phase 5 |
| Host | cPanel, with no SSH or WP-CLI on the demo. Anything the shop needs has to work from wp-admin |
| Catalogue | About 300 products across 24 brands: 160 deck variations with widths, wheels (52 to 58 mm), Philly's hardware, grip sheets and strips, apparel and accessories. No trucks, bearings or risers yet (owner question 13). Every product and variation has a SKU (BRAND-TYPE-STYLE plus size or colour) |

## The phases

| Phase | What | Where it stands (9 Nov 2026) |
| --- | --- | --- |
| 1. Foundation | Store settings, the brand system, templates, header and footer, the homepage, performance and security, the end-to-end suite | Complete (26 Oct) |
| 2. Catalogue and launch | The full catalogue, filters, search, size guides, brand pages, reviews, tracking, back-in-stock, payments, the go-live checklist | Mostly done ahead of its formal start; what's left waits on the owner and the move |
| 3. Builder and loyalty | **Creative Skateboard Builder** (this plugin), and Creative Loyalty, the agency's own points plugin | Starting with this brief |
| 4. Dealer portal | A dealer login that switches the same catalogue to net prices, pack sizes and the pre-book calendar; order sheets and reorder | Researched; waits on the owner's trade terms |
| 5. French site | The shop in French, after launch | Built, switched off |

The order we recommended: the builder first, then the dealer portal, then Creative Loyalty. The builder is a large job that shoppers will notice, and it needs nothing from the other two. The homepage's builder band and the menu's Build a complete link already point at /build-a-complete/, so nothing else moves when it ships.

## The agency's plugins on the shop

The builder calls none of these directly. Where they meet, the builder offers a hook and the other plugin answers it, so the builder stays usable on a shop without them.

| Plugin | What it does | Where it meets the builder |
| --- | --- | --- |
| Creative Checkout (Cowork) | Delivery dates (cut-off, holidays, each zone's business days), the cart's estimate and free-shipping meter, the order confirmation | Answers `cbuild_arrival_estimate` and `cbuild_delivery_line`, as it answers Shipment Tracking's `ctracking_expected_delivery`; adds the assembly lead time and an assembly step to "What happens next" (a companion release) |
| Mature Content for WooCommerce (Cowork) | Blurs flagged products and designs (`mcw_is_flagged`, `mcw_image_selectors`, the `mcw-mature` class) | Adds its class to a flagged part through `cbuild_part_classes`, and the builder's image selectors to `mcw_image_selectors` (a companion release) |
| Creative Shipment Tracking (Claude Code) | Tracking numbers, the tracking page and "In this parcel" | Groups a build's parts under the kit's name, reading the build's order item meta (a companion release) |
| Creative Reviews (Claude Code) | Review requests 10 days after an order completes | Leaves the kit out, reading the line's role (a companion release) |
| Creative Loyalty (Claude Code, Phase 3) | Points | Points per part line; a points line under the total through `cbuild_summary_notes` |
| Creative Stockroom (Claude Code) | Stock shared by printed decks and blank boards | Nothing: it keeps each deck's stock right in WooCommerce, which the builder reads |
| Creative Restock (Claude Code) | Notify me on sold-out products | A sold-out part links to its page, where Notify me waits |
| Creative Search (Claude Code) | The shop's search | The kit stays out of its results; the Build a complete page is found by its title |
| Creative Schema (Cowork) | Product markup, shipping and returns policy | The kit prints no product markup |
| Creative Shop, Creative Colour Cards (Cowork) | Shop lists, category guides, cards with colour rows | The kit isn't listed |
| Creative Hardening (Cowork) | Security headers, login limits | The builder works under its headers (checked on the demo) |
| Creative Forms, Creative Media, Creative Rail (Cowork) | Form validation, image handling, the Rail block | Nothing |
| Kadence Interim Fixes, Interim Fixes for WooCommerce (Cowork) | Workarounds for Kadence's and WooCommerce's bugs, each until fixed upstream | Nothing; if the builder trips on one of those bugs, the fix goes there, not into the builder |
| Skatehouse Core 2.x (Cowork) | The site's own settings and copy | Places "Make it a complete" on deck pages; answers the builder's hooks for anything only Skatehouse wants |
| Skatehouse French (Cowork) | The French site, switched off | Answers `cbuild_slot_label`, `cbuild_preset_label` and `cbuild_kit_text` when French goes live |

## How the work runs

- **Repository:** `~/web-projects/creative-skateboard-builder`, private on GitHub as `zachatkinson/creative-skateboard-builder`, GPL-2.0-or-later (D-003).
- **Local:** wp-env on ports 8898 and 8899 (8888 to 8897 belong to the other projects). Docker runs through OrbStack.
- **CI:** GitHub Actions, as in Creative Search.
- **Releases:** a version tag publishes the zip and its manifest to `zachatkinson/creative-releases`. Shops update from there through the plugin's updater. Zach installs releases on the demo himself.
- **Review:** each milestone's release, its pull request and its summary go to Claude in Cowork once it is merged and tagged. It reviews the code against the spec and `AUDIT.md`, checks what shows on the demo, and lists blockers, should-fixes and minors with file and line. Its fixes go into the next milestone (D-223).
- **The demo's own tests:** Skatehouse has a Playwright suite run against the demo. After Skatehouse's setup it gains a build-and-checkout test.

## Standing rules that reach the repository

- **Claude Code never touches the demo or the live site.** It works in wp-env and CI only. Anything on the demo is Zach's to install and Cowork's to check.
- **No credentials in the repository.** No credential, key or token in code, tests, fixtures or docs. Secrets live in GitHub's secrets (`RELEASES_TOKEN`). Tests use made-up data and `shopper+N@example.com` addresses, and no test sends real email.
- **Nothing shop-specific in code.** No Skatehouse names, colours or rules. Shop choices are settings or another plugin's answer to a hook.
- **Copy rules.** Plain words. No em dashes, superlatives or templated constructions (`STANDARDS.md`).
- **Baymard is a standing guide.** Every screen is checked against it (`AUDIT.md`, UX and content).
- **World-class UX, with modern motion that helps.** Current (2026) practice in design, development and UX.
- **Ask before:**
  - adding a dependency;
  - changing a table after milestone 1;
  - renaming anything public;
  - moving a floor;
  - anything that belongs to the owner.

## The documents

Living documents in Claude, which Zach can open. Claude Code can't, which is why the repository's `docs/` stand on their own.

| Document | What it holds |
| --- | --- |
| [Creative Skateboard Builder spec](https://claude.ai/artifact/A9USQwiUNUQHDHHZEbwKTf) | The source of `docs/SPEC.md` |
| [Creative Skateboard Builder: UX deep dive](https://claude.ai/artifact/447cwcRifAicW4B7BRMgwo) | The source of `docs/UX-RESEARCH.md` |
| [Skatehouse status](https://claude.ai/artifact/4uVJrrVWLveDUQGjyvcjar) | Where every phase stands, and what's next |
| [Skatehouse: questions for the owner](https://claude.ai/artifact/NtszoXMTEDsu9GRekKyGbH) | The owner's open decisions, numbered (the builder waits on 13 and 17 to 19) |
| [Go-live checklist](https://claude.ai/artifact/MhF4s6usryv4ZHyZV67hgJ) | Everything before the move to the live address |
| [Baymard check](https://claude.ai/artifact/MZv4jNjku25vjLMPJMC199) | The shop checked against Baymard's public guidelines |
| [Wholesale research](https://claude.ai/artifact/WszohRWp2PGsHYud9HiUW5) and [loyalty](https://claude.ai/artifact/MgLjR6D4tppqJNzo1AS64E) | Phases 4 and 3's other halves |
