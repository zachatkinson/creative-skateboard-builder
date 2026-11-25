# Settings

Everything a shop owner can set, with its default and its range. A setting is added here in the same commit that adds it to the code.

## Contents

- [Milestone 1 (0.1.0)](#milestone-1-010)
- [Milestone 3 (0.3.0)](#milestone-3-030)
  - [The screen](#the-screen)
- [Milestone 4 (0.4.0)](#milestone-4-040)
  - [The screen](#the-screen-1)
- [Milestone 7 (0.7.0)](#milestone-7-070)
- [Milestone 10 (1.0.0)](#milestone-10-100)
- [Milestone 11 (1.1.0)](#milestone-11-110)
- [Milestone 12 (1.2.0)](#milestone-12-120)
  - [Width guide (#114)](#width-guide-114)
  - [Not offered in the builder (#116)](#not-offered-in-the-builder-116)
- [Options beside the settings](#options-beside-the-settings)

## Milestone 1 (0.1.0)

No setting yet. The option `cbuild_settings` arrives in milestone 3.

Two switches exist for a developer, and neither is a setting. Both are in [HOOKS.md](HOOKS.md):

| Switch | Default | What it does |
| --- | --- | --- |
| `CBUILD_DISABLE_UPDATES` (constant) | Not defined | Defined as true in `wp-config.php`, it turns the update check off. |
| `cbuild_updates_enabled` (filter) | True | Returning false turns the update check off. |

## Milestone 3 (0.3.0)

The option `cbuild_settings` arrives with its first two keys, set at WooCommerce > Settings > Builder. Whatever the option holds, the plugin reads a valid setting: a key that is missing or malformed reads as its default.

| Key | Default | Who can change it | What it does |
| --- | --- | --- | --- |
| `attribute_mapping` | Empty | `manage_woocommerce` | A list of lines, each an `attribute` (a taxonomy by its name, such as `pa_width`, or the name of a product's own attribute, such as `Length`; a line reads the one it names and never the other), the `spec` it feeds (a spec key from [HOOKS.md](HOOKS.md)) and a `unit` (`in`, `mm` or empty). A part's value for the attribute becomes that spec; a variation with none of its own takes its parent's, where the parent does not use the attribute for variations. The unit is what an unmarked value is in, for a measure; empty means the spec's own unit. A value is read with the shop's decimal separator or a point. A line with no attribute, a name over 190 characters or a spec the plugin does not know is left out. |
| `source_order` | `table`, `field`, `attribute` | `manage_woocommerce` | Which source wins where two say different things of one spec: the maker's chart, the part's own field, a mapped attribute. First wins. Anything that is not all three exactly once reads as the default. |

### The screen

WooCommerce > Settings > Builder, for a user with `manage_woocommerce`. It works with scripts off, apart from WooCommerce's own Save button, which WooCommerce keeps disabled until a script sees a change.

- **Attribute mapping.** One group of three controls per line: the attribute (type a name, or choose one of the shop's attribute taxonomies from the list the field offers), the spec and the unit.
  - The saved lines are shown with three blank ones after them; saving adds three more.
  - A line with its attribute and its spec both cleared is removed.
  - The mapping holds 50 lines at most.
- **Which source wins.** Three lists, first, second and third, each naming a source.

**A save is all or nothing.** When any line or the order is wrong, nothing is stored, the screen comes back with what was typed, and each problem is written beside its field. What the screen refuses:

| Problem | What the screen says |
| --- | --- |
| A line with a spec and no attribute | Choose the attribute this spec is read from, or clear the line. |
| A line with an attribute and no spec | Choose the spec this attribute feeds, or clear the line. |
| An attribute's name over 190 characters | The attribute's name is too long. Use 190 characters or fewer. |
| A spec or a unit that is not in its list | Choose a spec from the list. / Choose a unit from the list. |
| A unit on a spec that is not a length | This spec is not a length, so it has no unit. Set the unit to the spec's own. |
| The same attribute feeding the same spec twice | This attribute already feeds this spec on a line above. Clear one of the two. |
| More than 50 lines | The mapping holds 50 lines at most. Clear some lines. |
| A source placed twice, or left out | Put each source in one place: first, second and third. |

A save that changes something indexes every part again, in the background. A save that changes nothing queues nothing.

Code that writes the option itself is held to the same shape: `schema/settings.json` ([HOOKS.md](HOOKS.md)).

## Milestone 4 (0.4.0)

Five more keys of `cbuild_settings`, on the same screen and saved with the rest: one wrong field and nothing is stored. Each reads as its default when it is missing or malformed.

| Key | Default | Who can change it | What it does |
| --- | --- | --- | --- |
| `build_discount` | `0` (none) | `manage_woocommerce` | A percentage taken off each part of a build, as text with a point and up to two decimals: `7.5`. From 0 to 99.99. The cart reads it from 0.5.0: each part's line of a build is charged the shop's price less this, rounded half up to the currency's smallest unit, and the kit's line with the assembly fee is left as it is. |
| `fixed_coupons` | `kit` | `manage_woocommerce` | Where a fixed product discount coupon that takes the kit lands. `kit`: on the kit's line alone. `parts`: on the kit's line and on every part of the build, so its amount comes off each. The cart reads it from 0.5.0. A coupon that names the kit, or a category of the kit's, as excluded reaches none of the build's lines under either. |
| `rate_limit` | `120` | `manage_woocommerce` | How many times a minute one address may ask the builder's state route. A whole number from 10 to 6000. An address over the limit is answered with 429 and told how long to wait. |
| `trusted_proxy_header` | Empty | `manage_woocommerce` | The request header the visitor's address is read from, for a shop behind a proxy or a content delivery network: `X-Forwarded-For`, `CF-Connecting-IP`. A header's name of up to 64 letters, digits and hyphens, starting with a letter. Empty reads `REMOTE_ADDR`. Name one only when every request reaches the shop through that proxy: where a visitor can send the header themselves, they can get past the limit. Where the header lists several addresses, the last is read: it is the one the shop's own proxy wrote, and the ones before it are whatever the visitor sent. Behind two proxies in a row, name the header the outer one sets with the visitor's address alone, such as `CF-Connecting-IP`. A header that holds no address falls back to `REMOTE_ADDR`. An IPv6 address is counted by its /64. |
| `kit_defaults` | No assembly by default, no fee, no lead time, no returns notice | `manage_woocommerce` | What a new kit starts from. It holds `assembly`, an assembly document as `schema/kit-assembly.json` has it: `default_on`, `fee` (text with a point and up to four decimals), `lead_time` (200 characters at most) and `returns_notice` (1,000 at most). A new kit's Build tab shows these until the kit is saved with its own; a kit that has its own keeps them. |

### The screen

Three groups follow Part specs:

- **Builds.** The build discount (%), and the list that says where a fixed product discount coupon applies.
- **Visitors.** Requests a minute from one address, and the trusted proxy header.
- **New kits.** Whether assembly is chosen when a build starts, the fee, the lead time and the returns notice.

The discount and the fee are typed with the shop's decimal separator or a point, and shown with the shop's. A discount or a fee left empty is none. What the screen refuses:

| Problem | What the screen says |
| --- | --- |
| A discount that is not a percentage from 0 to 99.99 with up to two decimals | Enter a percentage below 100 with up to two decimals, or 0 for none. |
| A coupon choice that is not in the list | Choose where the coupon applies from the list. |
| A rate that is not a whole number from 10 to 6000 | Enter a whole number from 10 to 6000. |
| A header that is not a header's name | Enter the header's name alone, such as X-Forwarded-For: letters, digits and hyphens, starting with a letter, 64 characters at most. |
| A fee that is not an amount with up to four decimals | Enter an amount with up to four decimals, or 0 for none. |
| A lead time over 200 characters | The lead time is too long. Use 200 characters or fewer. |
| A returns notice over 1,000 characters | The returns notice is too long. Use 1,000 characters or fewer. |

Saving these alone reads no part again: they feed no spec.

## Milestone 7 (0.7.0)

No new setting. What the picture needs is kept with the products it draws, each in [HOOKS.md](HOOKS.md):

- **A part's Layer image** on its Part specs panel (`_cbuild_layer`), and
- **a kit's layer order and wheelbase** in the Build tab's Picture section (`_cbuild_picture`).

**The composed pictures' limits are fixed, not settings:** 50 builds waiting, 10 of them from pages; 200 MB in the folder; 30 days unshown (D-197, D-198, D-205).

## Milestone 10 (1.0.0)

A kit's Build tab gains a **Layout** section, kept in the kit's `_cbuild_layout` document (`schema/kit-layout.json`, D-255). There is one layout and no mode switch, and nothing for variations. A kit saved before 1.0.0 has none and is laid out with the defaults. The kit's export carries the document with the others.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Stage side on a desktop | `stage_side` | `left`, `right` | `left` | The side of the screen the board takes from 64rem wide. A phone always shows the board above the step. |
| Board size on a desktop | `board_size` | `large` (60 % of the width), `balanced` (50 %) | `large` | How much of the width the board takes there. |
| Search in the steps | `search` | `creative_search` (Creative Search where it is active, the builder's own otherwise), `own` (the builder's own always) | `creative_search` | Whether a step's search asks Creative Search to rank and correct it (D-248). |

## Milestone 11 (1.1.0)

The Layout section gains **Motion**, in the same `_cbuild_layout` document. A kit saved before 1.1.0 has none and is playful. The Layout document also holds **Show ready builds**, drawn in the Presets section.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Motion | `motion` | `playful` (parts move onto the board), `subtle` (parts fade in, for every shopper) | `playful` | How the board's parts, the steps and the total move (spec: Milestone 11, D-256). A shopper whose device asks for reduced motion always sees fades of 150 ms or less, whatever the kit says. The builder's root gets `cbuild--subtle` on subtle. |
| Show ready builds | `show_presets` | `true`, `false` (a checkbox in the Build tab's Presets section) | `true` | Whether the builder shows "Start from a ready build" with the kit's ready builds. Off hides the section on the page, the state route's `presets` each say `shown` false (D-336), and it keeps the ready builds as they are. A ready build with a part that's gone, sold out or no longer fits is never shown; the Catalogue check lists it. A kit saved before 1.1.0 has no key and shows its ready builds (#104, D-315). |

## Milestone 12 (1.2.0)

The Layout section gains a group, **Where shoppers find it**, kept in a new document, `_cbuild_settings` (`schema/kit-settings.json`), which 1.1.0 never reads, so a rollback to 1.1.0 still reads the kit (D-354). A kit saved before 1.2.0 has none of its keys and behaves as 1.1.0 did. Each is saved with the rest of the Build tab, through WooCommerce's product save, by a user with `manage_woocommerce`.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Builder page | `builder_page` | The kit's own page, or a published page that holds this kit's Builder block (a select) | The kit's own page (the key left out) | The page shoppers build on. The kit's own page sends a visitor there with their build, and "Build a complete with this deck", the cart's Edit build, My builds' Open and the builder's own links start from it. The select lists the site's published pages without a password that hold this kit's Builder block, the first 500 by title. The page is checked each time it is used: one unpublished, made private, given a password, trashed, deleted or without the block is passed over for the kit's own page, and the tab marks it "(not in use now)" and keeps it until it is changed. `cbuild_builder_url` answers last, starting from this page (#113, D-348). |
| Show "Build a complete with this deck" on its parts' pages | `start_button` | `true`, `false` (a checkbox) | `true` | Whether the kit places "Build a complete with this deck" after the add-to-cart form of each part its first slot offers in stock (D-253). Off, the kit is passed over, and another kit that offers the part may place it. `cbuild_start_button` answers last, and may still name a kit that is off (#115, D-349). |

**Which kit's button a part's page shows.** Where several kits offer a part in their first slot, the button opens the one with the lowest menu order whose Show "Build a complete with this deck" on its parts' pages is on, and the lowest ID among kits of the same order. A kit's menu order is the Products screen's Sorting, or Menu order under the product's Advanced tab.

### Width guide (#114)

The Build tab gains a **Width guide** section (#114, D-350), kept in the same `_cbuild_settings` document as `width_guide`. A kit saved before 1.2.0 has none, and shows the plugin's rows as 1.1.0 did.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Show the width guide | `width_guide.shown` | `true`, `false` (a checkbox) | `true` | Whether "Not sure of your width?" is under the deck step's width question. Off takes it out of the page and out of the state route's answer, words and all, not hidden by a style, and keeps the rows. `cbuild_width_guide` is still asked, with no rows, and answers last. |
| Use the builder's rows | none stored (a checkbox) | ticked, unticked | ticked | The restore-defaults control. Ticked, no `rows` are stored and the guide has the plugin's rows, by US shoe size in the shopper's language. The tab shows those rows in the editor to start from; typing in a row, adding or removing one unticks it where the page runs its script, and a screen reader hears it once as typing unticks it. Without the script, rows typed while it is ticked are not saved, and the tab says so with a link to the box. |
| Rows | `width_guide.rows[]` with `shoe`, `from`, `to` | One to twelve rows. **Shoe size**: plain text as the shop writes it, up to 40 characters, through `cbuild_kit_text` (`width_guide_shoe`). **Deck width from** and **to**: 5 to 12 in, typed as the shop writes a measure (its decimal separator, or a fraction); **to** left empty is "and wider", and is never under **from**. | none | The guide's rows, in the order given. Add a row and Remove work by keyboard; the server draws twelve rows, and those past the kit's and one empty one wait hidden. A row left empty is no row. A row that is wrong is named at its field and the guide is not saved; its notice leads to the field, and the rest of the kit saves (D-385). |

### Assembly time (#142)

The Build tab's **Assembly** section gains a field beside the lead time, kept in the same `_cbuild_settings` document as `lead_days` (D-375), so a rollback to 1.1.0 still reads the kit (D-354). A kit saved before 1.2.0 has none.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Assembly time in business days | `lead_days` | A count, such as `2`, or a range, the fewer first, such as `2-4`, from 0 to 60. Typed as `2-4`, `2 - 4` or `2–4`, and kept as `2-4`; no word joins a range, in any language (D-389); a range of one day is kept as that day. Digits of any script are kept as ASCII digits: `٢-٤` is kept as `2-4`. Left empty, the key is left out. | none (the key left out) | How many business days putting a build together takes, the form Creative Checkout's "Delivery time (business days)" takes. Shoppers do not see it; the lead time stays the words the review shows. `cbuild_arrival_estimate` and `cbuild_delivery_line` get it as `lead_days` in their context for an assembled build, and the order keeps it in `_cbuild_snapshot` as sold. A value that is not business days, or is past 60, is not saved: the notice leads to the field, which shows it as typed, and the rest of the kit saves. |

**Reading it from another plugin.** A cart line of a build carries the kit's ID as `kit` in its `cbuild`; the kit's `_cbuild_settings` meta holds `lead_days`, and `schema/kit-settings.json` describes it. Within a request of the builder, the filters' `lead_days` is already that value.

### Not offered in the builder (#116)

On a product's Part specs tab, and on each variation's Part specs fields, kept as `_cbuild_not_offered` product meta (D-351). A part saved before 1.2.0 has none, and is offered.

| Setting | Key | Choices | Default | What it does |
| --- | --- | --- | --- | --- |
| Not offered in the builder | `_cbuild_not_offered` (product meta) | ticked (`yes`), not ticked (no meta) | not ticked | Keeps the part out of every kit, exactly as `cbuild_part_available` returning `false` does, and the filter still answers last. On a variable product it keeps out every variation; on a variation, that variation alone. The part stays in the shop. The Catalogue check lists it under "Parts kept out of the builder", not as a fix. Saved by who may edit products, through WooCommerce's product save and its nonce. |

## Options beside the settings

The plugin keeps seven options of its own, which no screen edits: `cbuild_db_version`, `cbuild_migrations`, `cbuild_migration_lock` and, from 0.3.0, `cbuild_reindex_state`, `cbuild_reindex_owed`, `cbuild_import_state` and `cbuild_import_rows`. [HOOKS.md](HOOKS.md) says what each holds, and [PRIVACY.md](PRIVACY.md) lists everything stored.
