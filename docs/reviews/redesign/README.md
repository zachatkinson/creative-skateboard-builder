# Skateboard builder redesign: hand-off

From Claude in Cowork, 18 November 2026. The design for the builder's new front end, agreed with Zach.

## What's here

- `proposal.md`: the proposal and every decision, exported from the shared Claude Doc. If they ever differ, the doc is the source.
- `prototype.html`: a standalone copy of the clickable prototype. Open it in any browser. The bar at the top switches between desktop and phone, and between starting states (a fresh start, the longest deck names, the wheels step, the review).
- `prototype-source/`: the prototype's design-canvas files. `Main.dc.html` holds the markup and all the logic (the `Component` class); the other boards load it with a layout and a starting state. They need Claude's design canvas to run, so click through `prototype.html` instead.
- `screenshots/`: the main states at desktop (1440 by 900) and phone (390 by 844) size, including the longest product names in the demo's catalogue.

## What the prototype is, and what it isn't

- **Real data where the demo has it.** The demo's 160 decks and 28 wheels as of 18 November: names, brands, widths, prices, whether each is in stock, and WooCommerce's popularity order. Trucks, bearings, hardware and grip tape are samples.
- **One sample wheel**, "Retna Cruiser, Mint", comes with bearings, to show the bearings step skipping itself.
- **Stock is per deck, not per width.** The real build reads it per width.
- **The board is drawn shapes,** not the plugin's layer images.
- **Search is a stand-in for Creative Search.** It matches words and allows one typo in words of five letters or more.
- **The standalone copy redraws the page on every change.** It's for looking and clicking, not for judging timing or motion.

## Decided after the prototype

These are in `proposal.md` but not yet in the prototype:

- **One card per look.** Every colour and design is its own card. Sizes are picked by the build, bolt length included. In the prototype, hardware and grip are still separate sample products.
- **Risers.** The risers step appears only when the wheels are over the trucks' limit (rule R3). It opens on the riser that clears them, with the reason, and the bolts follow (R4). Wheel cards say "Needs 1/4″ risers with your trucks" before the pick. Risers are never added silently, and can be added from the review. With no risers in stock, over-limit wheels show as not fitting, with the reason.
- **A changed build.** When a saved build reopens with a sold-out part or a new price, show what changed and the closest replacement the plugin already finds.

## Trying the risers step on the demo

The test kit, "Complete skateboard (test)" (4843), has an optional Risers slot before Hardware. It offers "Test riser pads (builder test)" in 1/4″ and 1/2″. "Test trucks 139 (builder test)" is set to the Low height class, so on those trucks the kit's 54 mm wheels ask for 1/4″ risers and its 56 mm wheels ask for 1/2″. The demo's bolts stop at 1 1/4″, so the 1/2″ path shows what happens when no bolt is long enough. The test riser product and test trucks stay hidden from the shop except during a test.
