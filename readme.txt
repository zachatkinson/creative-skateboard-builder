=== Creative Skateboard Builder ===
Contributors: creativebranding, zachatkinson
Tags: woocommerce, skateboard, product builder, configurator, bundle
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sells complete skateboards built from parts the shop stocks, with fit checked from specs and each part on its own cart and order line.

== Description ==

Creative Skateboard Builder lets a shopper put a complete skateboard together from the decks, trucks, wheels, bearings, hardware and grip a WooCommerce shop already sells. It checks that the parts fit from each part's specs, draws a picture of the build, keeps the total in view, and gives each part its own line in the cart and on the order, so stock, tax and refunds work as they do for any other product.

This is version 1.0.0. A kit's page is the builder: the board on one side, the steps on the other, and the total at the foot, and the Builder block puts it on any other page. The shopper goes one step a slot, picks a deck by the width they ride, sees one card a look with only the parts that fit what they picked, can search, sort and filter a long step, can start from one of your rider builds, and reaches a review with the total, a link to the build and Add to cart. A deck's own page can open the builder with that deck picked. The builder draws the board to scale from each part's layer image, top and underside, and the cart, the order, its emails and a shared build link show a picture of it. A signed-in customer can save a build and find it again under My builds in My account. The workshop gets each order's build in a box on the order screen and a printed assembly ticket.

It works with a keyboard and a screen reader, on a phone, without JavaScript, in English and French (Canada), and right to left.

The plugin needs WooCommerce 11.2 or newer, WordPress 7.0 or newer and PHP 8.5 or newer. Below any of them it does nothing and says so on the Plugins screen.

== Installation ==

1. Make sure WooCommerce 11.2 or newer is active.
2. Upload the zip under Plugins > Add New > Upload Plugin, and activate it.

== Frequently Asked Questions ==

= Does it send anything off the site? =

Nothing about the shop or its shoppers. The plugin makes two kinds of request, both for its own updates: when WordPress checks for plugin updates it reads its release notes, at most once in 12 hours, or once an hour after a read that failed, from the agency's public releases on GitHub, and when you install an update it downloads that release's zip from the same place. Neither sends the site's address. Turn both off with `define( 'CBUILD_DISABLE_UPDATES', true );` in wp-config.php.

= What does deleting the plugin remove? =

Everything the plugin made: its three tables, saved builds among them, its options and transients, its scheduled actions, the resized layer images and composed pictures in `uploads/creative-skateboard-builder/`, and on a network the must-use file it wrote. Products and orders are never touched, and the build details on past orders stay, because they are part of the shop's records.

= Does it work on a multisite network? =

Yes. Activating it for the network installs it on every site, and a site added later installs itself the first time an administrator opens its dashboard. On a network it writes a small must-use file, so that deleting a site drops the plugin's tables and deleting an account deletes its saved builds on every site, even where the plugin is not active. A network admin's visit to wp-admin writes it again after an update, and Site Health says if the folder cannot be written.

= Is it translated? =

It ships in English and French (Canada). Every string goes through the text domain `creative-skateboard-builder`, so a translation plugin or a language pack can add any other language.

= How does a kit's page look in my theme? =

In a block theme, a kit's page takes the plugin's own template, made of your theme's header and footer, which you can edit under Appearance > Editor > Templates. In a classic theme, the builder fills the width before the product's summary, and the summary's title, price and form are left out for kits; a slimmer header is your theme's own setting.

A page builder's product template (Kadence's, Elementor's and the like) draws the builder in its add-to-cart block's column. For the full width, leave kits out of that template, by their category or a tag, so they take the theme's own product page, or give kits a template of their own whose add-to-cart block has a full-width row.

The kit's Build tab has a Layout section: the board on the left or the right of a desktop, large or balanced, and which search the steps use.

= My theme shows a sticky add-to-cart bar on a kit's page =

The builder has its own bar with the total, Back and Next, so a theme's bar on a kit's page is one too many. Leave kits out of it in your theme's settings. In Kadence Pro, the bar is one of its Elements: edit that element, and in its Exclude Settings add Hide On for each kit, or for a tag your kits share.

= Can other plugins build on it? =

Yes. Its hooks, REST routes, block attributes, settings keys, order item keys, CSS custom properties and DOM events are listed with the version each arrived in, and from 1.0.0 they follow Semantic Versioning: no minor or patch release removes or renames one, and a deprecation comes first.

== Changelog ==

= 1.0.0 =
* A kit's page is a configurator: the board beside the steps on a desktop, above them on a tablet or a phone, a rail of steps, and the total at the foot. A block theme gets a kit template of its own.
* The deck step asks the width you ride first, with a guide by shoe size, and remembers a signed-in customer's width.
* One card a look, with its sizes after the card once it is picked. A long step gets sort, filter chips with true counts, search (through Creative Search where it is active) and 30 cards at a time.
* A search that finds nothing says why and offers the way on.
* "Build a complete with this deck" on a deck's own page, and rider builds with their own photo or the build's picture.
* A saved build that has changed says so when it is opened: the parts gone, sold out or no longer fitting, with the closest part in each place, and the new prices.
* The kit's Build tab has a Layout section: the stage's side, the board's size and the search.
* Wheels can say they come with their bearings, and then need none of their own.
* Steps hide wheels that need a riser the kit can't supply with bolts long enough, and risers no bolts in stock reach through, each with its reason.
* Part specs opens Sold in sets for parts in slots that take more than one unit. Saving a kit whose presets name a hidden part keeps the presets and says which part.

= 0.9.0 =
* Focus is seen on a dark theme and a light one: the ring has two tones of its own.
* On a phone, the bar gives the estimate for a complete board under the total.
* My builds has Undo after Delete.
* The order's picture has a short alt text, since its parts are listed under it, and the Build box says "No specs" for a part with none.
* On a network, the must-use file is written only by a network admin in wp-admin.
* French (Canada).

= 0.8.0 =
* Nothing is picked for the shopper. After the deck, each step labels its recommendation, and the board and the total count only what was picked, with an estimate of the complete board beside the total.
* A signed-in customer saves a build from the review and finds it under My builds in My account, with its picture, its total today and the date it was saved. Open takes it back to the builder, checked again; Rename and Delete work without JavaScript.
* "Read every part again" makes the resized copies of layer images that are missing, and the update to 0.8.0 makes them in the background.
* With Yoast SEO, a shared build's address keeps its brackets encoded.
* The board turns over. The grip tape step shows its top: the deck's outline, the grip tape filled into it, a bolt head at each hole and the wheels at the edges. A button by the board turns it over on any step, and the description says which side is shown. A grip's layer image is now a swatch of the tape, and a hardware's can be one bolt's head.
* For the workshop: an order with a build shows it in a Build box, part by part with the specs it was sold with, and warns when the order's lines no longer match it. The assembly ticket prints from there, or for many orders at once from the orders list, which also gains an Assembly column.
* For the customer: the order's page in My account and the order emails show the build's picture under its kit, with the parts under it, and the emails say the lead time and the returns notice.
* Privacy: WordPress's personal data export and erasure include saved builds, a deleted account's builds go with it, on a network too, and the privacy policy guide gains a suggested paragraph.

= 0.7.0 =
* Each part's Part specs panel has a Picture group with a Layer image field: a transparent PNG, WebP or AVIF from the Media Library, shown at the size the picture draws the part. An image that is not transparent, is too small, or is another kind of file is refused, and the notice says why.
* The builder draws the board to scale as the shopper picks: deck, grip, trucks and wheels, each from its layer image, with the parts that have none shown as photos beside it.
* A build in the cart shows a picture of it, made in the background, in place of the deck's photo. A shared build link unfurls with that picture, the build's name and its total.
* Pictures not shown for 30 days are deleted, and the folder is kept under 200 MB. Site Health says when the host cannot make them, or when parts' layer images have no resized copies.
* Updating from 0.6.0 by uploading the zip and choosing Replace current with uploaded works in one step, and the uploaded zip is deleted. From a release older than 0.6.0, deactivate the plugin before updating, then activate it again.
* The seeded Independent truck chart gives each size's baseplate, from Independent's own pages. An upgrade fills the baseplates your copy of the chart leaves empty, and keeps any you typed.

= 0.6.0 =
* The builder: a kit's page shows it in place of the add-to-cart form, one step a slot, with the parts that fit, the total kept in view, and a review that adds the build to the cart.
* The Builder block shows a kit's builder on any page or post, and a filter can send a kit's page to it.
* Presets: a shopper can start from any of a kit's presets. One holding a sold-out part says so on its tile.
* A part that no longer fits after an earlier pick changes is named in a panel, with Use these and Undo.
* Width and brand filters, and Show more on a step with many parts.
* On a phone, a bar at the foot of the screen keeps the total and the next step in reach.
* Each build in the cart has an Edit build link that opens the builder at the review. Adding there takes the old build's place at the same quantity.
* A line under the total says what shipping costs or how far the build is from free shipping, worded from your shipping zones for a shopper whose address the shop holds, or by another plugin.
* Each step has an address of its own, so Back and Forward work, and the review has a link to the build to share. Search engines index the clean page only.
* After your parts are read again in the background, the first shopper to open the builder no longer waits for them.
* A slot can no longer be keyed step, assembly, preset or review. A build's link uses those words for itself. The upgrade renames such a slot in a kit you saved before, to review_slot for instance, keeps its label, and notes the kit in WooCommerce's log.

= 0.5.0 =
* A build can be added to the cart: the kit with a line for each part, each at your shop's own price, with the assembly fee on the kit's line.
* A kit added to a cart with no build is refused, whichever way it arrives.
* A build in the cart stays together: its quantity sets its parts', removing any line removes the build, and Undo brings all of it back.
* Before an order is placed, each build is checked again against your stock and your parts' specs.
* The build discount setting now comes off each part of a build in the cart, with the part's own price struck through.
* A coupon for a kit reaches its parts. A fixed product coupon lands on the kit's line, or on each part too if you choose that in the settings.
* A coupon that leaves a product, a category or sale items out still leaves that part out when it is in a build.
* Order again on an order with a build puts the build back whole, checked against your stock and specs as they are now, or leaves it out and says so.
* The cart's count shows a build as one item.
* A build is shown as one group in the Cart and Checkout blocks, the Mini-Cart and a classic cart or checkout: each part sits under its kit, says which slot it fills, and cannot be changed or removed alone.
* A build you assemble ships as one package, even where another plugin splits a cart into several.
* Apple Pay and Google Pay buttons from WooCommerce Stripe Gateway and WooPayments are kept off a kit's page, where they could not add a build.
* An order keeps each build together: the kit's line shows whether it is assembled and lists its parts, each part's line says which slot it fills, and the parts' specs are kept with the order as they were when it was placed.
* The cart charges each part at the price your shop holds at that moment.
* The builder's prices include tax for the shopper's own address, where your shop shows prices with tax.
* The classic mini-cart widget indents a build's parts under its kit.
* An order no longer queues background work for each product it sells.
* A coupon added or removed on an order's screen treats a build as the cart did.
* An order keeps each part's SKU as it was when the build was sold.
* A kit cannot be added to an order by hand from the order's screen. Add its parts instead.
* On a shop that shows prices without tax, every shopper is priced from one stored set of parts, whatever their address.

= 0.4.0 =
* A new product type, Builder kit: the product a shopper will build from parts. It cannot be bought as an ordinary product, and it holds no price of its own.
* A Build tab on a kit: its slots and what may fill each, the fit rules, up to six ready builds, and assembly with its fee. What cannot be saved is named with a link to its field, and the rest is saved.
* Five new settings: a build discount, where a fixed coupon lands, how often one address may ask for a build, a trusted proxy header, and what a new kit starts with.
* The builder's own address for a build's state, which the builder page will ask in a later version: which parts fit, why others do not, and what the build costs. It is read-only and limited for each address.
* Two abilities for signed-in tools, to check a build and to price one.
* Other plugins can rule a part out, price some shoppers apart, and translate a kit's own words.
* The Catalogue check lists a kit whose settings can no longer be read.
* A kit stays out of your catalogue, search results, feeds and sitemaps, Yoast SEO's among them.
* The Catalogue check now reads your kits: a slot with no parts or nothing in stock, a part missing a spec its slot needs, a ready build that no longer fits.
* The Part specs tab opens the group of fields a product's slot needs.
* A product that no kit takes no longer shows Part specs fields in its variations.
* When a value on the Part specs tab is not saved, the notice is now a link that takes you to its field.
* Site Health's Info tab shows the state of the specs index in words.

= 0.3.1 =
* Fixed: the Part specs fields no longer add to what a product's edit form sends unless their group is open. On a host that limits a form to 1,000 fields, a product with many variations could go over the limit when updated, and other plugins' settings on that product, its short description or its slug could go unsaved.
* A link to a variation's Part specs now checks a moment later that the fields are still in view.

= 0.3.0 =
* The index of part specs: one value per part and spec, from the part's own fields, an attribute the shop maps or a maker's size chart.
* A Part specs tab on each product, and the same fields in each variation, where the shop fills in what the builder needs to know of a part.
* WooCommerce > Builder, where the shop keeps each truck maker's size chart.
* Import and export under WooCommerce > Builder: download every part's specs as a CSV file, fill it in with a spreadsheet, and import it. The file is checked and shown first, and nothing changes until you confirm.
* A Catalogue check under WooCommerce > Builder: lists the parts whose sources disagree or hold a value that cannot be read, and background work that failed, each with a link to where it is fixed.
* The same work from the command line, for shops that use WP-CLI: export and import specs, run the Catalogue check, and rebuild the index.
* Every part is read into the index when the plugin is installed, updated or activated, and the Catalogue check has a button to read them all again.
* Three Site Health tests, which say when the specs index was not rebuilt, a background job failed or an import stopped, or the Catalogue check has something to fix.
* A settings screen, WooCommerce > Settings > Builder, to say which product attribute holds which spec and which source wins where two disagree. A variation takes an attribute from its parent product when it has none of its own.

= 0.2.0 =
* The fit rules: the checks that will decide which trucks, wheels, risers, bolts, bearings and grip go with a deck, from each part's specs.
* How a build is priced, and the check every build will pass before it reaches the cart.
* Nothing a shopper or shop owner sees yet. The builder arrives in later versions.

= 0.1.0 =
* Foundation: the plugin skeleton, the two tables, the upgrade steps, uninstall, the update check, the test harness and the CI gates. Nothing a shopper or shop owner sees yet.

== Upgrade Notice ==

= 1.0.0 =
Update as usual, from the updater or by uploading the zip. No step is needed, from any earlier release.

= 0.9.0 =
Update as usual, from the updater or by uploading the zip. No step is needed, from any earlier release. On a network, a network admin's next visit to wp-admin writes the must-use file again.

= 0.8.0 =
Update as usual, from the updater or by uploading the zip. The update makes any resized copies of layer images that are missing, in the background.

= 0.7.0 =
From 0.6.0, update as usual.
