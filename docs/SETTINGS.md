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

The option `cbuild_settings` arrives with its first two keys. Their screen, WooCommerce > Settings > Builder, lands later in this milestone; until then the defaults apply. Whatever the option holds, the plugin reads a valid setting: a key that is missing or malformed reads as its default.

| Key | Default | Who can change it | What it does |
| --- | --- | --- | --- |
| `attribute_mapping` | Empty | `manage_woocommerce` | A list of lines, each an `attribute` (a taxonomy such as `pa_width`, or the name of a product's own attribute), the `spec` it feeds (a spec key from [HOOKS.md](HOOKS.md)) and a `unit` (`in`, `mm` or empty). A part's value for the attribute becomes that spec. The unit is what an unmarked value is in, for a measure; empty means the spec's own unit. A value is read with the shop's decimal separator or a point. A line with no attribute, a name over 190 characters or a spec the plugin does not know is left out. |
| `source_order` | `table`, `field`, `attribute` | `manage_woocommerce` | Which source wins where two say different things of one spec: the maker's chart, the part's own field, a mapped attribute. First wins. Anything that is not all three exactly once reads as the default. |

## Options beside the settings

The plugin keeps three options of its own, which no screen edits: `cbuild_db_version`, `cbuild_migrations` and `cbuild_migration_lock`. [HOOKS.md](HOOKS.md) says what each holds, and [PRIVACY.md](PRIVACY.md) lists everything stored.
