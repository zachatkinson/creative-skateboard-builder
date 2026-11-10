# Benchmarks

The spec's budgets are tests: a build fails when one is missed. The numbers from each tag are added below.

## Budgets (spec: Quality bar, Performance)

| What | Budget | Measured by | From |
| --- | --- | --- | --- |
| The state route's server time, p95, 500 candidate parts in a slot | Under 80 ms | `composer test:benchmark` and the integration suite | Milestone 4 |
| Adding a 7-part build to the cart | Under 300 ms | The integration suite | Milestone 5 |
| The fit engine with 500 candidates | Grows linearly with options times rules | `composer test:benchmark` | Milestone 2 |
| Rebuilding the specs index for 2,000 parts | Recorded; in jobs of 100 | The integration suite | Milestone 3 |
| The builder's script, compressed | Under 15 kB | `npm run budget` | Milestone 6 |
| The builder's styles, compressed | Under 10 kB | `npm run budget` | Milestone 6 |
| LCP on the builder page, mobile profile, median of three | 2.5 s or less | `npm run lhci` | Milestone 1 (the front page until milestone 6) |
| CLS, the same run | Under 0.1; 0 is the target | `npm run lhci` | Milestone 1 |
| TBT, the same run | Under 200 ms | `npm run lhci` | Milestone 1 |
| A pick's own selected state painted, on a 4× slowed CPU | Within 100 ms | Playwright | Milestone 6 |
| Queries the plugin adds to a request with the schema current | 0 | The integration suite (`InstallHooksTest`) | Milestone 1 |
| Requests for a file of the plugin's on a page without a builder | 0 | Playwright (`smoke.spec.ts`) | Milestone 1 |

Until its first benchmark exists, `composer test:benchmark` says so and passes, and `npm run budget` measures an empty `build/`. Each was broken on purpose to see it fail (`docs/reviews/M1.md`, Deliberate breaks).

## v0.1.0

Machine: Apple M1 Max, PHP 8.5.12, Node 24.22.2, wp-env on OrbStack with WordPress 7.1.4 and WooCommerce 11.2.2. 9 November 2026. CI runs the same gates on `ubuntu-26.04`.

The plugin draws nothing yet, so these are the site's own numbers with the plugin active: the floor the builder starts from.

| What | Result | Budget |
| --- | --- | --- |
| LCP, front page (Twenty Twenty-Five), minified assets (D-032) | 2,362 ms median (2,349, 2,362, 2,647) | 2,500 ms |
| The same page with the plugin deactivated, `SCRIPT_DEBUG` on | 2,723 ms median | For comparison |
| The same page with the plugin active, `SCRIPT_DEBUG` on | 2,728 ms median | For comparison |
| CLS | 0.012 | Under 0.1 |
| TBT | 0 ms | Under 200 ms |
| Lighthouse SEO | 0.91 | 0.9 or more |
| Queries added to a request with the schema current | 0 | 0 |
| Plugin files requested on a shop page | 0 | 0 |

The plugin's presence costs nothing measurable: 5 ms between the two debug-mode medians is inside the run-to-run spread. The site alone leaves about 140 ms under the LCP budget on this machine and theme, and one run of the three was over it (2,647 ms, the first after the assets changed). That is what the builder page has to fit in from milestone 6.
