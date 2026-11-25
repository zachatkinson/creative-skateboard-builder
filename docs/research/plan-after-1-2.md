# The plan after 1.2.0

Approved by Zach on 24 Nov 2026, with his answers at the end. Built from Cowork's competitor review (eight items and the 3D view spec), the four teardowns, and Zach's #131 and #132. Each item is an issue in its milestone, and each milestone's `/plan-milestone` writes its full plan, with the risks under all twelve dimensions, in `docs/reviews/`.

## Contents

- [The order, and why](#the-order-and-why)
- [Milestone 13: Choosing made easier (1.3.0)](#milestone-13-choosing-made-easier-130)
- [Milestone 14: The 3D view (1.4.0)](#milestone-14-the-3d-view-140)
- [Milestone 15: At real size (1.5.0)](#milestone-15-at-real-size-150)
- [Planned only: the fit helper](#planned-only-the-fit-helper)
- [Zach's answers (24 Nov 2026)](#zachs-answers-24-nov-2026)

## The order, and why

| Milestone | Release | Cowork's items | Also |
| --- | --- | --- | --- |
| 12 (now) | 1.2.0 | 1 (F27 to F29), and most of 3 | #130, #133 |
| 13 | 1.3.0 | 2, 5, 6, the rest of 3 | #131, #132 |
| 14 | 1.4.0 | 4 | |
| 15 | 1.5.0 | 7 | |
| none | none | 8, planned only | |

- **The 2D work before the 3D.** The guided start, smart swaps, compare, and the cards and thumbnails help every shopper on every device, and with 3D off by default they're what most shoppers see. The teardowns found the leader ahead on finishing touches, not on 3D, so this is where second place closes the gap fastest.
- **Item 3 mostly ships in 1.2.0.** F27 and F28 turn the board to the side each step works on, and M11 already settles a new part into place with a cross-fade under reduced motion. What's left is the brief highlight on the changed area, which goes in 1.3.0.
- **3D in a milestone of its own.** It is a new renderer, a new script chunk and a new test kind (pictures of a canvas at fixed camera angles). Mixing it with the 2D work would make both reviews harder.
- **AR after 3D,** since it is built from the 3D scene.
- **The fit helper isn't built,** because it needs an AI provider, a cost and a privacy call that are Zach's.

## Milestone 13: Choosing made easier (1.3.0)

### Cards side by side on every step (#131)

- **What:** every step's cards put the picture beside the words, as the deck step does (D-322). The thumbnail is sized to its part: about 5rem square for the square product photos of trucks, wheels and bearings.
- **No admin setting.** A `cbuild_card_layout` filter only if testing shows a shop needs the other layout.
- **Accepted when:**
  - each step shows at least as many whole cards in the first view as now, at 1440 by 900 and 390 by 844, on the three themes;
  - a wheel's colour can still be told apart at the thumbnail's size: the walkthrough's wheel step, looked at;
  - nothing moves as a picture loads (CLS 0 in the browser test).
- **Tests:** a first-view count per step and theme, the CLS check, visual baselines per step, axe.

### Product thumbnails in the review (#132)

- **What:** each row of the review gets the part's thumbnail, the same component and rendition as its card.
- **Accepted when:**
  - every row with a picture shows it, and a row without one keeps the space;
  - the review loads no image a card hasn't already loaded, for parts already seen;
  - a part Mature Content flags is blurred in its row;
  - the image is `alt=""`, so a screen reader hears each row once.
- **Tests:** a browser test per point above, a visual baseline, an integration test with Mature Content's flag.

### Guided start (item 2)

- **What:** before the first step, optional questions: shoe size or height, where they skate (street, park, bowl, cruising), and a budget. The answers give a recommended deck width and wheel size, and three ready builds (budget, mid, premium), from in-stock parts that pass the fit rules.
  - Each pick says why in plain words ("8.25″ for a size 10 shoe", "54 mm for street and park").
  - Skippable at every point: one button goes straight to the builder, and any answer can be left blank.
  - The recommendations are rules in the domain, as fit is, with the numbers as settings the shop can change. The width guide's rows (D-253 and on) already hold shoe size to width.
  - Answers are kept only in the build's link. Nothing new is stored.
- **Baymard:** guided selling for shoppers new to a category, always skippable, recommendations explained.
- **Accepted when:**
  - each of the three builds passes the fit engine and is in stock when shown;
  - each recommendation names its reason;
  - a shopper can skip from any question, by keyboard and screen reader;
  - with no JavaScript, the questions are a form that works the same;
  - a shop with too few parts for three builds shows as many as it can, never a build that fails fit.
- **Tests:**
  - unit: the rules from answers to width and wheel size, at every boundary; the three builds' choice, including a shop with one part a slot;
  - integration: the builds against a real catalogue, sold-out parts left out;
  - browser: the flow on every engine and width, skipping at each point, no-js, French, RTL, axe.

### Smart swaps (item 5)

- **What:** on the review and on a picked card, "Same board for less" and "Better wheels" (and the same per slot): one tap swaps in a part that fits everything else, with the price difference shown.
  - "For less" is the cheapest in-stock part that fits; "better" goes by a ranking the shop sets (a setting, or a hook), never a guess from price alone.
  - The swap goes through the fit engine on the server, as any pick does.
- **Accepted when:**
  - every swap offered fits the whole build, and is in stock;
  - the price difference shown equals the total's change;
  - no swap is offered when none exists, rather than an empty button;
  - one Undo puts the build back.
- **Tests:** unit for the choice of swap; integration for prices with tax and the shop's currency; browser for the tap, the total, Undo, and the focus after it.

### Compare saved builds (item 6)

- **What:** under My account's My builds, pick up to three saved builds to see side by side, with the parts that differ and the price gap marked.
- **Accepted when:**
  - differences are marked in words as well as colour;
  - prices are today's, with a note where a part has changed since the build was saved (D-254's wording);
  - it works on a phone, one build per column with a scroll, and by keyboard as a real table.
- **Tests:** integration for today's prices and changed parts; browser for the table at each width, axe, French.

### The changed area's highlight (the rest of item 3)

- **What:** after a pick, the part's area of the board is outlined for about 600 ms. Under reduced motion, nothing moves; the outline shows and fades without motion.
- **Tests:** the animation's timing read in the browser, as M11's moments are; reduced motion; no layout shift.

## Milestone 14: The 3D view (1.4.0)

Built to Cowork's 3D spec. The teardown report (`docs/research/3d-teardown.md`) has the competitors' numbers this beats.

### What it is

- **Settings:** "3D view", off by default so the update changes nothing, and "Trick buttons", on or off. No other modes.
- **Loading:** only when a shopper taps "View in 3D". It stays live on every step while they build.
- **Generated, not modelled.** No model files and no HDR.
  - **Deck:** its outline from width, length, wheelbase, nose and tail; plies drawn in code on its edge; concave and kick from a default where a deck has none, flagged in the Catalogue check.
  - **Trucks:** by axle width and height, on the real hole pattern, the casting simplified.
  - **Wheels:** by diameter and width, with a contact band and a bearing seat.
  - **Bolts** at the 8 holes, and risers.
  - **The copy says so:** sizes true to scale, shapes simplified.
- **Textures** are the layer renditions the 2D board already caches: 1 px/mm on a phone, 2 px/mm on a desktop.
- **Lighting:** generated studio light (RoomEnvironment through PMREM, or plain lights).
- **Rendering:** on demand only, nothing while nothing moves, paused off-screen and in a hidden tab, pixel ratio capped at 2 (1.5 on a low-memory device), everything disposed on close.
- **Camera and controls:**
  - the camera turns to each step's side;
  - drag to orbit, pinch or scroll to zoom, arrow keys and + and − for the same;
  - reset, top, underside, and a close-up of the deck's graphic, all real buttons.
- **Tricks:** ollie, kickflip, heelflip, pop shove-it and 360 flip, timed from the teardown (an ollie about 0.9 s, a 360 flip about 1.5 s).
- **Accessibility:** the canvas carries the build sentence the 2D board already has; reduced motion turns off spin and tricks.
- **Fallbacks:** no WebGL2, a lost context or any error goes back to the 2D board with a short message, and never blocks Add to cart.
- **A part change swaps that part only,** never rebuilding the scene. Skate Warehouse's rebuild and 7 s replay is the thing to beat.

### The library

**three.js r187 with `WebGLRenderer`**, in its own chunk loaded on the tap. Measured with everything a generated board needs (renderer, scene, camera, extrude, lathe, standard material, RoomEnvironment through PMREM, OrbitControls):

| Bundle | gzip | Brotli |
| --- | --- | --- |
| three.js, `WebGLRenderer` | 152 kB | 125 kB |
| three.js, `WebGPURenderer` | 240 kB | 195 kB |
| OGL 1.0.11 | 18 kB | 16 kB |

- **It fits,** 48 kB under the 200 kB budget. About 133 kB is three's floor; the board's own needs add about 20 kB.
- **Generated lighting costs 1.8 kB** and downloads nothing. Its first build takes about 0.75 s of shader compiling, so it runs after the first frame is shown.
- **Not WebGPU:** 40 kB over, and opt-in, not three's default.
- **Not OGL,** though about 120 kB lighter: we would write and own a PBR material, environment lighting, extrude, lathe and colour management. It becomes the better choice only if the budget falls toward 50 kB.
- **Two calls for Zach:** `three` is a new run-time dependency, and the 3D chunk needs its own line in `npm run budget` (200 kB gzipped), beside the builder's 15 kB.

### Accepted when

- **Weight:** the 3D chunk is under 200 KB gzipped, and with 3D on the builder page's bytes, LCP and CLS match 3D off until the tap.
- **Accuracy:**
  - the deck's width, length and wheelbase in the scene are within 0.5 mm of its specs;
  - the truck's holes meet the deck's holes;
  - the wheels sit on the axle's ends, and the wheel's width is its own, not a default.
- **Idle:** no frames drawn after the last move settles, off-screen or in a hidden tab.
- **Fallback:** with WebGL off, the 2D board shows with the message, and Add to cart still works.
- **Accessibility:** every control reachable by keyboard with a visible focus, the canvas named, reduced motion honoured, axe clean.
- **Phone:** the 3D never traps a vertical swipe; the page still scrolls past it.

### Tests

- **Unit, the geometry from sizes:** truck holes meet deck holes for every hole pattern; wheel positions from axle width; the deck's outline at every shape family's extremes; defaults applied and flagged where a size is missing.
- **Browser:**
  - pictures of the canvas at fixed camera angles (top, underside, three-quarter, close-up), in the visual project, with a tolerance for GPU differences;
  - frames counted over 5 s idle, off-screen and in a hidden tab: zero;
  - no WebGL, and a lost context forced with `WEBGL_lose_context`;
  - the page's bytes, LCP and CLS with 3D on against off;
  - keyboard, reduced motion, axe.
- **CI:** a budget check on the 3D chunk, as `npm run budget` does for the script and styles.

### What it replaces

ROADMAP.md's "Module B: 3D on a tap" assumed model files and the owner paying US$1,400 to $10,400 for them. Generated geometry needs neither, and trucks no longer wait on being stocked. This milestone replaces Module B.

## Milestone 15: At real size (1.5.0)

- **What:** "See it at real size": the 3D scene in AR at true scale.
  - **Android:** WebXR's AR session, from the same scene, generated on the device.
  - **iOS:** Quick Look needs a USDZ file. Making it on the device (three.js's USDZ exporter, then a blob link) keeps "no model files hosted".
  - Where neither is available, the button isn't shown.
- **Accepted when:** the board in AR measures within 1 % of its specs against a real ruler (a walkthrough check on a phone), and a device without AR never shows the button.
- **Tests:** unit for the export's scale; browser for the button's absence without AR; a manual check on one iPhone and one Android phone, recorded in the review.
- **A risk to flag now:** Quick Look's support for a blob-made USDZ is the part I trust least. The milestone's plan starts by proving it on a real iPhone, and falls back to Android only, with your OK, if it fails.

## Planned only: the fit helper

Item 8, not built until you decide:

- **A helper that answers fit questions** ("will these wheels fit my trucks?") from our own compatibility data. It needs an AI provider, a monthly cost and a privacy position: what a shopper types leaves the site, which today only the update check does.
- **Rider picks and Creative Reviews in each step:** no AI needed. Rider builds already exist as presets, and Creative Reviews is the agency's plugin. This half could join milestone 13 if you want it sooner.

## Zach's answers (24 Nov 2026)

1. **The order:** 2D (13) before 3D (14), as recommended.
2. **Guided start's numbers:** the width comes from the width guide each kit already has (#114, D-350: shoe size to width, set on the Build tab). Only riding style to wheel size is new: a small table on the same tab, with defaults from published wheel guides, that the shop can change.
3. **Smart swaps' "better":** a ranking the shop sets per slot, falling back to price where a slot has none.
4. **Rider picks and Creative Reviews:** into milestone 13, since they need no AI and build on presets and the agency's own plugin.
5. **The fit helper:** no budget, so dropped from the plan. It goes in ROADMAP.md's "Later ideas, not planned".

6. **three.js as a run-time dependency:** approved ("three it is"), loaded only on the 3D tap. Babylon.js, PlayCanvas, model-viewer, Filament, React Three Fiber, regl and TWGL were weighed and set aside: heavier engines, built around model files, or more to write than OGL.
7. **The 3D chunk's budget:** its own line in `npm run budget`, 200 kB gzipped, enforced in CI. It is a new limit, not a moved one, so the builder script's 15 kB stands.
