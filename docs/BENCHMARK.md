# Benchmarks

The spec's budgets are tests: a build fails when one is missed. The numbers from each tag are added below.

## Budgets (spec: Quality bar, Performance)

| What | Budget | Measured by | From |
| --- | --- | --- | --- |
| The state route's server time, p95, 500 candidate parts in a slot | Under 80 ms | `composer test:benchmark` and the integration suite | Milestone 4 |
| Adding a 7-part build to the cart | Under 300 ms | The integration suite | Milestone 5 |
| The fit engine with 500 candidates | The slowest slot under 40 ms, half the state route's budget; four times the candidates in under six times the time | `composer test:benchmark` (`FitEngineBenchmarkTest`) | Milestone 2 |
| Rebuilding the specs index for 2,000 parts | Recorded; in jobs of 100, each under 10 s and 400 queries | `composer test:benchmark:integration` (`SpecsBenchmarkTest`) | Milestone 3 |
| The specs export, the Catalogue check, a 5,000-row import read and shown, and each job of 100 import writes, at 2,000 parts | Each under 10 s, a third of PHP's usual limit for a request, and one query a product | `composer test:benchmark:integration` | Milestone 3 |
| The builder's script, compressed (Brotli, quality 11) | Under 15 kB | `npm run budget` | Milestone 6 |
| The builder's styles, compressed (Brotli, quality 11) | Under 10 kB | `npm run budget` | Milestone 6 |
| LCP on the builder page, mobile profile, median of three | 2.5 s or less | `npm run lhci` | Milestone 1 (the front page until milestone 6) |
| CLS, the same run | Under 0.1; 0 is the target | `npm run lhci` | Milestone 1 |
| TBT, the same run | Under 200 ms | `npm run lhci` | Milestone 1 |
| A pick's own selected state painted, on a 4× slowed CPU | Within 100 ms | Playwright | Milestone 6 |
| Queries the plugin adds to a request with the schema current | 0 | The integration suite (`InstallHooksTest`) | Milestone 1 |
| Requests for a file of the plugin's on a page without a builder | 0 | Playwright (`smoke.spec.ts`) | Milestone 1 |

`composer test:benchmark` runs with Xdebug off, as a shop's PHP does. Until the builder's assets exist, `npm run budget` measures an empty `build/`. Each was broken on purpose to see it fail (`docs/reviews/M1.md`, Deliberate breaks).

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

## v0.2.0

Machine: Apple M1 Max, PHP 8.5.12, Xdebug off. 9 November 2026.

The fit engine alone, with no WordPress: every slot of a generated catalogue judged against a complete build, the quickest of five runs. The catalogue has a shop's faults in it (parts that warn, are blocked, lack a spec or a set size, or are sold out), so the hidden groups are counted too.

| Candidates a slot | The slowest slot (grip) | All seven slots |
| --- | --- | --- |
| 500 | 9.3 ms | 55.5 ms |
| 1,000 | 17.8 ms | 109.3 ms |
| 2,000 | 36.3 ms | 224.9 ms |

Four times the candidates took 4.05 times as long, so the time grows in step with them. The state route judges one slot a request, so at 500 candidates the engine takes about 9 ms of the route's 80 ms here. With Xdebug loaded the same run takes 23 ms for that slot, which is why the benchmark turns it off.

The test's two limits were each broken on purpose and seen to fail: the growth limit set to 3.5 ("4.04 is less than 3.5"), and the slot's budget set to 4 ms.

## v0.3.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.2.2, wp-env's own database server, Xdebug off, no persistent object cache. 10 November 2026.

2,000 simple products, each with three specs of its own. WordPress's cache is emptied before each piece, as a request starts.

| What | Time | Queries |
| --- | --- | --- |
| The reindex, start to end | 0.80 s in 22 batches | 318 a batch of 100 at most |
| Its slowest batch | 57 ms | |
| The export, 2,000 rows (131 kB) | 0.31 s | 2,107 |
| The Catalogue check | 0.46 s | 2,112 |
| A 5,000-row file read | 0.07 s | 3 |
| The same file planned, as the screen shows it (2,000 rows are parts, 3,000 name none) | 0.28 s | 2,009 |
| The import confirmed: 1,778 parts written, in 20 jobs of 100 | 12.82 s | 8,126 a job at most |
| Its slowest job | 706 ms | |

Peak memory for the whole run was 111 MB, with 2,000 products made in the same process; the import in hand is 117 kB, its rows in an option written once (D-090). A job of 100 writes would take about 2.5 s on CI's runner, a quarter of what a job is allowed, so the batch stays at 100.

Each piece is two orders under what a request allows, so the Catalogue check stays in the request that asks for it (D-077) and the dry run is planned whole when the page is drawn (D-076).

The query counts are one a product and a page's own. That one is WooCommerce reading a product's meta when the product is built (`WC_Data_Store_WP::read_meta()`), a look-up by the product's ID on an indexed column, which WooCommerce caches in the object cache where a shop has one. Reading the meta past WooCommerce would save it and break the rule that products go through its CRUD (D-081). A batch late in the reindex's first pass asks what an early one does: that pass walks the index's rows by ID, not by offset, and the test holds the last full batch to the second's count. The second pass pages the shop's products by offset, through WooCommerce's own query (D-071).

The test's limits were each broken on purpose and seen to fail: see `docs/reviews/M3.md`.

