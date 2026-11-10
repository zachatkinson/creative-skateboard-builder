=== Creative Skateboard Builder ===
Contributors: creativebranding, zachatkinson
Tags: woocommerce, skateboard, product builder, configurator, bundle
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sells complete skateboards built from parts the shop stocks, with fit checked from specs and each part on its own cart and order line.

== Description ==

Creative Skateboard Builder lets a shopper put a complete skateboard together from the decks, trucks, wheels, bearings, hardware and grip a WooCommerce shop already sells. It checks that the parts fit from each part's specs, draws a picture of the build, keeps the total in view, and gives each part its own line in the cart and on the order, so stock, tax and refunds work as they do for any other product.

This is version 0.3.0. It holds the foundation (the plugin's two tables, its upgrade steps and its update check), the rules that decide which parts fit together and what a build costs, and the first of the work that reads each part's specs from the shop's products. The builder itself arrives in later versions, and nothing a shopper or shop owner sees changes yet.

The plugin needs WooCommerce 11.2 or newer, WordPress 7.0 or newer and PHP 8.5 or newer. Below any of them it does nothing and says so on the Plugins screen.

== Installation ==

1. Make sure WooCommerce 11.2 or newer is active.
2. Upload the zip under Plugins > Add New > Upload Plugin, and activate it.

== Frequently Asked Questions ==

= Does it send anything off the site? =

Nothing about the shop or its shoppers. The plugin makes two kinds of request, both for its own updates: when WordPress checks for plugin updates it reads its release notes, at most once in 12 hours, or once an hour after a read that failed, from the agency's public releases on GitHub, and when you install an update it downloads that release's zip from the same place. Neither sends the site's address. Turn both off with `define( 'CBUILD_DISABLE_UPDATES', true );` in wp-config.php.

= What does deleting the plugin remove? =

Everything the plugin made: its two tables, its options and transients, and its scheduled actions. Products and orders are never touched, and the build details on past orders stay, because they are part of the shop's records.

= Does it work on a multisite network? =

Yes. Activating it for the network installs it on every site, and a site added later installs itself the first time an administrator opens its dashboard.

== Changelog ==

= 0.3.0 =
* The index of part specs: one value per part and spec, from the part's own fields, an attribute the shop maps or a maker's size chart. More of this version's work is listed here as it lands.
* A Part specs tab on each product, and the same fields in each variation, where the shop fills in what the builder needs to know of a part.
* WooCommerce > Builder, where the shop keeps each truck maker's size chart.
* Import and export under WooCommerce > Builder: download every part's specs as a CSV file, fill it in with a spreadsheet, and import it. The file is checked and shown first, and nothing changes until you confirm.
* A Catalogue check under WooCommerce > Builder: lists the parts whose sources disagree or hold a value that cannot be read, and background work that failed, each with a link to where it is fixed.
* WP-CLI commands for the same work: `wp cbuild specs export`, `wp cbuild specs import`, `wp cbuild check` and `wp cbuild reindex`.
* Two Site Health tests, which say when the specs index was not rebuilt or a background job failed.
* A settings screen, WooCommerce > Settings > Builder, to say which product attribute holds which spec and which source wins where two disagree.

= 0.2.0 =
* The fit rules: the checks that will decide which trucks, wheels, risers, bolts, bearings and grip go with a deck, from each part's specs.
* How a build is priced, and the check every build will pass before it reaches the cart.
* Nothing a shopper or shop owner sees yet. The builder arrives in later versions.

= 0.1.0 =
* Foundation: the plugin skeleton, the two tables, the upgrade steps, uninstall, the update check, the test harness and the CI gates. Nothing a shopper or shop owner sees yet.
