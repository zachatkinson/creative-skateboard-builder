# Privacy and stored data

Everything the plugin stores, why, for how long, who sees it, and how it is exported and erased. A pull request that stores something new updates this file in the same commit.

The plugin sets no cookie of its own, sends nothing about a shopper anywhere, and loads nothing from another host.

## Personal data

None in 0.1.0. Saved builds (milestone 8) will be the only personal data the plugin keeps: the customer's user ID, the picks, the name they give the build and when it was saved, with a personal data exporter and eraser.

## What 0.1.0 stores

| Where | Name | What it holds | Personal | Kept until | Tests |
| --- | --- | --- | --- | --- | --- |
| Option, autoloaded | `cbuild_db_version` | The installed schema version, a number. | No | Uninstall | `ActivationTest`, `UninstallTest` |
| Option | `cbuild_migrations` | Each upgrade step's status, attempts, cursor, last error message and next attempt time. | No | Uninstall | `MigrationTest`, `UninstallTest` |
| Option | `cbuild_migration_lock` | The time an upgrade took its lock, in UTC seconds, and a random token naming that run. | No | The upgrade ends, or 300 seconds; deactivation; uninstall | `MigrationTest`, `ActivationTest` |
| Site transient | `cbuild_update_manifest` | The agency's release manifest: versions, a download address, a checksum, release notes. | No | 12 hours (1 hour after a failed request); uninstall | `UpdaterTest`, `UninstallTest` |
| Table | `{prefix}cbuild_specs` | One row per part and spec: the product's or variation's ID, the spec's key, its value, which source it came from (the part's field, an attribute, a spec table), what in that source, and when it was read. Nothing about a person. | No | Uninstall; the site's deletion on a network; rebuilt from the products, never edited | `ActivationTest`, `UninstallTest`, `SpecIndexRepositoryTest` |
| Table | `{prefix}cbuild_builds` | Empty in 0.1.0. From milestone 8: saved builds. | Will be | Uninstall; the site's deletion on a network | `ActivationTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, action `cbuild_migrate` | A queued upgrade run, with no arguments. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `InstallHooksTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed upgrade step's name, its cursor and the error's message. | No | WooCommerce's own log retention | `MigrationRunnerTest`, `MigrationTest` |
| Option | `cbuild_settings` | The shop's settings: which attribute feeds which spec, and which source wins. Nothing about a person. | No | Uninstall | `UninstallTest`, `SpecSourcesTest` |
| Product meta | `_cbuild_specs` | On a product or variation: the part's own specs, a value per spec key. Nothing about a person. | No | Uninstall; with the product | `UninstallTest`, `SpecSourcesTest` |
| Product meta | `_cbuild_spec_table` | On a product or variation: the spec table and size it names. Nothing about a person. | No | Uninstall; with the product | `SpecTablesTest`, `UninstallTest` |
| Posts | Type `cbuild_spec_table`, with the meta `_cbuild_rows` and `_cbuild_seed` | A maker's size chart: its name, a row per size, where each row was read and on which day. The post's author is the user who made it, as WordPress records for any post; the seeded ones have none. | No | The shop deletes it; uninstall | `SpecTablesTest`, `UninstallTest` |
| Option | `cbuild_reindex_state` | Where the reindex is: its phase, its place, how many parts it has indexed, when it started and finished, and the last error's message. Nothing about a person. | No | Uninstall | `IndexJobsTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, actions `cbuild_index_part` and `cbuild_reindex` | A queued indexing job: for one product, its ID and how many times it has been tried; for the reindex, nothing. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `IndexJobsTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed indexing job's product ID or batch, and the error's message. | No | WooCommerce's own log retention | `IndexJobsTest` (unit) |
| Option | `cbuild_import_state` | The specs import in hand: the rows read from the file (product IDs, SKUs, names and specs) while it waits to be confirmed and while it runs; after, the counts and the rows it refused. While it waits, the ID of the user who sent the file, so only they can confirm it; the ID is dropped when the import starts. The file itself is read where PHP received it and is never moved into the site or kept. | A user ID, for an hour at most | A waiting import: an hour, or until it is confirmed, put away or replaced. A finished one: the next file. Uninstall | `SpecImportTest` (unit), `ImportScreenTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, action `cbuild_import` | A queued batch of a confirmed import, with no arguments. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `ImportScreenTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed batch of an import: its first row's number, how many times it has failed and the error's message. | No | WooCommerce's own log retention | `SpecImportTest` (unit) |
| Object cache | Group `cbuild` | Nothing in 0.1.0. | No | Uninstall flushes the group | `UninstallTest` |

Order item meta is part of the shop's order records and is kept on uninstall (spec: Uninstall). None is written in 0.1.0.

## What leaves the site

Two requests, both to GitHub, both for the plugin's own updates, and neither about a shopper.

- **The update check.** When WordPress checks for plugin updates, the plugin reads `info.json` from the agency's public releases: at most once in 12 hours, or once an hour after a read that failed.
- **The download.** When someone installs an update, WordPress downloads the release's zip from the same repository, and the plugin checks its SHA-256 before it is installed.

Neither carries the site's address, the version of WordPress or WooCommerce, or any identifier: the user agent of both names only the plugin and its version, in place of WordPress's default, which includes the site's address (`UpdaterTest`). GitHub sees the server's IP address, as any host does. `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter turns both off, and then nothing leaves the site at all.

## What is left behind, and when

- **Deactivation** keeps everything but pending scheduled actions and the upgrade lock, so reactivation finds the shop as it was.
- **Uninstall** removes everything in the table above, on every site of a network.
- **A site deleted from a network** has its two tables dropped with it where the plugin is loaded on the request that deletes it: active for the network, or on the site the deletion is made from. Deleted from elsewhere, with the plugin active only on that site, its two tables stay in the database until someone drops them. They hold no personal data before milestone 8, when saved builds arrive; closing the gap before then is raised with Zach in `docs/reviews/M1.md`.

## Who sees it

Only people who can already read the site's database or WooCommerce's logs. No screen of the plugin's shows any of it in 0.1.0.
