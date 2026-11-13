# Benchmarks

The spec's budgets are tests: a build fails when one is missed. The numbers from each tag are added below.

## Budgets (spec: Quality bar, Performance)

| What | Budget | Measured by | From |
| --- | --- | --- | --- |
| The state route's server time, p95, 500 candidate parts in a slot | Under 80 ms | `composer test:benchmark:integration` (`StateRouteBenchmarkTest`) | Milestone 4 |
| Adding a 7-part build to the cart, p95, by the Store API | Under 300 ms, and 54 queries | `composer test:benchmark:integration` (`CartBenchmarkTest`) | Milestone 5 |
| A Store API cart read with a 7-part build in it | Recorded; 30 queries, and the add's 300 ms | `composer test:benchmark:integration` (`CartBenchmarkTest`) | Milestone 5 |
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

## v0.4.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.2.2, wp-env's own database server, Xdebug off, no persistent object cache. 10 November 2026.

A kit of seven slots over 2,000 simple products: 500 trucks and 250 in each other slot, every one with its slot's specs. The request picks a deck and asks for the trucks step, so all 500 trucks are judged, some offered and the rest hidden with why, and 24 cards are read. 40 requests a run, over seven decks.

| The state route | p95 | Median | Slowest | Queries at most |
| --- | --- | --- | --- | --- |
| Cold: WordPress's cache emptied before each request, as on a site with no persistent object cache | 58 ms | 56 ms | 66 ms | 42 |
| Warm: the cache as the last request left it | 41 ms | 35 ms | 45 ms | 15 |
| The first request after one part moves, cache emptied, 40 requests since D-123 (the figures are of 20) | 71 ms | | 73 ms | |
| With no row kept: the first request in a price context | 470 ms, one request | | | 100 |

The first three are inside the 80 ms budget and held to it. They hold because a request loads no product to learn a slot's parts: what a build needs of each part is a row of the plugin's own table, `cbuild_parts`, and a part that moves has its row deleted and is the only one read from the shop again (D-116). Before that, the same run took 489 ms at p95, 440 ms of it loading 2,000 products.

The last row is outside the budget and is not held to it: it is what one request pays the first time a kit is asked for in a price context (a currency, a language, a set of tax rates), and again after a reindex or a brand or attribute term is edited, which empty the table. The benchmark prints it on every run so that it is known.

The figures move with the machine. On 11 November 2026 the same Mac, busier, measured 67 to 70 ms cold at p95 and 71 ms after a part moves, for this code and for the commit the table was taken from alike; GitHub's hosted runner measured 66 ms cold, 46 ms warm and 81 ms after a part moves on a sample of 20, which failed the run (D-123). The next run of the same commit there measured 84 ms cold. Since D-124 the 80 ms budget is held where the machine is known, in the audit's local run before each milestone's pull request, and CI holds the query counts (42 cold, 15 warm, 37 after a part moves) and a ceiling of 120 ms.

An earlier design of this milestone, never released, kept each slot's list in a transient: 53 ms cold, but 435 ms on the first request after any one product moved. The table costs 5 ms cold and takes that request to 71 ms.

Of the cold 58 ms, the fit engine takes about 20 ms for all seven slots, finding the slots' parts and reading their rows and the kit about 15 ms, and the 24 cards about 20 ms, since without a persistent object cache each card's product is loaded on every request.

## v0.5.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.2.2, wp-env's own database server, Xdebug off, no persistent object cache. 11 November 2026.

A kit of seven slots and a build with a part in each, trucks twice: a kit's line and seven parts' lines. 40 requests a run through the Store API, WordPress's cache emptied before each.

| The cart | p95 | Median | Slowest | Queries at most |
| --- | --- | --- | --- | --- |
| Adding the build (`POST /wc/store/v1/cart/add-item`) | 26 ms | 25 ms | 44 ms | 54 |
| Reading a cart with the build in it (`GET /wc/store/v1/cart`) | 21 ms | | | 30 |
| Reading a cart of the same seven products with no build | 4 ms | | | 10 |

The add is inside the 300 ms budget and held to it, with 450 ms as CI's ceiling and the query counts held everywhere (D-134).

The difference between the last two rows is what the plugin does on every cart response while a build is in the cart: the kit's line's quantity limit and the check that the build is still one the shop would sell (D-129). It reads the build's parts from WooCommerce each time and keeps nothing.

The first run measured 132 queries for the add and 84 for the read, at 37 ms and 31 ms: each part was read from the shop by itself. A build's parts are now read together (D-134).

The state route, for a visitor whose session holds that build, on this small kit and with the cache emptied before each request:

| The state route | p95 | Queries at most |
| --- | --- | --- |
| A request that starts with no customer, as every request to the route does | 8 ms | 38 |
| The same with the shopper already known | 8 ms | 37 |

The one query is WooCommerce reading the session. The route makes no cart, so the build in the session is not read (D-138), and the difference is held at one query.

That query is paid only where a price the shop shows can depend on the shopper's address: prices shown with tax, or entered with tax and adjusted for where the shopper is. On any other shop the session is not read at all (D-139), and the measurement sets the shop to show prices with tax so that it is.

Measured again on WooCommerce 11.3.0, the newest leg from D-139, on the same machine and day: adding the build p95 26 ms and 54 queries at most; a cart read with the build in it 21 ms and 30 queries, beside 3 ms and 10; the state route 44 queries with the session read and 43 without. Nothing moved. Where a shop's default customer address is Geolocate, a guest's first request also pays WooCommerce's GeoIP lookup, which this does not measure; milestone 6's plan does.

## v0.6.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.3.0, wp-env's own database server, Xdebug off, no persistent object cache. Measured 12 November 2026, and again on 13 November after the audit's fixes; the table is the second run.

The kit and catalogue of v0.4.0: seven slots over 2,000 simple products, 500 of them trucks.

| The state route, 500 candidates in the step | p95 | Median | Queries at most | Budget |
| --- | --- | --- | --- | --- |
| Cold | 59.4 ms | 58.2 ms | 42 | 80 ms |
| Warm | 43.7 ms | 38.2 ms | 15 | 80 ms |
| A move to the next step, cold (the builder's own request, new in 0.6.0) | 63.1 ms | 58.8 ms | 37 | 80 ms |
| A move to the next step, warm | 40.3 ms | 38.4 ms | 15 | 80 ms |
| The first request after one part moves | 61.1 ms | 57.7 ms | 37 | 80 ms |
| The first request after a reindex of 2,000 parts, cache emptied | 55.4 ms | | 37 | 80 ms |
| With no row kept at all: after a plugin says every part changed, or in a price context no one has asked in | 469 ms | | 101 | Printed, not held |

A move's slowest single request was 90.7 ms, a cold one; the budget is the p95's. Adding the build to the cart by the Store API measured p95 24.3 ms and 54 queries, and a cart read with the build in it 18.4 ms and 30, the same as 0.5.0's.

| The builder page | Result | Budget |
| --- | --- | --- |
| The store's script (`build/builder/view.js`), Brotli 11 | 2,581 B | 15,360 B |
| The block's editor script (`build/builder/index.js`) | 1,177 B | 15,360 B |
| The styles (`build/builder/style-index.css`, and its RTL twin) | 2,445 B | 10,240 B |
| LCP, Kadence, mobile profile, median of three | 1.96 s | 2.5 s |
| CLS, Kadence | 0.000 | Under 0.1 |
| TBT, Kadence | 0 ms | Under 200 ms |
| Lighthouse SEO, both themes | 0.92 | 0.9 or more |
| LCP and CLS, Twenty Twenty-Five, reported (D-165) | 2.28 s and 0.133 | Not held: the shift is the theme's web font |
| A pick's own selected state painted, 4× slowed CPU | Under 100 ms in Chromium, desktop and phone (only Chromium can slow its CPU) | 100 ms |

Until 0.6.0 the first row was the second: a reindex emptied the parts table a batch at a time, and the next visitor's request read every part from the shop. Each batch now writes the rows of the parts it indexed, in the background (issue #28, D-140), and the first request after it is held to the 80 ms budget and to the cold run's 42 queries.

The reindex pays for it: 2,000 parts in 42 batches took 3.54 s in all, a batch 110 ms at its slowest (3.85 s and 130 ms on 12 November), where the index alone takes 0.84 s with no kit to write rows for. A batch is still well inside one request of the queue's.

The second row stays outside the budget and is printed, not held. It is now paid only after `cbuild_parts_changed` is fired with no part named, and by the first visitor shown other prices than the shop's default: another language, another currency, or, where a shown price goes by address, other tax rates.
