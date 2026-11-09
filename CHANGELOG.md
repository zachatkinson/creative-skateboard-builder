# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html). One entry per milestone; `v1.0.0` closes milestone 9.

## [Unreleased]

## [0.1.0] - 2026-11-09

Milestone 1, the foundation. Nothing a shopper or shop owner sees yet: the skeleton the next eight milestones build on, with every CI gate wired and passing against it.

### Added

- The bootstrap (`creative-skateboard-builder.php`): headers, constants, the PHP and WordPress floors checked before the autoloader, the activation and deactivation hooks, compatibility declared for HPOS and the Cart and Checkout blocks, and boot on `plugins_loaded`. Below PHP 8.5, WordPress 7.0 or WooCommerce 11.2 the plugin hooks nothing and shows one notice (D-023).
- `Plugin`, a hand-written `Container` and the `Hookable` contract, in the PSR-4 layers `docs/STANDARDS.md` sets out, enforced by deptrac.
- The two tables, `cbuild_specs` and `cbuild_builds` (`Install\Schema`), created on activation with nothing seeded. `dbDelta()` a second time changes nothing.
- The migration framework (`Install\Migrations`): versioned, ordered, idempotent steps; a lock taken in one statement that expires after 300 seconds (D-021); batches that resume from a saved cursor; a first schema step and a no-op step. Migrations run inline only for a shop manager on an admin page, and otherwise in one queued `cbuild_migrate` action (D-022).
- Activation on a single site and across a network, with a site added later installing itself (D-025); deactivation, which unschedules pending actions and releases the lock; uninstall, which removes the tables, options, transients, scheduled actions with their logs and group, and the cache group, with or without WooCommerce active (D-024), and keeps order item meta.
- `Port\Clock` and `Port\Logger` with `Adapter\SystemClock` (UTC) and `Adapter\WooCommerceLogger` (source `creative-skateboard-builder`); `Port\Queue` over Action Scheduler.
- The updater (`Updates\`): reads the agency's release manifest over HTTPS, refuses a package from another host and a zip whose SHA-256 does not match, and sends nothing about the site. Off with `CBUILD_DISABLE_UPDATES` or the `cbuild_updates_enabled` filter.
- The public API snapshot (`tests/Fixtures/api-surface.json`), `docs/HOOKS.md` and `composer api:check`, which fails when the code, the snapshot and the document disagree.
- Tests: 115 unit tests; 42 integration tests in wp-env on the newest and oldest WordPress and WooCommerce, MySQL 8.4, MariaDB 10.11, HPOS off, HPOS syncing, multisite and a persistent object cache, among them two real processes racing for the migration lock; a browser smoke test with axe on nine Playwright projects.
- CI (`.github/workflows/ci.yml`): PHPCS, PHPStan level 9, deptrac, the grep gates, the coverage floors, the API snapshot, the JavaScript and CSS standards, the asset budgets, Infection, the benchmarks, the integration matrix, Plugin Check on the built zip, the browser projects, Lighthouse CI, the dependency audits and a gitleaks scan, with every action pinned to a commit SHA. A weekly canary on WordPress trunk and WooCommerce's pre-release, the release workflow with a CycloneDX SBOM, and Dependabot.
- Docs: `HOOKS.md`, `SETTINGS.md`, `SECURITY.md`, `PRIVACY.md`, `A11Y.md`, `BENCHMARK.md`, `DECISIONS.md` (D-020 to D-032), `reviews/M1.md`, `README.md` and `readme.txt`; the POT file.
