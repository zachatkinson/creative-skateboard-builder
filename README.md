# Creative Skateboard Builder

A WooCommerce plugin that sells complete skateboards built from parts the shop already stocks. It checks fit from each part's specs, draws a picture of the build, keeps the total in view, and gives each part its own cart and order line. Built by Creative Branding; Skatehouse is the first shop.

Version 0.1.0 is the foundation: the plugin's two tables, its upgrade steps, uninstall and its update check. The builder itself arrives over the next milestones ([docs/ROADMAP.md](docs/ROADMAP.md)).

- The spec: [docs/SPEC.md](docs/SPEC.md) · The roadmap: [docs/ROADMAP.md](docs/ROADMAP.md)
- Decisions: [docs/DECISIONS.md](docs/DECISIONS.md)
- Hooks and the public API: [docs/HOOKS.md](docs/HOOKS.md) · Settings: [docs/SETTINGS.md](docs/SETTINGS.md)
- Security: [docs/SECURITY.md](docs/SECURITY.md) · Privacy: [docs/PRIVACY.md](docs/PRIVACY.md) · Accessibility: [docs/A11Y.md](docs/A11Y.md)
- Benchmarks: [docs/BENCHMARK.md](docs/BENCHMARK.md)
- How it is judged: [docs/AUDIT.md](docs/AUDIT.md) · Milestone reviews: [docs/reviews/](docs/reviews/)

Requires WordPress 7.0 or newer, WooCommerce 11.2 or newer and PHP 8.5 or newer, on MySQL 8.0 or MariaDB 10.6 or newer, as WordPress itself asks. Tested on MySQL 8.4 and MariaDB 10.11. GPL-2.0-or-later.

## Developing

```
composer install && npm ci
composer check && npm run check
npx wp-env start
composer test:integration && npm run plugin-check && npx playwright test
```

`npm run env:start` serves the site at http://localhost:8898 and the integration suite's site at http://localhost:8899. See [CLAUDE.md](CLAUDE.md) for every command and the rules the code is held to.

## Installing a release

Releases are published to [zachatkinson/creative-releases](https://github.com/zachatkinson/creative-releases) once CI has passed on the tagged commit. Upload the zip under Plugins > Add New > Upload Plugin. From then on WordPress offers each new version on the Plugins screen, and the plugin checks the zip's SHA-256 before WordPress installs it.
