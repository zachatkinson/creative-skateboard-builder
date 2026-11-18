# Cowork's corrections to the milestone 10 and 11 drafts

18 November 2026, on the four drafts in `from-claude-code/`, checked against the builder at afef15d and Creative Search on `csearch-1.7.0-api` (1ab3553 and the D-163 work in progress). The drafts follow the agreed proposal closely. Fifteen fixes (11 to 15 found on a second, full pass through every reference in the drafts), then smaller notes.

## Fixes

1. **A riser with no bolts long enough is a dead end (D-245, D-246).** R4 blocks a bolt shorter than ⅞″ plus the riser, so a ½″ riser needs bolts of 1⅜″ or more, and the demo's stop at 1¼″. As drafted, the ½″ riser is in stock, so the 56 mm wheels stay offered on the Low test trucks. The shopper takes the riser, and every Hardware look then sits in the hidden parts: a required step with nothing to pick. Count a riser as clearing the wheels only when the kit also has an in-stock bolt R4 allows with it, and say so in the hidden wheel's reason ("Needs ½″ risers, and no bolts here are long enough for them"). The risers step leaves such a riser out, with R4's reason. The browser test runs the 56 mm case both ways: with long enough bolts, and without, as on the demo.

2. **Allen and Phillips heads aren't looks (D-240).** The spec makes every attribute that isn't a size a look, head included, so one bolt in Allen and Phillips becomes two cards with the same photo. The proposal keeps choices you can't see off the cards. A rule that needs no setting: a product's variations that share a photo and a layer image are one look, and any other attribute that differs between them (a head, a wheel's hardness) is chosen on the card, as a wheel's diameter is, preselected by its chip. Colours with their own photos keep their own cards, and no two cards show the same photo. The Catalogue check then says which colours share a photo, and so share a card.

3. **Size chips only where the shopper picks the size (D-247).** Where the build picks the size (axle, riser, bolt length), a chip for it contradicts the build. The proposal's chips were brand and width on decks (now the width question); brand, diameter and hardness on wheels; head and colour on hardware. So size chips go on wheels, and on any step where the size is the shopper's, and not on trucks, risers or hardware.

4. **Two more sizes the build decides (D-240).**
   - **Grip:** the smallest sheet R6 allows, at least as wide and as long as the deck. As drafted it falls to the card.
   - **Hardware:** R4's usual length, or else the nearest length R4 allows. A look sold in ⅞″ and 1¼″ has no 1″, and shouldn't drop out for that.

5. **A card can't hold the size buttons (D-240, accessibility).** Each card is the label of a native radio (`A11Y.md`), so buttons inside it put controls inside a label, and 30 wheel cards would add 30 tab stops. Show the sizes on the picked card only, as a radio group of their own after it ("Size for Retna Cruiser, Mint"). The other cards show the preselected size's price and stock.

6. **The sticky bar isn't Shop Kit's (D-239, and the spec's "Where it renders").** That label was my error in the proposal, now corrected there. On the demo it's a Kadence Pro element, "Product page: phone buy bar" (#1068), fixed to the bottom after scrolling, on every product. D-239's body says Kadence Pro already; its title and the spec's line still say Shop Kit. The readme can say: if your theme or a site element adds a sticky add-to-cart bar, leave kit products out of it (in Kadence Pro, in that element's display settings). The demo's bar now leaves the kit out (18 November). The steps for the readme, in Kadence Pro: Appearance > Elements, open the element that holds the bar, then Element Settings > Display Settings > Exclude Settings. Set Hide On to Single Products and Select product by to Individually, press Select Items, pick each kit, press Select, and save. Kadence can't pick products by type, so each new kit is added the same way; a shop with many kits can tag them and pick the tag under Group instead.

7. **Creative Search answers with parents; the builder shows looks (D-248).** D-162 folds variations into their parent and returns parent IDs, so "mint" finds the Retna Cruiser parent and would bring every look of it. Within each parent it returns, keep the looks whose own words match the query (or Creative Search's `corrected` query when it gives one), and all of them if none do, since then the name or brand matched. Pass the shopper's locale as `lang`.

8. **Creative Search drops IDs given as strings (D-162, in progress).** `CallerSearch::ids()` keeps only `is_int` values, so a caller passing IDs from `$wpdb->get_col()`, which returns strings, gets an empty answer and no warning. Accept positive numeric strings too, as `wp_parse_id_list()` does, and the same for `limit`.

9. **Issues #61 and #62 belong in milestone 10.** Neither draft names them, and milestone 10 changes both screens. `bearings_included` joins the Part specs panel, where a closed group's inputs are disabled, so "Sold in sets" isn't saved unless it's opened (#61). Presets gain an image, and a kit's presets aren't saved while one names a hidden part (#62). Fix them there if 0.9.x doesn't.

10. **ROADMAP.md's "Skatehouse's setup"** still lists "Make it a complete" from Skatehouse Core 2.x. D-253 moves that button into the plugin, so that line changes with the milestone 10 entry.

## Added after a full pass

11. **The demo's kit page is a Shop Kit template, so D-238's classic path doesn't reach it.** The demo draws every product page with Shop Kit's "Single Post" template (Products > Woo Templates): its title, gallery, add-to-cart and reviews blocks in a two-column row, with no WooCommerce summary. The builder renders inside the add-to-cart block's column, so "full width before the product's summary" has nothing to attach to, and Skatehouse runs the same stack. As with the bar, it's the shop's setup, not Shop Kit code: leave the kits out of that template (its Hide On uses the same rules as Elements), so they get the theme's own product layout, or give kits a template whose add-to-cart block sits in one full-width row. Say so in the spec and the readme, and run the layout tests under Kadence and the suite's Shop Kit stand-in (`builder.spec.ts`, from line 2550), not only on the demo. I'll set the demo up for the milestone 10 review, with Zach's OK then, and leave it as it is until 0.9.0's review is done.

12. **The keyboard (accessibility).** "Enter or Space picks" isn't how the cards work, and the slip was mine in the proposal. They're native radios in a form that picks on change (`templates/builder/step.php`: `data-wp-on--change="actions.pick"`), so the arrow keys move and pick together, and Enter submits the form, which is Next. Write the keyboard section as 0.9.0 behaves, and make sure milestone 11's motion and the route keep up with a shopper arrowing through 30 cards: each pick cut short by the next, stale answers dropped.

13. **One h1 (D-238).** Where the theme's title is left out (Storefront, or Kadence's own product layout), the configurator prints the product's name as the page's h1. Where a template keeps its own title block (Shop Kit's), it adds none.

14. **The rate limit (D-244, D-247, D-248).** Search as you type, chips and Show more all go through the state route, which allows 120 requests a minute per address (`rate_limit`). That's plenty for one shopper, but a shop's wifi or a phone carrier's shared address puts several shoppers on one. Check the default during the hallway test, and say what a step shows after a 429 mid-search: the last results, and when to try again.

15. **`ChangePlanner::closest()` is private (D-254).** Reopening a saved build should share it, moved where both can call it, not copied.

## Smaller

- **The block template's name (D-238).** `single-product-cbuild-kit` is the template hierarchy's name for the one product whose slug is `cbuild-kit`. Register it with `register_block_template()` under a name that can't be read as a slug, and add it for kits through `single_template_hierarchy`.
- **Which kit the deck's button opens (D-253),** when more than one kit offers the deck in its first slot: the first by menu order, say, with `cbuild_start_button` to choose.
- **Kept prices (D-254):** keep the currency beside each price and compare like with like, so a currency switcher doesn't make every price look changed.
- **D-245's reason in a kit with no Risers slot:** "none are in stock" isn't true there; say the kit has no risers.
- **French:** the agency has no French speaker, so find the person who reads fr_CA now. Milestone 10 adds many new strings.
- **The hallway test:** include at least one iPhone, since CI's WebKit isn't iOS Safari.
- **The repository is public now (D-237):** the proposal, prototype and screenshots can go into `docs/reviews/redesign/` as they are. I checked them, and there's nothing private in them.

## Checked and fine

- D-237 is in `DECISIONS.md` at afef15d, so D-238 to D-257 follow it.
- `StateAnswer::SHOWN` and `BuildLink::PAGE` are 24, and `BuildLink::MOST` is 500. SPEC.md:703 asks for the closest fit, and `ChangePlanner::closest()` exists (private today).
- 0.9.0 shows the Risers step only when R3 asks for it, the slot is required or a riser is picked, as D-246 says.
- With nothing picked, P4's "+$" is the part's full price, so the deck step needs no special case.
- D-252 matches the proposal: a rider photo when there is one, the composed picture otherwise.
- The Creative Search brief matches what's being built (D-161 to D-163). It said "D-160 on" and the records start at D-161, which is fine. Half-typed queries logged under `builder` are what `/suggest` logs already, and D-163 keeps them out of the shop's numbers.
- The Mini-Cart aim falls back to a fade where a theme has none, so it isn't aimed at one theme.
- The hooks the drafts lean on exist (`cbuild_summary_notes`, `cbuild_delivery_line`, `cbuild_arrival_estimate`), as do `composer test:benchmark:integration`, `npm run upgrade:all`, `schema/kit-presets.json`, D-213, D-229's two-tone focus ring and the My builds sentence. The budgets leave the motion room: the script is 3,027 B and the styles 2,987 B, against 15 kB and 10 kB.
- Every other place the spec departs from the proposal is a decision Zach agreed: search by product ID rather than by category, Recently viewed later, no swipe yet, the delivery line at the phone's review, and three settings now with motion in milestone 11.

In the milestone 10 review I'll check, on the demo, the Kadence placement (including whether a Shop Kit template draws the product pages), the bar, and the risers flow on the test kit.
