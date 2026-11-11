# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html). One entry per milestone; `v1.0.0` closes milestone 9.

## [Unreleased]

### Added

- The product type `cbuild_kit`, a kit: virtual, not purchasable as a product, its price the assembly fee and never in WooCommerce's price fields, so a kit is not sold with the plugin switched off.
- A kit stays out of the catalogue, search results, product feeds, WordPress's sitemap and Yoast SEO's, and prints no product markup. Its catalogue visibility is always hidden.
- The shop's kits are read from its kit products, and a slot's parts from its sources: the products in its categories, of its brands and with its attribute values, and any it names, each variable product as its variations. A part is one a shopper could be shown and could buy (D-101).
- The Catalogue check's kit rules run on the shop's kits: a slot with no parts or nothing in stock, a part missing a spec its slot needs, a preset that no longer fits.
- The Build tab on a kit's product screen: its slots (label, help, required, units, the categories, brands, products and attribute values that may fill it, a default part), the rules R1 to R6 with their settings, up to six presets and the assembly settings. A section that fails its checks is not saved while the rest is, each problem is a notice that links to its field, and the tab shows the section once more as it was typed. Rows move by Move up and Move down as well as by dragging. Saved by who may manage the shop (D-106, D-108).
- Five more keys of the option `cbuild_settings`, set at WooCommerce > Settings > Builder and saved with the rest or not at all: `build_discount` and `fixed_coupons`, which the cart reads from 0.5.0; `rate_limit` and `trusted_proxy_header`, for the state route; and `kit_defaults`, the assembly settings a new kit's Build tab starts from (D-109).
- A part's stock count is held against what a build takes of it. A part the shop holds too few of is not recommended and is not the closest fit in a change list, and the validator refuses it with the new problem code `part_short` (#14).
- What a part's card shows (its name, page, price, availability and photo, as the shop shows them) is kept in the object cache per part for an hour at most, and read again when the part is saved, restocked, sold, put on or taken off sale, or deleted (D-111).
- The state route, `GET cbuild/v1/kits/<id>/state` (registered as `cbuild/v1/kits/(?P<id>[1-9][0-9]{0,17})/state`), with the arguments `picks`, `previous`, `step`, `assembly` and `locale`: the state of a build of a kit, as `schema/state.json` describes it. Each step's pick, the part it starts on and how many parts it offers and hides and why; the current step's options, 24 at most, each with its fit, its reasons as sentences and its card; the picks refused; what keeps the build from the cart; the change list when a change breaks a later pick; and the total with the assembly fee and the build discount. Public and read-only, answered `no-store`, in the language asked for when the site has it. A kit that is not published is answered as one that is not there (`cbuild_kit_not_found`).
- The state route's rate limit: the setting `rate_limit` a minute for each address, then 429 `cbuild_rate_limited` with `Retry-After`. What is counted is a salted hash of the address and the minute, kept two minutes at most; the address is stored nowhere (D-112).
- Five filters. `cbuild_part_available` lets a plugin rule a part out of every kit. `cbuild_price_context` lets a plugin that prices some shoppers apart say what tells them apart, so each is shown a card of their own. `cbuild_slot_label`, `cbuild_preset_label` and `cbuild_kit_text` let a translation plugin answer for the owner's own words: a slot's label and help, a preset's name and description, the lead time and the returns notice. The state route's answer carries the kit's texts and presets (D-114).
- Two abilities, `creative-skateboard-builder/check-build` and `creative-skateboard-builder/price-build`: read-only, in the plugin's category, they take a kit and its picks and give the state route's own answer to whether the build may go to the cart and to what it costs (D-115).
- The state route answers within its budget on a large catalogue: 53 ms at p95 with 2,000 parts in a kit and 500 in the step, on a site with no persistent object cache. Each slot's parts are kept for an hour at most, in a transient, and forgotten when a product, a price, a stock count, a term or a spec moves; the first request after that reads them from the shop again (D-116).
- The action `cbuild_parts_changed`, which a plugin does when its answer to `cbuild_part_available` changes, or a price it sets, with no product saved. The option `cbuild_parts_mark`, a random mark the kept lists carry.
- A kit's four documents as product meta, each as JSON text: `_cbuild_slots`, `_cbuild_rules`, `_cbuild_presets` and `_cbuild_assembly`. Their schemas have been public since 0.2.0.

### Changed

- A card's price is plain text, as its name is, and no longer WooCommerce's markup: the route's answer is data, and the page that prints it escapes it (D-112).
- A settings save asks for a reindex only when the attribute mapping or the order of the sources changed.
- A value the Part specs tab does not save is named in a notice that is a link to its field: following it opens the tab and the field's group and moves focus to the field. A variation's notice leads to the variation.
- Site Health's Info tab shows the index's state in words, and copies its code. The ability's schema descriptions are translated.
- The settings screen shows a problem with the order of the sources under the order alone.
- The Part specs tab opens the group a product's kit slot needs, as well as any group with a value.
- Where the shop has kits and none takes a product, its variations draw no Part specs fields and its tab says how to make it a part (D-105).
- `composer test:integration:yoast` runs the tests that need Yoast SEO, with Yoast SEO 28.8 put in the integration site for the run (D-102).
- The index's and the import's services are built when a part is saved or a job runs, not on every request.
- CI's mutation job may take 25 minutes, up from 15, which a full run on `main` reached, and runs Infection on every core of its runner, where it had run on one (D-107).
- `composer api:check` also holds Site Health's test ids, each WP-CLI command's arguments and flags, the CSV's columns and each ability's output schema.

## [0.3.1] - 2026-11-10

### Fixed

- The Part specs panel no longer pushes a product's edit form past PHP's `max_input_vars` (issue #23). It added 21 named fields to the product's tab and to each variation WooCommerce loads, 15 to a page, on every product. Where the limit is 1,000, a common host default, PHP kept the first 1,000 fields of an Update and dropped the rest without an error: other plugins' fields, the short description, the slug. A group of fields that is closed is now drawn disabled and sends nothing, and the screen's script enables a group when it opens. A part with no specs adds no field to the form; one with specs adds its open groups'. A group that sends nothing keeps what the part has.
- A link to a variation's fields (`#cbuild_variation_<id>`) checks a frame later that the control it moved focus to is still in view, and scrolls it back if the page moved.

## [0.3.0] - 2026-11-10

### Added

- The specs index's sources, as its `source` column holds them: `field`, `attribute` and `table`. The indexer keeps one value per part and spec, the winner by the shop's order with the maker's chart first by default, and records where sources disagree (D-069).
- A part's own specs in the product meta `_cbuild_specs`, a value per spec key; a variation left blank for a spec takes its parent's.
- The option `cbuild_settings` with its first two keys: `attribute_mapping` (which attribute feeds which spec, in which unit) and `source_order` (which source wins where two disagree).
- The settings screen, WooCommerce > Settings > Builder: the attribute mapping, a line per attribute, and which source wins. A save with a mistake in it stores nothing and names each mistake beside its field. `schema/settings.json` is the shape the option is written in (D-073).
- The index is kept in the background. Saving or deleting a product queues the `cbuild_index_part` action for it, and the request that saves indexes nothing. Saving the settings or editing a term of a mapped attribute queues `cbuild_reindex`, which indexes every part again, 100 at a time, from a place it keeps in the option `cbuild_reindex_state`, so one stopped halfway carries on (D-071).
- Spec tables: the post type `cbuild_spec_table`, a maker's size chart with a row per size in the meta `_cbuild_rows`. A part names a table and a size in the product meta `_cbuild_spec_table` and takes that row's values; a variation left blank takes its parent's. Independent's, Thunder's and Venture's charts are made by a migration, each marked with `_cbuild_seed`, on a new site and an upgraded one alike. Saving, binning or deleting a table indexes every part again. (D-072).
- The Part specs panel: a tab of the product data box on simple and variable products, and a group of fields inside each variation. A field takes the shop's decimal separator or a fraction, and what is stored is the same in every locale. A value in use that comes from the parent product, a mapped attribute or a maker's chart is named beneath its field. A value that is wrong is not saved, what the part had is kept, and the rest of the product saves (D-074).
- WooCommerce > Builder, with the spec tables' screen: the list of charts, a chart's sizes to edit, a chart added by its maker's name, and a chart deleted after the page asks. A chart saves whole or not at all, and one that is refused comes back as typed with each problem in its row. Every form works with scripts off (D-075).
- WooCommerce > Builder > Import and export: every part's own specs as a CSV file, out and in, with the columns `id`, `sku`, `name`, `spec_table`, `spec_size` and one per spec key. A file that is chosen is checked and shown, with what it would change and each row it cannot read, and nothing is written until the person who sent it confirms. A confirmed import is written in the background by the action `cbuild_import`, 100 rows at a time, from a place it keeps in the option `cbuild_import_state`. A cell of an export that a spreadsheet would run is neutralised (D-076).
- WooCommerce > Builder > Catalogue check: run when asked, it reads every part and lists what keeps one out of the builder, each with a link to the part's fields: two sources that disagree about a spec, a value that cannot be read (a chart that is gone, a size its chart does not have), a reindex or an import that stopped, and the plugin's failed background jobs. For each kit it also lists parts that lack a spec a rule needs, specs a rule had to assume, slots with no parts or none in stock, and presets that can no longer be bought; a shop has kits from 0.4.0. It changes nothing.
- WP-CLI: the commands `cbuild specs` (`wp cbuild specs export [<file>]` and `wp cbuild specs import <file> [--dry-run] [--key=<id|sku>]`), `cbuild check` and `cbuild reindex` (`wp cbuild reindex [--background]`), each the service its screen runs. A command exits 1 when a file or a row is not read, a batch fails, or the check finds anything.
- The ability `creative-skateboard-builder/catalogue-check` (WordPress's Abilities API), in the category `creative-skateboard-builder`: the Catalogue check's findings for the shop's own tools, for those who can manage WooCommerce. Read-only, and served over REST by `GET`.
- Site Health: the tests `cbuild_index` (critical when a reindex stopped after its failures, recommended when one stopped part way) and `cbuild_jobs` (recommended when a background job of the plugin has failed), and a section on the Info tab with the plugin's versions and settings.
- Site Health: the asynchronous test `cbuild_catalogue`, which runs the Catalogue check and is recommended when it finds something in a part or a kit, with the counts and a link to the check. Site Health's page reads it from the REST route `cbuild/v1/site-health/catalogue-check`, which answers those who can see Site Health (D-094).
- The option `cbuild_reindex_owed`: a site activated while WooCommerce is missing has no queue, so the reindex is owed and asked for on the first request that finds one (D-095).
- The seeded charts hold each maker's own figures: Thunder's eight sizes from thundertrucks.com, with the 181 added and a deck range for every size, and Venture's five from venturetrucks.net (D-093).
- Every part is read into the index after an install, after an upgrade's migrations and on activation, in the background, 100 at a time. WooCommerce > Builder > Catalogue check has a button, "Read every part again", to start it by hand (D-082).
- A term of a mapped attribute that is deleted asks for a reindex, as an edited one does (D-082).
- A mapping line names one attribute: `pa_width` is the shop's Width taxonomy, and `Width` is a product's own attribute of that name. A variation with no value of its own for a mapped attribute takes its parent's, where the parent does not use the attribute for variations, and the Part specs panel says so (D-089).
- The option `cbuild_import_rows`: the rows of the specs import in hand, written once when the file is read. `cbuild_import_state` keeps the import's place and counts, so a batch no longer saves the whole file again (D-090).
- A size an import or the panel names is matched to a chart's row by its number when no name matches: `5` finds Venture's `5.0`, as a spreadsheet writes it (D-091).
- A narrowest deck wider than the widest is refused, on the Part specs panel, in a chart's row and in an import, with both figures named. A chart cannot take a name another chart has (D-092).
- `wp cbuild reindex` and `wp cbuild specs import` run their batches themselves and leave nothing in the queue. The reindex drops index rows for products that are no longer parts (D-092).
- A link from the Catalogue check moves keyboard focus to the fields it opens, and a unit's problem on the settings screen is tied to its control (D-092).
- An import whose job was lost, as when the plugin is switched off while one runs, goes on from its place when Import and export is opened. Site Health's `cbuild_jobs` also reports an import that failed or stalled (D-082).
- A link from the Catalogue check opens the product's Part specs tab, or the variation it names. The script that does it is the plugin's first built file, `build/admin/part-specs.js`, loaded on the product screen alone (D-083, D-086).
- `Domain\Kit\Part::$stock`: a part's managed stock count, or null where stock is not managed. Nothing reads it yet.
- A value a person types is read with the shop's decimal separator and, when that does not read, with a point: in a shop that writes a comma, `8,25` and `8.25` are one value, on the Part specs panel, in a chart and in a mapped attribute.

### Fixed

- Uninstall forgets the ID Action Scheduler caches for the plugin's group. On a shop with a persistent object cache, a reinstall within ten minutes filed its jobs under a group that was gone (D-084).
- Deactivation unschedules a pending action whatever its arguments. With the group given, Action Scheduler cancelled only actions with no arguments, which was every action until this version (D-071).

### Changed

- A class that attaches hooks is built only on a request where its hooks can fire: wp-admin, WP-CLI, cron, REST or the storefront (D-067).
- A migration's status is an enum, and the rule for running migrations on an admin page is a service of its own (D-067). Nothing stored changes.
- The object-cache leg of the integration suite runs on a Redis server with Redis Object Cache's drop-in (D-066).
- The browser tests log in once for the run, and a draft pull request runs one desktop and one phone engine; every project runs once it is ready (D-085).
- Mutation testing covers the admin's labels and sentences, and every class it leaves out has its reason beside it (D-087).

## [0.2.0] - 2026-11-09

### Added

- The kit's four document schemas, `schema/kit-slots.json`, `schema/kit-rules.json`, `schema/kit-presets.json` and `schema/kit-assembly.json`, each at `schema_version` 1, with the domain's value objects that read the same documents and an integration test that holds the two together.
- The spec keys: `deck_width`, `deck_length`, `wheelbase`, `hole_pattern`, `axle_width`, `deck_min`, `deck_max`, `truck_height`, `height_class`, `max_wheel`, `baseplate`, `axle_diameter`, `wheel_diameter`, `riser_thickness`, `bolt_length`, `bearing_bore`, `spacers_built_in`, `grip_width`, `grip_length` and `set_size`.
- The fit rules R1 to R6 (`Domain\Rule`), each answering with findings: a severity, a message code, the values behind it, its source and the slots it read (D-055). The message codes: `spec_missing`, `deck_below_range`, `deck_above_range`, `axle_narrower`, `axle_wider`, `pattern_mismatch`, `pattern_assumed`, `wheel_needs_riser`, `wheel_no_riser`, `limit_from_class`, `bolts_too_short`, `bolts_too_long`, `bore_mismatch`, `axle_assumed`, `spacers_built_in`, `grip_too_narrow`, `grip_too_short` and `length_unchecked`. R3's seeded limits were checked against their sources and stand (D-054).
- The fit engine (`Domain\Fit`): each slot's options against the picks before it, the parts it hid grouped by rule and reason, the quantity a build takes of each part (R7), and a whole build held together for the cart (D-056).
- The change list for a pick that breaks later ones (the picks kept, each that no longer fits with the closest part that does, the build proposed and the undo) and the recommendation each slot after the deck starts on (D-057).
- Pricing (`Domain\Pricing`): each part at the price passed in times the quantity the build takes, the build discount off each part's price rounded half up, and the assembly fee on the parent, all in whole minor units (D-058).
- The build validator the cart will use, which is the engine asked about a whole build, with the problems `slot_unknown`, `slot_empty`, `part_not_in_slot`, `part_sold_out` and `part_blocked`; and the invariant, over seeded random builds on a generated catalogue: what the engine offers, the validator accepts, and what it hides, the validator refuses (D-059).
- The picture's layout, from the specs to each layer's position and size: the deck by its width and length, the trucks by the wheelbase or the shape's usual one, the wheels at the axles' ends and the grip as a strip along the edge. It names every spec it had to assume (D-060).
- The engine's benchmark: each slot of a generated catalogue at 500, 1,000 and 2,000 candidates. It fails when the slowest slot at 500 takes 40 ms or more, or when the time grows faster than the candidates. `docs/BENCHMARK.md` has the figures.
- The rules' ids `r1`, `r2`, `r3`, `r4`, `r5`, `r6` and `r7`, and the names of the values each message code carries, which a finding may not add to.
- The refusal codes, each the `reason` of a typed exception: `document_not_an_object`, `document_unknown_version`, `document_field_missing`, `document_wrong_type`, `document_out_of_range`, `document_bad_measure`, `document_bad_key`, `document_duplicate`, `slots_deck_not_first`, `slots_risers_after_hardware`, `rules_out_of_order`, `presets_unknown_slot`, `presets_slot_not_filled`, `spec_wrong_kind`, `spec_unknown_choice`, `spec_not_positive`, `spec_too_many`, `measure_malformed`, `measure_zero_denominator`, `measure_negative`, `part_id_not_positive`, `part_price_negative`, `discount_out_of_range` and `price_decimals_over_eight`.
- R1 holds a truck to whichever end of its deck range the maker publishes; the other end is open (D-062).
- The assembly fee in the shop's minor units at up to eight price decimals.
- After Claude in Cowork's review (D-064): a slot that takes one unit needs no `set_size` on its parts; the risers step starts on the riser the wheels ask for; a swapped truck is the one nearest the new deck's width; a row of the specs index this version cannot read is passed over, and the part keeps its other specs; a missing spec's finding has the source `none`; a whole number followed by a fraction of one or more is refused; and the schemas' patterns refuse a trailing line break, as the domain does.
- Gates: no words in a string in `src/Domain` (11), no exception of PHP's own made there (12), and no statement at the top of a file there that is not a declaration (13). A mutant of the domain that only static analysis catches must be on a list (D-063). The runs that measure the domain, and the benchmark, fail when they find nothing to measure.
- `composer api:check` reads the schemas, by path and by content, the spec keys, with each one's kind and unit, the message codes with their value names, the build problems, the rule ids and the refusal codes. It also fails when `docs/HOOKS.md` dates an item to a version newer than the plugin header's, or `readme.txt` has no entry for that version (D-062).

### Changed

- The update's "View details" popup and the release's notes show `readme.txt`'s changelog, written for shop owners, not this file's (D-053).
- `composer api:check` also fails when the changelog's entry for an item's version does not name it (issue #5). The 0.1.0 entry now names its options and its site transient.
- The line floor is on all of `src/`, read from the unit and integration suites together by `composer test:coverage:all`; no class is left out by name (D-050, issue #6). It is 95 %, up from 90 %, and each file's direct-access guard is left out of the count by one rule in `tools/coverage-merge.php` (D-061).
- Infection's floor outside the domain is 95 %, up from 90 % (D-061).
- The weekly canary fails on a high or critical advisory anywhere in the npm tree (D-051, issue #9).
- The toolchain: TypeScript 6.0, the newest `typescript-eslint` accepts, and `actions/github-script` 9 in the canary. Dependabot leaves TypeScript 6.1 and later, and `@types/node`'s major versions, alone (D-049).

### Fixed

- The browser test of the Plugins screen opens a page of its own, so a request cancelled by a reload is no longer reported as the screen's error (D-049).

## [0.1.0] - 2026-11-09

Milestone 1, the foundation. Nothing a shopper or shop owner sees yet: the skeleton the next eight milestones build on, with every CI gate wired and passing against it.

### Added

- The bootstrap (`creative-skateboard-builder.php`): headers, constants, the PHP and WordPress floors checked before the autoloader, the activation and deactivation hooks, compatibility declared for HPOS and the Cart and Checkout blocks, and boot on `plugins_loaded`. Below PHP 8.5, WordPress 7.0 or WooCommerce 11.2 the plugin does nothing and shows one notice to people who can manage plugins (D-023).
- `Plugin`, a hand-written `Container` and the `Hookable` contract, in the PSR-4 layers `docs/STANDARDS.md` sets out, enforced by deptrac.
- The two tables, `cbuild_specs` and `cbuild_builds` (`Install\Schema`), created by the first migration on activation with nothing seeded (D-037). `dbDelta()` a second time changes nothing, and a table it fails to create fails the migration.
- The migration framework (`Install\Migrations`): versioned, ordered, idempotent steps; a lock taken in one statement that names its holder and expires after 300 seconds (D-021, D-033); batches that resume from a saved cursor, read from the table once the lock is held; a failed step retried after 1, 5 and 30 minutes, then hourly. Migrations run inline only for a shop manager on an admin page, for up to 2 seconds, and otherwise in one queued `cbuild_migrate` action, which queues its own continuation (D-022, D-043).
- Activation and deactivation on a single site and across a network, with a site added later installing itself (D-025); uninstall, which removes the tables, options, transients, scheduled actions with their logs and group, and the cache group, on every site, with or without WooCommerce active and on a PHP below the floor (D-024, D-039, D-043), and keeps order item meta.
- `Port\Clock` and `Port\Logger` with `Adapter\SystemClock` (UTC) and `Adapter\WooCommerceLogger` (source `creative-skateboard-builder`); `Port\Queue` over Action Scheduler.
- The updater (`Updates\`): reads the agency's release manifest over HTTPS, takes a package only from this plugin's own releases, refuses a zip whose SHA-256 does not match, and names only the plugin in its two requests. Off with `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter, whenever either is set; its own package is then refused, not installed unchecked.
- The public API: the `cbuild_updates_enabled` filter; the constants `CBUILD_DISABLE_UPDATES`, `CBUILD_VERSION`, `CBUILD_FILE`, `CBUILD_PATH` and `CBUILD_URL`; the options `cbuild_db_version`, `cbuild_migrations` and `cbuild_migration_lock`; the site transient `cbuild_update_manifest`; the tables `cbuild_specs` and `cbuild_builds`; the `cbuild_migrate` action and the log source `creative-skateboard-builder`, each in `docs/HOOKS.md` and the snapshot (`tests/Fixtures/api-surface.json`). `composer api:check` fails when the code, the snapshot and the document disagree.
- Tests: 153 unit tests; 62 integration tests in wp-env on the newest and oldest WordPress and WooCommerce, MySQL 8.4, MariaDB 10.11, HPOS off, HPOS syncing, multisite and with WordPress told a persistent object cache is present (a real shared cache is issue #4), among them two real processes racing for the migration lock; a browser smoke test on nine Playwright projects, with axe.
- CI (`.github/workflows/ci.yml`): PHPCS, PHPStan level 9, deptrac, the grep gates and their self-test, the coverage floors, the API snapshot, the JavaScript and CSS standards, the asset budgets, Infection and StrykerJS, the benchmarks, the integration matrix, Plugin Check on the built zip, the zip installed, upgraded over itself and uninstalled, the browser projects, Lighthouse CI, a release dry run, the dependency audits and a gitleaks scan, with every action pinned to a commit SHA. A weekly canary on WordPress trunk and WooCommerce's pre-release, the release workflow with CycloneDX SBOMs, and Dependabot.
- Docs: `HOOKS.md`, `SETTINGS.md`, `SECURITY.md`, `PRIVACY.md`, `A11Y.md`, `BENCHMARK.md`, `DECISIONS.md` (D-020 to D-048), `reviews/M1.md`, `README.md` and `readme.txt`; the POT file.
