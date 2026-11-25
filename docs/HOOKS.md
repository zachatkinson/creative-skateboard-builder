# Hooks and the public API

Everything another plugin, a theme or a shop's own code may rely on, added as it appears: filters and actions, constants, options, tables, scheduled actions, the names the plugin writes under, the kit's document schemas and the spec keys. Names carry the `cbuild_` prefix. An item listed in the spec and not here is not part of the API.

`composer api:check` reads the code and fails when an item is missing from this file or from `tests/Fixtures/api-surface.json`. Renaming or removing anything here is a breaking change and needs Zach.

## Contents

- [Stability from 1.0.0](#stability-from-100)
- [Milestone 1: the foundation (0.1.0)](#milestone-1-the-foundation-010)
  - [`cbuild_updates_enabled` (filter)](#cbuild_updates_enabled-filter)
  - [`CBUILD_DISABLE_UPDATES` (constant)](#cbuild_disable_updates-constant)
  - [Constants the plugin defines](#constants-the-plugin-defines)
  - [Options](#options)
  - [Site transients](#site-transients)
  - [Tables](#tables)
  - [Scheduled actions](#scheduled-actions)
  - [Log source](#log-source)
- [Milestone 2: the domain (0.2.0)](#milestone-2-the-domain-020)
  - [Document schemas](#document-schemas)
  - [Spec keys](#spec-keys)
  - [Findings and message codes](#findings-and-message-codes)
  - [Build problems](#build-problems)
  - [Refusal codes](#refusal-codes)
- [Milestone 3: specs (0.3.0)](#milestone-3-specs-030)
  - [Index sources](#index-sources)
  - [Product meta](#product-meta)
  - [Post types](#post-types)
  - [Settings keys](#settings-keys)
  - [Options](#options-1)
  - [Scheduled actions](#scheduled-actions-1)
  - [The specs sheet](#the-specs-sheet)
  - [The Catalogue check](#the-catalogue-check)
  - [WP-CLI commands](#wp-cli-commands)
  - [Abilities](#abilities)
  - [Site Health](#site-health)
  - [REST routes](#rest-routes)
- [Milestone 4: kits (0.4.0)](#milestone-4-kits-040)
  - [Product types](#product-types)
  - [Kit meta](#kit-meta)
  - [The state route](#the-state-route)
  - [Filters](#filters)
  - [Actions the plugin listens for](#actions-the-plugin-listens-for)
  - [Abilities](#abilities-1)
- [Milestone 5: cart and checkout (0.5.0)](#milestone-5-cart-and-checkout-050)
  - [Adding a build](#adding-a-build)
  - [Cart item keys](#cart-item-keys)
  - [Why a build is not added](#why-a-build-is-not-added)
  - [A build in the cart stays whole](#a-build-in-the-cart-stays-whole)
  - [A build shown in the carts](#a-build-shown-in-the-carts)
  - [Shipping, express pay and delivery](#shipping-express-pay-and-delivery)
  - [Order item keys](#order-item-keys)
- [Milestone 6: the builder (0.6.0)](#milestone-6-the-builder-060)
  - [The build link](#the-build-link)
  - [What the state route's answer gains](#what-the-state-routes-answer-gains)
  - [The Builder block](#the-builder-block)
  - [Filters](#filters-1)
  - [Adding a build: `replace`](#adding-a-build-replace)
  - [Edit build on a kit's line](#edit-build-on-a-kits-line)
  - [Styling the builder](#styling-the-builder)
  - [DOM events](#dom-events)
- [Milestone 8: orders and after (0.8.0)](#milestone-8-orders-and-after-080)
  - [The state route and the price ability](#the-state-route-and-the-price-ability)
  - [Saved builds and My builds](#saved-builds-and-my-builds)
  - [Styling the board's top](#styling-the-boards-top)
  - [The workshop: the Build box, the assembly ticket and the orders list](#the-workshop-the-build-box-the-assembly-ticket-and-the-orders-list)
  - [Privacy tools and orders](#privacy-tools-and-orders)

## Stability from 1.0.0

**From 1.0.0 this list is frozen, and Semantic Versioning holds** (docs/AUDIT.md, dimension 10). No minor or patch release removes or renames anything here or changes what it means.

- **REST responses** only gain fields; a removal or a rename is a `v2` namespace, with `v1` kept until the next major release.
- **A hook to retire** is deprecated first, with `apply_filters_deprecated()` or `do_action_deprecated()`, and kept until the next major release and for at least six months.
- **Settings, option and meta keys** are never renamed in place: a migration copies them and the old key stays readable until the next major release.
- **Block attributes** keep working through the block's `deprecated` entries.
- **The builder's own is not API.** What the sections below call the builder's own (its template markup, the store's state, the state route's `view`, the keys of the builder's forms that start with an underscore, and markup said to be the plugin's own) is not part of the API and may change in any release.
- **The snapshot.** `tests/Fixtures/api-surface.json` as committed with 1.0.0 is the snapshot the next releases are held to.

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
| `cbuild_specs` | 0.1.0 | One row per part and spec: the index the fit engine reads, filled from 0.3.0. |
| `cbuild_builds` | 0.1.0 | Saved builds, filled from 0.8.0. |
| `cbuild_parts` | 0.4.0 | What a build needs to know of each part, one row per product or variation and price context: whether it may be sold, its brand, price, stock, a variation's attribute values and its specs; from 1.0.0 also the part's look and card as the builder shows them, kept for an hour (D-289). Derived from the shop: a row is read again when its part moves, so a row is never the place to change a part. To have rows read again, do `cbuild_parts_changed`. |

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
| `schema/kit-presets.json` | 0.2.0 | Up to six presets: a name, a description and a part for each slot it fills; from 1.0.0 also an optional `image`, a rider photo's attachment ID for the preset's tile (D-252). |
| `schema/kit-assembly.json` | 0.2.0 | Whether assembly is on by default, its fee as a decimal string in the shop's currency, the lead time and the returns notice. |
| `schema/kit-picture.json` | 0.7.0 | The picture's settings: the field order, slot keys from the bottom of the stack, each once, 20 at most; and the field wheelbase, where the trucks sit on a deck that gives none, inches as a decimal string from 10 to 20, or empty. A slot the order leaves out is drawn after it in the usual order (deck, grip, trucks, wheels), and a key the kit has no slot for is passed over. Unlike the other four, a kit may have none: it is drawn in the usual order with the wheelbase guessed from the deck's shape (D-192). |
| `schema/kit-settings.json` | 1.2.0 | The kit's settings from 1.2.0 on (D-354): `builder_page`, `start_button`, `width_guide` and `lead_days` (#142), and from 1.3.0 `wheel_guide` (#134), as `_cbuild_settings` below has them. Unlike every other kit document, it lets through a key it does not know (`additionalProperties` true at the top), so a later release adds its settings here and the release before still reads the kit. A kit may have none. |
| `schema/settings.json` | 0.3.0 | Not a kit document: the shape of the option `cbuild_settings` as the plugin writes it. `attribute_mapping` is up to 50 lines, each an `attribute` of 1 to 190 characters, a `spec` that is a spec key and a `unit` of `in`, `mm` or empty; `source_order` is `table`, `field` and `attribute`, each once. From 0.4.0 it also holds the five keys that version adds, listed under Settings keys with their ranges: the discount as text, the coupon's place, the rate as a whole number, the header's name, and a new kit's assembly as `schema/kit-assembly.json` has it. Other keys are allowed, so a later version's settings pass. It has no `schema_version`: a key that is missing or malformed reads as its default. |
| `schema/order-snapshot.json` | 0.5.0 | Not a kit document: the order item meta `_cbuild_snapshot`, a build as it was sold. See Order item keys. From 1.2.0 it closes no object with properties (`additionalProperties`), as the state route's schema does not, so a reader that checks a snapshot against one release's schema accepts the keys a later release adds. |

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
| `bearings_included` | 1.0.0 | Flag | | Whether wheels come with their bearings, so they take no others and fill the kit's Bearings slot (D-246). |
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
| `riser_not_in_stock` | 1.0.0 | R3 | Block | `wheel_diameter`, `limit`, `over`, `riser` | The wheels step hides the wheel: no riser of at least `riser` is in stock (D-245). |
| `riser_no_bolts` | 1.0.0 | R3 | Block | `wheel_diameter`, `limit`, `over`, `riser` | The wheels step hides the wheel: risers that clear it are in stock, and no bolts in stock reach through them (D-245). |
| `riser_no_slot` | 1.0.0 | R3 | Block | `wheel_diameter`, `limit`, `over`, `riser` | The wheels step hides the wheel: it needs a riser and the kit has no Risers slot (D-245). |
| `bolts_too_short` | 0.2.0 | R4 | Block | `bolt_length`, `shortest`, `usual`, `riser_thickness` | The bolts are shorter than 7/8 in plus the riser. |
| `bolts_too_long` | 0.2.0 | R4 | Warn | `bolt_length`, `shortest`, `usual`, `riser_thickness` | The bolts are more than 1/4 in longer than usual and will stand proud. |
| `riser_bolts_short` | 1.0.0 | R4 | Block | `riser_thickness`, `shortest` | The risers step hides the riser: no bolts in stock are `shortest` or longer (D-245). |
| `bore_mismatch` | 0.2.0 | R5 | Block | `bearing_bore`, `axle_diameter` | The bearing's bore is not the axle's diameter. |
| `axle_assumed` | 0.2.0 | R5 | Fits | `axle_diameter` | The truck gives no axle diameter and was counted as 8 mm. |
| `spacers_built_in` | 0.2.0 | R5 | Block | None | The bearings have their spacers built in, so separate spacers are not offered. As a spec key it is the bearing's flag. |
| `bearings_included` | 1.0.0 | R5 | Block | None | The wheels come with their bearings, so a separate bearing is not offered. As a spec key it is the wheels' flag. |
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
| `_cbuild_layer` | 0.7.0 | Products and variations | The part's layer image for the picture: an attachment's ID as text. The Part specs panel stores only a PNG, WebP or AVIF in the Media Library, of a type the server's image library reads, whose four corners are transparent and which holds two pixels to the millimetre of the size the picture draws the part at, across or along (D-190). A variation with none takes its parent's. Uninstall removes it. |
| `_cbuild_not_offered` | 1.2.0 | Products and variations | "Not offered in the builder", from the Part specs panel: `yes` when ticked, and none when not (`@since 1.2.0`, #116, D-351). Ticked, the part is no part of any kit: it is the first value, false, of the part-available filter below. A variable product's box keeps out every variation; a variation's keeps out that variation alone. Uninstall removes it. |
| `_cbuild_rank` | 1.3.0 | Products and variations | The part's rank in its slot, from the Part specs panel's Rank group: a whole number from 0 to 999999999 as digits, higher being better, and none where the shop gives it none (`@since 1.3.0`, #135, D-395). Smart swaps' "better" is the next rank up among the parts that fit; in a slot where no part has one it is the next price up. A variation with none takes its product's. It is the first value of `cbuild_part_rank`. Uninstall removes it. |
| `_cbuild_rows` | 0.3.0 | Spec tables | The table's rows, a list. Each row: `size`, the size's name, up to 40 characters; `specs`, a value per spec key, as text in the key's unit with a point for the decimal; `source`, where the row was read, up to 200 characters; `read_on`, the day it was read as `2026-11-10`, or empty. |
| `_cbuild_seed` | 0.3.0 | Spec tables | On a table the plugin made: `independent`, `thunder` or `venture`. A table the shop made has none. Read it, never write it. |

### Post types

| Post type | Since | What it is |
| --- | --- | --- |
| `cbuild_spec_table` | 0.3.0 | A maker's size chart: the title is the maker's name, the rows are in `_cbuild_rows`. Only a published one feeds the index. Not public: it has no page, archive, search result, sitemap entry or REST route, and every capability on it is `manage_woocommerce`. The shop edits it at WooCommerce > Builder (`admin.php?page=cbuild`); WordPress's own post screens are off for it. Saving, binning, restoring or deleting one queues `cbuild_reindex`. Independent, Thunder and Venture are made by the migration `spec_tables_1`. From 0.7.0 Independent's rows carry each size's `baseplate`, and the migration `spec_tables_2` fills it on a chart made before, only where the chart holds none (D-189). |

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
| `cbuild_endpoints` | 0.8.0 | The account endpoints the rewrite rules were last made with: `my-builds`. When it differs, the plugin makes the rules again once, on `init`, so My builds answers after an update with no step for the shop (D-217). Autoloaded. Read it, never write it. |

### Scheduled actions

Action Scheduler, in the group `creative-skateboard-builder`. Each can run twice and leave the index as running once would. Pending ones are unscheduled on deactivation, whatever their arguments.

| Action | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_index_part` | 0.3.0 | `part`, a product's or variation's ID; `attempt`, on a retry | Indexes one product's specs off the request that saved it: a variable product's variations, or the product itself. A product that is gone loses its rows. One waits per product at a time. A failure is logged and tried again after 1, 2 and 4 minutes, then recorded as failed. |
| `cbuild_reindex` | 0.3.0 | None | Indexes every part again, 100 at a time from a saved place, and queues itself until done: first the parts the index already holds, so rows of a deleted product go, then the shop's products and variations by ID. Asked for when the settings are saved or a term of a mapped attribute is edited. A product deleted while it runs makes it pass once more. A failed batch keeps its place and is tried again after 1, 2, 4 and 8 minutes; the fifth failure in a row stops it. |
| `cbuild_import` | 0.3.0 | None | Writes a confirmed specs import, 100 rows at a time from a saved place, and queues itself until done. Each row is checked again against what the shop holds when the batch runs. A failed batch keeps its place and is tried again after 1, 2 and 4 minutes; the fourth failure in a row stops the import, with the parts before it written. |
| `cbuild_compose_picture` | 0.7.0 | `kit`, the kit's ID; `picks`, the part's ID for each slot filled, in step order | Draws one build's composed pictures: 480 by 480 WebP for the cart, 1200 by 630 WebP for a share card and 600 by 600 JPEG for the emails, each once, in `uploads/creative-skateboard-builder/pictures/`. Asked for by a kit's add to the cart, Edit build's among them. The build is worked out again when it runs, and one that does not pass validation, or where a recommendation would fill a slot the build left, gets none. One waits per build at a time, and while 50 builds wait nothing more is asked for (D-197). |
| `cbuild_prune_pictures` | 0.7.0 | None | Daily, from a day after the first composed picture: deletes the pictures not shown for 30 days, a file left part-written with them, then the oldest of the rest until the folder holds 200 MB at most. Handing out a picture's address marks it shown, at most once a day (D-198). |

### The specs sheet

The CSV file that WooCommerce > Builder > Import and export writes and reads. One row per product or variation; a variable product has a row beside its variations, since it names the chart they take their sizes from. The headings are the same in every language. An export is UTF-8 with a byte-order mark, comma-separated, with a point for the decimal.

| Column | Since | Export | Import |
| --- | --- | --- | --- |
| `id` | 0.3.0 | The product's or variation's ID. | The key, when parts are matched by ID. |
| `sku` | 0.3.0 | Its SKU. | The key, when parts are matched by SKU. Matched by ID, a SKU that is not the product's refuses the row. |
| `name` | 0.3.0 | Its name, for the reader. | Passed over. |
| `spec_table` | 0.3.0 | The name of the spec table it names, empty for none. | A spec table's name. Empty removes it. |
| `spec_size` | 0.3.0 | The size it names. | A size. With a table, it must be one of the table's. Empty removes it. |
| `layer_image` | 1.0.0 | The file name of its own Layer image, as uploaded, empty for none. A variation's parent's is not written. | An attachment's ID, or the file name of an image in the Media Library, held to the Layer image field's rules. A name more than one image has is refused, and the row names their IDs. Empty leaves a product's image as it is, and takes a variation's own away, so it takes its parent's. A change is saved through the product, which queues its index job and the image's copies. The product image and gallery are left as they are (D-287). |
| One per spec key | 0.3.0 | The part's own value, as stored. What it takes from a chart, an attribute or its parent is not written. | A value, read as the Part specs panel reads one: the shop's decimal separator or a point, or a fraction. Empty removes it. |

What an import reads:

- **Size and format.** An import reads a file of 2 MB and 5,000 rows at most, comma- or semicolon-separated, in UTF-8 with or without the mark.
- **Headings.** A heading is matched whatever its case, and a column the sheet does not have is passed over. A column the file leaves out is left as it is.
- **Cells.** A cell that an export neutralised with a leading apostrophe is read without it.
- **Rows.** A row with anything wrong in it is not written at all.

Why a file is not read: `upload_missing`, `upload_failed`, `upload_not_csv`, `file_too_big`, `file_not_utf8`, `file_empty`, `rows_too_many`, `key_column_missing`, `column_twice`, `no_spec_columns` and `import_running`.

Why a row is not written:

- `key_missing`, `key_twice`, `part_missing`, `sku_mismatch`, `table_unknown`, `table_ambiguous`, `table_missing`, `size_missing`, `size_long`,
- and the refusal codes of a value (`measure_malformed`, `measure_zero_denominator`, `measure_negative`, `spec_unknown_choice`, `spec_not_positive`, `spec_wrong_kind`),
- and from 1.0.0 a layer image's: `layer_not_image`, `layer_not_found`, `layer_ambiguous`, `layer_wrong_type`, `layer_unreadable`, `layer_opaque` and `layer_too_small`.

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
| `layer_missing` | 0.7.0 | A kit, a slot and a part | A part of a slot the picture draws (deck, grip, trucks, wheels) has no layer image of its own or its parent's, or its image is no longer in the Media Library, so the picture shows its labelled photo. Listed after every other finding. The screen links to the part's Layer image field. |
| `look_shared` | 1.0.0 | A kit, a slot and a product | The product's parts in the slot share a photo and a layer image, so the builder shows them as one card and asks for what tells them apart after the pick. `values.parts` lists their IDs. Listed after the parts with no layer image, in a list of its own, and not counted as a fix (D-281). |
| `part_not_offered` | 1.2.0 | A part | The shop ticked "Not offered in the builder" on the part, or on a variation's product (`@since 1.2.0`, #116, D-351). `values.from` is `part` or `product`. Listed last, in a list of its own, and not counted as a fix or by Site Health. Parts a plugin's answer to the part-available filter keeps out are not listed. |

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

Three direct tests and one asynchronous test on Tools > Site Health, and a section on its Info tab.

| Test | Since | Good | Recommended | Critical |
| --- | --- | --- | --- | --- |
| `cbuild_index` | 0.3.0 | No reindex has been asked for, the last one finished, or one is under way with its job waiting or running. | A reindex is under way and has no job: it stopped part way. | A reindex stopped after five failures. |
| `cbuild_jobs` | 0.3.0 | No action of the group `creative-skateboard-builder` is marked failed. | One or more is, with links to the failed actions and the Catalogue check. | Never. |
| `cbuild_pictures` | 0.7.0 | GD or Imagick can read PNG and WebP and write WebP and JPEG, the uploads folder can be written, and every drawn part's layer image has its resized copies. | Neither library can, so carts and share cards show the deck's photo; the uploads folder cannot be written; or drawn parts' layer images have no resized copies, counted from what the Media Library stored, with no file opened (D-209). From 1.2.0 it names up to five of those parts (D-372). | Never. |
| `cbuild_network` | 0.8.0 | On a network only: the must-use file `creative-skateboard-builder-network.php` is in `wp-content/mu-plugins` as this release writes it, so deleting a site drops the plugin's tables and deleting an account deletes its saved builds on every site (#8, D-220). | The folder cannot be written, so a site deleted where the plugin is not active for the network keeps its tables, and an account deleted from the network keeps its builds on such a site. The test names the file and the folder. | Never. |
| `cbuild_catalogue` | 0.3.0 | The Catalogue check finds nothing in any part or kit. | It finds something: the result counts the findings and the parts they are in, and links to the check. Background work that failed is left to the first two tests above. From 0.7.0, parts with no layer image are counted apart, by part, in a sentence and a label of their own, since they stay in the builder (D-211). | Never. |

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
| `_cbuild_picture` | 0.7.0 | `schema/kit-picture.json` | The picture: the layer order and the trucks' anchor on a deck with no wheelbase. A kit may have none. |
| `_cbuild_layout` | 1.0.0 | `schema/kit-layout.json` | The builder's layout: the stage's side and the board's size on a desktop, and the steps' search (D-255). From 1.1.0 also `motion` (D-256) and `show_presets`, whether the kit's ready builds are shown (#104, D-315). Its keys are 1.1.0's and stay so: a later setting goes in `_cbuild_settings` (D-354). A kit may have none, and has the defaults. |
| `_cbuild_settings` | 1.2.0 | `schema/kit-settings.json` | The kit's settings from 1.2.0 on, in a document the release before never reads, so a rollback leaves the kit readable (D-354): `start_button`, whether the kit places "Build a complete with this deck" on its parts' pages (#115, D-349); `builder_page`, the ID of the published page its builder is on (#113, D-348), left out for the kit's own page; and `width_guide` (#114, D-350): `shown`, whether "Not sure of your width?" is on the page, and `rows`, the shop's own rows, one to twelve, each with `shoe` (plain text, up to 40 characters), `from` and, optionally, `to` (deck widths in inches, 5 to 12, as decimals); with no `rows` the guide has the plugin's; and `lead_days` (#142, D-375), the assembly time in business days, `"2"` or `"2-4"` from 0 to 60, left out for none. **@since 1.3.0** `wheel_guide` (#134, D-400): `rows`, up to four, each with `style` (`street`, `park`, `bowl` or `cruising`, each once), `from` and, optionally, `to` (wheel sizes in millimetres, 40 to 90, as decimals, `to` never under `from`), the wheel sizes the guided start recommends for that style; a style with no row, or a kit with no `wheel_guide`, has the plugin's: street 50 to 54, park 53 to 56, bowl 55 to 58, cruising 59 to 65. A key it does not know is kept and passed over. A kit may have none, and has the defaults. |

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
| `width` | 1.0.0 | The deck width the `shelf` lists, as its `widths` name one: `8.25in`, `210mm`. Other steps keep it, for the visit. |
| `chips[<group>][]` | 1.0.0 | The chips chosen: four groups at most, each `brand`, the slot's size spec's key or an attribute's taxonomy, with 20 values at most, each a slug or a size as the chips name it. Values in a group widen, and groups narrow each other. |
| `q` | 1.0.0 | A search of the step, 100 characters at most. Matched against each look's brand, name, size and values, with accents and case folded, or ranked by Creative Search where it answers `csearch_ranked_ids` (D-248). The builder keeps it nowhere; where Creative Search ranks it, Creative Search keeps it in its own search log under the source `builder` (PRIVACY.md). |
| `sort` | 1.0.0 | `recommended` (the default), `price-asc`, `price-desc` or `newest`. |
| `from` | 1.0.0 | Where the shelf's page of 30 starts, as the last answer's `shelf.more` said: 0 to 500, the most a step lists. |

Beside `cbuild`, the five shelf arguments change what the link asks of the shelf, and the view's addresses follow.

| Schema | Since | What the document holds |
| --- | --- | --- |
| `schema/state.json` | 0.4.0 | The route's answer (draft 4, with `definitions`): `kit`, `step`, `ready`, the kit's `texts` (lead time and returns notice) and `presets`, the `steps` (each slot's label, its pick, the part it recommends, how many parts it offers and hides, and the hidden ones counted by reason, each with a sentence), the current step's `options` and `hidden` parts (24 of each at most, each with its fit, its messages and its card), the picks that were `refused`, the `problems` that keep the build from the cart, the `changes` and the `total`. From 0.6.0 it also holds the step's `filters`, `matching` and `more`, the `picture`, `delivery` and `notes`, each option's `size` and `delta`, and each preset's `image`, `total`, `ready` and `problems` (Milestone 6, below), and closes no object. `npm run types` writes `assets/src/types/state.d.ts` from it. |

What the answer holds beside the schema's own descriptions:

- **Codes and sentences.** A message carries its rule, its message code and its severity (Findings and message codes, above) with the sentence beside them, and a problem its code (Build problems, above) with its sentence. The sentences are in the language of the answer and are not public API: read the codes.
- **Money** is an `amount` in whole minor units and a `text`, the amount as the shop writes its prices, in plain text.
- **A card** is what the shop shows of a part: its name, its page, the price of one, its availability in the shop's stock display format, and its photo. All of it is plain text for the page to escape.
- **A step lists 24 options,** those the shop can supply first, and always the part the step counts. `steps[].offered` and `steps[].hidden` say how many there are in all.
- **Fields are added** in minor versions, and none is renamed or removed within a major one.

Every answer of the route, a refusal included, carries `Cache-Control: no-store` and `X-Robots-Tag: noindex`.

The `Server-Timing` header, for a shop manager:

- **From 1.1.0,** an answer to a signed-in user who can manage WooCommerce also carries `Server-Timing: cbuild-route;dur=<ms>, cbuild-queries;desc="<n>"`: the route's own time from its callback's start, to a tenth of a millisecond, and its database queries.
- **From 1.2.0** the same header goes on with each phase of the answer that ran, in milliseconds, in the order it first ran: `cbuild-shelf` (the step's shelf), `cbuild-cards` (what each card shows of its part, read in one call) and, on the deck step, `cbuild-boards` (the board each deck would draw alone, D-308), so a manager sees where the route's time goes (`@since 1.2.0`, #125, D-365). From 1.3.0 also `cbuild-swaps` (smart swaps, on the review and on a step with a pick but the deck's; `@since 1.3.0`, #135, D-396), before `cbuild-cards`.
- **No one else is sent it,** and no filter changes that (#90, D-304).
- **Reading it.** The builder's own requests carry no REST nonce, so WordPress answers them as signed out and they never get it, a manager's included; a manager reads it on a request made with one, such as `wp.apiFetch( { path: '/cbuild/v1/kits/<id>/state', parse: false } )` in wp-admin's console, and the answer's `server-timing` header.

Its refusals are REST errors, each with `code`, `message` and `data.status`:

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
| `cbuild_part_available` | 0.4.0 | `bool $available` (true; from 1.2.0 false where the shop keeps the part out), `int $id`, `WC_Product $part` | Whether a product or a variation may be a part of a build. From 1.2.0 (`@since 1.2.0`, #116, D-351) `$available` starts false where "Not offered in the builder" is ticked on the part or a variation's product (`_cbuild_not_offered`), so the filter answers last and may offer it all the same. `false` rules it out: no kit offers it, and one picked all the same is refused as `part_not_in_slot`. Asked only of a part a shopper could be shown and could buy. Anything but `false` leaves the part in. The answer is kept in the part's row of `cbuild_parts` for one to two hours, or until the product, its price, its stock or its terms move: a plugin whose answer changes without one of those does `cbuild_parts_changed`. |
| `cbuild_part_rank` | 1.3.0 | `int\|null $rank` (the part's `_cbuild_rank`, a variation's product's where it has none, or null), `int $id` | The part's rank in its slot for smart swaps' "better", higher being better (`@since 1.3.0`, #135, D-395). Null gives it none; anything but a whole number is taken as none. Where any part of a slot has a rank, a ranked part is better than one without; where none has, the builder goes by price. Asked on the review for every candidate of each picked slot, and on a step for its own slot's, on each request: nothing is kept. Every swap is still checked by the fit engine, so no answer can offer a part that does not fit or is not in stock. |
| `cbuild_price_context` | 0.4.0 | `array $context` | What a part's price depends on beside the part: the currency, its decimals, the language, the tax display, whether prices are entered with tax, and the tax rates that apply. A card is kept per context, and so is each slot's list of parts, so a plugin that shows some shoppers another price adds what tells them apart, such as a role or a price list's ID. Without it, a shopper may be shown the price kept for another. Add nothing that names a person. An answer that is no array is ignored. |
| `cbuild_slot_label` | 0.4.0 | `string $label`, `string $slot`, `int $kit` | A slot's label as a shopper reads it, in place of the one the owner typed. `$slot` is the slot's key. The label answered is also the one a problem's sentence names the slot by. |
| `cbuild_preset_label` | 0.4.0 | `string $name`, `int $position`, `int $kit` | A preset's name. `$position` is where the preset stands among the kit's, from 0. |
| `cbuild_kit_text` | 0.4.0 | `string $text`, `string $field`, `int $kit`, `string $of` | Another of the kit's texts. `$field` is `slot_help` (`$of` is the slot's key), `preset_description` (`$of` is the preset's position), `lead_time` or `returns_notice` (`$of` is empty). From 1.2.0 also `width_guide_shoe`, a shoe size of the kit's own width guide (`$of` is the row's position, from 0; `@since 1.2.0`, #114). |

The last three are for the owner's own words, which no translation file holds: a translation plugin answers them. An answer that is not a string leaves the owner's words as they are. The plugin does not escape or cut what a filter answers; the answer is plain text, and the page that prints it escapes it.

### Actions the plugin listens for

| Action | Since | Arguments | When a plugin does it |
| --- | --- | --- | --- |
| `cbuild_parts_changed` | 0.4.0 | `int[] $ids` (optional) | Something the builder keeps of a part changed and WooCommerce was not told: the plugin's answer to `cbuild_part_available`, or a price it sets with no product saved. `do_action( 'cbuild_parts_changed', array( 12, 34 ) )` and the next request reads those products, and their variations, from the shop again; with no IDs, every part. A product saved, a stock count set, a sale starting or ending and a term edited need no such word: the builder hears those itself. From 1.2.0 the plugin does it too, with the part's ID, when the Part specs panel ticks or unticks "Not offered in the builder" (`@since 1.2.0`, #116). |

### Abilities

In the category `creative-skateboard-builder`, which the Catalogue check's ability made in 0.3.0.

| Ability | Since | Who | Input | Output |
| --- | --- | --- | --- | --- |
| `creative-skateboard-builder/check-build` | 0.4.0 | Whoever may run an ability | `kit`, the kit product's ID; `picks`, an object of slot key to part ID, 20 at most; `assembly`, a boolean. Only `kit` is required. | `kit`, `ready`, `refused` and `problems`, as the state route's answer holds them: whether the build may go to the cart, and each thing in the way with its `code`, `slot`, `part`, `message` and the rules' `messages`. |
| `creative-skateboard-builder/price-build` | 0.4.0 | Whoever may run an ability | The same. | `kit` and `total`, as the state route's answer holds it: `lines` (each with `slot`, `part`, `name`, `quantity`, `price`, `charged` and `total`), `parts`, `saved`, `assembly`, `fee` and `total`. Each amount is `amount`, in whole minor units, and `text`. From 0.8.0 `total` counts the picks alone, and `estimate` is the board with each slot's recommendation in it, or null (D-213). |

Both are annotated read-only and idempotent and shown in REST, so WordPress serves each at `GET /wp-abilities/v1/abilities/<name>/run`.

- **Who.** Each answers to a signed-in user alone, over REST and when run in PHP (D-118), and refuses a `POST` with 405.
- **One answer with the route.** They ask the state route's own service, for the shopper WooCommerce knows the signed-in user as (from 0.5.0, D-138), and give parts of its answer, so the two cannot disagree: from 0.8.0 a slot with no pick is counted empty, as the route counts it (D-213); before, it was counted with the part the builder started it on.
- **Refusals.** A kit a visitor may not see is `cbuild_kit_not_found` to everyone, as it is on the route. Input that is not the schema's is refused by WordPress as `ability_invalid_input`; a slot key the kit lacks is not refused, and comes back in `refused` as `slot_unknown`.
- **The rate limit.** Neither is counted against the route's rate limit.

## Milestone 5: cart and checkout (0.5.0)

### Adding a build

A build is added by the kit's ID and one more parameter, `cbuild` (0.5.0), on either route:

- the Store API's `POST /wc/store/v1/cart/add-item`, beside `id` and `quantity`, as JSON: `"cbuild": { "picks": { "deck": 103, "trucks": 149 }, "assembly": true }`;
- WooCommerce's add-to-cart form, as fields: `cbuild[picks][deck]=103`, `cbuild[assembly]=1`, posted with `add-to-cart=<kit>`.

What is read:

- **`picks`** is slot key to part ID.
- **`assembly`** may be left out, and is then the kit's own default.
- **`quantity`** is how many of the build.
- **Nothing else is read:** no price, no quantity of a part and no line of the cart comes from the request. Each part is read from WooCommerce at that moment, so the cart charges what the shop holds even where the builder was shown an older price.

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
| `removed` | 0.6.0 | On a part, what the Cart block says once when its build is removed, such as "Street complete was removed from your cart.", in place of WooCommerce's notice for each part. |

**The parent key.** Where WooCommerce's Store API gives a cart item a `parent_item_key` (11.3.0 and later), a part's is its kit's cart item key, answered on `woocommerce_store_api_cart_item_parent_item_key`. The kit's line and a line of no build are left as WooCommerce or another plugin answered. WooCommerce counts a product's quantity in the cart from lines with no parent, so a part in a build is not counted as that product bought alone.

**The kit's thumbnail.** From 0.7.0 a kit's line shows its build's composed picture, 480 by 480, once the `cbuild_compose_picture` job has made it, and the deck's product photo until then, both with the kit's name as their alt text.

- **In the Store API** this is the line's `images`, answered on `woocommerce_store_api_cart_item_images`: one image, whose `id` is 0 for the composed picture, which is in no Media Library, and the deck's attachment for its photo.
- **In a classic cart and the mini-cart widget** it is `woocommerce_cart_item_thumbnail`, an `<img>` with the class `cbuild-cart-picture` for the composed picture.
- **A part's line, and a line of no build,** are left as WooCommerce or another plugin answered.

**Item data.** A part's line has one more entry in `woocommerce_get_item_data`, and so in the Store API's `item_data`: Part of, with the kit's name and the slot's label.

- **Its `raw_key`** is `cbuild_slot`, and its `display` wraps the words in `<span class="cbuild-part">`.
- **From 0.6.0 the slot's label,** in brackets, has a `<span class="cbuild-part__slot">` of its own inside it, which the plugin's styles hide on a row whose name already starts with the slot; the Mini-Cart's rows, which run no line filter, keep it.
- **Grouping.** A theme or a cart drawer that prints a line's item data can group a build's parts by that class, as the Mini-Cart's styles do.

**Classes.** A theme or a cart drawer may style these or group a build's lines by them:

| Class | Since | Where |
| --- | --- | --- |
| `cbuild-kit` | 0.5.0 | A kit's row, in the Cart and Checkout blocks and a classic cart, checkout or mini-cart widget. |
| `cbuild-part` | 0.5.0 | A part's row in the same places, and the Part of words in its item data. |
| `cbuild-quantity` | 0.5.0 | In a classic cart, the `<span>` holding a part's quantity, a number with no field. |
| `cbuild-edit` | 0.6.0 | A kit's Edit build link, in its item data. |
| `cbuild-assembly` | 0.6.0 | The `<span>` holding what a kit's line charges for assembly, in its item data. The plugin's styles hide the line's own price and total in the Cart and Checkout blocks and the Mini-Cart by it. |
| `cbuild-part__slot` | 0.6.0 | The slot's label in a part's Part of words. |
| `cbuild-cart-picture` | 0.7.0 | A kit's composed picture, as a classic cart's or the mini-cart widget's thumbnail. |

**Builder classes.** The places a part is shown in the builder, which `cbuild_part_classes` adds to and a theme may style:

| Class | Since | Where |
| --- | --- | --- |
| `cbuild-picture__item` | 0.6.0 | A part's place in the picture: its labelled photo, hidden where the board draws the part. |
| `cbuild-board__layer` | 0.7.0 | A part's layer on the board, in the picture and in the bar, with `cbuild-board__layer--<slot>` beside it. |
| `cbuild-bar__photo` | 0.6.0 | The bar's small picture of the deck's photo, until the board draws a layer. |
| `cbuild-review__item` | 0.6.0 | A part's line in the review, with `cbuild-review__item--wrong` where it no longer fits. |
| `cbuild-review__photo` | 1.3.0 | A part's thumbnail on its line in the review, the picture its card shows; hidden, its place kept, on a line with none (#132, D-403). |
| `cbuild-review__photo--drawn` | 1.3.0 | The deck's thumbnail in the review when it is the deck's layer image rather than its photo, as `cbuild-card__photo--drawn` marks its card (D-398), so a plugin that flags only the layer image can blur it alone. |
| `cbuild-preset__photo` | 1.1.0 | A ready build's tile picture, with the classes the answer gives each of the build's parts, merged and each once: a deck flagged on its card is flagged on the tile that shows it (Cowork's review of 1.0.0, F19; D-309). |
| `cbuild-chips__more` | 1.1.0 | The brand chips' "N more" button on a desktop, a disclosure for the chips past the row. Its rule sits at a class's weight, so a theme's `button` rule can't change its display (#98, D-314). |
| `cbuild-chips__more--idle` | 1.1.0 | "N more" with nothing to open: out of sight and of the flow, its width kept for measuring. |
| `cbuild-chips__chip--past` | 1.1.0 | A brand chip past the row: out of sight, the flow, the tab order and the accessibility tree, its width kept for measuring. |
| `cbuild-chips__options--row` | 1.1.0 | The brand chips' row, where its group has "N more". With the script, the row shows its first line alone until the store has folded it, so the fold moves nothing (D-314), and from 1.3.0 again after an answer that changes its chips, until the store has laid it out (D-405). |
| `cbuild-cards--compact` | 1.1.0 | The deck step's card lists, the sold-out fold's included: compact cards, the deck small and upright, two to a row at most (#99, D-322). |
| `cbuild-card__photo--drawn` | 1.1.0 | A card's picture when it is the deck's layer image rather than its photo (#99). |
| `cbuild-card` | 1.2.0 | A part's card in a step, the `<li>` the filter above adds the part's classes to, with `cbuild-card--chosen` on the one the build counts and `cbuild-card--sold-out` on one the shop cannot supply (#143, D-376). |
| `cbuild-card__control` | 1.2.0 | The card's own radio, which picks the part the card shows. Where that part comes in sizes, the card picked has a radio for each size after it, and its own radio stands aside (D-279, #143). |
| `cbuild-picture` | 1.2.0 | The picture's root, the `<figure>` on the stage that holds the board, the parts' photos and the sentence that says the build (#143). |
| `cbuild__stage` | 1.2.0 | The stage, around the picture, which stays in view as the panel scrolls (#143). |
| `cbuild-step` | 1.2.0 | A step's form, around its fields and cards. The same element on every step, hidden on the change panel and the review (#143). |
| `cbuild-step__above-cards` | 1.3.0 | An empty `<div>` in the step's form, just above its card list, which the builder renders for another plugin's own element and never draws into. Out of the layout while empty; hidden, with the cards, while the deck step asks its width (#151, D-407). |

**Places another plugin may add its own element.** From 1.2.0, the stage (`.cbuild__stage`) and the step's form (`.cbuild-step`), such as a button over the board or a notice above the step (#143, D-376). From 1.3.0 (`@since 1.3.0`), also the place just above the step's cards (`.cbuild-step__above-cards`), where a notice about the cards comes in with them (#151, D-407):

- **It stays, in its place.** An element another plugin adds once the builder is ready, as the last child of `.cbuild__stage`, as the first child of `.cbuild-step` or as a child of `.cbuild-step__above-cards`, stays there, the same element in the same place, through every redraw the browser test runs it through: a width, a pick, the change panel, Use these, Next, a search, a fold opened and a turn of the board. The builder never removes a child it did not render.
- **Above the cards.** `.cbuild-step__above-cards` is the builder's own element, rendered empty by the server on every step, with or without the script, and the builder never draws into it. It sits in the step's list of cards, so it shows when the cards do: while the deck step asks its width, it is hidden with them, and a notice put there comes in with the decks the width brings rather than above the question. Where a step lists nothing, it sits under the words that say why, where the cards would be. A notice there moves less as a width's answer comes than one first in the form: Cowork measured 0.067 of layout shift against 0.155 on a phone.
- **Other places are not promised.** Elsewhere among the builder's own children, a list the builder draws in the step's form may grow next to it, so its place may change.
- **It is hidden with the form.** The step's form is hidden on the change panel and the review, and so is anything in it, `.cbuild-step__above-cards` too.
- **When to look again.** Listen for `cbuild:rendered` (below) rather than watching the builder's attributes, which are the builder's own.

**What the plugin loads.** A few style rules in the page, under the handle `cbuild-cart`, with the Mini-Cart, the Cart or Checkout block, or a classic cart or checkout page. With the Cart or Checkout block, also the script `cbuild-cart`, which answers `cartItemClass`, `itemName` and `showRemoveItemLink` in the namespace above and needs `wc-blocks-checkout`. Both only where the cart holds a build as the page is drawn.

### Shipping, express pay and delivery

| Filter | Since | Arguments | What it decides |
| --- | --- | --- | --- |
| `cbuild_build_in_one_package` | 0.5.0 | `bool $together`, `int $kit`, `bool $assembly` | Whether a build's parts are put back in one shipping package where the shop's code has split the cart into several. By default `$assembly`: a build the shop assembles ships as one board. Asked only where `woocommerce_cart_shipping_packages` holds more than one package, at priority 100. |
| `cbuild_arrival_estimate` | 0.5.0 | `string $estimate`, `array $context` | When a build arrives, in words for the line under the builder's total. The plugin has no answer of its own: `$estimate` is empty, and an empty answer shows nothing. |
| `cbuild_delivery_line` | 0.5.0 | `string $line`, `array $context` | What a build's shipping costs, or how far it is from free shipping, in words for the same line. With an empty answer the builder words it from WooCommerce's shipping zones (from 0.6.0): how far the build is from the zone's free shipping, that it ships free, or the zone's flat rate, for the address WooCommerce holds for the shopper, and nothing where it holds none (D-166). |

`$context` is the same for both: `kit` (the kit product's ID), `assembly` (bool), `lead_time` (the kit's lead time in the owner's words, after `cbuild_kit_text`, and empty for a build that is not assembled), `total` (the build's total in whole minor units) and `decimals` (how many decimals that is). An answer is shown as plain text: tags are stripped. The builder's summary shows both from 0.6.0.

From 1.2.0 `$context` also has three keys, after those (`@since 1.2.0`, #142, D-375). No earlier key changes.

| Key | Since | What it holds |
| --- | --- | --- |
| `lead_days` | 1.2.0 | The kit's assembly time in business days, `"2"` or `"2-4"` (0 to 60), as the Build tab keeps it in `_cbuild_settings`. Empty for a build that is not assembled, or a kit with none. |
| `parts` | 1.2.0 | The parts the total counts, slot key to part ID (a product's or a variation's), in step order, as the context of the lines other plugins put under the total has them. A slot left empty is not in it. |
| `quantities` | 1.2.0 | How many of each part one build takes, by the same slot keys, as the cart's `cbuild` holds them: 2 for trucks sold singly, say. A slot left empty is not in it. |

A plugin that dates a delivery can count `lead_days` from the day the order is placed, and give no date while a part in `parts` is on backorder or pre-book.

The kit is virtual, so it is in no shipping package, and each part ships as the product it is.

**Express pay.** The plugin answers four filters of two payment plugins, so that neither offers Apple Pay or Google Pay on a kit's page:

- **WooCommerce Stripe Gateway:** `wc_stripe_hide_payment_request_on_product_page` (true for a kit) and `wc_stripe_payment_request_supported_types`;
- **WooPayments:** `wcpay_payment_request_is_product_supported` (false for a kit) and `wcpay_payment_request_supported_types`.

A kit that reaches the cart with no build is refused whichever way it comes.

### Order item keys

Both checkouts write them, on `woocommerce_checkout_create_order_line_item`. They are kept on uninstall, with the shop's orders.

| Key | Since | On | What it holds |
| --- | --- | --- | --- |
| `_cbuild_id` | 0.5.0 | Every item of a build | The build's identifier, the `id` of the cart's `cbuild`. Two builds on one order have two. |
| `_cbuild_role` | 0.5.0 | Every item of a build | `kit` or `part`. |
| `_cbuild_parent` | 0.5.0 | A part's item | Its kit's order item ID. |
| `_cbuild_children` | 0.5.0 | The kit's item | Its parts' order item IDs, in step order. |
| `_cbuild_slot` | 0.5.0 | A part's item | The key of the slot it fills. |
| `_cbuild_snapshot` | 0.5.0 | The kit's item | The build as sold, the document `schema/order-snapshot.json` describes, with a version of its own that moves only when this document does: `schema_version` (1), `kit`, `assembly` and `picks`, slot key to `label` (the slot's, as the shopper read it), `name` (the part's), `sku` (the part's SKU when it was sold, empty where it had none), `part` (its product ID), `quantity` (how many one build takes) and `specs` (spec key to value, as the index held them). Then `lead_time` and `returns_notice`, the kit's own as the shopper read them, each empty for a build the shop does not assemble. From 1.2.0 also `lead_days` (`@since 1.2.0`, #142, D-375), the kit's assembly time in business days when it was sold, `"2"` or `"2-4"`, empty for a build the shop does not assemble or a kit with none. It is a key added after the others, so `schema_version` stays 1, and a snapshot made before 1.2.0 has none. 1.1.0's published schema closed the snapshot, so a plugin checking a 1.2.0 snapshot against it refuses it; check it against 1.2.0's, which lets a later key through. The plugin's own tests close each object again, so a key the schema does not describe still fails them. |

The kit's item also carries two lines without an underscore, which WooCommerce shows wherever it shows an item's meta: Assembly (Yes or No) and Build (each slot's label, part and quantity). Their names and values are written in the shop's language as the order is placed.

They are for people to read. Code reads `_cbuild_snapshot`, and never those two: their keys are translated words, so they differ from one shop's language to the next and on one shop when its language changes.

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

No slot is keyed `step`, `assembly`, `preset` or `review`; a slots document with one is refused as `document_bad_key`. A kit saved by 0.4.0 or 0.5.0 with one has it renamed `<key>_slot` on upgrade (D-169).

Keys that start with an underscore are the builder's own forms' and are not part of a shared link. They may change in any release:

- `_was` (the picks before a change, slot key to part ID), `_brand` and `_size` (the step's filters), `_shown` (how many options the step lists), `_on` (the step whose form was sent: its filters and Show more count only on it) and `_replace` (the build in the cart an add takes the place of);
- from 1.0.0, `_width`, `_chips`, `_q` and `_sort` (the step's shelf, as the state route's arguments of those names), and `_saved` (the saved build My builds opened, checked against the shop once, D-278).
- **@since 1.3.0** `_guide` (#134, D-402), the guided start's answers, each optional: `_guide[shoe]`, the row of the kit's width guide, counted from 0; `_guide[style]`, `street`, `park`, `bowl` or `cruising`; `_guide[budget]`, a whole amount of the shop's currency, 1 to 999999; and `_guide[asked]`, `1` once "Help me choose" is sent. They change no other parameter, are kept only in the address of the builder's start, and are stored nowhere. An answer that is not one is left out.
- from 1.3.0, `_undo` (the picks before a smart swap, slot key to part ID, which the swap's Undo puts back; on a swap's own link alone, D-397).

A slot's key starts with a letter, so none can be one.

### What the state route's answer gains

`schema/state.json` describes each. None of 0.4.0's fields changed or went.

| Field | Since | What it holds |
| --- | --- | --- |
| `step` | 0.6.0 | May now be `review`, the step after the last slot's. It lists no options. |
| `options[].size`, `hidden[].size` | 0.6.0 | The part's size as its slot's parts are sized, as `value` (`8.25in`, `54mm`) and `text`, or null. A deck is sized by its width, trucks by their axle, wheels by their diameter, risers by their thickness and bolts by their length. A slot with a key of the owner's own has no size. |
| `options[].delta`, `hidden[].delta` | 0.6.0 | What taking the part does to the total, against the part the step counts now: `kind` (`more`, `less`, `free`, `included` or `none`), `amount` in minor units, and `text` as the card says it (`+$12.00`, `−$5.00`, `Same price`, `Included`; the kind `free` keeps its name). It is what the build charges, the build discount taken off, for as many as the build takes. |
| `steps[].against` | 0.6.0 | The picks before the step that its parts were checked against, by slot key (`["trucks"]` on the wheels step), or null where no rule the kit has on reads the slot. The cards' fit label names them. |
| `filters` | 0.6.0 | `brands` and `sizes`: each value the current step's offered parts have, with its `label`, how many parts have it (`count`) and whether the list is filtered by it (`selected`). A list with fewer than two values is empty. |
| `matching`, `more` | 0.6.0 | How many of the step's offered parts the filters let through, and how many to ask for to list the next 24, or null when all are listed. |
| `picture` | 0.6.0 | `items`, one for each part the total counts (`slot`, `part`, `label` such as `Wheels: Retna, 54 mm`, `size` such as `54 mm` or empty, `name`, `image`), and `description`, the build in one sentence. |
| `picture` | 0.7.0 | Gains `width` and `height`, the board in ten-thousandths of a millimetre or 0 before a deck with a width is picked, and `layers`, the board from the bottom of the stack (the deck, the grip, one for each truck and one for each wheel, each a part with a layer image): each layer's `slot`, `part`, its left edge `x` and top edge `y` from the board's with the nose at the top, its `width` and `height`, and its `image` (`src`, the layer image's own file, its pixels, and `sources`, a `type` and `srcset` for each of AVIF and WebP whose renditions are made). Each of `items` gains `drawn`: whether the board draws the part, which the builder then shows no labelled photo for. Nothing is taken away (D-195). |
| `delivery` | 0.6.0 | `arrival` and `line`: what `cbuild_arrival_estimate` and `cbuild_delivery_line` answer, in plain text, each empty where nothing is said. |
| `notes` | 0.6.0 | What `cbuild_summary_notes` answers: six lines at most, in plain text. |
| `view` | 0.6.0 | Only in an answer to `cbuild`: what the builder's template draws, as the page's first render has it. It is the builder's own and no part of this API: it may change in any release. |
| `shelf` | 1.0.0 | The current step as the configurator lists it, or null on the review. `looks`: a page of cards in stock, one a product or the variations of one that share a photo (`key`, `product`, the `part` the card shows as an option, its `sizes` on the slot's size and the `choices` you can't see on the photo; from 1.0.0 also the product's `name`, the `brand`'s name and the `look`, the values that tell it from the product's other looks (D-273); and on the look the step's pick is of, where nothing in the build decides its size, `sized`: each size as the part of it (`part`, `value`, `text`, `in_stock`, `picked`), for the radio group after the picked card (D-279)); `sold_out`, the matching looks sold out; `chips`, four groups at most (`name`, `label`, and each chip's `value`, `label`, `count` and `selected`); `widths`, on the deck step, each width the kit's decks come in with how many looks are `in_stock` in it; `offered`, `matching` and `more`; and the `query` and `sort` as read; where a search lists nothing, `elsewhere`, on the deck step the widths its matches are in stock in (`value`, `text`), and `hidden_matching`, how many parts that don't fit match it. `options`, `filters`, `matching` and `more` stay as they were (D-264). From 1.1.0 the brand group's chips come with the most looks first, then in natural order; other groups keep the natural order (#98). |
| `options[].messages[].short` and every other finding's | 1.0.0 | The finding as a card's short label where it has one, otherwise empty: `wheel_needs_riser` says the riser its wheels need, "Needs ¼″ risers with your trucks" (spec: Risers, before the pick). |
| `options[].card.photo`, `hidden[].card.photo`, `picture.items[].photo` | 1.0.0 | Whether the card's or the item's `image` is the part's photo. Without a photo it is the part's layer image, uncropped, and `photo` is false (D-282). |
| `picture.items[].large` | 1.1.0 | The part's photo uncropped, at WooCommerce's single-product size (`src`, `width`, `height`, `srcset`, `sizes`, as `image`), or null where the part has no photo. The builder shows the deck's in the board's place where the deck has no layer image (#82, D-303). Options' and hidden parts' cards do not carry it. |
| `presets[].at` | 1.1.0 | Each ready build's place among the kit's presets, counted from 0: the value `cbuild[preset]` takes to open it. `presets` lists every one of the kit's ready builds in order, so it is also the entry's own place in the list (D-336). |
| `presets[].shown` | 1.1.0 | Whether the builder shows the ready build's tile: false where the kit's Show ready builds is off, or a part that's gone, sold out or no longer fits keeps it from the cart (#104, D-315, D-336). A preset not shown keeps its place in `presets`, and its `image` is its first part's photo. |
| `options[].presets`, `hidden[].presets`, `shelf.looks[].part.presets` | 1.3.0 | The ready builds offered to the shopper that pick the part, by their places among the kit's (`presets[].at`), in the kit's order, or empty: none does, the kit's Show ready builds is off, or the ready build is not `shown` (`@since 1.3.0`, #137, D-392). The builder's card says "Picked for Street and Cruiser" from it. |
| `options[].card.rating`, `hidden[].card.rating`, `shelf.looks[].part.card.rating` | 1.3.0 | The part's star rating as WooCommerce keeps it from its reviews (`WC_Product::get_average_rating()` and `get_review_count()`): `average`, 0 to 5, `count`, and `text`, the words the card's star and score stand for, "4.5 out of 5, 12 reviews", in the site's language with the shop's decimal separator. A variation has its product's. Null where the part has no reviews or the shop shows no ratings (`wc_review_ratings_enabled()`), so it shows wherever the shop's own ratings do, with Creative Reviews or without it (`@since 1.3.0`, #137, D-392). |
| `swaps` | 1.3.0 | Smart swaps (`@since 1.3.0`, #135, D-394, D-396). On the review: the same board for less first, where one exists, then each picked slot's in step order; on a step: its own pick's alone; none on the deck's step, a step with no pick, or where nothing fits. Each has its `kind` (`board`, every picked slot but the deck at its cheapest that fits together; `less`, the slot's cheapest that fits; `better`, the next rank up, or the next price up where the slot has no rank), its `parts` (each `slot`, the part it `was`, the `part` it takes and its `name`) and its `change`: `amount`, the total's change in minor units, below 0 for less, as the total works it out with tax, the fee and the build discount, and `text`, the amount with no sign as the shop writes prices. Each swap is the build with its parts in place checked by the fit engine: every part fits the whole build with no warning, is in stock, and leaves no problem the build did not have. |
| `bearings_included` | 1.0.0 | Whether the wheels picked come with their bearings, so the kit's bearings slot counts as filled and its step is no step (D-246). |
| `currency` | 1.0.0 | How the shop writes a price, for a page that formats a total itself: `code`, `symbol` (plain text), `decimals`, `decimal_separator`, `thousand_separator` and `format`, WooCommerce's pattern with the symbol as `%1$s` and the amount as `%2$s`, as plain text: a space between them is a no-break space, not `&nbsp;` (D-290). From WooCommerce's settings and their filters, so a currency switcher's answer is the one sent. |
| `reopened` | 1.0.0 | Only in the answer to a build link carrying `_saved`, the UUID of the signed-in customer's own saved build of this kit, where it has changed: `changes` (each `slot`, `label`, `was`, `name`, `reason` of `gone`, `sold_out` or `no_fit`, and `closest`, `id` and `name`, or null), `prices` (each kept part whose price changed, `then` and `now` as text), the totals `then` (null where no prices were kept in this currency) and `now`, the `picks` with the closest parts in place, and the `first` slot that changed. The answer's state is that build, at the review (D-254, D-278). |

The published schema no longer closes its objects (`additionalProperties`), so a reader that checks an answer against one release's schema accepts the fields a later release adds. The plugin's own test still fails on any field the schema does not describe.

### The Builder block

| Block | Since | What it is |
| --- | --- | --- |
| `creative-skateboard-builder/builder` | 0.6.0 | The builder for one kit, on any page or post. Its attribute `kit` (integer, default `0`) is the kit product's ID. From 1.0.0, `0` on a kit's own page is that kit, as the kit template has it, and `title` (boolean, default `false`) prints the product's name as the page's h1, for a template with no title of its own. From 1.0.0 the block takes the editor's alignment, spacing, colours and font size on the builder's root, and `align` (string, default `full`) is full width unless the editor chose otherwise (D-283). A kit that is a draft, private or password-protected, a product that is no kit, and `0` anywhere else render nothing. The block can be used once in a post (`supports.multiple: false`), and a page shows one builder: the first one rendered, the kit's own page included. |

The kit's own product page shows the same builder:

- **From 1.0.0, in a block theme** a kit's page takes the template `creative-skateboard-builder//cbuild-kit` (Templates in the Site Editor, "Skateboard kit"), put first in `single_template_hierarchy`: the theme's header, the block with `title`, the product's details, and the theme's footer. The shop may edit it.
- **In a classic theme** the builder renders at `woocommerce_before_single_product_summary`, priority 5, at the page's width, with the product's name as its h1, and the summary's title (`woocommerce_template_single_title`) and form (`woocommerce_template_single_add_to_cart`) are left out for kits.
- **Elsewhere,** as in a page builder's product template, it renders where WooCommerce asks a product type for its add-to-cart area (`woocommerce_cbuild_kit_add_to_cart`), as in the Add to Cart form block, and adds no h1.
- **Each has a link back** to the shop page at the panel's head.
- **Another page.** Where the kit's Builder page (from 1.2.0) or `cbuild_builder_url` names another page, the kit's page sends a visitor there instead, with the build in their address, by a 302.

**Both are rendered on the server,** and the template's markup and the store's state are the builder's own: they may change in any release, and are no part of this API. With JavaScript, the store asks the state route for each move with `cbuild` and `page`, and shows the answer's `view` without loading the page. The store, `creative-skateboard-builder`, is private and locked: no other script can read or change it (D-148).

A page that holds a builder and carries a `cbuild` query, whatever it holds, says `noindex, follow` through `wp_robots`, and through `wpseo_robots_array` where Yoast SEO prints its own robots tag.

- **Its canonical** through `get_canonical_url` is the page without `cbuild`, so a canonical another filter wrote with the build in it is made clean. Yoast SEO prints no canonical on a noindex page.
- **An SEO plugin that reads neither filter** is asked to do the same through its own (D-147).

**From 0.7.0, such a page carries a share card:** Open Graph and X card tags with the build's name, its total, the build link as `og:url`, its brackets encoded as `%5B` and `%5D`, and the build's composed picture, or the deck's photo or else its layer image until the picture is made.

- **While the card falls back** that way, the page defines `DONOTCACHEPAGE` and sends `nocache_headers()` at `template_redirect`, so page caches keep no copy of it; once the picture is made it caches as any other.
- **A build that could not go to the cart,** a part sold out or no longer offered, has no card of the plugin's (D-212).
- **With no SEO plugin active,** the plugin prints them itself in `wp_head`, at priority 5.
- **With Yoast SEO,** they go through `wpseo_opengraph_title`, `wpseo_opengraph_desc` and `wpseo_opengraph_url`, and the picture replaces the page's Open Graph images on the presentation `wpseo_frontend_presentation` passes, all at priority 20, so a later answer of the shop's still wins.
- **Where Rank Math, SEOPress or All in One SEO is active,** the plugin prints no tags, so the page never has two sets.
- **A build that could go to the cart** asks for its picture when its link is opened (D-200).

### Filters

| Filter | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_builder_url` | 0.6.0 | `string $url`, `int $kit` | Where a kit's builder is: the address of the page its Builder block is on, with no build in it. `$url` starts as the kit's own page. From 1.2.0 it starts as the Builder page the kit's Build tab chose (`builder_page` in `_cbuild_settings`), where that is still a published page a visitor may see that holds the kit's Builder block, checked each time, and the kit's own page otherwise (#113, D-348). The filter answers last. An address on another host, or an answer that is no address, is ignored, and the starting value kept. A deck's "Make it a complete" link is this address with `cbuild[<deck slot key>]=<deck ID>` added (The build link, above): it opens on the step after the deck, with the deck picked. |
| `cbuild_part_classes` | 0.6.0 | `array $classes`, `int $part`, `string $slot`, `int $kit` | Classes to add to a part wherever the builder shows it, such as `is-flagged`: its card in a step, its place in the picture (`cbuild-picture__item`, and from 0.7.0 its layer on the board, `cbuild-board__layer`, in the picture and in the bar), the bar's photo (`cbuild-bar__photo`), its line in the review (`cbuild-review__item`) and from 1.3.0 its thumbnail there (`cbuild-review__photo`), so one answer can blur a part in every place. `$classes` starts empty. Each answer is made safe for a class attribute and put after the builder's own, which are kept whatever the answer; an answer that is no list adds nothing. `$part` is a product's or a variation's ID. |
| `cbuild_composed_layer` | 1.1.0 | `string $answer`, `int $part`, `string $slot`, `int $kit`, `string $use` | How a part's layer is drawn in a build's composed picture: `as_is`, `blurred` (its artwork can't be made out and its shape stays, about the 22 px blur a part gets on the page) or `left_out`. `$answer` starts as `as_is`, and any other answer is taken as it. `$use` is the picture's: `cart` (the cart's thumbnail, the ready builds' tiles and My builds), `share` (a build link's share card) or `email`, so a plugin can blur a part on the card a link preview shows to anyone and leave the shopper's own cart and emails as they are. The answer is in the picture's name: a change draws a new picture, asked for as a page asks, and the one drawn before is no longer shown. Where a use's picture is not made yet, a deck answered other than `as_is` has no stand-in: the share card has no image, and the cart and My builds show the kit's own (#106). Asked for each part with a layer image, each use, wherever a picture is looked up, so it has to be quick (D-309). |
| `cbuild_summary_notes` | 0.6.0 | `array $notes`, `array $context` | Lines under a build's total in the builder: the points a build earns, or a word to a signed-in dealer. `$notes` starts empty. `$context` holds `kit` (the kit product's ID), `parts` (the parts the total counts, slot key to part ID), `assembly`, and `total` in whole minor units with its `decimals`. Answer with a list of sentences in the request's language. Each is shown as plain text, the first six are kept, and anything that is no text is dropped. The answer is asked for on every pick and is never cached, so it has to be quick. Name no person in a line that a shared screen could show. |
| `cbuild_swaps` | 1.3.0 | `bool $offers` (the kit's Offer smart swaps, `swaps` in `_cbuild_settings`: true where the kit has none), `int $kit` | Whether the builder offers smart swaps for the kit (`@since 1.3.0`, #135, D-397). False: the state route's answer has no `swaps`, and the review and the picked card show no swap control. It turns them on or off and never adds one: every swap is the fit engine's. Anything but `true` is off. |
| `cbuild_width_guide` | 1.0.0 | `array $rows`, `int $kit` | The rows of "Not sure of your width?" under the deck step's width question, each an array with `shoe` (a shoe size, as the shop writes it) and `width` (the deck width it rides). `$rows` starts as the plugin's, by US shoe size after Skate Warehouse's deck buying guide, in its own translatable words. From 1.2.0 (`@since 1.2.0`, #114, D-350) it starts as the kit's own rows where its Build tab has them, each shoe size after `cbuild_kit_text`, and as an empty list where the kit hides the guide; the filter still answers last. Each is shown as plain text, a row without both is left out, and twelve rows are shown at most; an empty list takes the guide away, words and all, from the page and from the state route's answer. |
| `cbuild_guided_start` | 1.3.0 | `array $recommended`, `array $answers`, `int $kit` | **@since 1.3.0** (#134, D-402). What the guided start recommends from a shopper's answers, after the kit's settings: `width`, the deck width its ready builds are made at, from the kit's width guide (the in-stock deck width nearest the middle of the shoe size's row), and `wheels`, with `from` and `to`, the wheel sizes from the kit's `wheel_guide` for where they skate. Each size is written as the build link writes a width, a number and its unit, such as `8.25in` or `54mm`; an empty `width` is any width, an empty `wheels.from` any wheel and an empty `wheels.to` any from `from` up. `$answers` has `shoe` (the width guide's row, counted from 0, or null), `style` (or null) and `budget` (a whole amount, or null). The builds are then made at the answer, from parts in stock that pass the fit engine (D-401); a size that does not read is left out, so the builds take any. Asked once each time the answers are sent, never cached. |
| `cbuild_help_me_choose` | 1.3.0 | `bool $offered` (the kit's Help me choose, `guide` in `_cbuild_settings`: true where the kit has none), `int $kit` | **@since 1.3.0** (D-409). Whether the builder offers "Help me choose", the guided start (#134), at its start. False: the page draws no disclosure, form or builds, and answers in a link recommend nothing; the view's `guide.offered` is false. It turns the guide on or off and never changes what it asks or recommends: `cbuild_guided_start` does that. Anything but `true` is off. |
| `cbuild_card_picks` | 1.3.0 | `bool $shown` (the kit's Ready-build picks on cards, `picks` in `_cbuild_settings`: true where the kit has none), `int $kit` | **@since 1.3.0** (D-409). Whether a card in the builder says which ready builds pick its part, such as "Picked for Street" (#137). False: no card says it. It never adds a pick. Anything but `true` is off. |
| `cbuild_card_ratings` | 1.3.0 | `bool $shown` (the kit's Ratings on cards, `ratings` in `_cbuild_settings`: true where the kit has none), `int $kit` | **@since 1.3.0** (D-409). Whether a card in the builder shows its part's rating, a star and its score (#137). False: no card shows or says it. True still shows none while WooCommerce's star ratings are off. Anything but `true` is off. |
| `cbuild_start_button` | 1.0.0 | `array\|false $button`, `int $product` | "Build a complete with this deck" on a product's page (D-253). `$button` has `kit` (the first kit by menu order whose first slot offers the product in stock and, from 1.2.0 (`@since 1.2.0`, #115), whose `start_button` is on), `part` (the product, or its first variation that kit offers), `slot` (the slot it is picked in: the kit's first, or another of the kit's slots the filter names), `text`, `hook` (`woocommerce_after_add_to_cart_form`) and `priority` (10); or it is false where no such kit offers the product first. The filter answers last: from 1.2.0 it may still name a kit whose `start_button` is off. Return an array to choose the kit or the part, word the button or move it to another action; return false to leave it out. A kit a visitor may not see shows no button, and the builder checks the part as it checks any link. The link carries `rel="nofollow"`, as the rail's do. |

### Adding a build: `replace`

From 0.6.0, `cbuild` on either route of Adding a build (0.5.0, above) takes `replace`: the `id` of a build in the shopper's cart, as its kit's line holds it under `cbuild`. As JSON, `"cbuild": { "picks": { … }, "replace": "<id>" }`; as a field, `cbuild[replace]=<id>`.

- **The new build** is judged and added as any other is; only once all of it is in the cart is the build `replace` names taken out, its kit's line and its parts'.
- **A build the cart refuses** leaves the one before it as it was.
- **A `replace` that names no build** in this cart, or is no identifier, takes nothing out.
- **The builder's review** sends it when Edit build opened it (D-157).

### Edit build on a kit's line

Each kit's line in the cart has two item data entries from 0.6.0:

- **Assembly.** The first, `raw_key` `cbuild_assembly`, keyed "Assembly", says what the line charges: the fee as the cart shows prices, "free", or "not added" for a build the shop does not assemble; a classic cart prints no price or subtotal on the kit's line, and an order's line subtotal says "Assembly: …" in the same words.
- **Edit build.** The second, `raw_key` `cbuild_edit`, has an empty key, so the blocks print the link alone, and its `display` is a link with the class `cbuild-edit`. The link is the kit's builder, where `cbuild_builder_url` says it is, at the review, with the build's parts and assembly and `cbuild[_replace]`, the build's `id`.

The Cart and Checkout blocks, the Mini-Cart and a classic cart all print it.

### Styling the builder

The builder's stylesheet is the Builder block's `style` (`build/builder/style-index.css`, handle `creative-skateboard-builder-builder-style`), loaded wherever a builder renders, on a kit's own page as in the block.

- **Its rules sit inside `:where()`,** so a theme overrides any of them with a single class; only `.cbuild [hidden]`, `.cbuild .screen-reader-text` and, from 1.0.0, the focus ring are outside it, the ring so that it wins over a theme's own focus style on a button or a link (D-229).
- **Its classes are BEM** under the `cbuild-` prefix, on the builder's root `.cbuild`, and its templates are not for overriding (docs/STANDARDS.md).

A theme restyles it most simply by setting these on `.cbuild` (D-159 to D-162):

| Property | Since | What it sets | Falls back to |
| --- | --- | --- | --- |
| `--cbuild-text` | 0.6.0 | The builder's text | the text's own colour |
| `--cbuild-muted` | 0.6.0 | Secondary text: help, counts, notes | the text at 72 % |
| `--cbuild-line` | 0.6.0 | Borders and rules | the text at 28 % |
| `--cbuild-surface` | 0.6.0 | A card's and the summary's ground | the text at 5 % |
| `--cbuild-accent` | 0.6.0 | The chosen card, a pressed filter, the primary button | `--wp--preset--color--contrast`, then the text |
| `--cbuild-on-accent` | 0.6.0 | Text on the accent | `--wp--preset--color--base`, then `Canvas` |
| `--cbuild-focus` | 0.6.0 | The focus ring's outline. From 0.9.0 it no longer takes the theme's colours, since a dark theme's contrast preset can be dark too (D-229) | `#000` |
| `--cbuild-focus-contrast` | 0.9.0 | The band on each side of the focus ring, so the ring reads on a dark page and a light one | `#fff` |
| `--cbuild-wrong` | 0.6.0 | The edge of the error summary and of a part that does not fit; never text | `--wp--preset--color--vivid-red`, then `#b32d2e` |
| `--cbuild-radius` | 0.6.0 | Corners | `0.5rem` |
| `--cbuild-gap` | 0.6.0 | Space between the builder's pieces | `--wp--preset--spacing--40`, then `1.5rem` |
| `--cbuild-gap-small` | 0.6.0 | Space inside them | `--wp--preset--spacing--20`, then `0.5rem` |
| `--cbuild-target` | 0.6.0 | The least height and width of a button | `2.75rem` |
| `--cbuild-control` | 0.6.0 | A radio's size, and the least height of a link that is a control | `1.5rem` |
| `--cbuild-small` | 0.6.0 | Small text | `--wp--preset--font-size--small`, then `0.875rem` |
| `--cbuild-card-width` | 0.6.0 | The narrowest a part's card is before the cards wrap. From 1.0.0 the default is `13.5rem`, for the card's photo beside its words | `13.5rem` |
| `--cbuild-ground` | 0.6.0 | The phone bar's ground, and its parts list's | `--wp--preset--color--base`, then the colour that contrasts with the builder's text (`contrast-color()`), else `Canvas` |
| `--cbuild-on-ground` | 0.6.0 | Words on the phone bar's ground | `--wp--preset--color--contrast`, then the builder's text where the browser can pick a ground that contrasts with it, else `CanvasText` |
| `--cbuild-bar-height` | 0.6.0 | The phone bar's least height, which focus is kept clear of | `4.25rem` |
| `--cbuild-duration` | 0.6.0 | How long each movement lasts: the chosen card settling, a part arriving in the picture, a step crossing over. The step's crossing is the page's view transition, which reads it on `:root`, so set it there too. From 1.1.0 the board's moments have the spec's own times (below), and this times the card and the step | `200ms` |
| `--cbuild-bar-offset` | 0.6.0 | How far above the foot of the screen the phone bar sits, set on the page's root (below) | `0px` |
| `--cbuild-ease` | 0.6.0 | How each movement eases, read as `--cbuild-duration` is | `cubic-bezier(0.2, 0, 0, 1)` |
| `--cbuild-panel-shift` | 1.1.0 | How far, and which way, the panel slides as a step crosses over, read on `:root` as the view transition is. The builder sets it to `-16px` going back and in a right-to-left page; a theme sets the distance for going on | `16px` |
| `--cbuild-top` | 1.0.0 | The height of anything the theme keeps at the top of the screen, such as a sticky header: the stage sticks below it on a phone, and the stage and the panel fit under it on a desktop | `0px` |
| `--cbuild-stage-height` | 1.0.0 | The stage's height on a phone and a tablet, where it stays at the top above the step | `34svh` (D-284) |
| `--cbuild-stage-size` | 1.0.0 | Set by the builder's script, not by a theme: how far down the screen the stage reaches while it stays at the top, so the page scrolls a control in focus clear of it (WCAG 2.4.11). A theme can read it for its own sticky elements | `0px` |
| `--cbuild-board-size` | 0.7.0 | The board's height in the picture. The layers' `sizes` assume the default, so a board drawn much larger may fetch a smaller rendition than the screen could use | `min(60vh, 36rem)` |

From 0.7.0 the builder sets these on the board and its layers, for a theme's rules to read; a theme does not set them (D-195):

| Property | Since | What it holds |
| --- | --- | --- |
| `--cbuild-board-ratio` | 0.7.0 | On `.cbuild-board`: the board's width over its height, as `aspect-ratio` takes it |
| `--cbuild-layer-x` | 0.7.0 | On `.cbuild-board__layer`: its left edge, as a share of the board's width |
| `--cbuild-layer-y` | 0.7.0 | Its top edge, as a share of the board's height, the nose at the top |
| `--cbuild-layer-width` | 0.7.0 | Its width, as a share of the board's |
| `--cbuild-layer-height` | 0.7.0 | Its height, as a share of the board's |
| `--cbuild-bar-size` | 1.0.0 | On the page's root, with JavaScript: the bar's height while it sticks over the page, which the page's scroll padding reads so focus stays clear of it (D-272) |

From 1.1.0 the builder's motion (spec: Milestone 11; D-256, D-305). Each moment moves `transform` and `opacity` alone, and is cut short by the shopper's next press or pick:

| Moment | How | Time | Its name |
| --- | --- | --- | --- |
| Deck picked, trucks, wheels, a part swapped, grip, Show the top, Add to cart | The Web Animations API, on the board's own layers | 280, 240 (60 apart), 220 (40 apart), 200, 300, 400, 400 ms | The `Animation`'s `id`: `cbuild-deck`, `cbuild-trucks`, `cbuild-wheels`, `cbuild-swapped`, `cbuild-grip`, `cbuild-turn`, `cbuild-cart` |
| A part removed | The stage's other photos slide into their places | 180 ms | `cbuild-removed` |
| Price changes | The bar's total counts between amounts written from the route's `currency`, `aria-hidden`, and ends on the server's string; the estimate fades in | 300 ms | `cbuild-total` (the estimate) |
| Next step | The page's view transition: the panel (`view-transition-name: cbuild-panel`) slides 16 px the way the shopper goes, and the rail's current step (`cbuild-marker`) follows | 200 ms | `cbuild-panel-in`, `cbuild-panel-out` |

Where the shopper asks for reduced motion, or the kit's Motion is subtle (the root then has `cbuild--subtle`), each is a fade of opacity alone in 150 ms or less, and the total changes at once. While a step crosses over, the page's root has `cbuild-calm` where it only fades and `cbuild-back` going back. A theme's own animations on these elements are left alone, but one on `transform` would fight the moments.

On a phone the bar sits above any bar the theme fixes to the foot of the screen: with JavaScript the builder measures it and sets `--cbuild-bar-offset` on the page's root, which the bar's place and the page's scroll padding read. A theme may set it itself. Where an ancestor of the builder has `overflow: hidden`, which would keep the bar from sticking, the builder gives it `overflow: clip` in its place, and a plain block `display: flow-root` (D-163).

### DOM events

The builder sends three events on its own element (`.cbuild`), with JavaScript only. Each bubbles, so a listener on the document hears every builder on the page. The first two are for a shop's own analytics, under the shop's own consent; the third tells another plugin the builder has drawn a view. The plugin sends nothing anywhere itself, and each carries IDs and keys alone, never a name, a price or anything of the shopper. A listener cannot change what the builder does.

| Event | Since | `detail` | When |
| --- | --- | --- | --- |
| `cbuild:pick` | 0.6.0 | `{ kit: number, slot: string, part: number }`: the kit product's ID, the slot's key, and the part's ID, `0` for a slot left empty by choice. | The shopper picks a part, at once, before the state route answers. A pick the route then refuses is still sent. |
| `cbuild:added` | 0.6.0 | `{ kit: number, picks: { [slot: string]: number }, assembly: boolean }`: the kit's ID, the build's parts by slot, and whether it is assembled. | The Store API has added the build to the cart. An add that falls back to WooCommerce's own handler loads the page, and sends nothing. |
| `cbuild:rendered` | 1.2.0 | `{ kit: number, step: string }`: the kit's ID, and the step shown as the build link's `cbuild[step]` names it, a slot's key or `review`. On the change panel, the step it opened over. | Once when the builder is hydrated, as it sets `data-ready`, and then once per answer of the state route it draws, on the frame the view is drawn in: a pick's, a step's, a search's, a filter's. Never for a pick or a key before its answer, nor for an answer dropped because a later move overtook it. Not for a turn of the board (Underside or Top) or a fold opened, which change the page without an answer. Another plugin's script listens for it in place of watching the builder's attributes (#143, D-376). |

**The hydrated marker.** From 1.2.0 (`@since 1.2.0`, #143, D-376), `data-ready` on `.cbuild` says the builder has hydrated. It is set once, in the same callback that sends the first `cbuild:rendered`, just before it, and never removed. An event is not replayed for a listener added later, so a script that may load after hydration does both:

- **First,** checks for `.cbuild[data-ready]`. Where it is set, the builder is live and its first view is drawn; act on it now.
- **Then** listens for `cbuild:rendered` for every redraw after that.

The builder also sends WooCommerce's own `wc-blocks_added_to_cart` on `document.body` after an add, on which the Mini-Cart reads the cart again (D-155). It is WooCommerce's event, not part of this plugin's API.

## Milestone 8: orders and after (0.8.0)

### The state route and the price ability

| Item | Since | What changed |
| --- | --- | --- |
| `total.estimate` in `schema/state.json` and in `creative-skateboard-builder/price-build`'s output | 0.8.0 | What a complete board would cost with each slot's recommendation in it, priced as `total` is (assembly, build discount, the shop's decimals), while a recommendation fills a slot and every slot the kit requires has a part; otherwise null. |
| `options[].board` in `schema/state.json` | 1.1.0 | On the deck step, the board each deck the step lists, sold out or not, would draw alone, as the schema's `option` definition gives it: `width` and `height` in the picture's units, and `layers`, the deck's one layer shaped as `picture.layers`' are. A page fetches its image before the deck is picked, as the builder does while the pointer rests on its card. Null on every other step, and for a deck with no layer image (D-308). |
| `picture.top` in `schema/state.json` | 0.8.0 | The board seen from the top (#46), as a list of layers shaped as `picture.layers`' are (the schema's `layer` definition), in the same units and places: from the bottom, the wheels, the deck, the grip, and a bolt head at each of the deck's eight holes. From 1.1.0 the grip's layer is its whole sheet, `grip_width` by `grip_length` (9 by 33 in where it gives none), centred on the deck; before, it had the deck's box (F18, D-307). The deck's layer image is the top's outline, the grip's is its sheet, cut by that outline, and the hardware's is a bolt head; the trucks are not on it. Empty where the deck has no layer image. A part drawn on either side has `drawn` true. |
| `picture.layers` | 0.8.0 | The underside no longer holds the grip's 1 in strip: the grip is drawn on the top. Nothing else changes, and the composed pictures stay the underside. |
| `total`, `steps[].recommended` and each option's `recommended` | 0.8.0 | Nothing is picked for the shopper (D-213): `total` and its `lines` count the picks alone, where before they also counted the part each later slot started on. `recommended` keeps its name and says the part the slot recommends, which is labelled and not picked. A caller that read `total` as the recommended board reads `estimate`. |
| `creative-skateboard-builder/check-build`'s `ready` and `problems` | 0.8.0 | A required slot with no pick is now a problem, `slot_empty`, and the build is not `ready`, where before the part the builder started it on was checked in its place: a build of the deck alone was `ready` in 0.7.0 and is not now (D-213). The ability's description says so. |

### Saved builds and My builds

| Item | Since | What it is |
| --- | --- | --- |
| My account endpoint `my-builds` | 0.8.0 | My builds: each of the signed-in customer's saved builds with its picture, name, total today and the date it was saved, and Open, Rename and Delete. Added through WooCommerce's `woocommerce_get_query_vars`, with its menu item before Log out (`woocommerce_account_menu_items`). A shop that wants it elsewhere in the menu reorders that filter's answer at a later priority. |
| `cbuild_saved` | 0.8.0 | The query parameter Open adds: the saved build's UUID. Read only on its owner's page, where Save build then updates that build. Another customer's page, and a visitor's, ignore it; no build link, Copy link or `og:url` carries it. |
| `cbuild_compare` | 1.3.0 | The query parameter on My builds that compares saved builds side by side: `cbuild_compare[]`, a build's UUID each, in the order shown. The first three of the signed-in customer's own builds among them are compared; another customer's, a UUID asked twice and anything that is no UUID are left out. Fewer than two leaves My builds' list, which says to choose two or three. A link may carry it, as My builds' own Compare form does, sent by GET (#136, D-411). |

Save build, Rename, Delete and, from 0.9.0, Undo (the action `cbuild_restore_build`) are forms posted to My builds with a nonce each, answered on `template_redirect`. Their fields are the builder's own and no part of this API (D-217, D-231).

- **The phone bar's estimate line,** `.cbuild-bar__estimate` (1.0.0, D-230), is the builder's own markup, as the rest of the bar is.
- **From 1.1.0 the footer's Next** carries `aria-disabled="true"` while the step has no pick, and `.cbuild-bar__missing` says what is missing when it is pressed (#100, D-310); both are the builder's own markup too.
- **There is no REST route** for saved builds.

### Styling the board's top

The picture and the phone bar draw the board's top (#46) beside its underside, as `.cbuild-board.cbuild-board--top`, and show one side at a time: the picture has `cbuild-picture--top` and the bar's link `cbuild-bar__picture--top` while the top is shown, and `--turning` while the board turns over (with JavaScript, never on load).

Each of the top's layers is a `span.cbuild-board__layer` placed by the four `--cbuild-layer-*` properties above, and the builder sets these on it for the stylesheet to paint; a theme does not set them:

| Property | Since | What it holds |
| --- | --- | --- |
| `--cbuild-image` | 0.8.0 | On each of the top's layers but the deck's: the part's layer image, as a `url()`. The grip's sheet is drawn once at its size, given below (a 1-inch swatch from 1.0.x is repeated along the deck), the others fitted into their box |
| `--cbuild-grip-size` | 1.1.0 | On the top's grip layer, where its image is a whole sheet: the sheet's size against the deck's box, across then along, as a `background-size` (`112.5% 103.125%`: a 9 by 33 in sheet on an 8 by 32 in deck). The grip layer has the deck's box and outline; a 1-inch swatch has none (F18, D-307) |
| `--cbuild-mask` | 0.8.0 | On the top's deck and grip layers: the deck's layer image, as a `url()`, whose transparency is their outline (`mask-image`). A layer image served from another origin needs `Access-Control-Allow-Origin` for the browser to use it as a mask |

The buttons by the board, `.cbuild-picture__turn .cbuild-button`, have `aria-pressed` true while the top is shown. From 1.2.0 there are two, Underside and Top, in `.cbuild-picture__sides`, and each has `aria-pressed` true while its own side is shown (#128, D-369).

### The workshop: the Build box, the assembly ticket and the orders list

For users with `edit_shop_orders`. The Build box, the ticket and the orders list read an order's builds from its lines each time they are shown: each slot as sold from `_cbuild_snapshot`, matched to its line by `_cbuild_id` and `_cbuild_slot`.

| Filter | Since | Arguments | What a plugin answers |
| --- | --- | --- | --- |
| `cbuild_ticket_specs` | 0.8.0 | `string[] $specs`, `string $slot`, `int $kit` | Which specs the assembly ticket shows for a slot's part, as spec keys in the order shown. By default `deck_width` for the deck, `axle_width` for the trucks (their size), `wheel_diameter` for the wheels, `riser_thickness` for the risers and `bolt_length` for the hardware, and none for any other slot. The ticket shows each one the part was sold with, from the snapshot, with its name and unit. A key that is not a spec key, or an answer that is no list, shows nothing. |
| `cbuild_ticket_steps` | 0.8.0 | `string[] $steps`, `array $context` | The steps the ticket lists for a build, each with a tick box. By default, for an assembled build: the grip, the trucks (with the risers where it has them), the bearings into the wheels, the wheels onto the trucks, then a last check, each only where the build has those parts; none for a build the shop does not assemble. `$context` holds `kit` (the kit product's ID), `order` (the order's ID), `parts` (slot key to part ID) and `assembly` (bool). Each step is shown as plain text, tags taken out, an empty one dropped and each said once; the ticket lists the first 20. |

Both are asked as the ticket is drawn, in the admin's language, so a step a plugin adds goes through its own text domain.

The names another plugin may meet on the order screens:

| Item | Since | What it is |
| --- | --- | --- |
| The Build box's ID, `cbuild-build` | 0.8.0 | Its meta box, on `wc_get_page_screen_id( 'shop-order' )` with HPOS on or off, in the `normal` context. It is added only to an order with a build, or with a kit's line that has no build behind it. |
| `admin-post.php?action=cbuild_print_tickets` | 0.8.0 | The assembly ticket: a page of its own that prints, one board to a page. It takes `orders` (order IDs separated by commas, the first 100 printed), `more` (how many more the bulk print left out) and `_wpnonce` (a nonce for the action `cbuild_print_tickets`), and refuses with a 403 a user without `edit_shop_orders` or without the nonce. It reads orders and changes nothing. |
| `cbuild_print_tickets` | 0.8.0 | The bulk action Print assembly tickets on the orders list, with HPOS on (`woocommerce_page_wc-orders`, and `admin_page_wc-orders` for a user without `edit_others_shop_orders`) or off (`edit-shop_order`). It sends the picked orders to the ticket page. |
| `cbuild_assembly` | 0.8.0 | The Assembly column's key on the same lists, before the total: how many boards of the order the shop assembles, Not assembled, or empty for an order with no build. |

### Privacy tools and orders

| Item | Since | What it is |
| --- | --- | --- |
| Exporter and eraser `creative-skateboard-builder` | 0.8.0 | "Saved builds" in Tools > Export Personal Data and Erase Personal Data: each of the account's saved builds, found by its email address. One page each (D-220). |
| The build under a kit's order line | 0.8.0 | On `woocommerce_order_item_meta_end`: the build's picture, `.cbuild-email-picture`, with a short alt text from 0.9.0, "The build, as drawn", since the parts are listed under it, on the order's page and in HTML emails; the build in words in plain text. Each part's row has the class `cbuild-order-part` (D-221). The markup is the plugin's own and may change in any release. |
