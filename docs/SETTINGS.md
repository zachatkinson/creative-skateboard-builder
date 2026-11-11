# Settings

Everything a shop owner can set, with its default and its range. A setting is added here in the same commit that adds it to the code.

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

- **Attribute mapping.** One group of three controls per line: the attribute (type a name, or choose one of the shop's attribute taxonomies from the list the field offers), the spec and the unit. The saved lines are shown with three blank ones after them; saving adds three more. A line with its attribute and its spec both cleared is removed. The mapping holds 50 lines at most.
- **Which source wins.** Three lists, first, second and third, each naming a source.

A save is all or nothing. When any line or the order is wrong, nothing is stored, the screen comes back with what was typed, and each problem is written beside its field. What the screen refuses:

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
| `build_discount` | `0` (none) | `manage_woocommerce` | A percentage taken off each part of a build, as text with a point and up to two decimals: `7.5`. From 0 to 99.99. The cart reads it from 0.5.0; until then it changes nothing a shopper pays. |
| `fixed_coupons` | `kit` | `manage_woocommerce` | Where a fixed product discount coupon that takes the kit lands. `kit`: on the kit's line alone. `parts`: on the kit's line and on every part of the build, so its amount comes off each. The cart reads it from 0.5.0. |
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

## Options beside the settings

The plugin keeps seven options of its own, which no screen edits: `cbuild_db_version`, `cbuild_migrations`, `cbuild_migration_lock` and, from 0.3.0, `cbuild_reindex_state`, `cbuild_reindex_owed`, `cbuild_import_state` and `cbuild_import_rows`. [HOOKS.md](HOOKS.md) says what each holds, and [PRIVACY.md](PRIVACY.md) lists everything stored.
