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
| Table | `{prefix}cbuild_specs` | Empty in 0.1.0. From milestone 3: one row per part and spec. | No | Uninstall; the site's deletion on a network | `ActivationTest`, `UninstallTest` |
| Table | `{prefix}cbuild_builds` | Empty in 0.1.0. From milestone 8: saved builds. | Will be | Uninstall; the site's deletion on a network | `ActivationTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, action `cbuild_migrate` | A queued upgrade run, with no arguments. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `InstallHooksTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed upgrade step's name, its cursor and the error's message. | No | WooCommerce's own log retention | `MigrationRunnerTest`, `MigrationTest` |
| Object cache | Group `cbuild` | Nothing in 0.1.0. | No | Uninstall flushes the group | `UninstallTest` |

Order item meta is part of the shop's order records and is kept on uninstall (spec: Uninstall). None is written in 0.1.0.

## What leaves the site

Two requests, both to GitHub, both for the plugin's own updates, and neither about a shopper.

- **The update check.** When WordPress checks for plugin updates, at most twice a day, the plugin reads `info.json` from the agency's public releases.
- **The download.** When someone installs an update, WordPress downloads the release's zip from the same repository, and the plugin checks its SHA-256 before it is installed.

Neither carries the site's address, the version of WordPress or WooCommerce, or any identifier: the user agent of both names only the plugin and its version, in place of WordPress's default, which includes the site's address (`UpdaterTest`). GitHub sees the server's IP address, as any host does. `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter turns both off, and then nothing leaves the site at all.

## Who sees it

Only people who can already read the site's database or WooCommerce's logs. No screen of the plugin's shows any of it in 0.1.0.
