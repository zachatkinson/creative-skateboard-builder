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
| `attribute_mapping` | Empty | `manage_woocommerce` | A list of lines, each an `attribute` (a taxonomy such as `pa_width`, or the name of a product's own attribute), the `spec` it feeds (a spec key from [HOOKS.md](HOOKS.md)) and a `unit` (`in`, `mm` or empty). A part's value for the attribute becomes that spec. The unit is what an unmarked value is in, for a measure; empty means the spec's own unit. A value is read with the shop's decimal separator or a point. A line with no attribute, a name over 190 characters or a spec the plugin does not know is left out. |
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

## Options beside the settings

The plugin keeps four options of its own, which no screen edits: `cbuild_db_version`, `cbuild_migrations`, `cbuild_migration_lock` and, from 0.3.0, `cbuild_reindex_state`. [HOOKS.md](HOOKS.md) says what each holds, and [PRIVACY.md](PRIVACY.md) lists everything stored.
