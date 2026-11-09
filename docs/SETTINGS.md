# Settings

Everything a shop owner can set, with its default and its range. A setting is added here in the same commit that adds it to the code.

## Milestone 1 (0.1.0)

No setting yet. The settings page and its one option, `cbuild_settings`, arrive in milestone 4, and `Support\Options::settingsKeys()` is empty until then.

Two switches exist for a developer, and neither is a setting. Both are in [HOOKS.md](HOOKS.md):

| Switch | Default | What it does |
| --- | --- | --- |
| `CBUILD_DISABLE_UPDATES` (constant) | Not defined | Defined as true in `wp-config.php`, it turns the update check off. |
| `cbuild_updates_enabled` (filter) | True | Returning false turns the update check off. |

## Options beside the settings

The plugin keeps three options of its own, which no screen edits: `cbuild_db_version`, `cbuild_migrations` and `cbuild_migration_lock`. [HOOKS.md](HOOKS.md) says what each holds, and [PRIVACY.md](PRIVACY.md) lists everything stored.
