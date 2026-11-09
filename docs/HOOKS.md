# Hooks and the public API

Everything another plugin, a theme or a shop's own code may rely on, added as it appears: filters and actions, constants, options, tables, scheduled actions and the names the plugin writes under. Names carry the `cbuild_` prefix. An item listed in the spec and not here has not been built yet.

`composer api:check` reads the code and fails when an item is missing from this file or from `tests/Fixtures/api-surface.json`. Renaming or removing anything here is a breaking change and needs Zach.

## Milestone 1: the foundation (0.1.0)

The foundation adds one filter and one constant, both for the updater. It adds no action, REST route, ability, block, WP-CLI command, setting key, meta key, CSS class or DOM event.

### `cbuild_updates_enabled` (filter)

- **Since:** 0.1.0
- **Signature:** `apply_filters( 'cbuild_updates_enabled', bool $on ): bool`
- **What it does:** decides whether the plugin checks the agency's release manifest for a new version. `$on` is true unless `CBUILD_DISABLE_UPDATES` is defined and true. When the answer is false the plugin attaches none of its update hooks and makes no outbound request.
- **Where it runs:** `Updates\Updater::enabled()`, once on `plugins_loaded`, also while WooCommerce is missing, so a fix still arrives (D-003).
- **Example:**

```php
add_filter( 'cbuild_updates_enabled', '__return_false' );
```

### `CBUILD_DISABLE_UPDATES` (constant)

- **Since:** 0.1.0
- **What it does:** `define( 'CBUILD_DISABLE_UPDATES', true );` in `wp-config.php` turns the update check off for a site whose releases are deployed another way. The `cbuild_updates_enabled` filter runs after it and may turn the check back on.
- **Where it is read:** `Updates\Updater::enabled()`.

### Options

Per site. All three are removed on uninstall.

| Option | Since | Autoloaded | What it holds |
| --- | --- | --- | --- |
| `cbuild_db_version` | 0.1.0 | Yes | The installed schema version. Behind the code's version means migrations are pending. |
| `cbuild_migrations` | 0.1.0 | No | Each migration's status, attempts and cursor, so an interrupted one resumes. |
| `cbuild_migration_lock` | 0.1.0 | No | The time the running migration took the lock, in UTC seconds. It expires after 300 seconds (D-021). Read it, never write it. |

### Site transients

| Transient | Since | What it holds |
| --- | --- | --- |
| `cbuild_update_manifest` | 0.1.0 | The release manifest, cached for 12 hours, or for 1 hour after a failed request. One for the network. Removed on uninstall. |

### Tables

Per site, each named with the site's table prefix first (`wp_cbuild_specs` on a default install). The columns are in the spec's Data model and in `Install\Schema`. Read them if you must; write them only through the plugin. Both are dropped on uninstall and when a network site is deleted.

| Table | Since | What it holds |
| --- | --- | --- |
| `cbuild_specs` | 0.1.0 | One row per part and spec: the index the fit engine reads. Empty until milestone 3. |
| `cbuild_builds` | 0.1.0 | Saved builds. Empty until milestone 7. |

### Scheduled actions

Action Scheduler, in the group `creative-skateboard-builder`.

| Action | Since | Arguments | What it does |
| --- | --- | --- | --- |
| `cbuild_migrate` | 0.1.0 | None | Runs pending migrations off the request, within a 20 second budget, and queues itself again if there is more. At most one is pending at a time (D-022). |

Pending actions are unscheduled on deactivation. Every action in the group, with its logs and the group's own row, is deleted on uninstall (D-024).

### Log source

Every log line goes through `wc_get_logger()` with the source `creative-skateboard-builder` (since 0.1.0), under WooCommerce > Status > Logs.
