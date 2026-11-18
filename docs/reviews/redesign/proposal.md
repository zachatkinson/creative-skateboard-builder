# Skateboard builder redesign: proposal

Nov 18, 2026 · @Zach Atkinson

**Status:** agreed 18 November. The build spec is now `docs/SPEC-CONFIGURATOR.md` in the plugin (decisions D-238 to D-257), for milestone 10 (1.0.0) and milestone 11 (the motion, 1.1.0). Where it differs from this proposal, the spec wins: a slim header on block themes only; the sticky bar on kit pages is turned off in Kadence, not by a setting; a wheel's diameter is chosen on its card; the width is remembered for the visit, not the next one; three settings in milestone 10, with motion added in 11; no swiping between steps until after the hallway test; search by the step's own product IDs rather than its categories; and no Recently viewed until it can read WooCommerce's own cookie.

## Summary

Make the builder a full-screen configurator. The board sits on a stage, one step at a time sits in a panel beside it, and the total and estimate are always in view.

- **The board is the screen:** about 60% of a desktop and the top of a phone, so every pick lands where the shopper is looking.
- **One decision at a time:** a step rail replaces the long page, and any finished step is one tap away.
- **Finding a part:**
  - width first for decks, then one card per look (each colour and design), with sizes picked by the build;
  - search, filters and 30 cards at a time in big steps, through Creative Search;
  - rider builds and "start from this deck" as quick ways in.
- **Motion:** parts landing, swapping and lifting away, the board turning over and the price counting, all under 300 ms, with fades for reduced motion.
- **One layout, four settings,** not a mode switch.

Under it, fit, prices, the estimate, the picture, saved builds and orders stay as they are. Only the front end changes, over two milestones after milestone 9.

Try the [clickable prototype](https://claude.ai/artifact/3TpBGdNAY5pbZ9GbJ9tptd) at desktop and phone size. It uses the demo's 160 decks and 28 wheels as they are today; the other parts are samples. The second row of artboards shows the longest names in the catalogue, to check the layout. Press Play on an artboard.

## What feels clunky today

The builder can do everything it needs to. It shows it as a long form rather than a configurator, and four things follow from that. The evidence comes from the milestone 6 to 8 reviews on the demo.

| Pain point | What the reviews saw |
| --- | --- |
| Finding a deck | The deck step shows 24 cards at a time from 955 parts, one card per width, so one graphic fills up to five cards (Rascals 8.00″, 8.25″, 8.50″, 8.75″). Width and brand chips sit above the grid; there is no search. A keyboard user presses Tab 37 times to reach the first deck. |
| The long scrolling page | Steps, picture, filters, cards, summary and the product page's own sections share one page about 9,000 px tall on a desktop. The picture scrolls away while you choose, so a pick and its effect are rarely on screen together. |
| The phone layout | The bar, the parts sheet and the demo's sticky add-to-cart bar (a Kadence Pro element) stack at the bottom; the estimate hides in the closed sheet (M8 F2); the board shows as an 11 by 44 px thumbnail in the bar. |
| It doesn't feel alive | A pick swaps an image after about a second, with no sense of a part landing on the board, no feedback on the price, and keyboard focus that can't be seen on the demo's dark theme (M8 F1). |

None of this needs new business logic. Fit, picks, prices, the estimate, the picture's layout and the state route already do the hard work.

## What the best configurators do

The leading configurators make the product the screen and the choices a narrow panel beside it, and keep the price in view. They also guide the shopper without locking them in. Each row below is a source I read and what we take from it.

| Source | What it does or recommends | What we take |
| --- | --- | --- |
| [Smashing Magazine, Designing a perfect configurator UX](https://smashingmagazine.com/2018/02/designing-a-perfect-responsive-configurator) | The product visual gets most of the screen: 65 to 75% of the height on a phone, about half the width on a desktop. Price and summary always in view. A stepper you can jump around in. Smart defaults and presets as starting points. | A stage and a panel; the total and estimate pinned; a step rail you can jump in; presets as a way in. |
| [BMW Group Designworks, BMW configurator](https://www.bmwgroupdesignworks.com/case-studies/bmw-configurator-making-digital-configuration-a-true-brand-experience/) | A car configured in a few clicks on a desktop or swipes on a phone, with snap scrolling on mobile and motion treated as part of the brand. | Swipe between steps on a phone; motion that shows the board being built, not decoration. |
| [Nielsen Norman Group, Wizards](https://www.nngroup.com/articles/wizards/) | Wizards suit infrequent, unfamiliar tasks: show the steps and the current one, label Next and Previous, let people stop and come back. Avoid them where people compare across steps or want control. | A guided path that never traps: every finished step stays one tap away, and Save build (milestone 8) covers stopping and coming back. |
| [Baymard, combine variations into one list item](https://baymard.com/blog/combine-variations-one-list-item) | Listing each variation separately clutters the list and hides a product's options; 12% of sites still do it. Show one item with its variations as swatches and a count. | In a builder step, one card per look (each colour and design) and none per size; see Finding a part. |
| [SkatePro, Build a skateboard](https://www.skatepro.com/en-us/build-a-skateboard.htm) | Seven steps, only compatible parts shown, a warning when changing an earlier step removes later picks, and team rider builds that ask before replacing yours. | Our fit logic already matches; keep the warnings and presets, and give presets a rider-style presentation. |
| [web.dev, same-document view transitions are Baseline](https://web.dev/blog/same-document-view-transitions-are-now-baseline-newly-available?hl=es) | Same-document view transitions work in all three engines since 14 October 2025 (Firefox 144). Firefox lacks transition types, so progressive enhancement is advised. | Animate step changes and part swaps with view transitions, falling back to an instant change where unsupported. |

## The layout

The kit's page opens as a full-screen configurator: the board on a stage, one step at a time in a panel beside it, and the total always in view. The theme's header shrinks to a slim bar with the logo, the cart and "Back to the shop". The product's own description, specs and reviews follow below the configurator for anyone who scrolls on, and for search engines.

| Screen | Stage (the board) | Panel (the step) | Always in view |
| --- | --- | --- | --- |
| Desktop, 1024 px and wider | Left, about 60% of the width, full height, the board large with Show the top and parts without art as labelled chips | Right, 380 to 520 px wide: the step rail, then the step's search, filters and cards, scrolling inside the panel | A footer under the panel: total, estimate, Next or Add to cart |
| Tablet, 768 to 1023 px | Top, about 40% of the height, sticky | Below the stage, scrolling | The same footer, pinned to the bottom |
| Phone, under 768 px | Top, 40 to 45% of the height, sticky, the board turned sideways when that fits it better | A sheet under the stage; swipe or Next moves between steps | A bar pinned to the bottom: total, estimate on its own line, Next |

- **The step rail.** Deck, Trucks, Wheels, Bearings, Hardware, Grip tape, Review, as the kit's slots say. Each shows done, current or to come, and a finished step is one tap away, as NN/g advises.
- **One decision at a time.** A step's panel holds only that step. Picking a part moves the board first, then offers Next, so the effect of a pick is always on screen beside it.
- **The review** lists each part with Change, the assembly choice, Save build, Share and Add to cart.
- **The theme's sticky add-to-cart bar** (on the demo, a Kadence Pro element) is turned off on kit pages, so nothing covers the builder's bar.
- **Where it lives.** The full-screen layout is the kit page's template, so a shop's theme still supplies the fonts, colours and header.

## Finding a part

Every step works the same way, so a step with 500 parts reads as easily as one with 20. The demo already has 160 deck graphics from 12 brands, in widths from 7.75″ to 9″, and 28 wheels; a bigger shop's trucks, wheels and bearings can run into the hundreds too. These keep a step short:

1. **One card per look.** Every colour and design gets its own card with its own photo, so a step shows every look at once and one tap puts it on the board. Sizes never get a card: the build picks them, and deck width is asked first. A product's colours sit next to each other in the list, so a big step still reads model by model, and choices you can't see, like Allen or Phillips heads, are filter chips. Baymard's advice to combine colours on one card is for long shop listings; in a builder step the look is the whole choice, and Baymard's swatch research found people miss colours hidden behind a "+N" label. Each colour needs its own photo and layer image, or the cards look alike.
2. **Width first.** The deck step opens on one question, what width do you ride, with how many decks are in stock in each. There is no All. The trucks step needs the width anyway, and asking first hides almost nothing (on the demo, 8.25″ still shows 90 of the 92 decks in stock), but it makes every card's stock true for that width and says up front when a width has none (7.75″, 8.125″ and 9″ on the demo today). In Baymard's apparel testing, 39% of participants found on the product page that something they liked wasn't in their size. A "Not sure of your width?" link sizes by shoe size, the width is remembered for next time, and rider builds and the deck page's own button bring theirs.
3. **Only what fits and is in stock.** Every step shows the parts that fit what is already picked, under today's fit rules. Sold-out parts fold into one line at the end, such as "Show 68 sold out", instead of filling the grid.
4. **A sensible order.** The part recommended for the build comes first, then the shop's featured products (the star in WooCommerce's product list), then best sellers. Price and newest are one tap away, so the first screen is already a short list worth picking from.
5. **30 at a time.** A step shows 30 cards, then a "Show 30 more" button under "Showing 30 of 90", instead of pages or endless scrolling. In Baymard's testing, a load-more button kept people browsing more than pages did and reading more closely than endless scrolling, and on a phone endless scrolling would push the product details and footer out of reach. Coming back to a step keeps its filters and its place in the list.
6. **Search in big steps.** Any step with more than about 40 parts gets a box at the top that searches brand, name, colour and size as you type, through Creative Search's index, so "rascals", or a typo like "kazmi", lands in one go. The step's chips narrow the results; a search that finds nothing says why and offers the nearest match, such as the nearest width.
7. **Filters from the step's own data.** Each step offers the attributes nearly all its parts carry, as chips that show how many parts each one leaves: brand and width on decks; brand, diameter and hardness on wheels; head and colour on hardware. An attribute set on only a few parts stays out, because filtering by it would hide every part nobody filled in: on the demo, concave is set on 21 of 160 decks. Colour joins the decks once the shop fills it in for most of them. On a phone they sit behind a Filters button, so the parts come first.
8. **Tools that grow with the step.** A step with a dozen parts or fewer shows just the cards. Sort and filter chips appear past about 12 parts, and search past about 40, so a small step stays a simple choice and a big one gets what it needs, with no setting to manage.
9. **Ways in that skip the hunt.**
   - **Start from this deck:** every deck's own product page gets a "Build a complete with this deck" button, which opens the builder with the deck picked (the deck link from milestone 6).
   - **Recently viewed:** the decks the shopper looked at this visit, first in the list.
   - **Rider builds:** the kit's presets shown as complete boards to start from.
10. **The card itself.** The picture large, the brand and name, the price, and stock for the chosen size. Tapping the card picks it and lands it on the board.

Under the hood, the state route sends one page of cards and the chip counts instead of every part, and thumbnails load as they scroll into view, so a step with 500 parts costs about the same as one with 20. Trucks, wheels and the rest keep today's fit rules and "Recommended for your deck" labels.

## Motion

The motion is playful: parts land with a little bounce and squash, trucks snap onto their holes, wheels spin on, and the price ticks over. Every animation still shows a part being built, changed or priced, and none of them waits on the shopper. They use transforms and opacity only, run under 300 ms except the flip and the add to cart, and become short cross-fades when the shopper has asked for reduced motion.

| Moment | What moves | Time | With reduced motion |
| --- | --- | --- | --- |
| Deck picked | The deck settles onto the stage from just above, its shadow growing under it | 280 ms | Fades in, 150 ms |
| Trucks added | Each truck slides in from the side onto its bolt holes, the second 60 ms after the first | 240 ms | Fades in |
| Wheels added | Each wheel slides onto its axle end and spins a quarter turn as it lands, 40 ms apart | 220 ms | Fades in |
| A part swapped | The old part lifts away as the new one settles in its place, as one view transition | 200 ms | Cross-fade |
| A part removed | It lifts and fades, and the board closes the gap | 180 ms | Fades out |
| Grip tape on the top | The sheet lays down from nose to tail | 300 ms | Fades in |
| Show the top | The board turns over like a card | 400 ms | Cross-fade |
| Price changes | The total counts from the old amount to the new; the estimate fades in or out | 300 ms | Changes at once |
| Next step | The panel's content slides 16 px the way you're going; the rail's marker follows | 200 ms | Cross-fade |
| Add to cart | The board shrinks toward the cart icon, which bumps its count | 400 ms | The count changes |

- **How it's built.** View transitions for step changes and swaps, with the Web Animations API for the board's parts. No animation library. The extra script stays under 15 KB gzipped.
- **Never in the way.** A pick is accepted the moment it's tapped; animations run alongside the state route's answer and are cut short if the shopper picks again. Interaction to Next Paint stays under 200 ms on a mid-range phone.
- **The screen reader** hears the same status sentence as today, once per change.

## Settings

There is one layout, done well, and a shop gets four settings on the kit's Build tab, each with a default that suits most shops.

| Setting | Choices | Default |
| --- | --- | --- |
| Stage side on a desktop | Left or right | Left |
| Board size on a desktop | Large (60% of the width) or balanced (50%) | Large |
| Motion | Playful, or subtle (fades only, for every shopper) | Playful |
| Search in the deck step | Creative Search where it is active, the builder's own search otherwise | Creative Search where active |

There is no mode switch between different builders. Each extra mode would double the design, the tests and the visual baselines across the twelve audit dimensions, and shops seldom pick well between modes.

## Accessibility, performance and search engines

The new layout keeps every promise the current builder makes, and fixes the two gaps the milestone 8 review found.

- **Keyboard.** A skip link to the step, then the rail, the search, the chips, the cards and the footer, in that order. Arrow keys move between cards and pick as they go, as native radios do; Enter goes to the next step. Reaching the first deck takes a handful of key presses, not 37. A focus ring that contrasts on light and dark themes alike (M8 F1).
- **Screen readers.** The stage is a labelled figure; the status region speaks each change once; the rail is a list of links with the current step marked; a finished step says what was picked.
- **Reduced motion and zoom.** Reduced motion swaps every movement for a fade; the layout holds at 200% zoom and 320 px wide.
- **Phone.** Tap targets of at least 44 px; the bar never covers the control in focus; the estimate shows in the bar (M8 F2).
- **Speed.** Largest paint under 2.5 s on a mid-range phone over 4G, the deck's resized copies loaded first; Interaction to Next Paint under 200 ms; no layout shift as parts land, because the stage keeps its size.
- **Search engines.** The kit's page stays one indexable page with its description below the configurator. Build links stay noindex with the clean page as canonical, as today.

## What stays, the plan, and open questions

The redesign replaces the builder's front end and keeps everything under it.

- **Stays:**
  - the state route and its schema, with additions only;
  - fit, prices and the estimate (D-213);
  - the picture's layout and resized copies (milestone 7);
  - saved builds, orders, the Build box, the assembly ticket and emails (milestone 8);
  - the hooks and class names HOOKS.md promises, with any rename deprecated properly.
- **New underneath, small:** showing size variations once, since the build picks the size (WooCommerce holds a deck graphic as one variable product with a variation per width), while each colour and design keeps its own card; cards sent a page at a time with their chip counts, a search call to Creative Search limited to the parts a step offers, and a setting to turn off the theme's sticky bar on kit pages.

**The plan, after milestone 9:**

1. **Now:** this proposal and a clickable prototype, for Zach to try on a phone and a desktop.
2. **Milestone 10, the configurator:**
   - the full-screen template, stage, panel, rail and footer;
   - the phone sheet;
   - width first, one card per look, search, filters and 30 at a time, and the bearings and risers steps that appear only when needed; the three Creative Search additions, in its own repository;
   - the four settings.
3. **Milestone 11, the motion:**
   - every animation in the motion table, with reduced motion;
   - visual and performance baselines for each moment.

Each milestone is reviewed on the demo as today.

**Decided on 18 November:**

- **Search:** Creative Search searches only the parts the step offers.
  - The builder calls it in PHP from the state route, scoped to the slot's categories through its existing scope option (up to 500 products ranked), then keeps the parts the step offers. The whole category is ranked, so no match is lost and counts stay right.
  - Without Creative Search, the builder searches its own list.
  - Creative Search gains a small documented way for other plugins to call it from PHP (today its scope is reachable only through internal classes), an option to search only given product IDs for slots that list parts by hand, and its own log source, so builder searches stay apart from shop searches in its analytics. None of this goes on its public REST route.
- **Footer:** the site's footer stays, below the product details.
- **Rider builds:** named builds drawn by the builder for now. Each card takes a rider photo when there is one.
- **Bearings:** a step after Wheels.
  - Every board takes eight standard 608 bearings, two a wheel ([Skate Warehouse](https://www.skatewarehouse.com/learning-center/skateboard-bearing-buying-guide.html)).
  - They fit any standard skateboard wheel on an 8 mm axle ([Sector 9](https://sector9.com/blogs/blog/skateboard-bearing-sizes-what-you-need-to-know)), so the step needs no fit rule.
  - Wheels sold with bearings skip it.
- **Motion:** playful.
- **Deck width:** asked first, with no All, so deck cards drop their width chips.
- **Variations:** every colour and design is its own card; sizes are picked by the build, bolt length included: 1″ on a standard board with no riser pads ([Warehouse Skateboards](https://www.warehouseskateboards.com/help/Skateboard-Hardware-Buying-Guide)). No admin settings for this.
- **Risers:** no step for everyone. The risers step appears only when the wheels are over the trucks' limit (rule R3), opens on the riser that clears them with the reason, and the bolts follow (R4). Wheel cards say "Needs 1/4″ risers with your trucks" before the pick, risers are never added silently, and anyone who wants them anyway can add them from the review. A kit with no risers in stock shows over-limit wheels as not fitting, with the reason.

**Sources**, read 18 November 2026:

- [Smashing Magazine, Designing a perfect configurator UX](https://smashingmagazine.com/2018/02/designing-a-perfect-responsive-configurator)
- [BMW Group Designworks, BMW configurator](https://www.bmwgroupdesignworks.com/case-studies/bmw-configurator-making-digital-configuration-a-true-brand-experience/)
- [Nielsen Norman Group, Wizards](https://www.nngroup.com/articles/wizards/)
- [Baymard, combine variations into one list item](https://baymard.com/blog/combine-variations-one-list-item)
- [SkatePro, Build a skateboard](https://www.skatepro.com/en-us/build-a-skateboard.htm)
- [web.dev, same-document view transitions are Baseline](https://web.dev/blog/same-document-view-transitions-are-now-baseline-newly-available?hl=es)
- [Baymard, put the size filter near the top](https://baymard.com/blog/apparel-put-size-filter-near-top-and-expand-for-sidebar-filtering)
- [Baymard via Smashing Magazine, pagination, infinite scrolling or load more](https://smashingmagazine.com/2016/03/pagination-infinite-scrolling-load-more-buttons)
- [Baymard, number of items loaded by default](https://baymard.com/blog/number-of-items-loaded-by-default)
- [Skate Warehouse, deck buying guide](https://www.skatewarehouse.com/learning-center/Skateboard_Deck_Buying_Guide.html)
- [Baymard, colour swatches in mobile product lists](https://baymard.com/blog/mobile-interactive-color-swatches)
