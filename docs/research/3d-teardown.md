# 3D teardown: four competitor builders

Cowork's competitor review asked for an optional 3D view. Before we design one, this page records how four shops' builders do 3D (five views in all, since Lumien and 2HEX were measured together), measured on 24 Nov 2026. It holds ideas and numbers only, from four sets of notes taken that day, which are not kept in the repository.

- [Summary](#summary)
- [Comparison](#comparison)
- [Skate Warehouse UK](#skate-warehouse-uk)
- [Empire Skate](#empire-skate)
- [Boardpusher](#boardpusher)
- [Lumien](#lumien)
- [2HEX](#2hex)
- [Across builders](#across-builders)
  - [Camera moves](#camera-moves)
  - [Tricks and other motion](#tricks-and-other-motion)
  - [When a part changes](#when-a-part-changes)
  - [Lighting](#lighting)
  - [Accessibility](#accessibility)
- [What a generated board needs](#what-a-generated-board-needs)
- [Method](#method)
- [Source handling](#source-handling)

## Summary

Skate Warehouse UK is the one to beat: it loads 3D only once the build is complete, generates the deck and wheels from their sizes, and falls back to a clean 2D picture without WebGL, but it weighs about 2.2 MB, renders every frame forever, rebuilds the whole scene and replays a 7 s intro on every part change, and ignores reduced motion. Empire Skate loads 2.8 MB on every page view, draws every part at one fixed size, and leaves an empty box without WebGL; Boardpusher shows the deck only, from 0.3 to 4.0 MB uncompressed Blender meshes per size that it then fits to the same screen height; 2HEX shows one wheel shape at one size, 8 to 14 % off its own named proportions; and Lumien is a CSS tilt of a flat drawing that ignores the width picked. No one draws every part to scale from its own specs, shows fit, stays light, idles at zero cost, and works by keyboard and with reduced motion, and that whole set is the gap we can win.

## Comparison

Sizes are in KB, transferred / decoded where both were measured. "Measured error" compares what the 3D draws with the size sold.

| Builder | When 3D loads | Script | Model | Textures | Lighting | Total | Library | How the model is made | To scale, measured error | Idle rendering | Phone (375 px) | No WebGL | Keyboard | Reduced motion |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Skate Warehouse UK** | On demand, once the build is complete (step 9 or Random board); a 2D canvas picture while building | 271 / 854 for the 3D chunk, plus 95 / 269 for the builder on page load | 295 / 295 (custom `.bin`, no compression) | About 260 of the shop's product photos per build, drawn into 30 canvas textures | 1,616 / 1,615 HDR, no compression | About 2,180 transferred on first view, plus about 260 of photos | three.js r158 | Deck and wheels generated from sizes; trucks from the model file; bolts, nuts and bushings generated | Deck width exact; wheel diameter exact (52 mm drew 2.05 in); wheel width fixed at 1.25 in for every wheel; truck hanger span tracks the deck width (4.85 to 5.5), so it seems stretched to fit (inferred) | Every frame (120/s headless), about 4.7 passes a frame, about 250 ms of main thread per 5 s; stops on another builder step | Works; the view sits 927 px down, below the buy panel; trick row overflows; pixel ratio capped at 2 | Clean 2D picture, large; the 3D chunk, model and HDR never download | Real buttons only; the canvas isn't focusable; no arrow keys | Ignored |
| **Empire Skate** | With the page, unasked (WebGL context at about 3.85 s, before `load`) | 291 br / 1,139 (React 18, react-three-fiber, drei, react-spring, three), plus 2 / 5 and 4 / 14 of glue | 390 br / 780 GLB | 1,405 at start (2048 px); 155 to 1,224 more per picked part | 732 / 732 HDR, no compression | About 2,800 on every page view | three.js r139 | One generic hand-made GLB; a hand-kept lookup of 431 product images | No: every popsicle deck draws at 7.6 in (an 8.5 in deck is 0.9 in, 10.6 %, narrow); trucks with wheels always 7.7 in wide (8.75 in trucks picked, 12 % narrow); wheels always 53.7 mm (52 mm picked) | 60 fps forever, 6 renders a frame, in view or off-screen, about 30 ms of main thread a second | Works; the canvas is 375×536 and traps vertical swipes; pixel ratio capped at 2; all 2.8 MB loads | Empty 720 px grey box, uncaught error; buttons stay and do nothing; the 1.1 MB script still runs | Canvas, camera presets and parts list unreachable; only the trick buttons work | Ignored (float 49 mm against 51 mm) |
| **Boardpusher** | On tap of "SHOW 3D"; a grey spinner over the 2D deck (0.6 s on fibre, 4.4 s at 9 Mbps, 23.4 s at 1.6 Mbps) | 564.6 / 563.8, no compression (114 with Brotli) | 5.2 + 3,448 for 8-1/4 (300 to 3,956 by size), no compression | 551.9 / 550.7 (331 to 730 by size) | 0 (three lights, no HDR) | About 4,570 | three.js r157 | One hand-made Blender model per deck size, deck only | Widths up to 0.15 in narrow (8-1/2 drew 8.35, 1.8 %; 8 drew 7.88), mixed units and axes; the view fits every size to the same height, hiding scale | 60 fps forever, still 60 after closing | Works; full takeover; pixel ratio uncapped (a 2.6 MP buffer) | Downloads the 564 kB loader, then silently hides the link; 2D stays | None: no focus, no keys, Enter doesn't open, Escape doesn't close | Ignored |
| **Lumien** | With the page; a server-rendered SVG tilted with CSS | 8.5 gz / 7.0 br / 35.7 raw for a shared chunk; the tilt is under 1 | 0 (7 SVG nodes, about 1 in the HTML) | 0 | 0 | About 8.5 gzipped | None (CSS 3D transforms on a React page) | One generic hand-drawn top view | No: the width options (7.75 to 8.5 in) change nothing in the drawing | 0 rAF frames at idle | Works; touch drag tilts; a swipe on the stage can't scroll the page | Not applicable; works without JS at its rest tilt | None for the tilt; the option buttons are real | Ignored (its transition never visibly runs) |
| **2HEX** | On demand at step 2, behind a "3D Preview" toggle; but three.js is in the site-wide bundle on every page | `app.js` 515 / 2,600, site-wide | 693 OBJ for the default shape, no compression (338 to 1,260 by shape) | 0 | 0 (two analytic lights) | About 690 per shape viewed, on top of the site bundle | three.js r135 (`vue-3d-model`) | One OBJ per shape at one size; picking a size changes nothing | No: diameter ÷ width is 8 to 14 % off each file's named size, so wheels draw narrow | Continuous (about 40 fps in SwiftShader); every shape viewed leaves a loop running (4 after 3 changes) | Fixed 300 px canvas, 15 px off the left edge | Text message, an uncaught error; the Photo tab still works | None; the steps themselves are click-only | No 3D motion; its 800 ms panel fade ignores it |

## Skate Warehouse UK

**What it does.** A builder of nine steps shows a small top-down 2D canvas while the shopper picks parts. Once every part is chosen, step 9 loads three.js r158 with a custom model and a studio HDR, plays an intro of about 7 s, and offers three camera presets and five tricks. It never reaches WebGL on browsers without it.

**Keep:**

- **3D only on request,** after the build is complete, with a 2D picture while building and as the no-WebGL fallback.
- **Build from sizes:** the deck at its exact width and its own length, and wheels lathed to the exact diameter.
- **Three camera presets with clear words** (Reset, Show the graphic or grip, Close up), each eased in about 500 ms, with the label toggling.
- **Real buttons for every control,** 44 px tall on phones.
- **Zoom on Ctrl or ⌘ + wheel only,** so the page still scrolls past the canvas.
- **A soft ground shadow that shrinks with height** (to 0.60 to 0.72 at a trick's peak).
- **Queueing a second trick press** instead of cutting the first.
- **A confirmation before Random replaces a built board** ("Your current board will be replaced with a random one. Every part will still fit.").
- **An honest caption** under the view ("Drawn from the product photos…").
- **Stopping rendering** while the 3D is on a hidden step.

**Beat:**

- **Weight:** about 2.2 MB before photos. The HDR is 74 % of it, and it and the model are sent with no compression.
- **Truck and wheel accuracy:** draw the hanger from the truck's own axle width and the wheel at its own width, not stretched to the deck or fixed at 1.25 in.
- **Idle cost:** render on demand, and stop when hidden or off-screen, where they draw every frame at about 4.7 passes.
- **Part change:** keep one renderer and swap the changed part, where they create a new WebGL context and replay the intro.
- **Reduced motion:** no auto-rotate, no auto trick, instant camera moves.
- **Keyboard and screen readers:** a named canvas (`role="img"` listing the build), arrow-key orbit and +/− zoom with a visible focus ring, and a polite live region for view changes.
- **Phone:** keep the rows from overflowing, don't bury the view below the buy panel, and set `touch-action` so a pinch doesn't zoom the page.

**Drop:**

- **The 7 s auto-playing intro** with a cut and an automatic ollie, on every visit and every part change.
- **Endless auto-rotate** (about 13.5°/s).
- **The 1.6 MB studio HDR,** and a post-processing chain on a product view.
- **Snapshot renders that stall the GPU** (ReadPixels) during the intro.
- **A two-direction shadow rig** (2048² and 1024² soft shadow maps) when a blob shadow does most of the work.
- **A tricks bar as a core feature.** If ever, it's M11 motion, behind reduced motion.

## Empire Skate

**What it does.** A React and react-three-fiber designer loads with the product page and shows a floating blank board before anything is picked. Picks swap colour maps from a hand-kept lookup, accordion rows fly the camera to each part, and a trick mode plays two baked 1.0 s clips. There is no fit checking: 8.75 in trucks on an 8.0 in deck draw with no warning.

**Keep:**

- **Camera presets per part** (deck, grip, trucks, wheel), tied to the part being picked, with short spring moves of about 0.8 to 1.1 s and a small drag allowance (±5° or ±10°) in close-ups.
- **A soft contact shadow** under the board, baked or rendered once per change.
- **Tricks as a delight,** short (about 0.7 s of motion) and only on request, respecting reduced motion.
- **A plain hint under the view** ("Drag the board around…"); ours can say it's to scale.
- **Part-specific mesh swaps** (a shaped deck, truck families) and grip hidden where it doesn't apply.
- **A neutral studio look:** image-based light, ACES and a light grey.

**Beat:**

- **Accuracy:** generate from each part's real sizes, and show the overhang and why a pick doesn't fit.
- **Fit checking:** they have none; show the fit engine's result in the picture.
- **Weight:** on request, no GLB, small textures, a tiny or generated environment, and no React runtime, against their 2.8 MB on every view.
- **Coverage without hand work:** any stocked part drawn from its specs and colour, against 431 hand-made entries where anything missing shows blank.
- **Idle cost:** render on demand; pause with IntersectionObserver and on `visibilitychange`.
- **No WebGL:** catch the context error and keep the 2D picture.
- **Accessibility:** a named canvas, focusable buttons for the views, arrow keys, reduced motion honoured, and a parts list reachable by keyboard.
- **Phone scrolling:** `touch-action: pan-y` with a horizontal drag to turn, or a tap-to-interact mode.
- **Honest copy:** say what the picture shows and to what scale, not "some aspects may differ slightly".

**Drop:**

- **An endless idle float** at 60 fps that ignores reduced motion.
- **A per-frame contact-shadow render and `preserveDrawingBuffer`** just for a photo button.
- **A full React, react-three-fiber, drei and react-spring stack,** with Draco, KTX2 and meshopt code it never uses.
- **Hand-made 4096 px textures per product** in a third-party custom-fields app.
- **Camera icons as images with `role="button"`.**
- **Loading the 3D on every page view,** phones and no-WebGL browsers included.
- **A photo mode** that resizes the live canvas; a server-side image is better if we ever need one.

## Boardpusher

**What it does.** A 2D deck designer with a "SHOW 3D" link that swaps the whole stage for a full-bleed canvas of the deck alone. Each deck size is its own Blender model, and the shopper's 2D design is rasterised into the bottom texture. A spin-and-wobble intro plays on each load and size change.

**Keep:**

- **3D on request,** with the 2D view in place until the 3D is ready.
- **One edge mesh with a ply-stripe texture,** separate from the top and bottom, so the colourway is a texture swap.
- **The bottom texture from the same render as the 2D view,** so 2D and 3D can't disagree.
- **A narrow camera (fov about 10°)** that reads close to a product photo.
- **Simple lights with no HDR download:** ambient, one key and one spot.
- **OrbitControls basics:** one-finger orbit, pinch zoom, clamped distance.
- **Feature-detect before building the renderer,** and keep the 2D view working.

**Beat:**

- **Generate the deck from its sizes** (width, length, nose and tail, kick and concave) in a few thousand triangles, not 0.3 to 4 MB per size.
- **Plies true to the spec:** ply count and colours from the part's specs, not one of four stock JPEGs.
- **Compress everything,** and ship only what's used (no bottom texture the design replaces, no unused decoders).
- **Keep sizes to scale:** one fixed camera per category, so a 9 looks wider than an 8.
- **Render on demand,** stop on close, hide or off-screen, and cap the pixel ratio at about 2.
- **Real loading progress** in a live region, if loading is long enough to need it.
- **Accessibility:** named buttons, a labelled focusable canvas, arrow keys, +/−, Home to reset, Escape to close with focus returned, and dialog semantics if it overlays.
- **Reduced motion:** no intro spin and no damping coast.
- **The builder usable beside the 3D,** so a part change updates the model live.
- **A clamped polar angle** and a reset-view control.

**Drop:**

- **The full-stage takeover** that hides the designer.
- **The 940 ms spin-and-wobble intro,** which puts the rest pose at about 2.3 s.
- **A 2.9 s damping coast** after a short drag.
- **Shadow maps on with nothing casting.**
- **A quality-1 JPEG through `toDataURL`** for the design texture (about 0.6 s of freeze and ReadPixels stalls).
- **Silently hiding the 3D link** without WebGL.
- **Hand-edited `?vb=` cache busters** with no cache headers.

## Lumien

**What it does.** A product demo that draws a generic board as seven SVG nodes and tilts it with CSS 3D transforms as the shopper drags. It is server-rendered, works without JS, and costs nothing at idle. The width options change only the spec text.

**Keep:**

- **Server-rendered,** working without JS at a rest tilt.
- **Zero idle cost.**
- **One Pointer Events path with pointer capture,** so mouse, pen and touch behave the same, coalesced to one frame.
- **Clamped angles** (Y ±32°, X −14° to +16°), so the board never goes edge-on by accident.
- **The strong ease-out curve,** `cubic-bezier(0.17, 0.17, 0.3, 1)` over 500 ms.
- **Option buttons with `aria-pressed`.**

**Beat:**

- **Thickness and an edge** for a turn, so a board at 90° doesn't vanish into a line.
- **Hardware on its own depth layers** for parallax.
- **A shadow outside the rotated group,** scaled by `|cos θ|`.
- **Perspective scaled to the stage** (theirs is a fixed 1200 px, flat on a phone).
- **A keyboard equivalent and a button for the turn,** reduced motion honoured, the drawing to scale, and a live region for changes.

**Drop:**

- **One generic drawing for every product.**
- **`touch-action: none` across the stage on phones;** prefer `pan-y` and a horizontal drag, or a turn button.
- **An em dash in an accessible name.**

The notes also answer whether a CSS turn of our own 2D board could feel physical without WebGL: yes, with two faces and an edge layer at the board's real thickness, hardware on `translateZ` layers at their real heights, a shadow outside the turn scaled by `|cos θ|`, shading driven by the angle, and perspective tied to the stage width. The turn would be one 180° rotation in about 500 to 700 ms, a cross-fade of about 150 ms or a cut under reduced motion, and a real button ("Show the top" or "Show the underside").

## 2HEX

**What it does.** A Vue wheel configurator whose second step has a "3D Preview" toggle beside a photo. Each of 13 shapes is one OBJ at one size, viewed with three's default lights and controls. The size list updates only a read-only contact-patch field.

**Keep:**

- **The contact patch as a number** next to the size.
- **Face-on default framing,** which shows the lip and seat at once.
- **A photo view beside the 3D.**
- **Their patch-to-width ratios** as defaults per shape family.

**Beat:**

- **Generate the wheel from diameter, width and patch,** to scale, not 13 uncompressed OBJ files of 0.34 to 1.26 MB.
- **three.js only on request,** not in the site bundle.
- **One renderer, rendering only on change,** paused off-screen and torn down.
- **Smooth shading and lights that follow the camera,** so the far side never goes black.
- **A responsive canvas,** a keyboard path, a named focusable view, and our 2D drawing as the fallback.
- **Label the contact patch, lip and core** in the view.

**Drop:**

- **OBJ as a delivery format.**
- **Unlimited pan and zoom,** which lets a shopper lose the wheel.
- **Cached renderer instances** that keep their loops running.
- **Remounting the viewer on each change,** which resets the camera.
- **An opaque white canvas on a grey page,** and an 800 ms panel fade.

## Across builders

### Camera moves

| Builder | Lens and default view | Moves and durations | Zoom and limits |
| --- | --- | --- | --- |
| Skate Warehouse UK | fov 32; orbit radius 44.1, height 25.8 (three-quarter view from above) | Presets ease in about 500 ms: Show the graphic (height −32.4, radius 23.4), Close up (radius 25.1, height 4.8), Reset; auto-rotate about 13.5°/s, stopped by any input | Ctrl or ⌘ + wheel only (radius 36.7 to 22.7 for 400 px, no easing); no inertia |
| Empire Skate | Fixed distance, orbit; deck view distance 0.75 m | Springs: to trucks settles at 1,000 ms, to wheel 840, to grip 1,090, into trick mode 640, out 960; opening an accordion row waits 200 ms, then settles at 1,060 to 1,160 | Zoom off; ±5° (trucks) or ±10° (wheel) of drag in close-ups |
| Boardpusher | fov 10, deck upright filling about 75 % of the height | No camera moves; the intro moves the deck | Distance 1 to 10; no polar limit; damping coasts 2.9 s after release |
| Lumien | CSS `perspective: 1200px`; rest `rotateY(-8deg) rotateX(4deg)` | None; drag maps +0.35°/px horizontally and −0.18°/px vertically | Y ±32°, X −14° to +16°; no zoom |
| 2HEX | fov 45, auto-framed face-on | None; resets to face-on on every shape change | Unlimited zoom and pan |

### Tricks and other motion

Durations are from the click; heights are the board's rise. Skate Warehouse's are from its GPU run; Empire's are its baked clips.

| Trick | Skate Warehouse UK | Empire Skate |
| --- | --- | --- |
| Ollie | About 930 ms; rises 12 in; nose up 24° at the pop, tail down 7° before landing | None |
| Kickflip | About 1,200 ms; rises 15 in; one full roll about the long axis | 1.0 s clip (25 keys at 25 fps); about 730 ms of motion; rises 0.30 m (about 11.8 in) |
| Heelflip | About 1,200 ms; rises 15 in; the roll the other way | None |
| Pop shove-it | About 1,130 ms; rises 12 in; 180° about the vertical | None |
| 360 flip | About 1,470 ms; rises 17 in; 360° about the vertical with a flip | "Treflip", the same 1.0 s clip with rotation laid over the kickflip |

- **Curves:** Skate Warehouse eases in and out; a second press waits its turn; auto-rotate carries on during tricks.
- **Ground shadow:** Skate Warehouse's blob shrinks to 0.60 to 0.72 of its size at the peak and turns with the board.
- **Intros:** Skate Warehouse about 7 s (snapshot renders, a cut, a 2.6 s hold, an automatic ollie, a 450 ms ease out, a 0.8 s pause, then auto-rotate). Boardpusher about 940 ms: about 240° of spin in 320 ms, a swing to +22° over 200 ms, to −21° over 200 ms, and a settle over about 220 ms; at rest about 2.3 s after the tap.
- **Idle motion:** Empire's float has a period of about 12.6 s, 28 to 51 mm of travel per 5 s and about 2° of tilt, and never stops. Lumien and 2HEX have none.

### When a part changes

| Builder | What the 3D does |
| --- | --- |
| Skate Warehouse UK | Rebuilds everything on return to step 9: a new WebGL context (2 to 3), the HDR re-requested from cache, the intro replayed from 210 ms; the model isn't re-fetched; no transition on the changed part |
| Empire Skate | Swaps the slot's colour map when its image arrives; a shaped deck or truck family swaps the mesh; the camera flies to the part's preset; no animation and no size change |
| Boardpusher | Only size changes reach 3D: the new size's model and textures download (about 4.6 MB for 8-1/2) and the spin replays about 1.5 s later; design changes need a close and reopen |
| Lumien | Fills swap instantly; width changes nothing |
| 2HEX | Shape: a new OBJ, a new renderer and a camera reset; size, hardness, colour and print: nothing in 3D |

**No one swaps a part in place with a short transition.** That, with the part drawn at its new size, is open ground.

### Lighting

| Builder | Environment | Lights | Shadows | Tone and output |
| --- | --- | --- | --- | --- |
| Skate Warehouse UK | 1.6 MB HDR through PMREM (768×1024 target); a placeholder cube render first (inferred) | Hemisphere 1.2; key directional 1.5; under light 0.95 for the flipped graphic; fill 0.35 | `PCFSoftShadowMap`, 2048² and 1024² maps, plus two blob planes; about 3.7 full-screen passes a frame (likely post-processing, inferred) | No tone mapping, exposure 1.05, sRGB; grey gradient background |
| Empire Skate | 732 KB HDR (1000×500 RGBE) as the only light | None | ContactShadows: a 512 px target re-rendered and blurred every frame (5 passes) | ACES Filmic, exposure 1, sRGB; transparent background |
| Boardpusher | None | Ambient 2.2; directional 1.5; spot 6 (36° cone) | Shadow maps on, nothing casts | No tone mapping, sRGB; a flat, even look |
| Lumien | None | None (flat fills) | A fixed ellipse that tilts with the board | Not applicable |
| 2HEX | None | Hemisphere 0.2; directional 0.8, fixed in the world | None | Linear output; opaque white background; the far side goes nearly black |

- **The lesson:** the two that download an HDR spend 0.7 to 1.6 MB on it, the two that don't look flat or go dark, and a small generated environment plus a key light that follows the camera sits between them.

### Accessibility

| Builder | Canvas | Controls | Keyboard | Live region | Reduced motion | axe |
| --- | --- | --- | --- | --- | --- | --- |
| Skate Warehouse UK | No role, name or tabindex | Real buttons, 44 px on phones; no visible focus ring found | Buttons only; Enter starts a trick | None | Ignored | 0 violations on the builder |
| Empire Skate | No role, name or tabindex | Camera presets are unfocusable `<img role="button">`; tricks are buttons | Canvas, presets and parts accordion unreachable | None | Ignored; never calls `matchMedia` | `image-alt` ×3, `dlitem`, `heading-order` |
| Boardpusher | No role, name or focus | `<a>` without href; Enter doesn't open 3D | None; no Escape; focus stays on `<body>` | None | Ignored | 8 violation types, all on the surrounding page |
| Lumien | `role="img"` with a label (which has an em dash); not focusable | Buttons with `aria-pressed` | No tilt by keyboard | None | Ignored | `color-contrast` ×19, `label-content-name-mismatch` |
| 2HEX | No role or name | Step tiles are click-only divs; the shape select has no label | None | None | No 3D motion; the 800 ms fade ignores it | None on the configurator row |

- **axe misses the worst of it.** It has no rule for an unnamed canvas or an unfocusable `role="button"`, so every gap above came from manual checks.

## What a generated board needs

### The deck's outline

- **Width exact,** as Skate Warehouse draws it (7.75, 8.0 and 8.25 in each to the size).
- **Length per deck:** Skate Warehouse's ran 32.17 to 34.38 in; Boardpusher's models ran 29.3 to 33.4 in.
- **Nose, tail, kick and concave from the part's specs.** Boardpusher's rise (concave plus kick) varies 1.24 to 2.59 in across its sizes, unsystematically.
- **A few thousand triangles.** Skate Warehouse's deck is 38,400 triangles in three materials; Boardpusher's 8-1/4 is 153,664, and its sizes differ 13× for near-identical shapes.

### Plies and edge

- **A separate edge with its own material.** Boardpusher uses an edge ring mesh (26,880 triangles on 8-1/4) with a ply-stripe texture; Skate Warehouse gives the ply edge its own physical material.
- **Ply count and colours from the specs.** Boardpusher's stripe texture holds about seven bands, two dyed black on one colourway, chosen per size from four stock JPEGs.
- **Thickness about 7 mm** (Boardpusher reads 6.9 mm at the centre).
- **The graphic wraps over the rail** on Boardpusher, so the bottom texture covers part of the edge.

### Wheel profile

From 2HEX's geometry and handbook, as a lathe profile:

| Feature | Value |
| --- | --- |
| Outer radius and width | D/2 and W, to scale (2HEX misses by 8 to 14 % on D÷W) |
| Flat contact band | About 0.52 W by default; 0.60 to 0.65 W for wide or square shapes; handbook range 0.49 to 0.65 W |
| Shoulder | A rounded quarter-ellipse from the band edge to the face |
| Lip (radius at the face) | About 0.55 R for round or conical; about 0.8 R for square-lip; an optional dished face |
| Bearing seat | The 608 bearing's real size: 22 mm across, 7 mm deep (2HEX's 0.44 to 0.53 R doesn't match 22 mm at its named sizes) |
| Centre ridge | About 0.33 to 0.40 R, across the middle third of W; the 8 mm axle hole shows through |
| Core | Optional second ring from the seat out to a core radius, in its own material; 2HEX models none |
| Resolution | 48 to 64 segments and 30 to 40 profile points: 3,000 to 5,000 vertices (2HEX: 76 segments, 8,000 to 13,000 vertices) |
| Normals | Smooth across tread and shoulder, hard at the lip and the seat step |
| Sizes offered at 2HEX | 50 to 60 mm across, 28 to 36 mm wide |

- **One lip setting plus the patch ratio** covers most of 2HEX's 12 stock shapes.
- **Width from the wheel's own spec.** Skate Warehouse's fixed 1.25 in for every wheel is the error to avoid.

### Trucks

- **The hanger from the truck's own axle width,** not stretched to the deck as Skate Warehouse's appears to be (4.85 to 5.5 tracking deck width; inferred).
- **Families as shapes:** Empire swaps meshes for a generic, a Thunder and a Venture shape.
- **Weight of a modelled truck:** Skate Warehouse's are two meshes of 22,260 and 15,520 triangles from its model file. The notes measure no truck profile beyond that.

### Bolt heads and small hardware

- **Generated primitives are enough.** Skate Warehouse draws bolts, nuts and bushings as small cylinders and spheres.
- **Empire draws none,** and its FAQ says bearings, bolts and accessories make no visible change.

### What the measurements suggest

- **Light enough to load on request:** generated geometry has nothing to download, so the chunk is the library and our code. Every competitor's weight is in models (0.3 to 4.0 MB) and HDRs (0.7 to 1.6 MB).
- **A scene budget well under Skate Warehouse's:** it draws about 172,600 visible triangles in 85 meshes with 30 textures and 30 programs.
- **Graphic from the same source as the 2D view,** as Boardpusher does, without its `toDataURL` freeze.
- **One fixed camera per category,** so sizes compare.
- **Pixel ratio capped at 2,** as Skate Warehouse and Empire do; Boardpusher's uncapped DPR 3 makes a 2.6 MP buffer.
- **Render on demand.** Every WebGL builder here renders every frame at idle, from about 30 ms of main thread a second (Empire) to about 250 ms per 5 s (Skate Warehouse).

## Method

- **Browser:** plain Playwright, headless Chromium, one browser at a time, at every target; WebKit once for Lumien.
- **Scene reading:** three.js's own devtools hook (`__THREE_DEVTOOLS__` "observe" events), set in an init script, with `renderer.render` wrapped to count renders and sample the camera and transforms, and `requestAnimationFrame` wrapped to count callbacks and main-thread time.
- **GPU backend:**
  - **Metal** (`--use-angle=metal`, Apple M1 Max) for every timing and frame figure at Skate Warehouse, Empire and Boardpusher.
  - **SwiftShader** (`--use-angle=swiftshader --enable-unsafe-swiftshader`) for first passes; its timings were discarded at Skate Warehouse (about 2.5 fps) and Boardpusher (7 to 10 fps). Empire used it for screenshots, the phone, reduced motion and keyboard. 2HEX ran on SwiftShader only, so its frame rates are software rates.
- **Other checks:** `--disable-webgl --disable-webgl2` for no WebGL; a 375×812 phone at DPR 3 with touch; `prefers-reduced-motion: reduce`; axe-core through `@axe-core/playwright`; network throttling at Boardpusher (9 Mbps with 60 ms, and 1.6 Mbps with 150 ms).
- **As a shopper:** no login, upload, form or purchase. Empire's cart routes were blocked as a safeguard, and nothing hit them.
- **What couldn't be measured:** a hidden tab. Headless Chromium kept `visibilityState` "visible" with another tab in front, and minimising didn't hide the page. Each loop is plain `requestAnimationFrame`, so a real browser would pause it in a hidden tab; that is inferred, and none of the builders has a `visibilitychange` handler of its own.
- **Downloads deleted:** every script, model, texture, HDR header, markup dump and API response fetched for analysis was deleted once the analysis was done, before the notes were finished. Only numbers, screenshots, logs and our own measuring scripts were kept.

## Source handling

- **Skate Warehouse's 3D script header carries a proprietary notice that asks AI tools not to reproduce it.** Its two builder scripts were fetched once, for a string check (the library version, and whether certain APIs appear at all), then deleted.
- **None of any builder's code, shaders, models, textures or HDR files is reproduced here** or in the notes. Every figure comes from run-time observation, file sizes, or numbers read from the files before they were deleted.
