# The picture

How the builder draws a board from its parts, and how to prepare the layer images it draws from. The first half is for a shop owner or the photographer who shoots the parts; the last section is for a developer. Every figure here is the code's, as of 0.7.0.

## What the picture is

The picture is the board as the shopper builds it, seen from underneath with the nose at the top. It heads every step of the builder and the review, and is drawn again with each pick.

It is drawn in two places from one layout:

- **In the page.** Each part that has a layer image is stacked on the board as its own image, to scale. Every other part the total counts shows its product photo underneath, labelled with its slot, brand and size, such as "Wheels: [brand], 54 mm". The phone bar shows the same board, 44 px tall. One sentence describes the build, and the status region reads it out once per pick.
- **As a composed picture.** One image of the same layout, drawn in the background, for places that cannot stack layers:
  - the kit's line in the cart, from 0.7.0;
  - a build link's share card, from 0.7.0;
  - the order emails, from milestone 8.

Nothing is laid out until a deck with a width (`deck_width`) is picked. Until then, every part shows its labelled photo.

## The parts it draws

The picture draws four slots: deck, grip, trucks and wheels. The layout sizes each from the part's specs, not from the image's pixels, so a layer image is always drawn at its part's real size.

| Slot | What the board shows | Its box, across by along | Specs it reads |
| --- | --- | --- | --- |
| Deck | The deck | Its width by its length; 32 in long where the deck gives no length | `deck_width`, `deck_length` |
| Grip | A strip along one long edge of the deck, the right as the board is drawn | 1 in (25.4 mm) by the deck's length | None |
| Trucks | Two trucks, one at each end, placed by the wheelbase | The axle's width by 3 in (76.2 mm): a baseplate's two rows of holes 2.125 in apart and 0.875 in past them. On an old school deck the rows are 2.5 in apart | `axle_width`; the deck's `wheelbase` and `hole_pattern` |
| Wheels | Four wheels, their outer faces at the axles' ends | Three fifths of the diameter by the diameter; 54 mm where the wheels give none | `wheel_diameter` |

A truck's or a wheel's image is drawn as it is at each place: one truck image at both ends, the same way up, and one wheel image at all four corners, not flipped.

Risers, bearings, hardware, spacers and a kit's own slots are not drawn. Risers sit under the trucks, and hardware and bearings cannot be seen from underneath. Their parts keep their labelled photos, and their Part specs panel opens with the Picture group closed.

**A part with no layer image** shows its labelled product photo in the page, beside the board. The composed picture leaves it out: a part it cannot draw is not in it. A build with no layer image at all gets no composed picture, and the cart shows the deck's photo instead.

## The shooting template

The spec fixes four things about every layer image:

- one camera height and lens for every shot;
- a fixed scale;
- the nose up: the end of the part that faces the board's nose is at the top of the image;
- a transparent background.

The spec leaves the setup's figures to the owner and the photographer. Record them here before the first shoot, so every later part is shot the same way:

| Setting | Value |
| --- | --- |
| Camera height | Not set yet: the owner's choice |
| Lens and focal length | Not set yet: the owner's choice |
| Scale at the part, in pixels to the millimetre | Not set yet: the owner's choice, and at least 2 (see The file) |

The picture fits each image into its part's box and never enlarges it, so a fixed scale does not change where a part is drawn. It keeps the parts consistent with each other, and keeps every shot above the field's floor of two pixels to the millimetre.

What each slot's image shows, as far as the layout says:

- **Deck:** its underside, seen square on, nose at the top. A cut-out from an existing product photo of the underside works where that photo is square to the deck and has the pixels the field asks for.
- **Trucks:** one truck seen from underneath, the axle across the image. The box runs the axle's width across and 76.2 mm along.
- **Wheels:** one wheel seen from underneath, its tread facing the camera, so the image is taller than it is wide: the box is three fifths of the diameter across and the diameter along.
- **Grip:** a strip 1 in wide along the deck's length. What the strip shows is left to the owner.

Crop each image close to the part. The picture centres an image in its box and keeps its shape, as CSS's `object-fit: contain` does, so empty space around the part makes the part draw smaller than its box.

## The file

The Layer image field takes an image already in the Media Library. On save it checks the file:

- **Type.** A PNG or a WebP, by the type WordPress gave the attachment on upload.
- **Transparency.** All four corners fully transparent. A cut-out shot nose up has no part in its corners, so this catches a file exported with its background left in, even where the file has an alpha channel. Only the corners are read: by GD where PHP has it, else by Imagick where it reads the format. Where neither can read the file, transparency is not known, and the file is checked for its type and size alone.
- **Size.** At least two pixels to the millimetre of the part's box, across or along: one side reaching its count is enough. A file is refused only when it falls short both ways. The size is checked only for a part in a drawn slot whose specs give its box.

What the field asks for at two pixels to the millimetre:

| Part | Box | Pixels needed, across or along |
| --- | --- | --- |
| A 32 in deck | Its width by 812.8 mm | Twice its width in millimetres, or 1,626 along |
| Grip | 25.4 by 812.8 mm | 51 across or 1,626 along |
| Trucks | The axle by 76.2 mm | Twice the axle in millimetres, or 153 along |
| A 54 mm wheel | 32.4 by 54 mm | 65 across or 108 along |

When a file fails, nothing is stored: the image the part had before is kept, and the product page shows a notice that links to the field. On a variation the notice starts with "Variation #" and its ID.

| Problem | The notice |
| --- | --- |
| Not the ID of an image in the Media Library | Layer image was not saved: "…" is not the ID of an image in the Media Library. |
| Not a PNG or WebP | Layer image was not saved: it is (its type), and a layer must be a PNG or WebP with a transparent background. |
| A corner not transparent | Layer image was not saved: its corners are not transparent. Export the cut-out with a transparent background. |
| Too small both ways | Layer image was not saved: it is too small for the size the picture draws this part. It needs at least (pixels) across or (pixels) along. |

## Where to set it

On each part's product page: Product data > Part specs > Picture > Layer image. The field is stored as `_cbuild_layer`, the attachment's ID.

- **Choose an image** opens the Media Library, offering PNG and WebP. Without JavaScript the button is hidden, and the field takes the attachment's ID typed in. Either way the save checks it.
- **The preview** draws the part's box at half a pixel to the millimetre (a 32 in deck is 407 pixels long), on a chequer that shows what is transparent. The caption gives the box in millimetres, nose up, and the pixels the image needs.
- **Variations** have the same field. A variation left empty takes its parent product's layer image, and the note says which image is in use. A variation's own image replaces the parent's.
- **Emptying the field** removes the layer image, and the part shows its labelled photo again.

The size check and the preview use the first slot the part fills that the picture draws, in any kit, and the specs in use from every source.

## The kit's Picture section

On the kit's product page: Product data > Build > Picture. It is stored as `_cbuild_picture`. A kit saved before 0.7.0 has none, and draws with the usual order and no anchor.

- **Layer order.** A number for each slot the picture draws, 1 at the bottom. The usual order, from the bottom, is deck, grip, trucks, wheels. Equal numbers keep the order the slots are listed in. A slot the order leaves out is drawn after the slots it names, in the usual order.
- **Wheelbase where a deck gives none (in).** Where the trucks sit on a deck whose specs give no `wheelbase`, from 10 to 20 in, as a decimal or a fraction. A figure outside that range is refused with a sentence that gives it. Left empty, the layout uses the usual wheelbase for the deck:

| The deck | The usual wheelbase |
| --- | --- |
| Gives its length | 44.5 % of the length |
| 8.5 in wide or more, no length | 14.5 in |
| 8 in wide or more, no length | 14.25 in |
| Narrower, no length | 14 in |

Either way the deck still lacks its wheelbase, and the layout counts it as assumed.

## The renditions

For the page, the plugin makes smaller copies of each layer image, and leaves WordPress's own image sizes and filters alone.

- **Sizes.** The part's box at half a pixel, one pixel and two pixels to the millimetre, the image fitted into each and never enlarged. An image too small for a size is made once at its own size. The page offers them in `srcset`, and the browser picks.
- **Types.** AVIF, then WebP, each through the image editor WordPress chooses. A type no editor on the host writes is not made.
- **When.** In the part's index job, `cbuild_index_part`, after its specs are indexed, since the specs give the box. Saving a new layer image queues the job. No page request makes a rendition: until the job has run, the page shows the image's own file.
- **Where.** `wp-content/uploads/creative-skateboard-builder/layers/`, named by the attachment's ID, a hash of its file's name and its size in bytes, then the pixels. An image edited or replaced in the Media Library gets new names, so the page never shows an old copy, and the old file's renditions are removed when the new ones are made. Deleting the image from the Media Library removes all of its renditions.

In the page, the deck's layer is fetched first (`fetchpriority="high"`), and every layer has an explicit size. A pick swaps one layer in place.

## The composed pictures

| Use | Size in pixels | Type |
| --- | --- | --- |
| The cart's thumbnail | 480 by 480 | WebP |
| A build link's share card | 1200 by 630 | WebP |
| The emails | 600 by 600 | JPEG |

- **The drawing.** The board fitted inside a margin of 4 % of the canvas's shorter side, nose up and centred, on a white ground. Each layer image is fitted into its part's box and centred in it, as in the page. The layer images' own files are drawn, not their renditions. Saved at quality 82.
- **When it is made.** Adding a kit to the cart asks for the build's pictures, and Edit build's add asks as well, as does opening a build link for a build that could go to the cart. The job, `cbuild_compose_picture`, works the build out again from its IDs. A build that does not pass validation, or where a recommendation would fill a slot the shopper left, gets no picture. One job waits per build, and while 50 builds wait, nothing more is asked for. No page waits for a picture.
- **Where.** `wp-content/uploads/creative-skateboard-builder/pictures/`, never in the Media Library. A name is the first 32 hex digits of a SHA-256 hash of the drawing: its version, the canvas, and each image's place, size and file. Nothing a visitor sends is in it, and two shoppers with the same build share one file.
- **The share card.** A build link's page carries Open Graph and X card tags: the build's name, as "8.25″ complete: Rascals, Independent 8.5″ and Retna 54 mm", its total, the build link as `og:url`, and the 1200 by 630 picture once it is made, the deck's layer image or else its photo until then. With Yoast SEO the tags go through its filters; with Rank Math, SEOPress or All in One SEO the plugin prints none, and theirs show the kit.
- **The cart.** The kit's line shows the 480 by 480 picture once it is made, and the deck's product photo until then, both with the kit's name as their alt text. A build with no deck keeps whatever the kit product shows. Each part keeps its own line with its own photo.
- **Pruning.** `cbuild_prune_pictures` runs daily, from a day after the first picture is drawn. It deletes the pictures not shown for 30 days, with any file a draw left half-written, then the oldest of the rest until the folder holds 200 MB at most. Showing a picture marks it used, at most once a day.
- **The libraries.** GD is tried first and Imagick second. Each must read PNG and WebP and write the canvas's type. A host with neither makes no composed picture: the cart, share cards and emails show the deck's photo, and the page's own picture is not affected.
- **Site Health.** Tools > Site Health runs "The skateboard builder's composed pictures". It recommends an action where neither library can make the pictures, or where the uploads folder cannot be written, and passes otherwise.

Uninstall removes `wp-content/uploads/creative-skateboard-builder/`, with the renditions and the composed pictures, and leaves the shop's own uploads.

## Finding parts without one

WooCommerce > Builder > Catalogue check lists each part of a drawn slot with no layer image: one line per kit, slot and part, saying "It has no layer image, so the picture shows its labelled photo in the slot …". A variation that takes its parent's image is not listed, and an image deleted from the Media Library counts as none. These lines come after every other finding, since a part without a layer image still sells. Each links to the part's Layer image field.

## For developers

- **Units.** The layout works in whole ten-thousandths of a millimetre, so a renderer's scale is one multiplication. A `Layer`'s centre is measured from the deck's centre, `x` to the right and `y` towards the nose. `Layout::corner()` gives a layer's top left corner from the board's, the nose at the top, which both renderers draw from. `Layout::drawn()` gives one part's box alone, which the Layer image field and the renditions use. Where a spec the layout needs is missing, a usual value stands in and `Layout::$assumed` lists the spec.
- **One layout, two renderers.** `Domain\Picture\Layout::of()` lays the board out from the picks and the kit's `PictureSettings`. `Port\PictureRenderer` takes that layout and the parts' `LayerArt`. `Front\PageRenderer` gives the page's layers, which the state route's `picture.layers` carries (see [HOOKS.md](HOOKS.md)). `Service\ComposedRenderer` gives a `Domain\Picture\Composition`, worked out to the pixel before any library is called, which `Adapter\WpPictureFiles` draws with GD or Imagick. The interface is internal and has no filter yet; a 3D view after 1.0 would be a third renderer.
- **Names change with the drawing.** `Composition::VERSION` is in every composed picture's name, so a change to how a picture is drawn gives new files rather than showing old ones.

The decisions behind this page: D-060 (the layout), D-190 (the layer image), D-192 (the kit's settings), D-193 (the Catalogue check), D-194 (the renditions), D-195 (the renderer and the page), D-196 (the composer), D-197 (the job), D-198 (pruning and Site Health), D-199 (the cart's thumbnail), D-200 (the share card) and D-201 (the renditions' cleanup), in [DECISIONS.md](DECISIONS.md).
