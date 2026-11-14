# UX research: what the builders we studied do well

From the [Creative Skateboard Builder UX deep dive](https://claude.ai/artifact/447cwcRifAicW4B7BRMgwo) (6 November 2026), arranged for the build:

- **Patterns P1 to P12** are what the builder does. The spec's The builder section uses them, and every review checks them.
- **Failures W1 to W9** are what it must never do.
- **The rest** is the evidence behind them, the 3D options, and the custom graphic module after 1.0.

## The answer

**Skate Warehouse UK's** builder is the one to beat. It:

- checks fit at every step and says why;
- adds a risers step only when the wheels need one;
- keeps the total and the next step in view;
- shows the board as it's built.

Ours matches it on all four and beats it where it slips: one cart line for the board, real buttons with distinct names, no late costs, and a page that works without JavaScript.

**From the car makers we take five things:**

- the deck first, and a fixed order of steps;
- starter builds the shopper can edit;
- a change that breaks later picks explained and confirmed, never silent;
- the full total, assembly included, always in view;
- a delivery date on the review.

**For the picture,** a layered 2D board in 1.0, built so a 3D view can load on a tap later. Full 3D needs US$1,400 to $10,400 of models before any code, for gains that independent research calls small.

**A shopper's own graphic** fits the same picture. It waits on one owner answer: whether his supplier will print a single film.

## The patterns we build

| # | Pattern | Seen at, and the evidence | In the spec |
| --- | --- | --- | --- |
| P1 | Show only the parts that fit, and say what's hidden and why ("9 trucks hidden: too narrow for 8.5″") | Skate Warehouse UK; NHS lets the shopper switch its filter off; PCPartPicker. With compatibility filters, shoppers found compatible products almost twice as often, and "Recommended" was misread as "fits" ([Baymard, 2015](https://baymard.com/blog/ecommerce-compatibility-databases)) | A step; Compatibility rules |
| P2 | Chain the rules: wheels bigger than the trucks take without risers bring a risers step with "No risers" as a choice, and bolt lengths follow the risers | Skate Warehouse UK, Skate Warehouse | A step; R3, R4 |
| P3 | Four to six presets the shopper can edit: street, park, cruiser; value and pro; a team rider's setup | Good, Better, Best at CCS, Daddies and NHS; the deck's pro setup at Skate Warehouse; BMW's pre-configured builds. Starting from a ready build raised satisfaction and spend across nine studies ([Hildebrand et al., 2014](https://www.alexandria.unisg.ch/id/eprint/236290)); choice overload averages near zero but bites for unsure buyers ([Scheibehenne et al., 2010](https://ideas.repec.org/a/oup/jconrs/v37y2010i3p409-425.html)) | Presets |
| P4 | "+$", "Same price" or "Included" on every option card, with stock | Hyundai's "+ $X"; CCS. Baymard's guideline on [price transparency of configuration options](https://baymard.com/guidelines/3108-price-transparency-of-configuration-options) | A step |
| P5 | The full total, assembly included, always in view, with the next step on its button. On phones, a bottom bar with the total and "Next: Wheels", and tapping the total lists the parts | Tesla's bottom bar; MINI's rail; Hammer's live saving. Baymard's guideline on [price visibility in complex configurations](https://baymard.com/guidelines/3105-price-visibility-in-complex-configurations); 40% of abandoners cite extra costs ([Baymard, 2026](https://baymard.com/blog/cart-abandonment-rate)); Canada's [Competition Bureau](https://competition-bureau.canada.ca/en/node/662) treats hidden mandatory fees as drip pricing | The total |
| P6 | A picture that changes with every part | SkatePro (also its cart thumbnail), Skate Warehouse UK, Empire; Ribble redraws its bike per part. Toyota buyers complained when photos didn't match their builds | The picture |
| P7 | A review with a fit tick per part, edit links, assembly, the delivery date, the total and the return terms, before Add to cart | Hyundai's summary; Tesla's and Rivian's dates. 41% of sites show no delivery date where shipping is chosen, and shoppers read dates as promises ([Baymard, 2023](https://baymard.com/blog/shipping-speed-vs-delivery-date)) | Review and add to cart |
| P8 | Explain, then confirm, a change that breaks later picks: keep what still fits, list what doesn't with the closest fit, offer undo | Porsche lists what it must change, with Accept and Cancel; BMW's assistant summarises. Automatic changes confused shoppers, and a Toyota conflict charged for eight wheels ([Thüm et al., 2018](https://ceur-ws.org/Vol-2220/01_CONFWS18_paper_35.pdf)) | A change that breaks later picks |
| P9 | Honest defaults: after the deck, a mid-priced part in stock that fits, labelled "Recommended for 8.25″"; never a pre-picked deck | Volvo's "Standard" and "No additional cost"; Tesla's pricier 2021 default drew criticism. The order of decisions changes how often a default is kept ([Levav et al., 2010](https://business.columbia.edu/faculty/research/order-product-customization-decisions-evidence-field-experiments)); shoppers read defaults as the seller's agenda | A step; Compatibility rules |
| P10 | One cart line for the board, its parts listed under it, and a way to edit the build from the cart | NHS's grouped line | Cart, checkout and orders |
| P11 | A distinct name on every control ("Choose Independent 149 trucks"), every change of total announced, a full keyboard path, a button for every drag | Failures at Skate Warehouse (seven buttons named "Add Mains") and Zumiez (six named "Set"). WCAG's [dragging movements](https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements) and [status messages](https://www.w3.org/WAI/WCAG21/Understanding/status-messages) | Keyboard and screen readers |
| P12 | The build saved in its link, shareable without an account | The [Porsche Code](https://configurator.porsche.com/porsche-code/en-IS) (8 characters, no account); MINI's Build Codes; Volvo's personal link. 18% of abandoners cite a forced account (Baymard, 2026) | Review and add to cart; SEO and sharing |

## What the builder must never do

| # | Failure | Seen at |
| --- | --- | --- |
| W1 | Let parts that don't fit through silently | Warehouse Skateboards (7.5″ trucks on an 8.25″ deck reached the cart), Empire, Daddies, Tactics |
| W2 | Put risers after bolts, or have no riser logic | Several |
| W3 | Show a cost late, or pre-tick one | SkatePro's assembly at Add to cart; Warehouse's shipping protection |
| W4 | Refuse a part with no reason, or leave no way to add it | Skate Hut's "Sorry, not compatible!"; SoCal's pop-ups with no Add button |
| W5 | Sell packages missing parts | No grip, no hardware, or "(or similar)" |
| W6 | Show a preview that never changes, or promise one it doesn't have | Daddies; Skate Hut's "real-time previews" |
| W7 | On phones, let the picture and the total scroll away, scroll pages inside pages, or cover controls with popups | SkatePro, CCS, Hammer's 52,000 px page |
| W8 | Give seven buttons one name; use clickable divs, unlabelled switches, or lists shown only on hover | Skate Warehouse, Zumiez, Skate Warehouse UK, NHS |
| W9 | Leave loose part lines or placeholder lines in the cart, or no way to edit a build once it's there | Skate Warehouse UK, Empire's $0.01 line |

**Not to copy from the car makers:**

- cloud-streamed 3D;
- finance calculators and prices "after savings";
- trims, packages and option codes;
- dealer lead forms;
- fully loaded starting builds;
- sign-in walls;
- upsell pop-ups mid-build.

## Skate shops' builders, hands-on

Each was walked as a shopper on a desktop and a phone on 6 November 2026. Only Skate Warehouse UK checks fit at every step, three shops show the chosen parts together on a board, and three builders are broken.

| Builder | Flow and presets | Fit | Price and assembly | Preview and saving | Problems |
| --- | --- | --- | --- | --- | --- |
| [Skate Warehouse UK](https://www.skatewarehouse.co.uk/boardbuilder) | A full-screen app, 6 steps and a review; example builds, a random board, an AI helper | Filters, and says so on each card ("Fits 7.9–8.125″ decks"); big wheels add a risers step, bolts follow | Total always in view, the next step on its button; free grip; built for £4.99 or free to build yourself | 3D from product photos; saved on the device, "send my board to someone" | Cards are clickable divs; parts land as loose cart lines |
| [SkatePro](https://www.skatepro.ca/en/84-51441.htm) | 7 steps; Team Rider Picks | Filters trucks and bolts; the risers step vanished after 57 mm wheels | Total with "Save $x"; $27.95 assembly appears only at Add to cart, pre-ticked | A 2D picture of the chosen parts, also the cart thumbnail; saved links, My Builds | The picture scrolls away on a phone; "Hurry up!" in the cart |
| [Skate Warehouse](https://www.skatewarehouse.com/buildcomplete.html) | 7 slots in any order; the deck's pro setup, Budget, Standard, Premium | Offers only trucks, risers and bolts that fit | Total on the Review button; free assembly; "+$" on options | Photos; the build lives in its link | Total not sticky; all 7 slot buttons named "Add Mains" |
| [NHS Skate Direct](https://nhsskatedirect.ca/pages/board-builder) | Good, Better, Best, or 8 steps | Fit filter on by default, can be switched off; risers warned only at review | Total in the header; free assembly adds 2 days | Photos; build in the link; one grouped cart line | The filter switch has no label; package parts show only on hover |
| CCS and Daddies | "Deck or complete" on every deck; Good, Better, Best, then swap parts | Trucks sized to the deck, yet Daddies' Better tier puts 8.0″ trucks on an 8.25″ deck | "+$" changes; free assembly and grip pre-ticked | A photo that never changes; build in the link | Pickers scroll inside the page; an SMS popup covers the builder |
| [Warehouse Skateboards](https://www.warehouseskateboards.com/build-a-complete-skateboard) | Deck, then a package or 6 steps | Advice only | Running total; $6 assembly; no returns once assembled | Part photos | Shipping protection pre-ticked; the Good package has no grip |
| [Skate Hut](https://www.skatehut.co.uk/custom-skateboard-builder.htm) | 6 tiles in an overlay; "Suggest a build" | Greyed out with no reason | Total on the button; 10% off; £4.99 build pre-ticked | A collage of part photos; build in the link | Promises previews it doesn't have |
| [Zumiez](https://www.zumiez.com/skateboard-complete-builder.html) | 6 "Set" slots on one page | Trucks filtered; no risers | Price hidden until all 6 are set; not assembled online | A stock photo | 6 buttons all named "Set" |
| [Hammer, Toronto](https://www.hammertoronto.com/collections/build-your-board) | A size band, then 7 steps on one page | The size band pre-filters; hints only | A sticky bundle box with a live 10% saving | None | A 52,000 px page with about 236 images |
| [Empire Skate](https://www.empireskate.co.nz/collections/no-discount/products/custom-skateboard-builder) | 9 accordion slots | None: 8.5″ trucks on an 8.25″ deck, no warning | Discount shown only in the cart | WebGL 3D; the camera flies to the changed part | 3D not sticky; a $0.01 placeholder line in the cart |
| [Boardpusher](https://www.boardpusher.com/custom) | Design a graphic, then deck, grip or complete | Trucks limited to 139–149 mm | Parts pre-picked; $10 assembly; ships in 72 hours | 3D of the deck alone | Built around custom graphics |
| [SoCal Skateshop](https://socalskateshop.com/custom-complete.html) | 7 part tabs | Filter on for trucks, not wheels | $5 off; lists the parts still missing | None; "Save this build" | Truck pop-ups had no Add button |
| [Orchard](https://orchardshop.com/pages/complete-builder) | A guide page and staff packages | Staff pick parts "(or similar)" | $15 off with a code | None | The promised assembly checkbox is missing |
| [Tactics](https://www.tactics.com/skateboardbuilder) | Builder retired; "Pre-Built +$87.75" on decks | Its kit puts 8.0″ trucks on an 8.25″ deck | A fixed kit | None | The offer sits about 1,240 px down on a phone |

## The picture: 2D now, 3D later

Whatever the picture is, the builder has to draw the board on the server first. It must work without JavaScript, and the page's largest paint must land within 2.5 s. So the 2D picture is the base every option stands on, not a rival to them.

| | A: layered 2D picture (1.0) | B: 3D with model-viewer (module after 1.0) | C: a full three.js scene (not planned) |
| --- | --- | --- | --- |
| Build | 50 to 75 hours, plus 25 to 45 of image preparation | A, plus 100 to 165 hours | A, plus 220 to 400 hours, and upkeep |
| Assets | Cut-outs of the 122 deck graphics from today's photos; trucks and wheels shot in one fixed pose once they're in stock | 6 to 9 truck models at $200 to $1,000 each, 4 to 6 wheel shapes and bolts at $40 to $200: about US$1,400 to $10,400, weeks of turnaround | As B, at a higher finish |
| Speed | Low risk: one small AVIF is the largest paint | Low if 3D loads only on a tap; a 1 to 3 MB scene then takes 5 to 15 s on a slow phone connection | Medium to high |
| Accessibility | Low risk: an image with a written description, ordinary form controls, no motion | Medium: click and keyboard controls, no auto-rotate, a description per build, French strings | High: everything custom |
| For the shopper | Their graphic with their trucks and wheels, to scale, on every device | Turn it over: grip, profile, wheel graphics, concave | Animation and tricks |

**Why A first**

- **Shoppers rarely use rich views.** 83% never tried a 360° view where one was offered, and Baymard calls rich media a supplement, not a replacement ([Baymard](https://baymard.com/product-page/benchmark/page-designs/video-and-360-views/)).
- **The lift from 3D is small.** Independent studies find the lift from 3D and AR small or conditional ([Tan et al., 2022](https://www.ama.org/2021/04/20/augmented-reality-in-retail-and-its-impact-on-sales-2/); [Hayati et al., 2025](https://proceedings.emac-online.org/pdfs/A2025-125566.pdf)). The large figures (+94%, +112%) are vendors comparing shoppers who chose to use the feature, with no control group.
- **B needs models** of trucks Skatehouse doesn't stock yet.
- **A shows a shopper's own graphic for free:** it is simply the deck layer.

**How A works**

- Every part is shot at one camera height and lens, at a fixed scale, nose up, and kept with a transparent background (`docs/PICTURE.md`).
- The server stacks the layers: the deck scaled by its width, the trucks placed by its wheelbase, then the wheels.
- Each layer is cached once for every build that uses it, and the stack carries one written description in English and French.
- With JavaScript, a pick swaps a layer in place and the change is announced.
- The off-the-shelf WooCommerce layer plugins stack PNGs, and none could be confirmed to work without JavaScript.

**The road to B**

- **Decks need no 3D artist.** One mesh per shape family is generated from width, length, wheelbase, nose, tail and concave. The graphic is laid on from the flat photo, and the grip is the top's material.
- **Trucks and wheels.** Trucks need one model per brand line, scaled to axle width. Wheels need one per shape, scaled from 52 to 58 mm.
- **Loading.** [model-viewer](https://github.com/google/model-viewer/releases) swaps textures as parts change and can load only when the shopper taps "See it in 3D", so it costs the page nothing until then.
- **AR adds little:** Android's Scene Viewer shows the file as downloaded, not the shopper's build.
- **Shared assets.** If the truck and wheel models are commissioned to render A's layers, B's assets come with them.

## Module A after 1.0: your own graphic

Uploading a graphic changes what Skatehouse makes as well as how it sells, so it hangs on one answer: can the owner print a single deck? His printed decks are films pressed onto blank decks today (what Creative Stockroom tracks), so his film supplier's price, lead time and file spec for one film decide it. The rules below follow two custom deck printers' published specs, to be set against that supplier's.

**Print rules**

| Rule | Value | Why |
| --- | --- | --- |
| Resolution | 300 pixels per inch at print size; 150 the floor | [Boardpusher](https://www.boardpusher.com/BP/help/design-help.aspx) asks 150 and suggests 300; [CustomSkateboards.com](https://www.customskateboards.com/printing-requirements) prints from a 200 dpi JPEG |
| In pixels | An 8.25 × 32 in deck with bleed: 2,550 × 9,675 px at 300, 1,275 × 4,838 at 150 | Worked out from the deck's size, so the builder says it for the width chosen |
| Bleed | The graphic runs past the cut line on every side (1/8 in is the usual figure) | Decks are cut and pressed; a graphic stopping at the line leaves a wood edge |
| Safe area | Text, faces and logos kept in from the edges and clear of the truck holes | Small text at an edge may be cut, and graphics can shift slightly in printing |
| Templates | One per shape and width, with the truck holes marked | Both printers work from per-shape templates |
| Files | JPEG or PNG, up to a size cap | What both printers accept; CustomSkateboards.com caps uploads at 10 MB |
| Colour | Uploaded as sRGB, converted to the supplier's print profile in production | Print is darker and duller than a screen; the proof says so |
| Enlarging | Never blown up past its own size | Enlarged photos print blurred |

**What the builder checks**

- JPEG and PNG only. Every file is re-saved on the server, which strips anything hidden in it; anything else is refused with the reason. No SVG or PDF, since they can carry scripts.
- The resolution at print size, live as the shopper zooms. 300 or more passes; 150 to 299 warns that it may print soft; under 150 can't be ordered.
- The cut line, bleed, safe area and truck holes, drawn over the graphic.
- Place, scale and rotate by buttons as well as by dragging, plus Fit to deck and Fill to edges.
- A new width redraws the template and checks again.
- A size cap, and a limit on uploads per visitor.

**Proof and approval**

- The review shows the proof (the graphic on the chosen deck, with the guides), with a line saying print comes out darker than a screen.
- The shopper confirms they've checked the proof, and that they own the graphic or have permission to print it.
- After the order, the owner approves the artwork in the admin before anything prints, or refuses it with a reason, and the custom deck is refunded.
- The server makes the print file at full resolution with bleed, in the supplier's format, attached to the order for staff alone.

**Rights, returns and files**

- The terms say Skatehouse can refuse any graphic, including logos and characters the shopper doesn't own.
- A one-off deck can't be returned unless it arrives faulty, and this is said before checkout.
- Uploads stay private (never in the public Media Library), only staff see them, and they're deleted once no longer needed.

**Questions for the owner**

1. Will your film supplier print a single deck film? Its cost, lead time and any minimum.
2. Their file spec: resolution, bleed, colour profile, file type, and a template per shape.
3. Do white areas print white, or show the wood?
4. Which blanks can take a custom graphic: shapes, widths, lengths and truck-hole positions?
5. The price of a custom deck, and is it sold alone as well as in a complete?
6. What will you refuse to print?
7. How long should uploaded files be kept after an order ships?

## Sources

All opened on 6 November 2026.

- **The skate shops' builders:** the sixteen linked in the table, walked in Claude's browser on a desktop and a phone. Eastern Boarder (every builder link answered 404), Skatedeluxe (won't ship to Canada) and Euroskateshop (out of stock) couldn't be walked.
- **Car configurators and research:** linked in the tables, plus:
  - [Porsche's feasibility notice](https://configurator.porsche.cn/en-WW/model/982310/feasibility-notification);
  - [Hyundai's build tool](https://www.hyundaiusa.com/us/en/build/options) and [Volvo UK's](https://www.volvocars.com/uk/build/v60);
  - [MINI's 2021 rebuild](https://justbritish.com/mini-usa-launches-new-byo-web-configurator/);
  - [Rivian on delivery windows](https://rivian.com/support/article/what-factors-impact-my-delivery-window-estimate);
  - [NN/g on wizards](https://www.nngroup.com/articles/wizards/);
  - [Villar et al. on progress indicators](https://research.google/pubs/where-am-i-a-meta-analysis-of-experiments-on-the-effects-of-progress-indicators-for-web-surveys/);
  - [Hoober on one-handed phone use](https://uxmatters.com/mt/archives/2013/02/how-do-users-really-hold-mobile-devices.php);
  - [J.D. Power's 2025 study of carmaker websites](https://www.businesswire.com/news/home/20250109801897/en).

  Tesla, Ford, Toyota, BMW, Mercedes and Kia's live configurators couldn't be read; what's said of them rests on the sources named.
- **3D:**
  - [model-viewer's releases](https://github.com/google/model-viewer/releases);
  - [three.js's WebGPU guide](https://www.utsubo.com/blog/webgpu-threejs-migration-guide) and [WebGPU support](https://web.dev/blog/webgpu-supported-major-browsers);
  - [Scene Viewer's limits](https://developers.google.com/ar/develop/java/scene-viewer);
  - [Sketchfab on model costs](https://sketchfab.com/blogs/enterprise/cost-augmented-reality);
  - [Haydenshapes' surfboard builder](https://medium.com/parliament-perspective/hsstudio-part-1-3-using-three-js-for-in-browser-3d-eb7d5c7bde8d).

  The build hours are estimates, not quotes.
- **Your own graphic:** [Boardpusher's design help](https://www.boardpusher.com/BP/help/design-help.aspx) and [deck specs](https://boardpusher.com/custom), and [CustomSkateboards.com's printing requirements](https://www.customskateboards.com/printing-requirements).
