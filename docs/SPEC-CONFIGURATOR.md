# The configurator: spec for milestones 10 and 11

Written 18 November 2026 from Cowork's redesign proposal (`docs/reviews/redesign/proposal.md`, agreed with Zach) and the plugin as built at 0.9.0. The proposal is the source; this file says how it lands in this plugin, and records a decision for each point the proposal left open (D-238 to D-258). Cowork's corrections of the same day (`docs/reviews/redesign/cowork-corrections-m10-m11.md`) are folded in. Where this file and `SPEC.md` differ, this file wins for the builder's front end; `SPEC.md` still holds for fit, prices, the cart, orders and the admin.

Milestone 10 builds the configurator and closes 1.0 (D-236). Milestone 11 adds the motion, as 1.1.0.

## What stays

The redesign replaces the builder's front end and keeps everything under it.

- The fit engine and R1 to R8, unchanged. Each step still offers the parts that fit the picks before it, names its hidden parts with their reasons (P1), and labels its recommendation without picking it (D-213).
- The state route, its schema and its `cbuild[…]` link parameters, with additions only.
- Prices, assembly, the estimate and the delivery line; the cart, orders, saved builds, the Build box, the ticket and the emails.
- The picture: the domain's layout, the layered renderer, resized copies, the top, the composed picture.
- Every hook, CSS custom property, class and DOM event in `docs/HOOKS.md`. A class the new layout no longer prints is kept on the element that takes its place, or deprecated with a release's notice before it goes.
- The rules in `CLAUDE.md`: the server renders every template and the store re-renders them; no markup built in JavaScript; the browser decides neither fit nor price; every string through the text domain.

## The layout

### Where it renders

- **On a kit's page** the builder takes the page's width and the screen's height below the theme's header, instead of the add-to-cart form's column.
  - **Block themes.** The plugin registers a template for kit products with `register_block_template()` (`wp-includes/block-template.php:506` in 7.1.5), under a name that can't be read as a product slug, and puts it first for kits through `single_template_hierarchy` (`template.php:62`). It holds the theme's header part, the configurator, the product's description, specs and reviews, and the theme's footer part; the shop can edit it in the Site Editor.
  - **Classic themes** (Kadence's own product layout, Storefront). The configurator renders full width before the product's summary, and the summary's title, price and form are left out for kits; the theme's own header stays (D-238).
  - **A page builder's product template** (Shop Kit's "Single Post" template on the demo, which draws title, gallery, add-to-cart and reviews blocks in two columns with no WooCommerce summary). The builder renders where the add-to-cart block is, so it gets that column's width. The readme says how to give kits the full width: leave kits out of that template (its Hide On), so they get the theme's own layout, or give kits a template whose add-to-cart block sits in a full-width row. The layout tests run under Kadence and the suite's Shop Kit stand-in, not only on the demo.
  - **One h1.** Where the theme's title is left out, the configurator prints the product's name as the page's h1. Where a template keeps its own title block, it adds none.
- **The Builder block** renders the same configurator at the block's width; `alignfull` gives the kit page's layout on any page.
- **Below the configurator:** the product's description, specs and reviews, then the site's footer. The page stays one indexable page; build links stay `noindex, follow` with the clean page as canonical.
- **Back to the shop:** a link at the panel's head, to the shop page, for themes whose header has no way back.
- **A theme's sticky add-to-cart bar** (on the demo, the Kadence Pro element "Product page: phone buy bar") is the shop's to set. `readme.txt` says: if your theme or a site element adds a sticky add-to-cart bar, leave kit products out of it. For Kadence Pro: Appearance > Elements, open the element that holds the bar, then Element Settings > Display Settings > Exclude Settings; set Hide On to Single Products, Select product by to Individually, pick each kit, and save. Kadence can't pick products by type, so each new kit is added the same way, or the kits share a tag picked under Group (D-239).

### Desktop, 1024 px and wider

| Part | What it holds |
| --- | --- |
| Stage | Left by default (the Stage side setting), 60 % of the width (Board size: large) or 50 % (balanced), the full height below the header. The board large, Show the top, and labelled chips for parts with no layer image. A labelled figure whose caption is the build's written description. |
| Panel | 380 to 520 px wide. The step rail, then the step: its heading, its question or tools, its cards, its hidden and sold-out parts. It scrolls inside itself. |
| Footer | Under the panel, pinned: the total, the estimate on its own line, the delivery line and `cbuild_summary_notes`, then Back and Next, or Add to cart at the review. |

### Tablet, 768 to 1023 px

The stage on top, about a third of the height and sticky (D-284); the panel below it, scrolling with the page; the footer pinned to the bottom.

### Phone, under 768 px

- The stage on top, about a third of the screen's height (34 %, the prototype's) and sticky (D-284). Where the board reads better sideways in that space, it is drawn turned a quarter turn, by a transform on the picture; the written description does not change.
- The panel is a sheet under the stage: the rail scrolls sideways as a row of steps, then the step.
- A bar pinned to the bottom: the total, the estimate on its own line, Back and Next. The bar never covers the control in focus (the page's scroll padding grows with it, as in 0.9.0). The delivery line and notes show at the review (D-250).
- No swipe between steps in milestone 10: Next and Back move, and so does the rail (D-251).
- The filters sit behind a Filters button, so the cards come first.

### The step rail

- One entry a step, in the kit's slot order, then Review. Each is done (with what was picked, in its accessible name: "Deck, done: Rascals 8.25″"), current (`aria-current="step"`) or to come.
- A done step, and the next step once the current one is picked, is a link: one tap away. Without JavaScript each is a GET link carrying the build.
- It is a list of links in a `nav` labelled "Steps".
- A step that is skipped (bearings when the wheels come with them; risers when none are needed) is not in the rail.

### One decision at a time

The panel holds the current step alone. A pick moves the board first and updates the footer's total; Next then offers the next step. Focus stays on the picked card; the status region says the change once, as today.

### The review

Each part with its fit tick, price and Change; the assembly choice with its fee and lead time; the arrival date and delivery line; the returns notice; Save build; Copy link; Add to cart. "Add risers" sits under the parts when the build has none, for a rider who wants them anyway (D-246).

## Finding a part

### One card per look (D-240)

- **A look** is what the shopper can see: a product, or the variations of one product that share a photo and a layer image. Each look is one card, and no two cards show the same photo. A colour or graphic with its own photo is its own look.
- **A size** is a variation attribute the slot reads as its size spec, through the attribute mapping: deck width, truck axle width, wheel diameter, riser thickness, bolt length, grip sheet size. Sizes never get a card of their own.
- **A choice you can't see** (an Allen or Phillips head, a wheel's hardness) is any other attribute that differs between a look's variations. It is chosen like a size the build doesn't decide, below.
- **The build picks the size** where the build decides it:
  - **Deck:** the width asked first.
  - **Trucks:** the variation that fits the deck under R1, nearest the deck's width.
  - **Risers:** the thickness R3 asks for.
  - **Hardware:** the length R4 makes usual, 1″ plus the riser, or else the nearest length R4 allows. A look sold in ⅞″ and 1¼″ has no 1″, and doesn't drop out for that.
  - **Grip:** the smallest sheet R6 allows, at least as wide and as long as the deck.
- **Where nothing decides it** (a wheel's diameter, and the choices you can't see), the shopper chooses after picking the card. Each card is the label of a native radio, so it holds no controls of its own. The picked card is followed by a radio group of its own, "Size for Retna Cruiser, Mint", with the size the step's chip has selected (or else the recommendation's) already chosen. The other cards show that size's price and stock.
- **The card** shows the photo large, the brand and name, the look's name, the part's full price, large, and under it, once the step has a pick, the change it makes to the total in smaller type ("+$12.00 over your pick", "Same price", "$8.00 less"; P4, D-249), with "Included" where the part comes with another, stock for the size the build would take, and the fit label. "Also in 8″, 8.5″ and 8.75″" names the other sizes on a deck card.
- **A product's looks sit side by side** in the list, so a big step reads product by product.
- **Each look's photo and layer image** come from its variation, falling back to the product's, as layer images do today. The Catalogue check says which variations share a photo, and so share a card.
- **No admin settings for this.** A part's sizes are the slot's mapped size spec; its looks follow its photos; everything else is chosen after the pick.

### The deck's width first, with no All (D-241)

- **The question.** The deck step opens on one question, "What width do you ride?", with each width the kit's decks come in and how many of them are in stock in it. A width with none in stock shows dimmed and named under the choices: "Nothing in 7.75″, 8.125″ or 9″ is in stock right now."
- **Then the decks.** Once a width is picked, the step lists the decks in it. The width shows as the step's first line, with Change, and changing it keeps the step's filters.
- **Not sure of your width?** This opens a guide by shoe size, from Skate Warehouse's deck buying guide, in the plugin's own translatable words. `cbuild_width_guide` lets a shop give its own rows.
- **What brings a width.** A preset, a build link, a saved build, and "Build a complete with this deck" each bring their own width, and skip the question.
- **Remembered** for the visit: the width travels in the build link and history. A signed-in customer's width comes from their most recent saved build. Nothing is stored on the device (D-242).
- **Without JavaScript** each width is a button in the step's GET form.

### Only what fits, then the sold-out fold

- Every step lists the parts that fit what is already picked, in stock, under today's rules.
- Sold-out looks fold into one line at the end, "Show 68 sold out", which opens them, each linking to Notify me as today.
- Parts that don't fit stay in the hidden-parts disclosure with their reasons (P1), as today.
- Wheels over the trucks' limit move into the hidden parts when the kit can't supply a riser that clears them together with bolts R4 allows for that riser. R3 still only warns; it is the step that hides them (D-245). The reason says which:
  - "Needs ½″ risers, and none are in stock."
  - "Needs ½″ risers, and no bolts here are long enough for them."
  - "Needs ½″ risers, and this kit has none." where the kit has no Risers slot.

### The order (D-243)

- **The default order:** the step's recommendation first, then the shop's featured products (WooCommerce's star), then best sellers (WooCommerce's sales count), then the rest by name.
- **Sort,** one tap away: Recommended, Price low to high, Price high to low, Newest.
- **How it's read:** through WooCommerce's CRUD, one `wc_get_products()` a step with the step's IDs and the order, cached with the step's cards.

### 30 at a time (D-244)

- A step shows 30 looks, then "Showing 30 of 90" and a "Show 30 more" button. It appends the next 30 and moves focus to the first new card.
- With JavaScript, the route sends only the new page, through a new `from` argument. The build link's `_shown` keeps the count, so a reload or Back shows the same list.
- Coming back to a step keeps its filters, its sort, its search and its place.
- The cap stays at 500 looks a step (`BuildLink::MOST`): search and filters narrow past it.

### Tools that grow with the step

| Looks on offer in the step | It gets |
| --- | --- |
| 12 or fewer | The cards alone |
| 13 to 40 | Sort and the filter chips |
| More than 40 | Sort, the filter chips and search |

The count is the step's offered looks before any filter, so the tools don't vanish as the shopper narrows.

### Filters from the step's own data (D-247)

- **The chips.** A step offers, as chip groups, brand; the slot's size spec, only where the shopper picks the size (wheels' diameter, and any step whose size no rule decides), never on decks, trucks, risers, hardware or grip; and each global product attribute (`pa_…`) that at least 80 % of the step's offered looks carry, up to four groups. On wheels that is brand, diameter and hardness; on hardware, head and colour.
- **Counts.** Each chip says how many looks it leaves, counted with the other groups' choices applied and its own group's not.
- **Choices.** Chips in one group widen (OR); groups narrow each other (AND).
- **What it fixes.** Today's counts ignore the other filter; this fixes that.
- **Without JavaScript,** the chips are buttons in the step's GET form.

### Search (D-248)

- **Where.** A step with more than 40 looks gets a search box at its head: "Search decks", with a hint of the step's own words ("Brand or graphic, like Rascals").
- **What it matches.** Brand, name, the look's attribute values and the size's label, as the shopper types, 150 ms after the last key, through the state route. The chips narrow the results, and their counts follow them.
- **With Creative Search** active and the Search setting left on, the builder asks the `csearch_ranked_ids` filter for the query's ranking among the step's own parts, by product ID as integers, at most 500, in the shopper's locale (`lang`), logged under Creative Search's `builder` source. The results keep Creative Search's order, typos and synonyms included.
- **Parents to looks.** Creative Search answers with parent products (it folds variations into their parent). Within each parent it returns, the builder keeps the looks whose own words match the query, or Creative Search's `corrected` query when it gives one, and all of them if none do, since then the name or brand matched.
- **Without it,** the builder searches its own list: every word of the query must start a word of the part's brand, name, look or size, ignoring case and accents. It allows no typos.
- **When nothing is found,** the step says why and offers the way on:
  - "No 8.25″ decks match Rascals. Rascals comes in 8.5″." with a button to that width;
  - "2 trucks match, but they don't fit your deck." with a link to the hidden parts;
  - or "No decks match Rascals." with Clear search.
- **Where the query travels:** in the build link as `_q` (100 characters at most), so Back and reload keep it. It is not stored and never logged by this plugin.
- **The rate limit.** Search, chips and Show more all go through the state route, which allows 120 requests a minute an address by default (`BuildTerms::RATE_DEFAULT`). After a 429 the step keeps its last results and says when to try again. The default is checked in the hallway test, since a shop's wifi or a carrier's shared address puts several shoppers on one address.
- **Stale answers.** A shopper typing, or arrowing through the cards, sends request after request; an answer older than the last request is dropped.

### Ways in that skip the hunt

- **Build a complete with this deck.** A deck's own product page gets a button after its add-to-cart form, opening the kit's builder with that deck picked, its width with it. It appears when the deck is offered by a kit's first slot; where more than one kit offers it, the button opens the first by menu order. `cbuild_start_button` can choose the kit, move the button, change its words or turn it off (D-253).
- **Rider builds.** The kit's presets, shown as complete boards to start from. Each tile shows the build's composed picture, or a rider photo set on the preset, then its name, who it's for and its total. A preset holding a sold-out part says so on its tile, as today (D-252).
- **Recently viewed:** not in milestone 10 (D-242).

## The steps that appear only when needed

### Bearings (D-246)

- **Where.** A step after Wheels, as the kit's slots order it. R5 stays: a bearing's bore must match the axle.
- **Wheels sold with bearings skip it.** A new spec, `bearings_included` (yes or no, on wheels), in the Part specs panel and the CSV, says a wheel comes with its bearings. When the picked wheels carry it:
  - the bearings step leaves the rail;
  - the kit's bearings slot counts as filled;
  - the review says "Bearings: included with your wheels".
- **Changing course.** A bearing already picked goes through the change list when the shopper then picks such wheels.

### Risers (D-246)

- **When the step appears.** As built in 0.9.0: only when the picked wheels are over the trucks' limit (R3). It opens on the riser that clears them, with the reason ("Your 56 mm wheels need ½″ risers with these trucks"), and "No risers" stays a choice. Risers are never added silently.
- **Before the pick.** Wheel cards say "Needs ¼″ risers with your trucks", from R3's warning on each option.
- **The bolts follow.** Hardware's length is the size R4 makes usual for the riser picked, or the nearest it allows (D-240). Where no bolt in a look is long enough, the look sits in the hidden parts with R4's reason.
- **No dead ends.** The step offers only a riser the kit has bolts for: one with no in-stock bolt R4 allows sits in the hidden parts with R4's reason. So a shopper who takes a riser always has hardware to pick.
- **Anyone can add them.** "Add risers" at the review opens the step when nothing asks for it.
- **No riser the kit can supply with bolts.** Those wheels are hidden with the reason (D-245). The browser test runs the test kit's 56 mm wheels both ways: with bolts long enough for a ½″ riser, and without, as on the demo.

## A saved build that has changed (D-254)

- **What's kept.** From 1.0.0, Save build keeps each part's price and its currency beside its pick, in the saved build's picks document (no new column). The prices are the shop's, not personal data. Prices are compared only in the same currency, so a currency switcher doesn't make every price look changed.
- **What's checked.** Opening a saved build from My builds compares it with the shop now:
  - a part sold out, or gone from the kit;
  - a part that no longer fits;
  - a price that changed.
- **The screen.** Any of these opens a "Your build has changed" panel before the review, with focus on its heading.
  - Each sold-out or gone part is named, with the closest part that fits in its place. That part is found by the change list's own finder, `ClosestPart::find()` (the nearest spec, then the same brand, then the nearest price, in stock), against the rest of the build. It was `ChangePlanner::closest()`, private, and moved to `src/Domain/Fit/ClosestPart.php` for both to call, not copied.
  - Each new price is named: "Independent 149 trucks: $59.00 when you saved, $64.00 now".
  - The total then and now.
  - "Use these" takes the replacements to the review. "Choose myself" opens the first changed step.
- **Builds saved before 1.0.0** have no prices kept, so they show the parts that changed but no price changes.
- **Without JavaScript,** the panel is a step of its own, as the change panel is.
- **My builds** still says "A part has changed since you saved it." and opens to the panel.

## The admin screens it changes

- **Part specs.** `bearings_included` joins the panel. "Sold in sets" opens for any part in a slot that takes more than one unit, since R7 needs its Number in a set and a closed group's fields are disabled (issue #61).
- **Presets.** They gain an image (D-252). Saving a kit whose stored presets are unchanged and name a hidden part saves the rest without calling the presets a failure, and says which preset holds which part (issue #62).

## Settings (D-255)

There is one layout and no mode switch. The kit's Build tab gets a Layout section with four settings, in a new `_cbuild_layout` document (`schema/kit-layout.json`). There are no settings for variations.

| Setting | Key | Choices | Default | Milestone |
| --- | --- | --- | --- | --- |
| Stage side on a desktop | `stage_side` | left, right | left | 10 |
| Board size on a desktop | `board_size` | large (60 %), balanced (50 %) | large | 10 |
| Search in the steps | `search` | creative_search (where it is active, the builder's own otherwise), own (the builder's own always) | creative_search | 10 |
| Motion | `motion` | playful, subtle (fades only, for every shopper) | playful | 11 |

`docs/SETTINGS.md` lists each one, and the kit's export and the abilities carry the document.

## Accessibility

- **Keyboard order:** a skip link to the step, then the rail, the step's question or search, the chips, the cards, Show more, and the footer.
- **Moving around:**
  - The cards are native radios in a form that picks on change (`templates/builder/step.php`, `data-wp-on--change="actions.pick"`), as in 0.9.0: the arrow keys move and pick together, and Enter submits the form, which is Next.
  - A shopper arrowing through 30 cards sends a pick at each; each is cut short by the next, and stale answers are dropped (milestone 11's motion too).
  - The first deck is reached in at most eight presses from the skip link once a width is picked; the browser test counts them.
- **The focus ring** is 0.9.0's two-tone ring, on every theme.
- **Screen readers:**
  - The stage is a figure labelled by the build's description.
  - The status region says each change once.
  - The rail is a list of links with the current step marked.
  - A finished step says what was picked.
  - "Show 30 more" says how many were added.
- **Targets:** 44 px on a phone and 24 px on a desktop.
- **Zoom and reflow:** the layout holds at 200 % zoom and at 320 px wide. Below 320 px the stage stops being sticky, so the panel is never squeezed out.
- **Forced colours:** the chosen card, a pressed chip, the current step and focus are each seen.
- **Without JavaScript** every step, chip, search, Show more, the width question and the changed-build panel work as GET forms.

## Speed

- **Largest paint** under 2.5 s on a mid-range phone over 4G (Lighthouse's mobile profile). The deck's resized copy loads first, at `fetchpriority="high"`, and the stage keeps its size, so nothing shifts as parts land.
- **Responsiveness:** a pick paints within 100 ms on a 4× slowed CPU, as since milestone 6, and Interaction to Next Paint is under 200 ms.
- **The route** sends one page of looks and the chip counts, not every part. A step with 500 parts costs about what one with 20 does: p95 under 80 ms at 500 candidates, as the benchmark holds it.
- **Thumbnails** load lazily as they scroll into view.
- **Budgets:** the builder's script under 15 kB and its styles under 10 kB, compressed with Brotli at quality 11, as today.

## Milestone 11: the motion

Every animation shows a part being built, changed or priced, and none waits on the shopper. Each moves `transform` and `opacity` only, through view transitions for steps and swaps and the Web Animations API for the board's parts, with no animation library. Each is cut short if the shopper picks again. Under `prefers-reduced-motion`, or with the Motion setting on subtle, each becomes a cross-fade of 150 ms or less: opacity, no movement (D-256).

| Moment | What moves | Time | Reduced motion or subtle |
| --- | --- | --- | --- |
| Deck picked | The deck settles onto the stage from just above, its shadow growing | 280 ms | Fades in, 150 ms |
| Trucks added | Each truck slides in onto its holes, the second 60 ms after the first | 240 ms | Fades in |
| Wheels added | Each wheel slides onto its axle end and turns a quarter, 40 ms apart | 220 ms | Fades in |
| A part swapped | The old part lifts away as the new one settles, one view transition | 200 ms | Cross-fade |
| A part removed | It lifts and fades; the board closes the gap | 180 ms | Fades out |
| Grip on the top | The sheet lays down from nose to tail | 300 ms | Fades in |
| Show the top | The board turns over like a card | 400 ms | Cross-fade |
| Price changes | The total counts from the old amount to the new; the estimate fades | 300 ms | Changes at once |
| Next step | The panel's content slides 16 px the way the shopper is going; the rail's marker follows | 200 ms | Cross-fade |
| Add to cart | The board shrinks toward the cart's icon, which bumps its count | 400 ms | The count changes |

- **The counting total** (D-257). The in-between amounts are formatted in the browser from the shop's currency settings, which the route sends (decimals, separators, symbol and its position, as the Store API gives them). They are `aria-hidden`, and the last frame is the server's own `wc_price()` string. A screen reader hears the total once, from the status region.
- **The cart's icon.** Add to cart aims at WooCommerce's Mini-Cart button when the page has one, and otherwise fades the board in place.
- **Firefox** has view transitions without transition types, so the direction of a step change is set by a class instead.
- **What it costs:** the motion code counts toward the same 15 kB script budget, and a pick still paints within 100 ms on a 4× slowed CPU.
- **Baselines.** Each moment gets a visual baseline at its end state and a performance trace in CI.
