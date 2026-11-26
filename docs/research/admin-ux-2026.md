# The admin in 2026: how to build it (for milestone 15)

Researched on 26 November 2026, during milestone 14, for #156. Zach approved `@wordpress/dataviews` on 26 November ([admin-2026.md](admin-2026.md) has the weights). This says how to use it so a shop owner who isn't technical finds the admin easy.

## Findings

### The tools

1. **DataForm has five layouts** in 22.1.0: `regular` (one field under another), `panel` (a summary row that opens a dropdown or a modal), `card` (wraps other layouts, with `withHeader`, `isOpened` and a `summary` in its header), `row` (fields side by side) and `details` (a native `<details>`). Sources: `src/types/dataform.ts` in the 22.1.0 package (read 26 Nov 2026); [DataViews, DataForm, et al. in WordPress 6.9](https://make.wordpress.org/core/2025/11/11/dataviews-dataform-et-al-in-wordpress-6-9) (11 Nov 2025).
2. **Field types and controls.** Types: text, integer, number, datetime, date, time, media, boolean, email, password, telephone, color, url and array. Controls include select, radio, toggle, toggleGroup, textarea, checkbox and, since 2026, combobox. A text control takes a `prefix` and `suffix`, so a unit or a currency sits in the field. A field can be `readOnly`, disabled, or hidden with `isVisible`. Sources: [the package README](https://github.com/WordPress/gutenberg/blob/trunk/packages/dataviews/README.md) (trunk, read 26 Nov 2026); [the changelog](https://github.com/WordPress/gutenberg/blob/trunk/packages/dataviews/CHANGELOG.md) (22.1.0, 18 Nov 2026).
3. **Validation is controlled and rule-based:** `required`, `elements`, `pattern`, `minLength`, `maxLength`, `min`, `max` and a `custom` function, sync or async. The form takes a `validity` object, so errors the server found can be shown at their fields. A field hidden by `isVisible` isn't validated. The `details` layout shows its fields' errors once focus leaves it, with a badge on the summary. Sources: the README; the 6.9 post; `components/dataform-layouts/details` in 22.1.0.
4. **There is no repeating group.** The `array` type is a token field of values (`dataform-controls/array.tsx` uses `ValidatedFormTokenField`), not a list of rows. An array of any field type (#71789) and an `object` type (#74409) were moved to the backlog in [the 7.0 iteration](https://github.com/WordPress/gutenberg/issues/73076) (updated 17 Feb 2026). Every list of rows we have needs DataViews for the list and DataForm for one row.
5. **DataViews** draws a `table`, `list`, `grid` or `activity` layout, with search, filters, sorting, paging, groupBy, an `empty` state, and row and bulk actions (`isEligible`, `supportsBulk`, `RenderModal`). It moves columns (`enableMoving`), but has no way to reorder rows. `@wordpress/views` keeps a view's filters and sort in the user's preferences. Sources: README; `types/dataviews.ts` in 22.1.0; [@wordpress/views](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-views) (read 26 Nov 2026).
6. **Versions move fast.** 22.0.0 (4 Nov 2026) broke action labels and removed an action context. 22.1.0 asks for `@wordpress/components` ^45 and `@wordpress/ui` ^0.27, a library still below 1.0. WordPress 7.2 bundles DataViews 22.0.0 with components 44.0.0 ([7.2 field guide](https://make.wordpress.org/core/2026/11/20/wordpress-7-2-field-guide), 20 Nov 2026). wp-scripts leaves `wp-components` to core, so on 7.0 and 7.1 our copy would run against an older components than it was built for.
7. **Accessibility.** The list layout was hard to use with a screen reader, and the table worked ([#67598](https://github.com/WordPress/gutenberg/issues/67598), Dec 2024). 22.x announces a running bulk action and fixes arrow keys between list rows (changelog). Use the table.
8. **What's experimental.** WooCommerce's React Settings UI is behind the `settings-ui` flag since 10.9 and "can change in backwards-incompatible ways in any release" ([Settings UI docs](https://developer.woocommerce.com/docs/extensions/settings-and-config/settings-ui), read 26 Nov 2026). Its default save, `form_post`, writes hidden inputs so `WC_Admin_Settings::save_fields()` saves as before, and a bad schema falls back to the classic page. Server-side registration of fields arrives with 7.2's Entity Fields API (field guide).

### Where they're used

- **WordPress core:** the Site Editor's Pages, Templates, Parts and Patterns screens, which 7.1 made configurable from PHP ([What's new for developers, August 2026](https://developer.wordpress.org/news/2026/08/whats-new-for-developers-august-2026)); the Posts, Pages and Media lists and the Fonts page in 7.0 ([uiPress, 21 May 2026](https://www.uipress.co/news/wordpress-7-0-admin-dataviews-what-changed)); and from 7.2 the editor's inspector and Quick Edit, built with DataForm from one set of fields (field guide).
- **WooCommerce:** the All Products proof of concept: a table with chosen columns, variations as child rows, and quick and bulk edit in core's modal. Its stated gap is speed on large catalogues ([Reimagining catalog management](https://developer.woocommerce.com/2026/05/21/reimagining-catalog-management), 21 May 2026; [What's the value of DataViews](https://developer.woocommerce.com/2026/08/05/the-value-of-dataviews), 5 Aug 2026). The payments settings, unified in July: one centred column, one idea per card with a title and a sentence or two, advanced settings in a collapsed card, one Save per screen, conditional fields directly beneath the choice that reveals them, `Notice` in the page and `Snackbar` for passing confirmations ([Unifying extension settings](https://developer.woocommerce.com/2026/07/08/unifying-extension-settings-in-woocommerce), 8 Jul 2026).
- **Plugins:** I found no leading third-party plugin that documents a DataForm settings screen. The public examples are WooCommerce's own and the Developer Blog's sample ([How to use DataForm to create plugin settings pages](https://developer.wordpress.org/news/2026/01/how-to-use-dataform-to-create-plugin-settings-pages), 20 Jan 2026), which ends on cards, one per group. Being early is a reason to stay close to what WooCommerce does.

### Guidance

- **WooCommerce's settings guidelines:** only settings that matter, smart defaults over questions, the most used first and destructive or rare ones last, no long mixed lists, progressive disclosure, helper text where a setting isn't plain, no jargon ([Settings](https://developer.woocommerce.com/docs/extensions/ux-guidelines-extensions/settings), read 26 Nov 2026). Instructions of 120 to 140 characters, longer text in the docs, and screens that work on a phone ([Best practices](https://developer.woocommerce.com/docs/extensions/ux-guidelines-extensions/best-practices)).
- **Notices** answer an action; validation messages sit beside their fields; no offers ([Notices](https://developer.woocommerce.com/docs/extensions/ux-guidelines-extensions/notices)). **First run:** a clear next step, helpful empty states, no redirect on activation, no tours, no dead ends, at most one dismissible notice of 125 to 200 characters ([Onboarding](https://developer.woocommerce.com/docs/extensions/ux-guidelines-extensions/onboarding)).
- **The plugin directory:** guideline 11, no hijacking the dashboard; notices limited in scope, dismissible, and saying how to resolve the problem ([guideline-11.md](https://github.com/WordPress/wporg-plugin-guidelines/blob/trunk/guideline-11.md)). A core proposal of 16 Nov 2026 would make every plugin notice declare its intent and hide promotional ones on core screens ([declared intent](https://make.wordpress.org/core/2026/11/16/wordpress-notifications-and-declared-intent-proposal-for-the-path-forward)).
- **Errors:** validate a field once it's complete, put the message beside it, never only in a summary, and help more on a repeated error ([NN/g, 10 guidelines for reporting errors in forms](https://www.nngroup.com/articles/errors-forms-design-guidelines)).
- **Empty states** say what would be here, how to fill it, and link straight to the task ([NN/g, empty states](https://www.nngroup.com/articles/empty-state-interface-design)).
- **Confirmation** only before consequential actions; undo is better where it's possible ([NN/g, confirmation dialogs](https://www.nngroup.com/articles/confirmation-dialog)).
- **Unsaved changes:** warn only when something changed, with the browser's own prompt for a closed tab ([Cloudscape](https://cloudscape.design/patterns/general/unsaved-changes); [MDN, beforeunload](https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event), 26 Oct 2026).
- **WCAG 2.2 AA** adds 2.5.7 dragging movements (a button for every drag), 2.4.11 focus not obscured (a sticky Save bar must not cover the focused field), 2.5.8 targets of 24 px, and 3.3.7 redundant entry ([What's new in WCAG 2.2](https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22)).

### What shop owners complain about

- **Sprawl and upsells:** "a maze of settings pages and upsell banners" ([Tribulant, 8 Nov 2026](https://tribulant.com/blog/wordpress-plugins/when-your-wordpress-store-has-more-plugins-than-products)); notice spam is now core's own subject (declared intent, above).
- **A new pattern on every screen:** WooCommerce found "four different mental models" for one job (Unifying extension settings).
- **Walls of fields** and **jargon** (WooCommerce's settings guidelines).
- **Losing work:** a long form that empties on a failed save, or a list that grows only by saving. Ours does the second today.

**Checked in the package itself (26 November).** `@wordpress/dataviews` 22.1.0's `build-module/field-types/` has no `object` type, and its `array` type refuses any value that isn't a string ("Every value must be a string.", `field-types/array.mjs`). Gutenberg's #71789 (nested fields) and #74409 (the `object` type) were both closed on 9 September 2026 with no reason given, and no release has shipped either. So the finding holds for the version we would install, and the plan checks it again against the release it pins.

## The principles we'll follow

1. **The server form stays the product.** Every screen renders today's form; the script mounts DataForm or DataViews over it and writes the same field names into hidden inputs, so the same POST, nonce and server validation run, as WooCommerce's `form_post` does.
2. **One description of each field,** in PHP: label, help, type, limits and choices, translated, printed as JSON for the script and used by the template. Client rules are hints; the server decides.
3. **Server errors come back to their fields** through `validity`, plus one notice at the top that links to each.
4. **One idea per card,** most used first, rare settings in a collapsed card, help of 140 characters at most.
5. **Lists of rows are DataViews tables** with Add, Edit in a DataForm modal, Remove with Undo before saving, and Move up and Move down where order matters. Nothing grows by saving.
6. **No jargon in labels:** no R1 to R8, no "trusted proxy header" at the top level.
7. **Every empty list says what goes there and offers the first step.**
8. **Warn before leaving only when something changed.**
9. **No promotional notice, ever;** notices answer an action.

## Our screens

### 1. Settings (WooCommerce > Settings > Builder)

**Now.** One long page of four groups. The attribute mapping shows three blank lines and grows three at a time by saving; a line is removed by clearing two fields. The source order is three selects that can name one source twice, which is then an error. The rate limit and the proxy header sit among the shop's everyday choices.

**Becomes.** DataForm, `card` layout, four cards:

- **Where specs come from** (open). The mapping is a DataViews table: Attribute, Spec, Unit. "Add a mapping" opens a DataForm modal: Attribute (combobox of the shop's attribute taxonomies, or a typed product attribute name, `maxLength` 190), Spec (select), Unit (select, `isVisible` only for a length spec, which removes the "not a length" error). Row actions: Edit, Remove (Undo in a snackbar). At 50 rows Add is disabled with "The mapping holds 50 lines at most." A duplicate is a `custom` rule on the modal: "This attribute already feeds this spec." Empty state: "No attribute feeds a spec yet. Parts take their specs from their own fields and the makers' charts. Add a mapping to read a spec, such as the deck's width, from an attribute." with Add a mapping. **Which source wins** is a list of the three sources with Move up and Move down, so a duplicate can't exist.
- **Builds** (open): Build discount, a text field with the suffix %, `pattern` for two decimals, help "Taken off each part of a build. 0 for none."; coupons as `radio`, two choices.
- **New kits** (closed, summary "Assembly off, no fee"): the toggle, Fee with the currency as prefix, Lead time (`maxLength` 200), Returns notice (`textarea`, 1,000).
- **Advanced: visitor requests** (closed): Requests a minute (integer, `min` 10, `max` 6000) and Proxy header (`pattern`), its warning as help.

One Save, WooCommerce's. The save stays all or nothing; refused, the page comes back as typed with each problem at its field.

**Without JavaScript.** Today's templates, unchanged, including the blank lines.

### 2. The kit's Build tab

**Now.** Eight sections in one tall product data panel. A slot has a dozen fields and a field per attribute taxonomy; a new slot is a blank row that "Type a label to add a slot" explains; a slot goes with a "Remove this slot when the kit is saved" checkbox. Rules are labelled R1 to R8. Presets and width rows wait as hidden blanks.

**Becomes.** DataForm with a `card` per section; Slots opens, the rest are closed with a summary in the header ("5 slots", "Assembly on, $15 fee"). A card with an error opens.

- **Slots:** a DataViews table (Label, Kind, Units, What may fill it). Add a slot and Edit open a modal: Kind of part (select, shown only for a new slot, help "Cannot be changed once saved"), Label, Help text, A build must have one (toggle), Units per build (integer, `min` 1), Categories, Brands, Products and attribute values (comboboxes with `getElements` from WooCommerce's own search routes), Default part. Actions: Move up, Move down, Remove. Remove isn't eligible while a preset names the slot, and its description says which preset. Empty state: "A kit needs a slot for each part a build is made of. Start with the deck." with Add a slot.
- **Rules:** a toggle per rule with a plain label ("Truck fits the deck's width", "Mounting holes match", "Wheels clear the trucks"), its settings `isVisible` beneath it when on. R7 and R8 become a sentence of help, not fields.
- **Presets:** a table (Name, Photo, Parts) with Add a preset (disabled at six) and a modal holding Name, Description, Photo and a combobox per slot of what that slot takes. The server's fit check answers on Update, at the preset's field.
- **Assembly:** `regular`; Fee with the currency prefix; Assembly time in business days with a `pattern` and the example "2 or 2-4".
- **Picture:** the layer order as a list with Move up and Move down; Wheelbase, suffix in, `min` 10, `max` 20, empty for the usual.
- **Layout:** `toggleGroup` for Stage side and Board size, select for Search, the Builder page select, and the feature switches as toggles.
- **Width guide:** Show the width guide (toggle); the rows as a table (Shoe size, From, To) with Add a row up to 12, Remove, and a "Use the builder's rows" button that asks once and restores them.
- **Wheel guide:** four fixed `row` layouts, Smallest and Largest, with the plugin's sizes as placeholders (`showPlaceholderIfEmpty`).

Saved by the product's Update, as now. The page warns before leaving while a field of the tab has changed.

**Without JavaScript.** Today's panel and its hidden blank rows.

### 3. Part specs

**Now.** Plain fields with units in labels, read-only values with their source, a Layer image, Rank and Not offered, repeated in each variation.

**Becomes.** DataForm `regular`, side labels, the fields for the part's kind only. Measures are text (they take 8 1/4) with the unit as suffix. A value from an attribute or a chart is `readOnly`, with "From the Width attribute" as its description. A variation's empty field shows its parent's value as placeholder. Rank and Not offered go in a `details` layout, "In the builder", last. The Layer image keeps the Media Library picker with its preview. Errors: "Width was not saved: 8,2.5 is not a measurement. Type a number, or a fraction such as 8 1/4." at the field. A variation's form mounts when its panel opens.

**Without JavaScript.** Today's panel.

### 4. The import

**Now.** A file field, Match parts by, Check the file, then a preview table and Import or Put the file away, then a progress line.

**Becomes.** Three steps with their names shown: Choose the file, Check what changes, Import.

- **Choose:** DataForm `regular`: the file (a native file input in a custom Edit), Match parts by (`radio`: Product ID or SKU). Help says what a file holds and links to the export as the place to start.
- **Check:** counts above ("124 parts would change. 3 rows cannot be read."), then a DataViews table of changes: Row, Part, Spec, Now, After, filterable by spec, searchable by part. A second view lists the rows that can't be read, with what's wrong and how to fix it. Actions: Import these changes (primary), Download the specs as they are now (a backup, since an import has no undo), Put the file away.
- **Import:** a progress bar, "40 of 124 rows done. This page can be left."

**Export** is a card beside the steps: one sentence and Download the CSV file.

**Without JavaScript.** Today's two-step form and its Check again link.

### 5. The Catalogue check

**Now.** Run the check, then four tables, each cut at 500 rows.

**Becomes.** One DataViews table of every finding: Part (linked to the field that fixes it), Kit, Slot, Kind, What to fix. Kind is a filter: Keeps a part out (the default view), Sources disagree, No layer image, Shares a card, Kept out on purpose. Filters for kit and slot, search by name or SKU, paging in place of the cut, and the view kept per user by `@wordpress/views`. Row action: Fix (opens the field). Run the check is the primary button, with "Last run 26 Nov, 14:02" beside it; Read every part again is secondary, in a menu. Empty states: before a run, "The check reads every part and lists what keeps one out of the builder. It changes nothing." with Run the check; after a clean run, "Nothing keeps a part out of the builder."; with no kit, "Set up a kit on a product's Build tab first."

**Without JavaScript.** Today's form and tables.

### 6. The spec tables

**Now.** A list of charts and an Add a chart field. A chart's sizes are field groups that grow by saving and go by clearing every field.

**Becomes.** The list is a DataViews table: Maker, Sizes, Parts using it, Read on. Actions: Edit, Delete, which asks once and says "12 parts name this chart and will lose its figures." Empty state: "A chart gives trucks their axle width and deck range by size, so they're checked against each deck." with Add a chart. A chart's screen has a card for its name and a table of sizes: Size, Axle width, Deck range, Height, Largest wheel, Baseplate holes, Source, Read on. Add a size and Edit open a modal; Deck range is a combined field (`children`: narrowest and widest, `row` layout) with a `custom` rule that the narrowest isn't wider. Duplicate sizes and the 100-size limit are rules on the modal. One Save chart.

**Without JavaScript.** Today's screens.

## Risks and questions for Zach

1. **Weight on the product screen.** Loading DataForm and DataViews on every product edit costs every shop manager. Load the Build tab's bundle on kit products only and Part specs' when its panel opens. The budget line is set at the plan.
2. **Version drift** (finding 6). Either bundle `@wordpress/components` with ours, which adds weight, or pin `@wordpress/dataviews` to a release built against the components that WordPress 7.0 ships, and test on both legs. I'd measure both at the plan.
3. **No repeating group or row reorder in DataForm.** Our six lists are our own composition of DataViews, a modal and Move buttons: more code to test and mutate. If #71789 lands, it can replace some of it.
4. **Two renderers per screen,** the template and React. Principle 2 keeps them on one description, and the `no-js` project covers the server's.
5. **The rule "No markup is built in JavaScript"** is the builder's. Recording that it doesn't cover the admin's DataForm and DataViews needs a decision.
6. **WooCommerce's Settings UI.** Adopt it while it's experimental, or mirror its `form_post` approach ourselves? I'd mirror it and revisit when it's stable.
7. **Translations.** The bundled copy's own strings use core's text domain; a string newer than core's translations shows in English in `fr-ca`. Check RTL styles too.
8. **Saved views** are stored in user meta through preferences; that's a `PRIVACY.md` line.
9. **A bulk "Offer in the builder again"** on the check's Kept out rows is new behaviour, and the owner's call.
10. **Before relying on any of it,** confirm in the installed sources that `wp-components`, `wp-preferences` and WooCommerce's product search routes behave as described on WordPress 7.0 and 7.1.5.
