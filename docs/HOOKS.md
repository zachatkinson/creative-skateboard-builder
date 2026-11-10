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

Per site, each named with the site's table prefix first (`wp_cbuild_specs` on a default install). The columns are in the spec's Data model and in `Install\Schema`. Read them if you must; write them only through the plugin. Both are dropped on uninstall and when a network site is deleted.

| Table | Since | What it holds |
| --- | --- | --- |
| `cbuild_specs` | 0.1.0 | One row per part and spec: the index the fit engine reads. Empty until milestone 3. |
| `cbuild_builds` | 0.1.0 | Saved builds. Empty until milestone 8. |

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
| `cbuild_spec_table` | 0.3.0 | A maker's size chart: the title is the maker's name, the rows are in `_cbuild_rows`. Only a published one feeds the index. Not public: it has no page, archive, search result, sitemap entry or REST route, and every capability on it is `manage_woocommerce`. Saving, binning, restoring or deleting one queues `cbuild_reindex`. Independent, Thunder and Venture are made by the migration `spec_tables_1`. |

### Settings keys

In the option `cbuild_settings`. [SETTINGS.md](SETTINGS.md) has each key's default and range.

| Key | Since | What it holds |
| --- | --- | --- |
| `attribute_mapping` | 0.3.0 | Which attribute feeds which spec, and the unit its values are in. |
| `source_order` | 0.3.0 | Which source wins where two disagree. |

### Options

| Option | Since | What it holds |
| --- | --- | --- |
| `cbuild_settings` | 0.3.0 | The shop's settings, one array, with the keys above. |
| `cbuild_reindex_state` | 0.3.0 | Where the reindex is: its phase (`rows`, `catalogue`, `done` or `failed`), its place in that phase, how many parts it has indexed, when it started and finished in UTC seconds, whether it must pass once more, its failures in a row with the last error's message, and which request it is, counted from 1. Not autoloaded. Read it, never write it. |

### Scheduled actions

Action Scheduler, in the group `creative-skateboard-builder`. Each can run twice and leave the index as running once would. Pending ones are unscheduled on deactivation, whatever their arguments.

| Action | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_index_part` | 0.3.0 | `part`, a product's or variation's ID; `attempt`, on a retry | Indexes one product's specs off the request that saved it: a variable product's variations, or the product itself. A product that is gone loses its rows. One waits per product at a time. A failure is logged and tried again after 1, 2 and 4 minutes, then recorded as failed. |
| `cbuild_reindex` | 0.3.0 | None | Indexes every part again, 100 at a time from a saved place, and queues itself until done: first the parts the index already holds, so rows of a deleted product go, then the shop's products and variations by ID. Asked for when the settings are saved or a term of a mapped attribute is edited. A product deleted while it runs makes it pass once more. A failed batch keeps its place and is tried again after 1, 2, 4 and 8 minutes; the fifth failure in a row stops it. |

