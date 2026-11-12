# Hooks and the public API

Everything another plugin, a theme or a shop's own code may rely on, added as it appears: filters and actions, constants, options, tables, scheduled actions, the names the plugin writes under, the kit's document schemas and the spec keys. Names carry the `cbuild_` prefix. An item listed in the spec and not here has not been built yet.

`composer api:check` reads the code and fails when an item is missing from this file or from `tests/Fixtures/api-surface.json`. Renaming or removing anything here is a breaking change and needs Zach.

## Milestone 1: the foundation (0.1.0)

The foundation adds one filter and one constant a site may set, both for the updater, and four constants it defines itself. It adds no action, REST route, ability, block, WP-CLI command, setting key, meta key, CSS class or DOM event.

### `cbuild_updates_enabled` (filter)

- **Since:** 0.1.0
- **Signature:** `apply_filters( 'cbuild_updates_enabled', bool $on ): bool`
- **What it does:** decides whether the plugin checks the agency's release manifest for a new version. `$on` is true unless `CBUILD_DISABLE_UPDATES` is defined and true. When the answer is false the plugin offers no update, shows no release details and makes no outbound request. A package of its own that WordPress was offered earlier is then refused with the error `cbuild_updates_off`, not left to install unchecked (D-043).
- **Where it runs:** `Updates\Updater::enabled()`, each time WordPress asks the plugin about an update, release details or a download, also while WooCommerce is missing, so a fix still arrives (D-003). It can be added at any point, a theme's `functions.php` included.
- **Example:**

```php
add_filter( 'cbuild_updates_enabled', '__return_false' );
```

### `CBUILD_DISABLE_UPDATES` (constant)

- **Since:** 0.1.0
- **What it does:** `define( 'CBUILD_DISABLE_UPDATES', true );` in `wp-config.php` turns the update check off for a site whose releases are deployed another way. The `cbuild_updates_enabled` filter runs after it and may turn the check back on.
- **Where it is read:** `Updates\Updater::enabled()`.

### Constants the plugin defines

Defined by the main file as soon as the plugin loads, also below the floors. Read them; never define them.

| Constant | Since | What it holds |
| --- | --- | --- |
| `CBUILD_VERSION` | 0.1.0 | The plugin's version, the same as its header. |
| `CBUILD_FILE` | 0.1.0 | The main file's full path. |
| `CBUILD_PATH` | 0.1.0 | The plugin's folder, with a trailing slash. |
| `CBUILD_URL` | 0.1.0 | The plugin's URL, with a trailing slash. |

### Options

Per site. All three are removed on uninstall.

| Option | Since | Autoloaded | What it holds |
| --- | --- | --- | --- |
| `cbuild_db_version` | 0.1.0 | Yes | The installed schema version. Behind the code's version means migrations are pending. |
| `cbuild_migrations` | 0.1.0 | No | Each migration's status, attempts, cursor, last error and, after a failure, the time of its next attempt, so an interrupted one resumes and a failing one waits. |
| `cbuild_migration_lock` | 0.1.0 | No | The time the running migration took or last refreshed the lock, in UTC seconds, then a colon and a token naming the run that holds it (D-033). It expires after 300 seconds (D-021). Read it, never write it. |

### Site transients

| Transient | Since | What it holds |
| --- | --- | --- |
| `cbuild_update_manifest` | 0.1.0 | The release manifest, cached for 12 hours, or for 1 hour after a failed request. One for the network. Removed on uninstall. |

### Tables

Per site, each named with the site's table prefix first (`wp_cbuild_specs` on a default install). The columns are in the spec's Data model and in `Install\Schema`. Read them if you must; write them only through the plugin. All are dropped on uninstall and when a network site is deleted.

| Table | Since | What it holds |
| --- | --- | --- |
| `cbuild_specs` | 0.1.0 | One row per part and spec: the index the fit engine reads. Empty until milestone 3. |
| `cbuild_builds` | 0.1.0 | Saved builds. Empty until milestone 8. |
| `cbuild_parts` | 0.4.0 | What a build needs to know of each part, one row per product or variation and price context: whether it may be sold, its brand, price, stock, a variation's attribute values and its specs. Derived from the shop: a row is deleted when its part moves and read again when next asked for, so a row is never the place to change a part. To have rows read again, do `cbuild_parts_changed`. |

### Scheduled actions

Action Scheduler, in the group `creative-skateboard-builder`.

| Action | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_migrate` | 0.1.0 | None | Runs pending migrations off the request, within a 20 second budget, and queues itself again if there is more. At most one is pending at a time, beside the one that is running (D-022, D-043). After a failed step the next run is scheduled 1, 5 and 30 minutes later, then hourly (D-033). A shop manager's admin page runs them inline for up to 2 seconds and leaves the rest to this action. |

Pending actions are unscheduled on deactivation. Every action in the group, with its logs and the group's own row, is deleted on uninstall (D-024).

### Log source

Every log line goes through `wc_get_logger()` with the source `creative-skateboard-builder` (since 0.1.0), under WooCommerce > Status > Logs.

## Milestone 2: the domain (0.2.0)

The domain adds no hook, route, setting, option, table or meta key. It fixes two things other code will read from milestone 3 on: the shape of a kit's four documents, and the keys a part's specs are stored under.

### Document schemas

A kit is four JSON documents. Each has a JSON Schema (draft 4) in the plugin's `schema/` folder, which is what the Build tab and the imports validate against with `rest_validate_value_from_schema()`. Every document carries `schema_version`, now 1; a document of a version the plugin does not know is refused, never guessed at. The meta keys that hold the documents arrive with the kit in milestone 4.

| Schema | Since | What the document holds |
| --- | --- | --- |
| `schema/kit-slots.json` | 0.2.0 | The kit's slots in step order, 1 to 20, the deck first: each one's key, label, help, whether it is required, the units a build takes, its default part and where its parts come from (categories, brands, products, attribute terms). |
| `schema/kit-rules.json` | 0.2.0 | Each fit rule, R1 to R6, on or off, with R1's tolerances, R3's limits by height class and its riser table, and whether R6 checks a deck's length. Measures are decimal strings in the rule's unit, with no unit mark. |
| `schema/kit-presets.json` | 0.2.0 | Up to six presets: a name, a description and a part for each slot it fills. |
| `schema/kit-assembly.json` | 0.2.0 | Whether assembly is on by default, its fee as a decimal string in the shop's currency, the lead time and the returns notice. |

| `schema/settings.json` | 0.3.0 | Not a kit document: the shape of the option `cbuild_settings` as the plugin writes it. `attribute_mapping` is up to 50 lines, each an `attribute` of 1 to 190 characters, a `spec` that is a spec key and a `unit` of `in`, `mm` or empty; `source_order` is `table`, `field` and `attribute`, each once. From 0.4.0 it also holds the five keys that version adds, listed under Settings keys with their ranges: the discount as text, the coupon's place, the rate as a whole number, the header's name, and a new kit's assembly as `schema/kit-assembly.json` has it. Other keys are allowed, so a later version's settings pass. It has no `schema_version`: a key that is missing or malformed reads as its default. |
| `schema/order-snapshot.json` | 0.5.0 | Not a kit document: the order item meta `_cbuild_snapshot`, a build as it was sold. See Order item keys. |

A schema says what shape a document has. Five things it cannot say are checked when the document is read: slot keys are unique, the deck is the first slot, the risers come before the hardware, R1's `axle_warn` is at least its `axle_fit`, and each row of R3's riser table reaches further than the one before. A preset must also fill every required slot and name only slots the kit has.

A change to a schema that refuses a document it accepted before is a breaking change and comes with a new `schema_version` and a migration.

### Spec keys

The keys a part's specs are stored under: in the specs index (`cbuild_specs`), in a part's meta and in imports from milestone 3. Each has one kind. A measure has one unit, and is held to four decimal places.

| Key | Since | Kind | Unit or values | What it is |
| --- | --- | --- | --- | --- |
| `deck_width` | 0.2.0 | Measure | in | A deck's width. |
| `deck_length` | 0.2.0 | Measure | in | A deck's length. |
| `wheelbase` | 0.2.0 | Measure | in | A deck's wheelbase. |
| `hole_pattern` | 0.2.0 | Choice | `new_school`, `old_school`, `both` | The mounting pattern a deck is drilled for. |
| `axle_width` | 0.2.0 | Measure | in | A truck's axle width. |
| `deck_min` | 0.2.0 | Measure | in | The narrowest deck a truck's maker gives for it. |
| `deck_max` | 0.2.0 | Measure | in | The widest deck a truck's maker gives for it. |
| `truck_height` | 0.2.0 | Measure | mm | A truck's height. |
| `height_class` | 0.2.0 | Choice | `low`, `mid`, `high` | The height class a truck is sold in. |
| `max_wheel` | 0.2.0 | Measure | mm | The largest wheel a truck takes without risers. |
| `baseplate` | 0.2.0 | Choice | `new_school`, `old_school`, `both` | The mounting pattern a truck's baseplate takes. |
| `axle_diameter` | 0.2.0 | Measure | mm | A truck's axle diameter. |
| `wheel_diameter` | 0.2.0 | Measure | mm | A wheel's diameter. |
| `riser_thickness` | 0.2.0 | Measure | in | A riser's thickness. |
| `bolt_length` | 0.2.0 | Measure | in | The length of a hardware set's bolts. |
| `bearing_bore` | 0.2.0 | Measure | mm | A bearing's bore. |
| `spacers_built_in` | 0.2.0 | Flag | | Whether a bearing has its spacers built in. |
| `grip_width` | 0.2.0 | Measure | in | A sheet of grip tape's width. |
| `grip_length` | 0.2.0 | Measure | in | A sheet of grip tape's length. |
| `set_size` | 0.2.0 | Count | | How many units one of the product holds: a pair of trucks is 2. Needed only in a slot that takes more than one unit. |

### Findings and message codes

A fit rule answers with findings. Each carries its rule (`r1`, `r2`, `r3`, `r4`, `r5` or `r6`, and `r7` for a part with no `set_size` in a slot that takes more than one unit; all since 0.2.0), a severity, a message code, the values behind it, where the figure came from and the slots it read. The state route carries them from milestone 4, and the translations turn a code and its values into a sentence. The domain holds no words.

Severities, since 0.2.0: `fits` (a note: a default was taken for a spec the part does not give), `warn` (the build can go ahead) and `block` (it can't). Sources, since 0.2.0: `maker` (a spec on the part), `kit` (a kit setting), `standard` (the trade's usual figure) and `none` (no figure: the finding is that a spec is missing).

| Code | Since | Rule | Severity | Values | What it means |
| --- | --- | --- | --- | --- | --- |
| `spec_missing` | 0.2.0 | Any | Block | `spec` | The part lacks a spec the rule needs, so it is not offered. |
| `deck_below_range` | 0.2.0 | R1 | Block | `deck_width`, `deck_min`, `deck_max` | The deck is narrower than the truck maker's range. |
| `deck_above_range` | 0.2.0 | R1 | Block | `deck_width`, `deck_min`, `deck_max` | The deck is wider than the truck maker's range. |
| `axle_narrower` | 0.2.0 | R1 | Warn or block | `deck_width`, `axle_width`, `gap` | A truck with no range has an axle narrower than the deck by more than the kit allows. |
| `axle_wider` | 0.2.0 | R1 | Warn or block | `deck_width`, `axle_width`, `gap` | The same, with the axle wider than the deck. |
| `pattern_mismatch` | 0.2.0 | R2 | Block | `hole_pattern`, `baseplate` | The baseplate does not take the deck's mounting pattern. |
| `pattern_assumed` | 0.2.0 | R2 | Fits | `hole_pattern` | The deck gives no pattern and was counted as new school. |
| `wheel_needs_riser` | 0.2.0 | R3 | Warn | `wheel_diameter`, `limit`, `over`, `riser` | The wheel is over the truck's limit; a riser of at least `riser` clears it. |
| `wheel_no_riser` | 0.2.0 | R3 | Warn | `wheel_diameter`, `limit`, `over` | The wheel is further over the limit than any riser in the kit's table covers. |
| `limit_from_class` | 0.2.0 | R3 | Fits | `height_class`, `limit` | The truck gives no largest wheel, so its height class's limit was used. |
| `bolts_too_short` | 0.2.0 | R4 | Block | `bolt_length`, `shortest`, `usual`, `riser_thickness` | The bolts are shorter than 7/8 in plus the riser. |
| `bolts_too_long` | 0.2.0 | R4 | Warn | `bolt_length`, `shortest`, `usual`, `riser_thickness` | The bolts are more than 1/4 in longer than usual and will stand proud. |
| `bore_mismatch` | 0.2.0 | R5 | Block | `bearing_bore`, `axle_diameter` | The bearing's bore is not the axle's diameter. |
| `axle_assumed` | 0.2.0 | R5 | Fits | `axle_diameter` | The truck gives no axle diameter and was counted as 8 mm. |
| `spacers_built_in` | 0.2.0 | R5 | Block | None | The bearings have their spacers built in, so separate spacers are not offered. As a spec key it is the bearing's flag. |
| `grip_too_narrow` | 0.2.0 | R6 | Block | `grip_width`, `deck_width` | The sheet is narrower than the deck. |
| `grip_too_short` | 0.2.0 | R6 | Block | `grip_length`, `deck_length` | The sheet is shorter than the deck. |
| `length_unchecked` | 0.2.0 | R6 | Fits | None | The deck gives no length, so the length check was skipped. |

The names under Values are public too: a translation's placeholders are written against them. A finding may leave a value out (a maker's range with one end published carries that end only) and never carries one its code does not name.

A measure among the values is in its spec's unit; `gap` is in inches, `limit` and `over` in millimetres, and `riser`, `shortest` and `usual` in inches.

### Build problems

Before a build is sold, the validator checks the whole of it and answers with what is wrong, if anything. Each problem carries one of these codes, the slot, the part where there is one, and for a part that does not fit, the findings that block it. The cart's notices and the state route carry them from milestones 4 and 5.

| Code | Since | What it means |
| --- | --- | --- |
| `slot_unknown` | 0.2.0 | The build holds a pick for a slot the kit does not have. |
| `slot_empty` | 0.2.0 | A slot the kit requires is empty. |
| `part_not_in_slot` | 0.2.0 | The part is not one of the slot's parts. |
| `part_sold_out` | 0.2.0 | The part cannot be bought now. |
| `part_short` | 0.4.0 | The part is in stock, but the shop holds fewer than the build takes of it: one truck left, sold singly, where a build takes two. |
| `part_blocked` | 0.2.0 | The part does not fit the rest of the build. Its findings say why. |

A build that only warns has no problem and can go to the cart.

### Refusal codes

When the domain refuses what it was given, it answers with a typed exception whose `reason` is one of these codes and, for a document, the path of the field. The Build tab, the Part specs panel and the imports turn them into a notice beside the field from milestones 3 and 4.

| Code | Since | Refuses | What it means |
| --- | --- | --- | --- |
| `document_not_an_object` | 0.2.0 | A kit document | The document, or an object inside it, is not an object. |
| `document_unknown_version` | 0.2.0 | A kit document | Its `schema_version` is one this version does not read. |
| `document_field_missing` | 0.2.0 | A kit document | A required field is not there. |
| `document_wrong_type` | 0.2.0 | A kit document | A field holds the wrong kind of value. |
| `document_out_of_range` | 0.2.0 | A kit document | A number, a list's length or a text's length is outside what the schema allows. |
| `document_bad_measure` | 0.2.0 | A kit document | A length or the fee is not a decimal with a point and up to four places. |
| `document_bad_key` | 0.2.0 | A kit document | A key or an attribute's taxonomy is not in its form. |
| `document_duplicate` | 0.2.0 | A kit document | A slot's key appears twice. |
| `slots_deck_not_first` | 0.2.0 | The slots | The first slot is not the deck. |
| `slots_risers_after_hardware` | 0.2.0 | The slots | The risers come after the hardware. The bolts are picked to reach through the riser, so the risers come first. |
| `rules_out_of_order` | 0.2.0 | The rules | A tolerance is under the one before it, or a riser row does not reach further than the last. |
| `presets_unknown_slot` | 0.2.0 | The presets | A preset picks for a slot the kit does not have. |
| `presets_slot_not_filled` | 0.2.0 | The presets | A preset leaves a required slot empty. |
| `spec_wrong_kind` | 0.2.0 | A part's spec | The value is not of the key's kind: a measure, a choice, a flag or a count. |
| `spec_unknown_choice` | 0.2.0 | A part's spec | The value is not one of the choice's. |
| `spec_not_positive` | 0.2.0 | A part's spec | A measure or a count of nothing. |
| `spec_too_many` | 0.2.0 | A part's spec | A count over 1,000. |
| `measure_malformed` | 0.2.0 | A typed measure | The text is not a number, a fraction or a mixed number, with or without its unit. |
| `measure_zero_denominator` | 0.2.0 | A typed measure | A fraction over zero. |
| `measure_negative` | 0.2.0 | A measure | A length below nothing. |
| `part_id_not_positive` | 0.2.0 | A part | The product id is not one or more. |
| `part_price_negative` | 0.2.0 | A part | The price is below nothing. |
| `discount_out_of_range` | 0.2.0 | A quote | The build discount is below 0 or is 100 % or more. |
| `price_decimals_over_eight` | 0.2.0 | A quote | The shop's prices have more than eight decimal places. |

A spec row in the index whose key this version does not know is passed over, not refused, so a part keeps its other specs after a rollback.

## Milestone 3: specs (0.3.0)

The rest of this milestone's items are added here as each lands.

### Index sources

Where a spec's value came from, as the specs index's `source` column holds it. `source_ref` says what in that source. Which source wins where two disagree is the shop's setting; the order below is the default.

| Source | Since | What it is | `source_ref` |
| --- | --- | --- | --- |
| `table` | 0.3.0 | The spec table and size the part names: a maker's chart. | The table and the size. |
| `field` | 0.3.0 | The part's own Part specs field. | Empty, or `parent` when a variation takes its parent's value. |
| `attribute` | 0.3.0 | A product attribute the shop mapped to the spec. | The attribute's name, such as `pa_width`. |

### Product meta

| Key | Since | On | What it holds |
| --- | --- | --- | --- |
| `_cbuild_specs` | 0.3.0 | Products and variations | The part's own specs: an array with a value per spec key, each as text in the key's unit with a point for the decimal (`8.2500`), a choice's value, `1` or `0` for a flag, or digits for a count. A variation with no value for a key takes its parent's. A key the plugin does not know is ignored. Write it through the product's CRUD; the Part specs panel and the import do. |
| `_cbuild_spec_table` | 0.3.0 | Products and variations | The spec table and size the part names: an array with `table`, the table's post ID as a number, and `size`, the size's name as text. A variation left blank for either takes its parent's. |
| `_cbuild_rows` | 0.3.0 | Spec tables | The table's rows, a list. Each row: `size`, the size's name, up to 40 characters; `specs`, a value per spec key, as text in the key's unit with a point for the decimal; `source`, where the row was read, up to 200 characters; `read_on`, the day it was read as `2026-11-10`, or empty. |
| `_cbuild_seed` | 0.3.0 | Spec tables | On a table the plugin made: `independent`, `thunder` or `venture`. A table the shop made has none. Read it, never write it. |

### Post types

| Post type | Since | What it is |
| --- | --- | --- |
| `cbuild_spec_table` | 0.3.0 | A maker's size chart: the title is the maker's name, the rows are in `_cbuild_rows`. Only a published one feeds the index. Not public: it has no page, archive, search result, sitemap entry or REST route, and every capability on it is `manage_woocommerce`. The shop edits it at WooCommerce > Builder (`admin.php?page=cbuild`); WordPress's own post screens are off for it. Saving, binning, restoring or deleting one queues `cbuild_reindex`. Independent, Thunder and Venture are made by the migration `spec_tables_1`. |

### Settings keys

In the option `cbuild_settings`. [SETTINGS.md](SETTINGS.md) has each key's default and range, and the screen that sets them: the tab `cbuild` of WooCommerce's settings (`admin.php?page=wc-settings&tab=cbuild`).

| Key | Since | What it holds |
| --- | --- | --- |
| `attribute_mapping` | 0.3.0 | Which attribute feeds which spec, and the unit its values are in. |
| `source_order` | 0.3.0 | Which source wins where two disagree. |
| `build_discount` | 0.4.0 | The percentage taken off each part of a build, as text with a point and up to two decimals: `7.5`. `0` is none, and it is always below 100. |
| `fixed_coupons` | 0.4.0 | Where a fixed product discount coupon that takes the kit lands: `kit` or `parts`. |
| `rate_limit` | 0.4.0 | How many times a minute one address may ask the state route: 10 to 6000. |
| `trusted_proxy_header` | 0.4.0 | The request header a trusted proxy names the visitor's address in: a header's name of up to 64 letters, digits and hyphens, starting with a letter. Empty reads `REMOTE_ADDR`. |
| `kit_defaults` | 0.4.0 | What a new kit starts from: `assembly`, an assembly document. |

### Options

| Option | Since | What it holds |
| --- | --- | --- |
| `cbuild_settings` | 0.3.0 | The shop's settings, one array, with the keys above. |
| `cbuild_reindex_state` | 0.3.0 | Where the reindex is: its phase (`rows`, `catalogue`, `done` or `failed`), its place in that phase, how many parts it has indexed, when it started and finished in UTC seconds, whether it must pass once more, its failures in a row with the last error's message, and which request it is, counted from 1. Not autoloaded. Read it, never write it. |
| `cbuild_reindex_owed` | 0.3.0 | Present, with the value `1`, while a reindex is owed: the plugin was activated on a site that had no queue, as when WooCommerce is missing. The first request that finds the queue asks for the reindex and removes the option. Autoloaded. Read it, never write it. |
| `cbuild_import_state` | 0.3.0 | The specs import in hand: its status (`waiting`, `running`, `done` or `failed`), a token naming it, the column rows are matched by (`id` or `sku`), the shop's decimal separator when the file was read, where each column sits, whether its rows are held (`has_rows`, while it waits and runs), how many rows there were and how many are done, the parts changed, the rows that changed nothing, each row refused with its column, a code and what the cell held, its failures in a row, and while it waits the ID of the user who sent the file. Not autoloaded. Read it, never write it. |
| `cbuild_import_rows` | 0.3.0 | The rows of the specs import in hand, kept apart so a batch does not write them again: the import's token, the rows read from the file, and the product each SKU matched when the file was read (for an import by SKU). Written once when a file is read, and removed when the import ends, is put away or is replaced. Not autoloaded. Read it, never write it. |

### Scheduled actions

Action Scheduler, in the group `creative-skateboard-builder`. Each can run twice and leave the index as running once would. Pending ones are unscheduled on deactivation, whatever their arguments.

| Action | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_index_part` | 0.3.0 | `part`, a product's or variation's ID; `attempt`, on a retry | Indexes one product's specs off the request that saved it: a variable product's variations, or the product itself. A product that is gone loses its rows. One waits per product at a time. A failure is logged and tried again after 1, 2 and 4 minutes, then recorded as failed. |
| `cbuild_reindex` | 0.3.0 | None | Indexes every part again, 100 at a time from a saved place, and queues itself until done: first the parts the index already holds, so rows of a deleted product go, then the shop's products and variations by ID. Asked for when the settings are saved or a term of a mapped attribute is edited. A product deleted while it runs makes it pass once more. A failed batch keeps its place and is tried again after 1, 2, 4 and 8 minutes; the fifth failure in a row stops it. |
| `cbuild_import` | 0.3.0 | None | Writes a confirmed specs import, 100 rows at a time from a saved place, and queues itself until done. Each row is checked again against what the shop holds when the batch runs. A failed batch keeps its place and is tried again after 1, 2 and 4 minutes; the fourth failure in a row stops the import, with the parts before it written. |

### The specs sheet

The CSV file that WooCommerce > Builder > Import and export writes and reads. One row per product or variation; a variable product has a row beside its variations, since it names the chart they take their sizes from. The headings are the same in every language. An export is UTF-8 with a byte-order mark, comma-separated, with a point for the decimal.

| Column | Since | Export | Import |
| --- | --- | --- | --- |
| `id` | 0.3.0 | The product's or variation's ID. | The key, when parts are matched by ID. |
| `sku` | 0.3.0 | Its SKU. | The key, when parts are matched by SKU. Matched by ID, a SKU that is not the product's refuses the row. |
| `name` | 0.3.0 | Its name, for the reader. | Passed over. |
| `spec_table` | 0.3.0 | The name of the spec table it names, empty for none. | A spec table's name. Empty removes it. |
| `spec_size` | 0.3.0 | The size it names. | A size. With a table, it must be one of the table's. Empty removes it. |
| One per spec key | 0.3.0 | The part's own value, as stored. What it takes from a chart, an attribute or its parent is not written. | A value, read as the Part specs panel reads one: the shop's decimal separator or a point, or a fraction. Empty removes it. |

An import reads a file of 2 MB and 5,000 rows at most, comma- or semicolon-separated, in UTF-8 with or without the mark. A heading is matched whatever its case, and a column the sheet does not have is passed over. A column the file leaves out is left as it is. A cell that an export neutralised with a leading apostrophe is read without it. A row with anything wrong in it is not written at all.

Why a file is not read: `upload_missing`, `upload_failed`, `upload_not_csv`, `file_too_big`, `file_not_utf8`, `file_empty`, `rows_too_many`, `key_column_missing`, `column_twice`, `no_spec_columns` and `import_running`.

Why a row is not written: `key_missing`, `key_twice`, `part_missing`, `sku_mismatch`, `table_unknown`, `table_ambiguous`, `table_missing`, `size_missing`, `size_long`, and the refusal codes of a value (`measure_malformed`, `measure_zero_denominator`, `measure_negative`, `spec_unknown_choice`, `spec_not_positive`, `spec_wrong_kind`).

### The Catalogue check

What WooCommerce > Builder > Catalogue check lists, each a `Service\CheckFinding` with a code. The check runs when asked, reads the sources as the indexer does and writes nothing. A kit's findings are the fit engine's and the build validator's own, asked of each part alone; the check holds no rule.

| Code | Since | About | What it means |
| --- | --- | --- | --- |
| `sources_disagree` | 0.3.0 | A part and a spec | Two sources hold different values. The finding carries each source's value, the one the settings' order uses first. |
| `value_unread` | 0.3.0 | A part, and a spec where there is one | A source holds a value that cannot be read: its `values` are the source, the refusal code and the text. A part that names a chart that is gone, or a size its chart does not have, is one. |
| `reindex_failed` | 0.3.0 | The shop | Reading every part again stopped after its failures. |
| `import_failed` | 0.3.0 | The shop | The last specs import stopped before its end. |
| `jobs_failed` | 0.3.0 | The shop | Actions of the group `creative-skateboard-builder` are marked failed in Action Scheduler. `values` holds how many, counted to 100. |
| `kit_unread` | 0.4.0 | A kit | The kit product's settings do not read, so the builder cannot open it: a document its schema refuses, or presets and slots that no longer agree. The screen links to the kit's Build tab. |
| `slot_empty` | 0.3.0 | A kit and a slot | The slot has no parts. |
| `slot_out_of_stock` | 0.3.0 | A kit and a slot | The slot has parts and none in stock. |
| `spec_missing` | 0.3.0 | A kit, a slot, a part and a spec | A rule needs the spec, the part lacks it, and the part is not offered. Said once per part and spec. |
| `spec_assumed` | 0.3.0 | A kit, a slot, a part and a spec | A rule went without the spec and assumed the usual: the hole pattern, the axle's diameter, the deck's length, or the wheel a truck clears. |
| `preset_broken` | 0.3.0 | A kit, a slot and a part | A preset names a part that is gone from its slot, is sold out, or no longer fits the preset's other parts. `values` holds the preset's name and the build problem's code. |

The kit codes are found once the shop has a kit, which milestone 4 builds: until then `Port\Kits` answers with none, and the screen says so.

### WP-CLI commands

Each runs the service its screen runs, and says a finding or a refused row in the same sentence. A command that ends with `Error:` exits 1.

| Command | Since | Usage | What it does | Exit status |
| --- | --- | --- | --- | --- |
| `cbuild specs` | 0.3.0 | `wp cbuild specs export [<file>]` | Writes the specs sheet to a path ending in `.csv`, in place of a file that is there, or prints it. | 0; 1 when the file cannot be written. |
| | | `wp cbuild specs import <file> [--dry-run] [--key=<id\|sku>] [--format=<table\|csv\|json\|yaml>]` | Reads a sheet from a path ending in `.csv`, lists each row it cannot read with its `row`, `column`, `code` and `message`, and writes the rest, here and now. With `--dry-run` it says what would change and writes nothing. | 0 when every row was read; 1 when the file was not read, a row was not, or a batch failed. |
| `cbuild check` | 0.3.0 | `wp cbuild check [--format=<table\|csv\|json\|yaml\|count>]` | Runs the Catalogue check and lists each finding's `code`, `part`, `kit`, `slot`, `spec` and `message`. | 0 when it found nothing; 1 when it found anything. |
| `cbuild reindex` | 0.3.0 | `wp cbuild reindex [--background]` | Reads every part's specs into the index again, here and now, or queues the `cbuild_reindex` action with `--background`. | 0; 1 when a batch failed, which the queue then tries again. |

`cbuild specs` is the namespace `export` and `import` sit under. A path with a scheme (`https://`, `phar://`, `php://`) is refused before anything opens it.

### Abilities

WordPress's Abilities API, in the category `creative-skateboard-builder`.

| Ability | Since | Who | Input | Output |
| --- | --- | --- | --- | --- |
| `creative-skateboard-builder/catalogue-check` | 0.3.0 | `manage_woocommerce` | None | `count`, how many things the check found, and `findings`, the first 500: each with its `code`, `part`, `kit`, `slot`, `spec`, `values` (an object of strings) and `message`, the sentence the screen shows, in the caller's language. The codes are the Catalogue check's, above. |

It is annotated read-only and idempotent and shown in REST, so WordPress serves it at `GET /wp-abilities/v1/abilities/creative-skateboard-builder/catalogue-check/run` to a signed-in user who can manage WooCommerce, and refuses a `POST` with 405.

### Site Health

Two direct tests and one asynchronous test on Tools > Site Health, and a section on its Info tab.

| Test | Since | Good | Recommended | Critical |
| --- | --- | --- | --- | --- |
| `cbuild_index` | 0.3.0 | No reindex has been asked for, the last one finished, or one is under way with its job waiting or running. | A reindex is under way and has no job: it stopped part way. | A reindex stopped after five failures. |
| `cbuild_jobs` | 0.3.0 | No action of the group `creative-skateboard-builder` is marked failed. | One or more is, with links to the failed actions and the Catalogue check. | Never. |
| `cbuild_catalogue` | 0.3.0 | The Catalogue check finds nothing in any part or kit. | It finds something: the result counts the findings and the parts they are in, and links to the check. Background work that failed is left to the two tests above. | Never. |

`cbuild_catalogue` reads every part, so it is asynchronous: the page asks for it once it has drawn, and WordPress's weekly check calls it directly.

### REST routes

| Route | Since | Method | Who | Answers |
| --- | --- | --- | --- | --- |
| `cbuild/v1/site-health/catalogue-check` | 0.3.0 | `GET` | `view_site_health_checks`, as WordPress's own Site Health routes | The `cbuild_catalogue` test's result, in the shape Site Health takes: `label`, `status` (`good` or `recommended`), `badge` (`label`, `color`), `description`, `actions` and `test`. It reads and changes nothing, and is for Site Health's page; another caller wants the ability above, which gives the findings. |

The Info tab's section `creative-skateboard-builder` lists the plugin's version, its database version, which source wins, how many attributes are mapped, the index's state and the count of failed jobs. None of it is marked private, and none of it is about a person.

## Milestone 4: kits (0.4.0)

### Product types

| Type | Since | What it is |
| --- | --- | --- |
| `cbuild_kit` | 0.4.0 | A kit: the product a shopper builds from parts. Virtual. `is_purchasable()` is false, so nothing but the builder puts one in a cart. Its price to whoever shows it is the assembly fee from `_cbuild_assembly`; WooCommerce's price fields stay empty, and a price set on a kit is not kept. With the plugin switched off WooCommerce reads a kit as a simple product with no price, which it does not sell. |

### Kit meta

Each is on a kit product, as JSON text, and is the document its schema describes (Document schemas, above). Read one with `json_decode( $product->get_meta( $key ), true )`. A document is checked against its schema and then by the plugin before it is used, so one written by hand that fails either is treated as missing.

| Key | Since | Schema | What it holds |
| --- | --- | --- | --- |
| `_cbuild_slots` | 0.4.0 | `schema/kit-slots.json` | The slots, in step order. |
| `_cbuild_rules` | 0.4.0 | `schema/kit-rules.json` | Which rules run for the kit, and their settings. |
| `_cbuild_presets` | 0.4.0 | `schema/kit-presets.json` | Up to six ready builds. |
| `_cbuild_assembly` | 0.4.0 | `schema/kit-assembly.json` | Assembly: on or off to start, the fee, the lead time and the returns notice. |

### The state route

| Route | Since | Method | Who | Answers |
| --- | --- | --- | --- | --- |
| `cbuild/v1/kits/(?P<id>[1-9][0-9]{0,17})/state` | 0.4.0 | `GET` | Anyone: it shows what the kit's page shows | The state of a build of the kit `<id>`: the document `schema/state.json` describes. |

It reads and changes nothing. Its arguments travel in the query:

| Argument | Since | What it takes |
| --- | --- | --- |
| `picks[<slot key>]` | 0.4.0 | A part's ID for a slot of the kit: a whole number above 0. Up to 20, and each key one of the kit's own. |
| `previous[<slot key>]` | 0.4.0 | The picks as they were before the last one changed. With it, the answer's `changes` says what the change does to the picks after it. |
| `step` | 0.4.0 | The key of the slot whose options are listed. Left out, the first slot with nothing picked. |
| `assembly` | 0.4.0 | `true` or `false`. Left out, the kit's own default. |
| `locale` | 0.4.0 | The language to answer in, such as `fr_CA`. The answer is in it when the site has it installed, and in the site's own otherwise. |
| `cbuild[…]` | 0.6.0 | A build link, as a page's address holds it (The build link, under Milestone 6). It takes the place of `picks`, `previous`, `step` and `assembly`, and sent beside any of them is refused as `rest_invalid_param`. The answer is what the builder's page shows for the link, with `view` added. `cbuild=` with nothing in it is the clean page. |
| `page` | 0.6.0 | With `cbuild`: the ID of the post the builder is on, for the addresses in `view`. A post a visitor may not see, or one whose Builder block is not this kit's, is not used, and the addresses go to where the kit's builder is (`cbuild_builder_url`). |

| Schema | Since | What the document holds |
| --- | --- | --- |
| `schema/state.json` | 0.4.0 | The route's answer (draft 4, with `definitions`): `kit`, `step`, `ready`, the kit's `texts` (lead time and returns notice) and `presets`, the `steps` (each slot's label, its pick, the part it starts on, how many parts it offers and hides, and the hidden ones counted by reason, each with a sentence), the current step's `options` and `hidden` parts (24 of each at most, each with its fit, its messages and its card), the picks that were `refused`, the `problems` that keep the build from the cart, the `changes` and the `total`. From 0.6.0 it also holds the step's `filters`, `matching` and `more`, the `picture`, `delivery` and `notes`, each option's `size` and `delta`, and each preset's `image`, `total`, `ready` and `problems` (Milestone 6, below), and closes no object. `npm run types` writes `assets/src/types/state.d.ts` from it. |

What the answer holds beside the schema's own descriptions:

- **Codes and sentences.** A message carries its rule, its message code and its severity (Findings and message codes, above) with the sentence beside them, and a problem its code (Build problems, above) with its sentence. The sentences are in the language of the answer and are not public API: read the codes.
- **Money** is an `amount` in whole minor units and a `text`, the amount as the shop writes its prices, in plain text.
- **A card** is what the shop shows of a part: its name, its page, the price of one, its availability in the shop's stock display format, and its photo. All of it is plain text for the page to escape.
- **A step lists 24 options,** those the shop can supply first, and always the part the step counts. `steps[].offered` and `steps[].hidden` say how many there are in all.
- **Fields are added** in minor versions, and none is renamed or removed within a major one.

Every answer of the route, a refusal included, carries `Cache-Control: no-store` and `X-Robots-Tag: noindex`. Its refusals are REST errors, each with `code`, `message` and `data.status`:

| Code | Since | Status | When |
| --- | --- | --- | --- |
| `rest_invalid_param` | 0.4.0 | 400 | An argument is not what the table above takes, or names a slot the kit does not have. WordPress's own code. |
| `cbuild_kit_not_found` | 0.4.0 | 404 | There is no such kit, or it is one a visitor may not see: a draft, a private kit or one behind a password. The answer is the same for all of them and for everyone. |
| `cbuild_rate_limited` | 0.4.0 | 429 | The address has asked more often this minute than the setting `rate_limit` allows. `Retry-After` gives the seconds left of the minute. |
| `cbuild_no_price` | 0.4.0 | 500 | The shop's currency has more decimals than a build can be priced in: more than eight. |

### Filters

Each is applied wherever the builder reads the thing it names, so the state route's answer, and from later milestones the page and the cart, all follow a plugin's answer.

| Filter | Since | Arguments | What a plugin answers |
| --- | --- | --- | --- |
| `cbuild_part_available` | 0.4.0 | `bool $available` (true), `int $id`, `WC_Product $part` | Whether a product or a variation may be a part of a build. `false` rules it out: no kit offers it, and one picked all the same is refused as `part_not_in_slot`. Asked only of a part a shopper could be shown and could buy. Anything but `false` leaves the part in. The answer is kept in the part's row of `cbuild_parts` for one to two hours, or until the product, its price, its stock or its terms move: a plugin whose answer changes without one of those does `cbuild_parts_changed`. |
| `cbuild_price_context` | 0.4.0 | `array $context` | What a part's price depends on beside the part: the currency, its decimals, the language, the tax display, whether prices are entered with tax, and the tax rates that apply. A card is kept per context, and so is each slot's list of parts, so a plugin that shows some shoppers another price adds what tells them apart, such as a role or a price list's ID. Without it, a shopper may be shown the price kept for another. Add nothing that names a person. An answer that is no array is ignored. |
| `cbuild_slot_label` | 0.4.0 | `string $label`, `string $slot`, `int $kit` | A slot's label as a shopper reads it, in place of the one the owner typed. `$slot` is the slot's key. The label answered is also the one a problem's sentence names the slot by. |
| `cbuild_preset_label` | 0.4.0 | `string $name`, `int $position`, `int $kit` | A preset's name. `$position` is where the preset stands among the kit's, from 0. |
| `cbuild_kit_text` | 0.4.0 | `string $text`, `string $field`, `int $kit`, `string $of` | Another of the kit's texts. `$field` is `slot_help` (`$of` is the slot's key), `preset_description` (`$of` is the preset's position), `lead_time` or `returns_notice` (`$of` is empty). |

The last three are for the owner's own words, which no translation file holds: a translation plugin answers them. An answer that is not a string leaves the owner's words as they are. The plugin does not escape or cut what a filter answers; the answer is plain text, and the page that prints it escapes it.

### Actions the plugin listens for

| Action | Since | Arguments | When a plugin does it |
| --- | --- | --- | --- |
| `cbuild_parts_changed` | 0.4.0 | `int[] $ids` (optional) | Something the builder keeps of a part changed and WooCommerce was not told: the plugin's answer to `cbuild_part_available`, or a price it sets with no product saved. `do_action( 'cbuild_parts_changed', array( 12, 34 ) )` and the next request reads those products, and their variations, from the shop again; with no IDs, every part. A product saved, a stock count set, a sale starting or ending and a term edited need no such word: the builder hears those itself. |

### Abilities

In the category `creative-skateboard-builder`, which the Catalogue check's ability made in 0.3.0.

| Ability | Since | Who | Input | Output |
| --- | --- | --- | --- | --- |
| `creative-skateboard-builder/check-build` | 0.4.0 | Whoever may run an ability | `kit`, the kit product's ID; `picks`, an object of slot key to part ID, 20 at most; `assembly`, a boolean. Only `kit` is required. | `kit`, `ready`, `refused` and `problems`, as the state route's answer holds them: whether the build may go to the cart, and each thing in the way with its `code`, `slot`, `part`, `message` and the rules' `messages`. |
| `creative-skateboard-builder/price-build` | 0.4.0 | Whoever may run an ability | The same. | `kit` and `total`, as the state route's answer holds it: `lines` (each with `slot`, `part`, `name`, `quantity`, `price`, `charged` and `total`), `parts`, `saved`, `assembly`, `fee` and `total`. Each amount is `amount`, in whole minor units, and `text`. |

Both are annotated read-only and idempotent and shown in REST, so WordPress serves each at `GET /wp-abilities/v1/abilities/<name>/run`. Each answers to a signed-in user alone, over REST and when run in PHP (D-118), and refuses a `POST` with 405. They ask the state route's own service, for the shopper WooCommerce knows the signed-in user as (from 0.5.0, D-138), and give parts of its answer, so the two cannot disagree: a slot with no pick is counted with the part the builder would start it on, as the route counts it. A kit a visitor may not see is `cbuild_kit_not_found` to everyone, as it is on the route. Input that is not the schema's is refused by WordPress as `ability_invalid_input`; a slot key the kit lacks is not refused, and comes back in `refused` as `slot_unknown`. Neither is counted against the route's rate limit.

## Milestone 5: cart and checkout (0.5.0)

### Adding a build

A build is added by the kit's ID and one more parameter, `cbuild` (0.5.0), on either route:

- the Store API's `POST /wc/store/v1/cart/add-item`, beside `id` and `quantity`, as JSON: `"cbuild": { "picks": { "deck": 103, "trucks": 149 }, "assembly": true }`;
- WooCommerce's add-to-cart form, as fields: `cbuild[picks][deck]=103`, `cbuild[assembly]=1`, posted with `add-to-cart=<kit>`.

`picks` is slot key to part ID. `assembly` may be left out, and is then the kit's own default. `quantity` is how many of the build. Nothing else is read: no price, no quantity of a part and no line of the cart comes from the request. Each part is read from WooCommerce at that moment, so the cart charges what the shop holds even where the builder was shown an older price.

A kit added with no `cbuild` is refused by every route, `WC_Cart::add_to_cart()` called in PHP among them: `is_purchasable()` is true for a kit only between its build being taken and its parts being added, in the same request.

### Cart item keys

| Key | Since | On | What it holds |
| --- | --- | --- | --- |
| `cbuild` | 0.5.0 | The kit's line | The build: `schema_version` (1), `id` (a UUID, which keeps two builds alike on two lines), `kit` (the kit product's ID), `picks` (slot key to part ID), `quantities` (slot key to how many of the part one build takes) and `assembly`. A line whose `cbuild` does not read this way is not sold. |
| `cbuild_children` | 0.5.0 | The kit's line | Its parts' cart keys, in step order. |
| `cbuild_parent` | 0.5.0 | A part's line | Its kit's cart key. |
| `cbuild_slot` | 0.5.0 | A part's line | The key of the slot it fills. |

Read them, and do not write them: the plugin puts a build together and takes it apart as one. A part's line is an ordinary line of that product, at the shop's price and quantity `quantities[slot]` times the kit's line's. The kit's line is priced at the assembly fee, or at nothing without assembly.

### Why a build is not added

The Store API answers with the code, and the form shows the sentence as an error notice.

| Code | Since | Status | When |
| --- | --- | --- | --- |
| `cbuild_no_build` | 0.5.0 | 400 | A kit arrived with no `cbuild`, or with one that holds no `picks`. |
| `cbuild_kit_not_found` | 0.4.0 | 404 | The shop has no such kit, or it is one a visitor may not see. From 0.5.0 the cart answers with it as the state route does. |
| `cbuild_build_refused` | 0.5.0 | 400 | The build has problems. The answer's `data.problems` lists each as `code` (Build problems, above), `slot` and `part`; the form shows a notice for each. |
| `cbuild_no_price` | 0.4.0 | 400 from the cart | The shop's currency has more decimals than a build can be priced in. |
| `cbuild_not_added` | 0.5.0 | 400 | WooCommerce would not add one of the parts, so the kit's line and every part already added were taken out again. |

### A build in the cart stays whole

The server keeps it so, whichever cart or plugin makes the change:

- **Quantity.** Setting the kit's line's quantity sets each part's line to `quantities[slot]` times it, before the totals are worked out. A part's line set alone goes back.
- **Removal.** Removing any line of a build removes all of it. Putting one back, as the classic cart's Undo does, puts all of it back, or none where a line can no longer be restored.
- **The Store API's limits.** A part's line has `quantity_limits.editable` false. A kit's line's `quantity_limits.maximum` is no more than its scarcest part allows, beside what the cart's other lines hold of that part, and never less than 1.
- **The check before paying.** On `woocommerce_check_cart_items`, which the cart page, both checkouts and every Store API cart response run, a build that has lost a line is removed with what is left of it, and each whole build is judged again as the shop is then. What is found is an error notice, which stops the order; the Store API lists it under the cart's `errors`.

- **Prices.** The kit's line is priced at the assembly fee or nothing, and each part's line at the shop's price less the setting `build_discount`, on `woocommerce_before_calculate_totals` at priority 999. A plugin that sets a line's price does so at an earlier priority, and the discount is taken from its price.
- **Coupons.** On `woocommerce_coupon_is_valid_for_product`, a part's line answers with its kit's: see `fixed_coupons` in `docs/SETTINGS.md`.
- **The count.** `woocommerce_cart_contents_count` leaves the parts' lines out, so a build counts as its kit's line does.

| Code | Since | Status | When |
| --- | --- | --- | --- |
| `cbuild_part_locked` | 0.5.0 | 400 | A part's line's quantity was set alone. The classic cart's Update cart says the same in a notice. |
| `cbuild_build_incomplete` | 0.5.0 | A notice | A build in the cart had lost its kit's line or one of its parts, and the rest of it was removed. |

A kit's line raised past what the shop has of a part is refused as `cbuild_build_refused`, with a `part_short` problem for each such part.

### A build shown in the carts

**The Store API.** Each cart item carries the plugin's data under `extensions`, in the namespace `creative-skateboard-builder`, registered with `woocommerce_store_api_register_endpoint_data()` on the cart item's schema. Every field is a string, and empty where it does not apply.

| Field | Since | What it holds |
| --- | --- | --- |
| `role` | 0.5.0 | `kit`, `part`, or empty for a line of no build. |
| `build` | 0.5.0 | The build's identifier, on the kit's line and each part's. |
| `parent` | 0.5.0 | On a part, its kit's cart item key. |
| `slot` | 0.5.0 | On a part, the key of the slot it fills. |
| `name` | 0.5.0 | On a part, its name with its slot's label, as the shop's language writes it. |

**The parent key.** Where WooCommerce's Store API gives a cart item a `parent_item_key` (11.3.0 and later), a part's is its kit's cart item key, answered on `woocommerce_store_api_cart_item_parent_item_key`. The kit's line and a line of no build are left as WooCommerce or another plugin answered. WooCommerce counts a product's quantity in the cart from lines with no parent, so a part in a build is not counted as that product bought alone.

**Item data.** A part's line has one more entry in `woocommerce_get_item_data`, and so in the Store API's `item_data`: Part of, with the kit's name and the slot's label. Its `raw_key` is `cbuild_slot`, and its `display` wraps the words in `<span class="cbuild-part">`. A theme or a cart drawer that prints a line's item data can group a build's parts by that class, as the Mini-Cart's styles do.

**Classes.** In the Cart and Checkout blocks and a classic cart or checkout, a kit's row has the class `cbuild-kit` and a part's `cbuild-part`. In a classic cart a part's quantity is a number in `<span class="cbuild-quantity">`.

**What the plugin loads.** A few style rules in the page, under the handle `cbuild-cart`, with the Mini-Cart, the Cart or Checkout block, or a classic cart or checkout page. With the Cart or Checkout block, also the script `cbuild-cart`, which answers `cartItemClass`, `itemName` and `showRemoveItemLink` in the namespace above and needs `wc-blocks-checkout`. Both only where the cart holds a build as the page is drawn.

### Shipping, express pay and delivery

| Filter | Since | Arguments | What it decides |
| --- | --- | --- | --- |
| `cbuild_build_in_one_package` | 0.5.0 | `bool $together`, `int $kit`, `bool $assembly` | Whether a build's parts are put back in one shipping package where the shop's code has split the cart into several. By default `$assembly`: a build the shop assembles ships as one board. Asked only where `woocommerce_cart_shipping_packages` holds more than one package, at priority 100. |
| `cbuild_arrival_estimate` | 0.5.0 | `string $estimate`, `array $context` | When a build arrives, in words for the line under the builder's total. The plugin has no answer of its own: `$estimate` is empty, and an empty answer shows nothing. |
| `cbuild_delivery_line` | 0.5.0 | `string $line`, `array $context` | What a build's shipping costs, or how far it is from free shipping, in words for the same line. With an empty answer the builder words it from WooCommerce's shipping zones (from 0.6.0). |

`$context` is the same for both: `kit` (the kit product's ID), `assembly` (bool), `lead_time` (the kit's lead time in the owner's words, after `cbuild_kit_text`, and empty for a build that is not assembled), `total` (the build's total in whole minor units) and `decimals` (how many decimals that is). An answer is shown as plain text: tags are stripped. The builder's summary shows both from 0.6.0.

The kit is virtual, so it is in no shipping package, and each part ships as the product it is.

**Express pay.** The plugin answers four filters of two payment plugins, so that neither offers Apple Pay or Google Pay on a kit's page: `wc_stripe_hide_payment_request_on_product_page` (true for a kit) and `wc_stripe_payment_request_supported_types` of WooCommerce Stripe Gateway, and `wcpay_payment_request_is_product_supported` (false for a kit) and `wcpay_payment_request_supported_types` of WooPayments. A kit that reaches the cart with no build is refused whichever way it comes.

### Order item keys

Both checkouts write them, on `woocommerce_checkout_create_order_line_item`. They are kept on uninstall, with the shop's orders.

| Key | Since | On | What it holds |
| --- | --- | --- | --- |
| `_cbuild_id` | 0.5.0 | Every item of a build | The build's identifier, the `id` of the cart's `cbuild`. Two builds on one order have two. |
| `_cbuild_role` | 0.5.0 | Every item of a build | `kit` or `part`. |
| `_cbuild_parent` | 0.5.0 | A part's item | Its kit's order item ID. |
| `_cbuild_children` | 0.5.0 | The kit's item | Its parts' order item IDs, in step order. |
| `_cbuild_slot` | 0.5.0 | A part's item | The key of the slot it fills. |
| `_cbuild_snapshot` | 0.5.0 | The kit's item | The build as sold, the document `schema/order-snapshot.json` describes, with a version of its own that moves only when this document does: `schema_version` (1), `kit`, `assembly` and `picks`, slot key to `label` (the slot's, as the shopper read it), `name` (the part's), `sku` (the part's SKU when it was sold, empty where it had none), `part` (its product ID), `quantity` (how many one build takes) and `specs` (spec key to value, as the index held them). Then `lead_time` and `returns_notice`, the kit's own as the shopper read them, each empty for a build the shop does not assemble. |

The kit's item also carries two lines without an underscore, which WooCommerce shows wherever it shows an item's meta: Assembly (Yes or No) and Build (each slot's label, part and quantity). Their names and values are written in the shop's language as the order is placed. They are for people to read. Code reads `_cbuild_snapshot`, and never those two: their keys are translated words, so they differ from one shop's language to the next and on one shop when its language changes.

`_cbuild_id` is what ties a build: every item with the same one is the same build, and it survives anything that gives the items new IDs. `_cbuild_parent` and `_cbuild_children` are worked out from it and hold order item IDs, so code that copies or remakes an order's items (a renewal, a duplicate, a refund's lines) ties them again from `_cbuild_id` and `_cbuild_role` and does not carry the two over.

`_cbuild_parent` and `_cbuild_children` are written once the order's items are saved: on `woocommerce_checkout_order_created` for the classic checkout, and on `woocommerce_store_api_checkout_update_order_meta` and `woocommerce_store_api_checkout_order_processed` for the Store API's. Read a build by them after those, not in `woocommerce_checkout_create_order_line_item`.

The plugin's keys are added to `woocommerce_hidden_order_itemmeta`, so the order screen neither lists them nor offers them for editing. On `woocommerce_order_item_name` a part's name is given as its slot's label and its name.

**A kit added by hand.** The order screen's Add products refuses a kit, on `woocommerce_ajax_add_order_item_validation`, with the error code `cbuild_kit_by_hand` and a sentence that says to add its parts instead: a kit's line with no build behind it would have none of the keys above. Code that makes an order's items itself (`WC_Order::add_product()`) is not stopped, so anything that reads a build treats a kit's item with no `_cbuild_id` as a line of no build.

## Milestone 6: the builder (0.6.0)

### The build link

A build is the query parameters of the page its builder is on, all under `cbuild`. A link made by any 1.x release opens in every later one.

| Parameter | Since | What it holds |
| --- | --- | --- |
| `cbuild[<slot key>]` | 0.6.0 | The part picked for the slot: a product's or a variation's ID, as digits. `0` leaves a slot the kit does not require empty by choice, so that it does not start on a part. |
| `cbuild[step]` | 0.6.0 | The step shown: a slot's key, or `review`. |
| `cbuild[assembly]` | 0.6.0 | `1` for a build the shop assembles, `0` for one it does not. Left out, the kit's own setting decides. |
| `cbuild[preset]` | 0.6.0 | Which of the kit's presets the build began as, counted from 0. |

The plugin writes a link in one order: the kit's slots in step order, then the step, assembly and the preset. It reads them in any order. An ID is checked each time a link is opened: that it is a part, that it may fill its slot, that it is in stock and that the rules accept it. A link carries nothing else, and never a price.

No slot is keyed `step`, `assembly`, `preset` or `review`; a slots document with one is refused as `document_bad_key`.

Keys that start with an underscore are the builder's own forms' and are not part of a shared link. They may change in any release: `_was` (the picks before a change, slot key to part ID), `_brand` and `_size` (the step's filters), `_shown` (how many options the step lists), `_on` (the step whose form was sent: its filters and Show more count only on it) and `_replace` (the build in the cart an add takes the place of). A slot's key starts with a letter, so none can be one.

### What the state route's answer gains

`schema/state.json` describes each. None of 0.4.0's fields changed or went.

| Field | Since | What it holds |
| --- | --- | --- |
| `step` | 0.6.0 | May now be `review`, the step after the last slot's. It lists no options. |
| `options[].size`, `hidden[].size` | 0.6.0 | The part's size as its slot's parts are sized, as `value` (`8.25in`, `54mm`) and `text`, or null. A deck is sized by its width, trucks by their axle, wheels by their diameter, risers by their thickness and bolts by their length. A slot with a key of the owner's own has no size. |
| `options[].delta`, `hidden[].delta` | 0.6.0 | What taking the part does to the total, against the part the step counts now: `kind` (`more`, `less`, `free`, `included` or `none`), `amount` in minor units, and `text` as the card says it (`+$12.00`, `−$5.00`, `Free`, `Included`). It is what the build charges, the build discount taken off, for as many as the build takes. |
| `filters` | 0.6.0 | `brands` and `sizes`: each value the current step's offered parts have, with its `label`, how many parts have it (`count`) and whether the list is filtered by it (`selected`). A list with fewer than two values is empty. |
| `matching`, `more` | 0.6.0 | How many of the step's offered parts the filters let through, and how many to ask for to list the next 24, or null when all are listed. |
| `picture` | 0.6.0 | `items`, one for each part the total counts (`slot`, `part`, `label` such as `Wheels: Retna, 54 mm`, `size` such as `54 mm` or empty, `name`, `image`), and `description`, the build in one sentence. |
| `delivery` | 0.6.0 | `arrival` and `line`: what `cbuild_arrival_estimate` and `cbuild_delivery_line` answer, in plain text, each empty where nothing is said. |
| `notes` | 0.6.0 | What `cbuild_summary_notes` answers: six lines at most, in plain text. |
| `view` | 0.6.0 | Only in an answer to `cbuild`: what the builder's template draws, as the page's first render has it. It is the builder's own and no part of this API: it may change in any release. |

The published schema no longer closes its objects (`additionalProperties`), so a reader that checks an answer against one release's schema accepts the fields a later release adds. The plugin's own test still fails on any field the schema does not describe.

### The Builder block

| Block | Since | What it is |
| --- | --- | --- |
| `creative-skateboard-builder/builder` | 0.6.0 | The builder for one kit, on any page or post. Its one attribute, `kit` (integer, default `0`), is the kit product's ID. A kit that is a draft, private or password-protected, a product that is no kit, and `0` render nothing. The block can be used once in a post (`supports.multiple: false`), and a page shows one builder: the first one rendered, the kit's own page included. |

The kit's own product page shows the same builder where WooCommerce asks a product type for its add-to-cart area (`woocommerce_cbuild_kit_add_to_cart`), in a classic theme and in the Add to Cart form block. Where `cbuild_builder_url` names another page, the kit's page sends a visitor there instead, with the build in their address, by a 302. Both are rendered on the server, and the template's markup and the store's state are the builder's own: they may change in any release, and are no part of this API. With JavaScript, the store asks the state route for each move with `cbuild` and `page`, and shows the answer's `view` without loading the page. The store, `creative-skateboard-builder`, is private and locked: no other script can read or change it (D-148).

A page that holds a builder and carries a `cbuild` query, whatever it holds, says `noindex, follow` through `wp_robots`, and through `wpseo_robots_array` where Yoast SEO prints its own robots tag. Its canonical through `get_canonical_url` is the page without `cbuild`, so a canonical another filter wrote with the build in it is made clean. Yoast SEO prints no canonical on a noindex page, and its `og:url` is the clean page. An SEO plugin that reads neither filter is asked to do the same through its own (D-147).

### Filters

| Filter | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_builder_url` | 0.6.0 | `string $url`, `int $kit` | Where a kit's builder is: the address of the page its Builder block is on, with no build in it. `$url` starts as the kit's own page. An address on another host, or an answer that is no address, is ignored. A deck's "Make it a complete" link is this address with `cbuild[<deck slot key>]=<deck ID>` added (The build link, above): it opens on the step after the deck, with the deck picked. |
| `cbuild_part_classes` | 0.6.0 | `array $classes`, `int $part`, `string $slot`, `int $kit` | Classes to add to a part's card in the builder, such as `is-staff-pick`. `$classes` starts empty. Each answer is made safe for a class attribute and put after the builder's own, which are kept whatever the answer; an answer that is no list adds nothing. `$part` is a product's or a variation's ID. |
| `cbuild_summary_notes` | 0.6.0 | `array $notes`, `array $context` | Lines under a build's total in the builder: the points a build earns, or a word to a signed-in dealer. `$notes` starts empty. `$context` holds `kit` (the kit product's ID), `parts` (the parts the total counts, slot key to part ID), `assembly`, and `total` in whole minor units with its `decimals`. Answer with a list of sentences in the request's language. Each is shown as plain text, the first six are kept, and anything that is no text is dropped. The answer is asked for on every pick and is never cached, so it has to be quick. Name no person in a line that a shared screen could show. |

### Adding a build: `replace`

From 0.6.0, `cbuild` on either route of Adding a build (0.5.0, above) takes `replace`: the `id` of a build in the shopper's cart, as its kit's line holds it under `cbuild`. As JSON, `"cbuild": { "picks": { … }, "replace": "<id>" }`; as a field, `cbuild[replace]=<id>`. The new build is judged and added as any other is; only once all of it is in the cart is the build `replace` names taken out, its kit's line and its parts'. A build the cart refuses leaves the one before it as it was. A `replace` that names no build in this cart, or is no identifier, takes nothing out. The builder's review sends it when Edit build opened it (D-157).

### Edit build on a kit's line

Each kit's line in the cart has one item data entry from 0.6.0, `raw_key` `cbuild_edit`, keyed "Build", whose `display` is a link with the class `cbuild-edit`. The link is the kit's builder, where `cbuild_builder_url` says it is, at the review, with the build's parts and assembly and `cbuild[_replace]`, the build's `id`. The Cart and Checkout blocks, the Mini-Cart and a classic cart all print it.

### Styling the builder

The builder's stylesheet is the Builder block's `style` (`build/builder/style-index.css`, handle `creative-skateboard-builder-builder-style`), loaded wherever a builder renders, on a kit's own page as in the block. Its rules sit inside `:where()`, so a theme overrides any of them with a single class; only `.cbuild [hidden]` and `.cbuild .screen-reader-text` are outside it. Its classes are BEM under the `cbuild-` prefix, on the builder's root `.cbuild`, and its templates are not for overriding (docs/STANDARDS.md). A theme restyles it most simply by setting these on `.cbuild` (D-159):

| Property | Since | What it sets | Falls back to |
| --- | --- | --- | --- |
| `--cbuild-text` | 0.6.0 | The builder's text | the text's own colour |
| `--cbuild-muted` | 0.6.0 | Secondary text: help, counts, notes | the text at 72 % |
| `--cbuild-line` | 0.6.0 | Borders and rules | the text at 28 % |
| `--cbuild-surface` | 0.6.0 | A card's and the summary's ground | the text at 5 % |
| `--cbuild-accent` | 0.6.0 | The chosen card, a pressed filter, the primary button | `--wp--preset--color--contrast`, then the text |
| `--cbuild-on-accent` | 0.6.0 | Text on the accent | `--wp--preset--color--base`, then `Canvas` |
| `--cbuild-focus` | 0.6.0 | The focus ring | `--wp--preset--color--contrast`, then the text |
| `--cbuild-wrong` | 0.6.0 | The edge of the error summary and of a part that does not fit; never text | `--wp--preset--color--vivid-red`, then `#b32d2e` |
| `--cbuild-radius` | 0.6.0 | Corners | `0.5rem` |
| `--cbuild-gap` | 0.6.0 | Space between the builder's pieces | `--wp--preset--spacing--40`, then `1.5rem` |
| `--cbuild-gap-small` | 0.6.0 | Space inside them | `--wp--preset--spacing--20`, then `0.5rem` |
| `--cbuild-target` | 0.6.0 | The least height and width of a button | `2.75rem` |
| `--cbuild-control` | 0.6.0 | A radio's size, and the least height of a link that is a control | `1.5rem` |
| `--cbuild-small` | 0.6.0 | Small text | `--wp--preset--font-size--small`, then `0.875rem` |
| `--cbuild-card-width` | 0.6.0 | The narrowest a part's card is before the cards wrap | `13rem` |
| `--cbuild-summary-width` | 0.6.0 | The summary's width beside the steps, from a builder 48rem wide | `19rem` |

### DOM events

The builder sends two events on its own element (`.cbuild`), with JavaScript only. Each bubbles, so a listener on the document hears every builder on the page. They are for a shop's own analytics, under the shop's own consent: the plugin sends nothing anywhere itself, and each carries IDs alone, never a name, a price or anything of the shopper. A listener cannot change what the builder does.

| Event | Since | `detail` | When |
| --- | --- | --- | --- |
| `cbuild:pick` | 0.6.0 | `{ kit: number, slot: string, part: number }`: the kit product's ID, the slot's key, and the part's ID, `0` for a slot left empty by choice. | The shopper picks a part, at once, before the state route answers. A pick the route then refuses is still sent. |
| `cbuild:added` | 0.6.0 | `{ kit: number, picks: { [slot: string]: number }, assembly: boolean }`: the kit's ID, the build's parts by slot, and whether it is assembled. | The Store API has added the build to the cart. An add that falls back to WooCommerce's own handler loads the page, and sends nothing. |

The builder also sends WooCommerce's own `wc-blocks_added_to_cart` on `document.body` after an add, on which the Mini-Cart reads the cart again (D-155). It is WooCommerce's event, not part of this plugin's API.

