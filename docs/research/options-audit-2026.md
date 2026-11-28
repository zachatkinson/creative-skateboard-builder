# The options audit: what a shop might want to change (for milestone 15)

Researched on 27 November 2026, during milestone 15, for #170, steps 1 to 3: the inventory, the benchmark and the sort. Read on `m15-admin` at 1.4.0 plus docs (head `c3f64da`). It starts from the draft of 26 November, read on `64b8131`, and refreshes every reference against this tree. Nothing in the code was changed. Step 4, building what earns a place, belongs to the screens it lands in.

- [The test](#the-test)
- [What is configurable already](#what-is-configurable-already)
- [What changed since the draft](#what-changed-since-the-draft)
- [Benchmark](#benchmark)
- [The sort](#the-sort)
- [Summary table](#summary-table)
- [For Zach](#for-zach)
- [Sources](#sources)

## The test

WordPress's "decisions, not options" applies. A candidate earns a home only if all three hold:

1. A real shop would want it different, not only a theme author.
2. No default can be right for every shop.
3. The cheapest home that works is used: a CSS custom property before a hook, a hook before a setting, and a kit setting only where kits differ.

Two standing rules shape the answers:

- **Feature switches** (Zach, 25 November): each optional shopper-facing feature gets a checkbox on the kit's Build tab, kept in `_cbuild_settings`, on by default and on for kits saved before it. Layout changes get no switch, and Compare gets none (it is account-wide, signed-in only and opt-in by saving).
- **Updates change nothing.** Every new setting defaults to 1.4.0's behaviour, and a heavy feature is on for new kits and off for old ones (D-476).

Wording is a special case. Every string is in the text domain, so a shop can always reword through a translation or `gettext`. A string earns more only where it is the shop's own voice to its shoppers, such as its button or its step headings.

## What is configurable already

- **Shop settings** (`cbuild_settings`, WooCommerce > Settings > Builder; SETTINGS.md): the attribute mapping and which source wins, the build discount and fixed coupons, the rate limit and the trusted proxy header, and new-kit defaults for assembly (default on, fee, lead time and returns notice).
- **Kit settings** in the Build tab: slots (label, help, units, sources, default part, required); rules R1 to R8 with their tolerances; up to six presets and Show ready builds; assembly; the picture's layer order and wheelbase; Layout (stage side, board size, search, motion); Where shoppers find it (Builder page, the start button, smart swaps); Shown in the builder (Help me choose, picks on cards, ratings on cards, and from 1.4.0 the 3D view and its trick buttons, `BuildTab.php:1107-1150`); the width guide's rows and the wheel guide's sizes.
- **Hooks** (HOOKS.md): labels, kit texts (`cbuild_kit_text`: `slot_help`, `preset_description`, `lead_time`, `returns_notice`, `width_guide_shoe`), part classes, the composed layer, the scene (`cbuild_scene`, which may set a part's `color`), summary notes, delivery and arrival words, smart swaps, the width guide, the guided start, the three card and guide switches, and the start button with its text.
- **CSS custom properties:** 26 that a theme sets, listed in HOOKS.md "Styling the builder" (`HOOKS.md:906-933`), `--cbuild-3d-ground` among them since 1.4.0.

## What changed since the draft

- **Shipped in 1.4.0:** the 3D view's ground as `--cbuild-3d-ground` (D-500, `style.scss:3162`), and the Build tab's 3D view and Trick buttons switches (D-474, D-476, `BuildTab.php:1122-1129`). The canvas is now clear, so no colour is set in script (`three/index.ts:66`).
- **Line numbers moved** throughout `BuilderView.php`, `stage.ts` and `parts.ts`; the table below has today's.
- **Found true on a re-read:**
  - the 3D view already takes a truck's and a wheel's colour from the scene (`parts.ts:205`, `:273`), which `cbuild_scene` can set, so the draft's L2 has its hook;
  - D-244 says the step's page grew from 24 to 30, but `StateAnswer::SHOWN` (`StateAnswer.php:72`) and `BuildLink::PAGE` (`BuildLink.php:63`) are still 24 beside `Shelf::SHOWN`'s 30 (`Shelf.php:52`). Neither is public API (not in `tests/Fixtures/api-surface.json`), so M1 is a plain fix;
  - the deck step already shows the deck slot's Help (`BuilderView.php:1924-1925`) beside the fixed lead (`:1927`), so the draft's C3, "Help in place of the lead", would drop the lead from every live kit whose deck slot has Help. It becomes a hook instead;
  - WooCommerce's own Duplicate copies every meta key a filter does not exclude (`class-wc-admin-duplicate-product.php:134-160`, WooCommerce 11.3.1), and the plugin excludes none, so a duplicated kit should already carry its slots, rules and settings. That answers most of the draft's A1;
  - the review always offers Add assembly or Remove assembly (`BuilderView.php:2530-2533`, `templates/builder/review.php:40-50`), so a shop that never assembles can't hide it today.
- **New candidates** this pass: the sold-out fold against WooCommerce's "Hide out of stock items", part prices in the cart, prices on cards, Edit build in the cart, the My builds address, the import preview's 200 rows and the tickets' 100 at once.

## Benchmark

**Key:** PB is Product Bundles, CP Composite Products, PAO Product Add-Ons, WPC WPC Product Bundles, MKL MKL Product Configurator, STG Staggs, ZAK Zakeke, 3DV 3D Viewer by bPlugins. Each source is listed with its address and the day it was read under [Sources](#sources).

### What the leading builders let a shop set

- **Step and item wording.** PB has Override Title and Override Short Description per bundled item [PB]. CP gives each component a Name, a Component Description and a Component Image, which head its step [CP]. PAO formats each field's Title as a label, a heading or hidden [PAO]. WPC sets the Add to Cart and Checkout button texts [WPC]; MKL relabels its Configure and Request a Quote buttons [MKL].
- **Order of options.** CP's Options Sorting lets shoppers sort by popularity, latest, rating or price. The default order is the order products were picked in, and sorting by menu order is a snippet on the `woocommerce_composite_component_default_orderby` filter, not a setting [CP], [CPS], [CPG]. WooCommerce's own shop has one shop-wide Default product sorting, whose default is "Default sorting (custom ordering + name)", the menu order [WC1].
- **Page size.** CP's Options Pagination is Classic or Load more; the number per page and the columns are snippets [CP], [CPS].
- **Price display.** CP's Option Prices are Absolute, Relative or Hidden, with Subtotal Visibility in the product, the cart and the order [CP]. PB has Price Visibility in the same three places [PB]. PAO shows only flat fees in the cart unless a filter says otherwise [PAO].
- **Assembly.** CP's Composite Type is Assembled or Unassembled, and a component can be Optional [CP]. The spec's own table of skate shops has every pattern: free with a deck, $6 or "Do Not Assemble", a yes or no, and never assembled (SPEC.md:151-156).
- **Visibility in the cart, the order and the emails.** PB's Visibility is set per item for the product page, the cart and the order details, emails included [PB]. WPC added "an option to hide bundled products on the order details (order confirmation or emails)" in 7.2.6 [WPC]. CP hides components in every template with a snippet [CPS].
- **Edit in the cart.** CP's Edit in Cart is a per-product setting [CP]; MKL users ask about the "Edit configuration" link [MKL-S2].
- **The 3D viewer.** STG sets the initial viewpoint and camera target, shadow intensity and softness, view limits, auto rotation with a delay and speed, the environment image as background, its exposure from 0 to 2, and AR [STG]. ZAK saves cameras that move the view as the shopper opens a step, and its Default camera sets FOV (10° to 120°), zoom limits, rotation limits and an auto rotate that runs "until the customer touches it" [ZAK]. 3DV sets exposure, shadows, an environment and HDR skybox, the initial view, zoom level, auto-rotate, rotation locks and a background colour [3DV].
- **Account pages.** WooCommerce lists every account endpoint's slug under Settings > Advanced > Account endpoints (`class-wc-settings-advanced.php:285-362`), and an extension can add its own there through `woocommerce_settings_pages` (`:366`) [WC3]. WooCommerce's Order Subscriptions puts its two endpoints in that list and registers them with WPML and Polylang [OS].

### What their users ask for

- **Button wording:** "Add to cart button label" and "'Done and Back' button instead add to cart" [MKL-S2], [MKL-S3].
- **Less in the emails:** "Disable 'View Configuration' link on order email" [MKL-S2]; "Bundled product: don't want emails sent" and "Hide Prices for Bundled Products everywhere except inside the order?" [WPC-S1]; "Email template" [MKL-S1].
- **Less in the cart:** "Hide bundled products on carts" [WPC-S1].
- **The 3D view's look and camera:** "Different background images" [MKL-S1]; "How custom angels works", "Zoom Level – Not displaying", "Zoom in possible?" and "hide progress bar" [3DV-S].
- **Order of options:** the menu-order snippet exists because shops ask for it [CPG].

Nothing found asks for a shop's own step order beside the kit's slots (already the owner's), for more sorts, for a different page size in a builder, or for the number of presets.

## The sort

Each item keeps the draft's number where it had one; new items carry the next. "Fixed at" is today's tree.

### Kit settings (five)

- **C1. A question per slot.** The step's heading is fixed for every named kind: "Choose your deck" and the rest (`BuilderView.php:1524-1535`), whatever the kit's label says, so a slot labelled "Complete deck" is headed "Choose your deck". An optional **Question** beside Label and Help, empty for today's translated words, and `cbuild_kit_text` answers it as `slot_question`. Evidence: CP's component Name heads its step [CP]; PB's Override Title [PB]. It is a new key in the slot document, not a table.
- **B6. Who decides assembly.** Today the shopper always may add or remove it (`BuilderView.php:2530-2533`). A kit choice, **Assembly: the shopper chooses (today), always assembled, or never assembled**. Always hides the toggle and keeps the fee on; never hides it, the fee, the lead time and the returns notice. Evidence: CP's Assembled or Unassembled [CP]; the spec's shops (SPEC.md:151-156).
- **T2. Where the 3D view opens.** Today the reset place, three-quarters from the build's side (`stage.ts:130-131`). A kit choice, **The 3D view opens on: the build (today), the graphic or the top**, reusing the places the buttons already name and test. Evidence: STG's initial viewpoint [STG]; ZAK's Default camera [ZAK]; 3DV's initial view and custom angles [3DV], [3DV-S]. No FOV: 30 is there so the board's proportions read true (`stage.ts:69-70`).
- **S1. Save build,** a switch on by default. Today it is always offered, with "Sign in to save this build" for a visitor (`BuilderView.php:2703-2709`, `BuilderPage.php:177`). Off, the review shows neither, and My builds keeps the builds already saved. Evidence: the feature-switch rule; a shop with no customer accounts has no use for a sign-in prompt.
- **S2. Link to this build,** a switch on by default (`BuilderView.php:2700-2703`). Off, the review shows no link or Copy link; a link already shared still opens, since the build link is also how the page itself works without JavaScript. Evidence: the feature-switch rule.

### Shop settings (two)

- **B1. The Recommended order.** Today the step's recommendation, then featured, then best-selling, then name; the menu order is ignored (`Shelf.php:263-276`). A choice, **Recommended order: best-selling first (today) or the shop's own order**, the menu order, which the Products screen's Sorting sets. A shop setting, not a kit's: merchandising is the shop's, and the menu order is already per product. Evidence: WooCommerce's own default is the menu order [WC1]; CP's menu-order snippet [CPG].
- **A4. The My builds address.** Fixed as `my-builds` (`SavedBuildHooks.php:48`). A field in WooCommerce's own Advanced > Account endpoints list, default `my-builds`, so a French shop can have `mes-planches`. The `cbuild_endpoints` option already makes the rewrite rules again when the endpoint changes (HOOKS.md:349). Evidence: WooCommerce's endpoints [WC3]; Order Subscriptions does the same [OS]. Lowest priority of the list; it touches no builder screen.

### CSS custom properties (one)

- **L3. `--cbuild-maple`.** The board top's maple is `#dcbf8f` in the stylesheet (`style.scss:1687`) and the same in the 3D view's colours (`three/parts.ts:38`). One property, default `#dcbf8f`, read by the 3D view when it opens as the ground is read by the frame. Evidence: none of the benchmarks needs a wood colour; it is cheap, and keeps 2D and 3D in step for a shop selling stained or black-ply decks. The 3D read needs `getComputedStyle` once per open, which the store does not do today: a small cost to record in the decision.

### Hooks (three new)

- **C2. Add to cart's words.** "Add to cart" (`BuilderView.php:2693`). `cbuild_kit_text` gains `add_to_cart`, with HOOKS.md giving a snippet. A filter, not a setting, because WooCommerce's own button is changed by filter (`woocommerce_product_single_add_to_cart_text`, `abstract-wc-product.php:2269`) or translation. Evidence: WPC and MKL set button text [WPC], [MKL]; MKL's users ask [MKL-S2], [MKL-S3].
- **C3. The deck step's lead.** "Start with a width. Every later step offers only what fits the deck you choose." (`BuilderView.php:1927`). `cbuild_kit_text` gains `step_lead` (`$of` the slot's key), empty taking the lead away. Not the draft's "Help in place of the lead": that would change every live kit whose deck slot has Help. Evidence: CP's Component Description [CP].
- **E1. The build's picture in order emails and order views.** Always drawn (`EmailHooks.php:74`, `:109-138`). `cbuild_email_picture` (bool, default true, with the order and the email's ID). Few shops will turn it off, so a filter. Evidence: PB's order-details visibility [PB]; WPC's option to hide items in emails [WPC]; MKL's and WPC's users [MKL-S2], [WPC-S1].

Three items already have their hook and need no change: **L2**, the untextured parts' colours (`parts.ts:37-45`; trucks and wheels take `color` from `cbuild_scene`, `parts.ts:205`, `:273`); **C5**, the start button's text (`StartButton.php:150`, `cbuild_start_button`); and **E4**, the assembly ticket's specs and steps (`cbuild_ticket_specs`, `cbuild_ticket_steps`).

### Fixes, not options (six)

- **L6. The 3D frame's corner** is `0.25rem` (`style.scss:3160`, `:3171`) while everything else follows `--cbuild-radius`. Use `calc(var(--cbuild-radius) / 2)`, which is `0.25rem` at the default, so nothing changes until a theme sets the radius.
- **M1. Two page sizes.** The shelf shows 30 looks (`Shelf.php:52`, D-244) while the route's own list and the build link still page at 24 (`StateAnswer.php:72`, `BuildLink.php:63`). Make them one constant, or record why they differ. Neither is public API.
- **E2. The shop's own emails** carry the build's lead time and returns notice (`EmailHooks.php:151-152`: `unset( $admin )`). Leave the returns notice out where `$admin` is true. Keep the lead time: the workshop reads it as the promise made. Evidence: PB's order-details visibility [PB].
- **M6. The check's and the import's cuts:** 500 rows per table on the Catalogue check (`CheckScreen.php:42`) and 200 on the import's preview (`ImportScreen.php:45`). Both go with M15's paged DataViews tables (screens 4 and 5). The import's 2 MB and 5,000 rows (`SpecImport.php:45-46`) stay.
- **A2. Blank lines that grow by saving:** 3 on the settings' mapping (`SettingsPage.php:43`) and 3 on a chart (`SpecTablesScreen.php:42`). They go with M15's modals.
- **A1. A new kit from an existing one.** Not a "New kits start from" setting: WooCommerce's Duplicate already copies a product's meta (`class-wc-admin-duplicate-product.php:134-160`) and the plugin excludes none. The fix is a test that a duplicated kit keeps its slots, rules, presets, layout and settings, a sentence in SETTINGS.md's New kits help, and a decision on the copy's Builder page, which names the original's page and so falls back to the copy's own until it is changed.

### Kept fixed, each with its reason

- **L4. 3D lighting** (`stage.ts:208-211`). An exposure slider is a setting nobody sets well; the lights read on Twenty Twenty-Five, Kadence and Storefront, light and dark (D-500). STG and 3DV offer exposure [STG], [3DV]; their models are a merchant's own, ours is drawn from specs and is always the same kind of object.
- **C4. The builder's other words:** "Start from a ready build" (`BuilderView.php:489`), "Help me choose" (`:545`), "Your build" and "Check your build" (`:2524`, `:2669`). Generic words a translation rewords; each feature already has its switch.
- **C6. The 3D view's note and buttons** (`templates/builder/three.php`). "Sizes true to scale, shapes simplified." is a claim the view keeps; it must not be reworded into a false one.
- **E3. Cart refusals and notices** (`src/Cart/Refusals.php`, `src/Front/Words.php`). Plain facts, reworded by translation.
- **B2. The sorts offered:** recommended, price both ways, newest (`ShelfAsk.php:23`). Four is the WooCommerce norm, and CP's list is much the same [CP].
- **B3. When Sort, chips and search appear:** from 13 and 41 looks (`BuilderView.php:49`, `:54`). The spec's "Tools that grow".
- **B4. The guided start's riding styles and tiers** (`RidingStyle.php:20-23`, `Tier.php:21-24`). Their sizes are already settings (the wheel guide). Styles of a shop's own are a feature for later, not an option.
- **B5. Smart swaps' two kinds,** for less and better, under one switch (`Swapper.php:57`). A split doubles the states for one ask nobody has made.
- **B7. Undo on a deleted saved build:** 15 minutes (`SavedBuildHooks.php:147`, D-275).
- **B9. Prices on cards are the part's own** (`BuilderView.php:1805`), never relative to the recommendation. Each part is its own cart line at that price; the smart swaps already say "for less" with the difference. CP's Relative and Hidden exist for a kit priced as one product [CP].
- **B10. Each part's price in the cart** (`ShowHooks.php:116-117`). The spec makes each part its own line for stock, tax and refunds; hiding a charged line's price makes the cart's sums unreadable. PB and WPC can hide them [PB], [WPC-S1] because their bundles can be priced as one.
- **B11. Edit build on every kit line** (`ShowHooks.php:301`). CP makes it a setting [CP]; here it is how a shopper changes a part, since a part's line can't be changed alone.
- **M2. Six presets per kit** (`Kit.php:23`). A layout limit of the tiles.
- **M3. 100 saved builds a customer, three compared** (`SavedBuildStore.php:31`, `CompareView.php:50`, D-217). Three columns is what a phone shows.
- **M4. 20 slots a kit, 99 units a slot, four brand chip groups** (`Slots.php:26`, `Slot.php:21`, `Chips.php:39`).
- **M5. The composed pictures' limits:** 50 waiting, 200 MB, 30 days (SETTINGS.md:108, D-197, D-198, D-205).
- **M7. 100 tickets printed at once** (`TicketPage.php:46`). One print run; a larger batch is a second one.
- **T3. Zoom limits** of 150 to 4,000 mm (`stage.ts:223-224`). Every board is about the same size. ZAK and STG offer them because their models vary [ZAK], [STG].
- **T4. A camera move of 500 ms** (`stage.ts:67`), instant where the shopper asks for reduced motion.
- **T5. No auto-rotate.** STG, ZAK and 3DV all offer it [STG], [ZAK], [3DV]. It moves unasked, needs a pause control past five seconds (WCAG 2.2.2) and fights the shopper's own drag; a builder's view is opened to look at a build, not to catch the eye. Record it as a decision so the ask has an answer.
- **T8. The pixel ratio's cap,** 2, or 1.5 on a device with little memory (`stage.ts:83`).
- **S3. No Compare switch.** Zach decided it on 25 November: account-wide, signed-in only, opt-in by saving.
- **S4. No Top and Underside switch** (`BuilderView.php:294-300`). The board's two sides are the picture's layout, which gets no switch: each step opens on the side its part is seen from, so without the top the hardware and grip steps would show a board their parts aren't on. A deck with no layer image has no top already.
- **S5. No switch for "A complete board from about"** (`BuilderView.php:2543`). It is information, not a feature: the full price before the build is complete (D-213), and it goes once every required slot is picked.

### For the owner

- **B8. The sold-out fold** (`Shelf.php:171`, `BuilderView.php:1474-1477`) ignores WooCommerce's "Hide out of stock items from the catalog" (`woocommerce_hide_out_of_stock_items`, `class-wc-settings-products.php:355-360`, read by `class-wc-query.php:985`). The plan calls following it a fix. It is not one by the rules here: a shop with that box ticked would see its builder change on update, and the spec keeps sold-out parts in their slot so Restock's Notify me can be reached (SPEC.md:90, :442). Recommendation: keep the fold, say in SETTINGS.md that the builder shows sold-out parts whatever that box says, and leave hiding them to `cbuild_part_available`.

## Summary table

| # | Item | Fixed at | Home | Reason | Evidence |
| --- | --- | --- | --- | --- | --- |
| L1 | The 3D view's ground | `style.scss:3162` | Shipped in 1.4.0 (`--cbuild-3d-ground`) | D-500 | [STG], [3DV], [MKL-S1] |
| T6 | View in 3D switch | `BuildTab.php:1122-1125` | Shipped in 1.4.0 (kit) | D-474, D-476 | Feature-switch rule |
| T7 | Trick buttons switch | `BuildTab.php:1126-1129` | Shipped in 1.4.0 (kit) | D-474 | Feature-switch rule |
| C1 | Step heading per slot | `BuilderView.php:1524-1535` | Kit setting (slot Question) and `cbuild_kit_text` `slot_question` | The heading contradicts the kit's own label | [CP], [PB] |
| B6 | Who decides assembly | `BuilderView.php:2530-2533` | Kit setting: shopper chooses, always, never | A shop that never assembles still offers it | [CP], SPEC.md:151-156 |
| T2 | Where the 3D view opens | `stage.ts:130-131` | Kit setting: the build, the graphic or the top | Reuses named places; no FOV | [STG], [ZAK], [3DV], [3DV-S] |
| S1 | Save build | `BuilderView.php:2703-2709` | Kit switch, on | Optional feature | Feature-switch rule |
| S2 | Link to this build | `BuilderView.php:2700-2703` | Kit switch, on | Optional feature | Feature-switch rule |
| B1 | Recommended order | `Shelf.php:263-276` | Shop setting: best-selling or menu order | Merchandising no default serves | [WC1], [CPG], [CPS] |
| A4 | My builds address | `SavedBuildHooks.php:48` | Shop setting in Account endpoints | WooCommerce lists every endpoint there | [WC3], [OS] |
| L3 | Board top's maple | `style.scss:1687`, `three/parts.ts:38` | CSS `--cbuild-maple` | Stained or black-ply decks; 2D and 3D agree | None found |
| C2 | Add to cart's words | `BuilderView.php:2693` | Hook: `cbuild_kit_text` `add_to_cart` | WooCommerce's own button is a filter | [WPC], [MKL], [MKL-S2], [MKL-S3] |
| C3 | The deck step's lead | `BuilderView.php:1927` | Hook: `cbuild_kit_text` `step_lead` | The draft's way changes live kits | [CP] |
| E1 | The picture in emails | `EmailHooks.php:109-138` | Hook: `cbuild_email_picture` | Few will turn it off | [PB], [WPC], [MKL-S2], [WPC-S1] |
| L2 | Untextured parts' colours | `three/parts.ts:37-45` | Hook, already there (`cbuild_scene`) | Trucks and wheels take `color` | [MKL], [ZAK] |
| C5 | Start button's text | `StartButton.php:150` | Hook, already there | `cbuild_start_button` | [WPC] |
| E4 | Ticket's specs and steps | `cbuild_ticket_specs`, `cbuild_ticket_steps` | Hook, already there | The workshop's order is code-adjacent | None needed |
| L6 | 3D frame's corner | `style.scss:3160`, `:3171` | Fix: half of `--cbuild-radius` | The one corner off the property | None needed |
| M1 | 24 and 30 per page | `StateAnswer.php:72`, `BuildLink.php:63`, `Shelf.php:52` | Fix: one constant, or a recorded reason | D-244 says 30 | None needed |
| E2 | Notices in the shop's emails | `EmailHooks.php:151-152` | Fix: no returns notice when `$admin` | The shop needs no returns notice | [PB] |
| M6 | Check's 500 and import's 200 rows | `CheckScreen.php:42`, `ImportScreen.php:45` | Fix: M15's paged tables | Cuts hide findings | admin-ux-2026.md |
| A2 | Blank lines by saving | `SettingsPage.php:43`, `SpecTablesScreen.php:42` | Fix: M15's modals | Lists grow only by saving | admin-ux-2026.md |
| A1 | New kit from a kit | `kit_defaults` (SETTINGS.md:77) | Fix: test and document WooCommerce's Duplicate | Duplicate copies the meta | [WC2] |
| B8 | Sold-out fold | `Shelf.php:171`, `BuilderView.php:1474-1477` | Owner's call; recommend kept, documented | Following WooCommerce changes live pages | SPEC.md:442, [WC4] |
| L4 | 3D lighting | `stage.ts:208-211` | Kept fixed | Read on three themes, light and dark | [STG], [3DV] |
| C4 | Builder's other words | `BuilderView.php:489`, `:545`, `:2524`, `:2669` | Kept fixed (translation) | Generic; features have switches | None found |
| C6 | 3D note and buttons | `templates/builder/three.php` | Kept fixed | A claim the view must keep | None needed |
| E3 | Cart refusals | `src/Cart/Refusals.php` | Kept fixed (translation) | Plain facts | None found |
| B2 | Sorts offered | `ShelfAsk.php:23` | Kept fixed | The WooCommerce norm | [CP] |
| B3 | When tools appear | `BuilderView.php:49`, `:54` | Kept fixed | Spec: Tools that grow | SPEC.md |
| B4 | Riding styles and tiers | `RidingStyle.php:20-23`, `Tier.php:21-24` | Kept fixed; a feature for later | Sizes per style are settings | None found |
| B5 | Swaps' two kinds | `Swapper.php:57` | Kept fixed | One switch is enough | None found |
| B7 | Undo of 15 minutes | `SavedBuildHooks.php:147` | Kept fixed | D-275 | None needed |
| B9 | Card prices | `BuilderView.php:1805` | Kept fixed | Each part is its own line at that price | [CP] |
| B10 | Part prices in the cart | `ShowHooks.php:116-117` | Kept fixed | The spec's own lines; sums must read | [PB], [WPC-S1] |
| B11 | Edit build in the cart | `ShowHooks.php:301` | Kept fixed | The only way to change a part | [CP], [MKL-S2] |
| M2 | Six presets | `Kit.php:23` | Kept fixed | Tile layout | None found |
| M3 | 100 saved, three compared | `SavedBuildStore.php:31`, `CompareView.php:50` | Kept fixed | D-217; a phone's width | None found |
| M4 | Slots, units, chip groups | `Slots.php:26`, `Slot.php:21`, `Chips.php:39` | Kept fixed | Far past any kit | None found |
| M5 | Composed picture limits | SETTINGS.md:108 | Kept fixed | D-197, D-198, D-205 | None needed |
| M7 | 100 tickets at once | `TicketPage.php:46` | Kept fixed | One print run | None found |
| T3 | Zoom limits | `stage.ts:223-224` | Kept fixed | Every board is about one size | [ZAK], [STG] |
| T4 | Camera move time | `stage.ts:67` | Kept fixed | Instant under reduced motion | None found |
| T5 | Auto-rotate | none (off) | Kept off; record the decision | WCAG 2.2.2; fights the drag | [STG], [ZAK], [3DV] |
| T8 | Pixel ratio cap | `stage.ts:83` | Kept fixed | Device memory | None found |
| S3 | Compare switch | `CompareView.php:50` | Dropped | Zach, 25 November | Feature-switch rule |
| S4 | Top and Underside switch | `BuilderView.php:294-300` | Dropped | Layout gets no switch | Feature-switch rule |
| S5 | Estimate switch | `BuilderView.php:2543` | Dropped | Information, not a feature (D-213) | UX-RESEARCH.md |

**Counts.** Five kit settings (C1, B6, T2, S1, S2), two shop settings (B1, A4), one CSS custom property (L3), three new hooks (C2, C3, E1) and three that already exist (L2, C5, E4), six fixes (L6, M1, E2, M6, A2, A1), 22 kept fixed, three of them dropped switches (S3, S4, S5), one for the owner (B8), and three shipped in 1.4.0 (L1, T6, T7). The R1 to R8 labels (A3) are already in the M15 plan as plain labels.

**Nothing here renames or removes a public item.** C1 and C3 add fields to `cbuild_kit_text`; E1 adds a filter; A4 keeps `my-builds` as the default and the endpoint's hook names, which use the key, not the slug; L3 adds a property. M1 changes the value of two constants that are not public API. Each new public item needs its HOOKS.md entry with `@since 1.5.0` and an API snapshot.

## For Zach

1. **The sold-out fold (B8).** Following WooCommerce's "Hide out of stock items" changes live builders on shops that tick it, and the spec keeps sold-out parts in view for Notify me. I'd keep the fold and document it.
2. **A1 as WooCommerce's Duplicate,** in place of a "New kits start from" setting. One fewer setting, if a test confirms Duplicate copies a kit whole.
3. **The three switches dropped** from the plan's list: Compare (your rule of 25 November), Top and Underside (layout), and the estimate (information). Save build and Link to this build stay.
4. **C3 as a hook,** not "the deck slot's Help in place of the lead", which would change live kits.
5. **A4, the My builds address,** is the least needed of the list. It could wait for a shop that asks.
6. **E2** keeps the lead time in the shop's own email and drops only the returns notice.

## Sources

Every page was read on 27 November 2026 unless it says otherwise.

- [PB] Product Bundles, Store Owner's Guide: <https://woocommerce.com/document/bundles/bundles-configuration/> (modified 25 Nov 2026).
- [CP] Composite Products, Basic Setup: <https://woocommerce.com/document/composite-products/composite-products-configuration/> (modified 25 Nov 2026).
- [CPS] Composite Products, Snippets: <https://woocommerce.com/document/composite-products/composite-products-snippets>.
- [CPG] "Sort Component Options by Menu Order", the vendor's snippet: <https://gist.github.com/somewherewarm-snippets/f92e611fe2aa63ee7d23b549910529d2> (found by search; title read).
- [PAO] Product Add-Ons: <https://woocommerce.com/document/product-add-ons/> (modified 27 Oct 2025).
- [WPC] WPC Product Bundles: <https://wordpress.org/plugins/woo-product-bundle/> (features and changelog).
- [WPC-S1] WPC Product Bundles support, page 1: <https://wordpress.org/support/plugin/woo-product-bundle/>.
- [MKL] MKL Product Configurator: <https://wordpress.org/plugins/product-configurator-for-woocommerce/> (version 2.2.1, updated 18 Nov 2026).
- [MKL-S1] MKL support, page 1: <https://wordpress.org/support/plugin/product-configurator-for-woocommerce/>.
- [MKL-S2] MKL support, page 2: <https://wordpress.org/support/plugin/product-configurator-for-woocommerce/page/2/>.
- [MKL-S3] MKL support, page 3 (search listing): <https://wordpress.org/support/plugin/product-configurator-for-woocommerce/page/3>.
- [STG] Staggs, Customizing the 3D configurator viewer options: <https://staggs.app/docs/customizing-3d-configurator-viewer-options> (updated 26 Oct 2024).
- [ZAK] Zakeke, Cameras: <https://zakeke.zendesk.com/hc/en-us/articles/12627137093020-Cameras>.
- [3DV] 3D Viewer by bPlugins: <https://wordpress.org/plugins/3d-viewer/>.
- [3DV-S] 3D Viewer support, page 1: <https://wordpress.org/support/plugin/3d-viewer/>.
- [OS] WooCommerce Order Subscriptions: <https://woocommerce.com/document/order-subscriptions>.
- [WC1] WooCommerce 11.3.1, the catalog's Default product sorting: `includes/customizer/class-wc-shop-customizer.php:323-330`, `:478-483`, in the integration site wp-env installed.
- [WC2] WooCommerce 11.3.1, Duplicate: `includes/admin/class-wc-admin-duplicate-product.php:134-160`.
- [WC3] WooCommerce 11.3.1, Account endpoints: `includes/admin/settings/class-wc-settings-advanced.php:285-366`.
- [WC4] WooCommerce 11.3.1, Out of stock visibility: `includes/admin/settings/class-wc-settings-products.php:355-360`; `includes/class-wc-query.php:985`.
- The draft: `csb-m14-handoff/M15-options-inventory.md`, 26 Nov 2026, outside the repository.
- Not confirmed: Threekit, Fancy Product Designer and Lumise; PB's and CP's own support forums, which are behind WooCommerce.com accounts.
