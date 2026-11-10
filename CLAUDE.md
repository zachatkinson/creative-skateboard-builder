# Creative Skateboard Builder

Creative Skateboard Builder is a WooCommerce plugin that sells complete skateboards built from parts the shop already stocks. Skatehouse is the first shop. The plugin:

- checks fit from each part's specs;
- draws a picture of the build;
- keeps the total in view;
- gives each part its own cart and order line.

It is the agency's showpiece, built to the twelve-dimension audit in `docs/AUDIT.md`.

**Read before any code:** `docs/SPEC.md` (what to build; start with "What changed on 9 Nov"), then the milestone you're on in `docs/ROADMAP.md`.

**Read as needed:**

- `docs/PROJECT.md`: the shop, the phases, the agency's other plugins.
- `docs/STANDARDS.md`: how the code is written.
- `docs/AUDIT.md`: how it's judged.
- `docs/UX-RESEARCH.md`: patterns P1 to P12, failures W1 to W9.
- `docs/DECISIONS.md`: what's decided. Continue the numbering from D-020.

## How each milestone runs

1. `/plan-milestone`: write the plan into `docs/reviews/M<n>.md`, with the risks under all twelve dimensions. Ask Zach anything that needs him before writing code.
2. Build. The spec's test cases come first. Run `/verify` before every commit (docs-only commits skip it; in milestone 1 it runs the gates that exist so far). Keep commits small and green.
3. `/audit-milestone`: the `spec-reviewer` and `auditor` agents read the diff since the last tag. Fix every blocker and should-fix with a test that fails without the fix, then write the review.
4. Open a draft pull request with the template and a `CHANGELOG.md` entry, and mark it ready once the gates pass locally (D-041). After Claude in Cowork's review, its fixes land and Claude merges, once the full CI run is green.
5. Stop. Don't start the next milestone until Zach says "Milestone N is approved". The next `/plan-milestone` tags the merge: `v0.<n>.0`, and `v1.0.0` for milestone 9.

## Commands

- `composer install && npm ci`
- `composer lint`: PHPCS with WordPress-Extra, WordPress-Docs, WordPress-VIP-Go and PHPCompatibilityWP (8.5-), zero warnings
- `composer analyse`: PHPStan 2, level 9 on `src/` and level 10 on `src/Domain`, with the WordPress and WooCommerce stubs and the strict, deprecation and disallowed-calls rules
- `composer arch`: deptrac's layers
- `composer gates`: `tools/gates.sh`. It checks:
  - SQL outside repositories;
  - banned functions and `date()`;
  - `strict_types`;
  - outbound requests;
  - WordPress in the domain;
  - em dashes and banned words in the POT;
  - a job of `release.yml` that holds the token and runs anything but `gh`, and the release plan's cases.
- `composer test:unit`: PHPUnit 13, no WordPress
- `composer test:coverage`: unit tests with the domain's floors: 100 % of lines and branches in `src/Domain` (Xdebug's path coverage, since pcov measures lines only)
- `composer test:coverage:all`: the unit and integration suites' line coverage merged, with the floor: 95 % of all of `src/`, each file's direct-access guard left out (D-050, D-061). Needs the integration site, which `npm run env:start` starts with Xdebug in coverage mode
- `composer test:benchmark`: the server budgets
- `composer infection`: 100 % MSI on `src/Domain`, 95 % elsewhere
- `composer api:check`: the public API against `docs/HOOKS.md`, `tests/Fixtures/api-surface.json` and the changelog's entry for each item's version. `composer api:snapshot` rewrites the snapshot; do it, `HOOKS.md` and the changelog in the same commit as the change
- `composer check`: lint, analyse, arch, gates, unit tests and api:check
- `npm run env:start`: two wp-env environments on WordPress 7.1.4, WooCommerce 11.2.2 and PHP 8.5: the browser site at :8898 (`.wp-env.json`, with Plugin Check) and the integration site at :8899 (`.wp-env.tests.json`, D-047). `npx wp-env start` starts the browser site alone; a command for the integration site takes `--config=.wp-env.tests.json`, and `--xdebug=coverage` when it starts it. `npm run env:oldest` switches to WordPress 7.0 with WooCommerce 11.2.0, and `npm run env:newest` back. `@wordpress/env` is pinned to the major Creative Search uses (^12). Search still runs its integration suite in wp-env's built-in tests site, which wp-env 12 deprecates
- `composer test:integration`: PHPUnit 9.6 from `tools/integration/`, inside the integration site, as Search's `tools/integration.sh`. The variants are `composer test:integration:hpos-off`, `:hpos-sync`, `:multisite` and `:object-cache`
- `npm run build` / `npm run start`: `wp-scripts --experimental-modules` (script modules, Interactivity API)
- `npm run types`: the state route's TypeScript types, generated from its JSON Schema. CI fails if the tree changes
- `npm run check`: ESLint, Stylelint, Prettier, `tsc --noEmit` and Vitest
- `npm run mutate:js`: StrykerJS on the builder's store (90 %)
- `npm run budget`: the script under 15 kB and the styles under 10 kB, compressed
- `npm run test:e2e`: Playwright with axe. The projects are `chromium-desktop`, `chromium-phone`, `webkit-desktop`, `webkit-phone`, `firefox-desktop`, `firefox-phone`, `no-js`, `fr-ca`, `rtl` and `visual`. `visual` runs only in CI's Linux container, so its baselines stay stable
- `npm run lhci`: Lighthouse CI on the builder page (LCP, CLS, TBT, SEO)
- `npm run plugin-check`: builds the zip and runs Plugin Check on it in wp-env. Any error or warning fails
- `composer i18n:pot` / `composer i18n:check` / `composer i18n:compile`: run inside wp-env (`.mo`, `.l10n.php` and script JSON)
- `composer audit:deps`: `composer audit` and `npm audit --omit=dev`
- `composer build`: `tmp/creative-skateboard-builder-<version>.zip`, without dev files
- `git tag v<version> && git push --tags`: run on the merge commit on `main`. `release.yml` publishes to `zachatkinson/creative-releases` once CI has passed on that commit. The zip anyone installs is the release's

## Rules

- **Layout.** PSR-4 under `src/`, in the layers `docs/STANDARDS.md` sets out; deptrac enforces them. `src/Domain` has no WordPress in it; if a domain class needs a shim to be tested, it's wrong.
- **One engine.** Hooks call services and hold no logic. There is one fit engine, in `src/Domain`, and the browser never decides fit or price.
- **Data access.** SQL only in `src/Repository/` and `src/Install/`, always prepared. Products and orders only through WooCommerce's CRUD.
- **Trust.** Nothing from the browser is trusted. Picks are IDs, re-validated on every use, and prices are the server's.
- **Markup.** One template per piece of the builder, rendered by the server and re-rendered by the store. No markup is built in JavaScript, and the store is private.
- **Strings.**
  - Every string goes through the text domain, and the domain returns codes, not English.
  - No concatenated sentences; `_n()` for counts.
  - Prices through `wc_price()`, units through the translatable patterns.
- **Time.** Times through the Clock (UTC), never `date()`, `time()` or MySQL `NOW()`.
- **No outbound requests** but the update manifest.
- **Nothing shop-specific in code.** No Skatehouse names. Shop choices are settings, or another plugin's answer to a hook.
- **Copy.** Plain words and sentence case. No em dashes, superlatives, exclamation marks or templated constructions.
- **Documentation:**
  - every public API item in `docs/HOOKS.md` with `@since`;
  - every setting in `docs/SETTINGS.md`;
  - everything stored in `docs/PRIVACY.md`;
  - every decision you make yourself in `docs/DECISIONS.md` with its reason;
  - a `CHANGELOG.md` entry per milestone.
- **Check before relying.** Where the spec names a WordPress or WooCommerce hook, function or event, confirm it in the source wp-env installed. Record any difference in `docs/DECISIONS.md` with the file and line.
- **Tests.**
  - The spec's cases come first.
  - A bug gets a failing test first, and a fix gets a deliberate-break run.
  - No floor is lowered and no test weakened to make a run pass.
  - A flaky test is fixed or quarantined with an issue the same day.

## Ask Zach before

- adding a run-time dependency, or a dev tool these docs don't already name;
- adding or changing a table after milestone 1;
- renaming or removing anything public;
- moving a floor;
- deciding anything the spec leaves to the owner;
- deferring a should-fix.

## Never

- Touch the demo (zachatkinson.com/demos/skate) or the live shop. This repository works in wp-env and CI only.
- Commit a credential, key or token. Secrets live in GitHub's secrets.
- Use a real person's address in tests (use `shopper+N@example.com`), or send mail outside wp-env.
- Change a sibling repository. When this brief says "as Creative Search", read `~/web-projects/creative-search` to see how, and copy the approach here.

## Quirks, learned in the sibling repositories

- Docker on this Mac is OrbStack (`open -a OrbStack`), not Docker Desktop.
- wp-env uses :8898 and :8899. Ports 8888 to 8897 belong to Stockroom, Search, Shipment Tracking, Restock and Reviews.
- The WordPress core test suite still needs PHPUnit 9.6. It lives with the Yoast polyfills in `tools/integration/`, a Composer project of its own. Register the plugin's Composer loader behind PHPUnit 9.6's, or PHPUnit 9.6 loads PHPUnit 13's classes and dies (Search D-021, D-023).
- The WordPress suite never reinstalls Action Scheduler's tables. Don't let a test depend on state another left behind (Search `reviews/M1.md`).
- `composer.json` pins `platform.php` to `8.5.99`, so no dependency can need more than the floor.
- Plugin Check doesn't read `phpcs.xml`. Suppress a sniff it runs inline (`phpcs:ignore` with `-- reason`), not in the ruleset.
- Run PHPCS through `composer lint`: it sets `error_reporting`, so PHP 8.5's deprecations from the sniffs don't swamp the report.
- The WP-CLI stubs don't cover WordPress 7. Stub the WP-CLI symbols PHPStan needs by hand in `tools/`.
- Firefox under Playwright on macOS 27 needs `CFFIXED_USER_HOME` set to a fresh directory (Search D-022).
- The demo's host has no SSH or WP-CLI. Anything a shop needs must also work from wp-admin.
