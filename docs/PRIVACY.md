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
| Transient | `cbuild_kit_draft_<kit>_<user>` | A section of a kit's Build tab that was not saved, as it was typed, and the sentences that say why. The kit's ID and the ID of the user who sent it are in the name. | No: the owner's own words and product IDs | The tab is next drawn for that user, or 1 hour; uninstall | `BuildTabTest`, `UninstallTest` |
| Table | `{prefix}cbuild_specs` | One row per part and spec: the product's or variation's ID, the spec's key, its value, which source it came from (the part's field, an attribute, a spec table), what in that source, and when it was read. Nothing about a person. | No | Uninstall; the site's deletion on a network; rebuilt from the products, never edited | `ActivationTest`, `UninstallTest`, `SpecIndexRepositoryTest` |
| Table | `{prefix}cbuild_builds` | Empty in 0.1.0. From milestone 8: saved builds. | Will be | Uninstall; the site's deletion on a network | `ActivationTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, action `cbuild_migrate` | A queued upgrade run, with no arguments. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `InstallHooksTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed upgrade step's name, its cursor and the error's message. | No | WooCommerce's own log retention | `MigrationRunnerTest`, `MigrationTest` |
| Option | `cbuild_settings` | The shop's settings: which attribute feeds which spec, which source wins, the build discount, where a fixed product discount coupon lands, the state route's rate limit and the name of the proxy's header, and the assembly settings a new kit starts from. The header's name is a name, such as `X-Forwarded-For`, and never an address. Nothing about a person. | No | Uninstall | `UninstallTest`, `SpecSourcesTest`, `SettingsPageTest` |
| Product meta | `_cbuild_specs` | On a product or variation: the part's own specs, a value per spec key. Nothing about a person. | No | Uninstall; with the product | `UninstallTest`, `SpecSourcesTest` |
| Product meta | `_cbuild_spec_table` | On a product or variation: the spec table and size it names. Nothing about a person. | No | Uninstall; with the product | `SpecTablesTest`, `UninstallTest` |
| Product meta | `_cbuild_slots`, `_cbuild_rules`, `_cbuild_presets`, `_cbuild_assembly` | On a kit product: its slots, which rules run, its ready builds and its assembly settings, each as JSON text. The owner's own words and product IDs. Nothing about a person. | No | Uninstall; with the product | `UninstallTest`, `KitProductTest` |
| Posts | Type `cbuild_spec_table`, with the meta `_cbuild_rows` and `_cbuild_seed` | A maker's size chart: its name, a row per size, where each row was read and on which day. The post's author is the user who made it, as WordPress records for any post; the seeded ones have none. | No | The shop deletes it; uninstall | `SpecTablesTest`, `UninstallTest` |
| Option | `cbuild_reindex_state` | Where the reindex is: its phase, its place, how many parts it has indexed, when it started and finished, and the last error's message. Nothing about a person. | No | Uninstall | `IndexJobsTest`, `UninstallTest` |
| Option | `cbuild_reindex_owed` | The value `1`, while a reindex waits for the site to have a queue. Nothing about a person. | No | When the reindex is asked for. Uninstall | `InstallHooksTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, actions `cbuild_index_part` and `cbuild_reindex` | A queued indexing job: for one product, its ID and how many times it has been tried; for the reindex, nothing. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `IndexJobsTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed indexing job's product ID or batch, and the error's message. | No | WooCommerce's own log retention | `IndexJobsTest` (unit) |
| Option | `cbuild_import_state` and `cbuild_import_rows` | The specs import in hand. `cbuild_import_rows` holds the rows read from the file (product IDs, SKUs, names and specs) while it waits to be confirmed and while it runs, and nothing about a person. `cbuild_import_state` holds its place and, after, the counts and the rows it refused. While it waits, the ID of the user who sent the file, so only they can confirm it; the ID is dropped when the import starts. The file itself is read where PHP received it and is never moved into the site or kept. | A user ID, for an hour at most | A waiting import: an hour, or until it is confirmed, put away or replaced. A finished one: the next file. Uninstall | `SpecImportTest` (unit), `ImportScreenTest`, `UninstallTest` |
| Action Scheduler | Group `creative-skateboard-builder`, action `cbuild_import` | A queued batch of a confirmed import, with no arguments. | No | It runs; deactivation unschedules it; uninstall deletes every row, log and the group | `ImportScreenTest`, `UninstallTest` |
| Log | Source `creative-skateboard-builder` | A failed batch of an import: its first row's number, how many times it has failed and the error's message. | No | WooCommerce's own log retention | `SpecImportTest` (unit) |
| Object cache, or a transient where the site has no persistent one | Group `cbuild`, key `rate_<hash>`; transient `cbuild_rate_<hash>` | From 0.4.0, how many times one address has asked the state route this minute: a number. The name is 40 characters of an HMAC-SHA256 of the address and the minute, keyed with the site's own secret (`wp_salt()`), so it names no address, cannot be turned back into one without the site's secret, and is a different name each minute. An IPv6 address is counted by its /64. The address itself is read from the connection, or from the header the setting `trusted_proxy_header` names, and is stored nowhere and logged nowhere. Nothing in 0.1.0 to 0.3.1. | No | 2 minutes at most; uninstall deletes every transient of the plugin's and flushes the group | `RateLimitTest`, `StateRouteTest`, `UninstallTest` |
| Table | `{prefix}cbuild_parts` | From 0.4.0, what a build needs to know of each part, one row per product or variation and price context: its parent, whether it may be sold, its brand, its price, whether it is in stock and how many, a variation's attribute values, its specs, and when it was read. The context is a hash of the currency, the language, the shop's tax display and the tax rates that apply, never of an address or a user. Nothing about a person. | No | A row is deleted when its product, price, stock, terms or specs move, and read again after one to two hours. Uninstall; the site's deletion on a network | `PartsTableTest`, `UninstallTest` |
| Object cache | Group `cbuild`, key `card_<part>` | From 0.4.0, what a part's card shows: its name, its page's address, its price and its availability as the shop words them, and its photo's address and size, for up to four price contexts. A price context is a hash of the currency, the language, the shop's tax display and the tax rates that apply, never of an address: where a visitor is does not reach the cache. Nothing in 0.1.0 to 0.3.1. | No | 1 hour; the part's product is saved, restocked, sold, put on or taken off sale, or deleted; uninstall flushes the group | `WooPartCardsTest`, `UninstallTest` |

Order item meta is part of the shop's order records and is kept on uninstall (spec: Uninstall). None is written in 0.1.0.

## What leaves the site

Two requests, both to GitHub, both for the plugin's own updates, and neither about a shopper.

- **The update check.** When WordPress checks for plugin updates, the plugin reads `info.json` from the agency's public releases: at most once in 12 hours, or once an hour after a read that failed.
- **The download.** When someone installs an update, WordPress downloads the release's zip from the same repository, and the plugin checks its SHA-256 before it is installed.

Neither carries the site's address, the version of WordPress or WooCommerce, or any identifier: the user agent of both names only the plugin and its version, in place of WordPress's default, which includes the site's address (`UpdaterTest`). GitHub sees the server's IP address, as any host does. `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter turns both off, and then nothing leaves the site at all.

## What is left behind, and when

- **Deactivation** keeps everything but pending scheduled actions and the upgrade lock, so reactivation finds the shop as it was.
- **Uninstall** removes everything in the table above, on every site of a network.
- **A site deleted from a network** has its tables dropped with it where the plugin is loaded on the request that deletes it: active for the network, or on the site the deletion is made from. Deleted from elsewhere, with the plugin active only on that site, its tables stay in the database until someone drops them. They hold no personal data before milestone 8, when saved builds arrive; closing the gap before then is raised with Zach in `docs/reviews/M1.md`.

## Who sees it

Only people who can already read the site's database or WooCommerce's logs, and from 0.3.0:

- **Who can manage WooCommerce** (`manage_woocommerce`): the Builder settings, the makers' charts, the specs sheet out and in, the Catalogue check on its screen and through the ability `creative-skateboard-builder/catalogue-check`, and the name of the manager whose import waits.
- **Who can edit products** (`edit_products`): a product's Part specs.
- **Who can see Site Health** (`view_site_health_checks`, an administrator): the three tests and the Info tab's section, which holds the plugin's versions, the source order, counts and the index's state. The Catalogue check's test, and the route `cbuild/v1/site-health/catalogue-check` that Site Health's page reads it from, give two counts: the findings and the parts they are in. None of it is marked private, since none of it is about a person or would help someone attack the site.
- **Who has a shell on the server:** the `wp cbuild` commands. A sheet written by `wp cbuild specs export <file>` is a file where that person put it; the plugin keeps no record of it and uninstall does not look for it.

Specs, charts and findings describe products. None of these screens shows a customer, an order or an address.
