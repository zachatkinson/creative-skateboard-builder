# Benchmarks

The spec's budgets are tests: a build fails when one is missed. The numbers from each tag are added below.

## Budgets (spec: Quality bar, Performance)

| What | Budget | Measured by | From |
| --- | --- | --- | --- |
| The state route's server time, p95, 500 candidate parts in a slot | Under 80 ms | `composer test:benchmark:integration` (`StateRouteBenchmarkTest`) | Milestone 4 |
| Adding a 7-part build to the cart, p95, by the Store API | Under 300 ms, and 58 queries from 0.7.0, 54 before (D-202) | `composer test:benchmark:integration` (`CartBenchmarkTest`) | Milestone 5 |
| A Store API cart read with a 7-part build in it | Recorded; 30 queries, and the add's 300 ms | `composer test:benchmark:integration` (`CartBenchmarkTest`) | Milestone 5 |
| The fit engine with 500 candidates | The slowest slot under 40 ms, half the state route's budget; four times the candidates in under six times the time | `composer test:benchmark` (`FitEngineBenchmarkTest`) | Milestone 2 |
| A build's three composed pictures, with GD, layers shot at 6.5 pixels to the millimetre | Under 4 s and 64 MB above the job's start; the files measured against the 200 MB cap | `composer test:benchmark:integration` (`ComposerBenchmarkTest`) | Milestone 7 |
| Rebuilding the specs index for 2,000 parts | Recorded; in jobs of 100, each under 10 s and 400 queries | `composer test:benchmark:integration` (`SpecsBenchmarkTest`) | Milestone 3 |
| The specs export, the Catalogue check, a 5,000-row import read and shown, and each job of 100 import writes, at 2,000 parts | Each under 10 s, a third of PHP's usual limit for a request, and one query a product | `composer test:benchmark:integration` | Milestone 3 |
| My builds with a customer's 100 saved builds, p95 of 10 views with the caches emptied | Under 600 ms and 130 queries, the same queries as for one build (D-222) | `composer test:benchmark:integration` (`MyBuildsBenchmarkTest`) | Milestone 8 |
| The builder's script, compressed (Brotli, quality 11) | Under 15 kB | `npm run budget` | Milestone 6 |
| The builder's styles, compressed (Brotli, quality 11) | Under 10 kB | `npm run budget` | Milestone 6 |
| LCP on the builder page, mobile profile, median of three | 2.5 s or less | `npm run lhci` | Milestone 1 (the front page until milestone 6) |
| CLS, the same run | Under 0.1; 0 is the target | `npm run lhci` | Milestone 1 |
| TBT, the same run | Under 200 ms | `npm run lhci` | Milestone 1 |
| LCP, CLS and TBT on a build link's review, its deck, trucks and wheels drawn as layers, on Kadence | The same limits; its SEO score is not asserted, as the page says noindex (D-147) | `npm run lhci` | Milestone 7 |
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

The figures move with the machine. On 11 November 2026 the same Mac, busier, measured 67 to 70 ms cold at p95 and 71 ms after a part moves, for this code and for the commit the table was taken from alike; GitHub's hosted runner measured 66 ms cold, 46 ms warm and 81 ms after a part moves on a sample of 20, which failed the run (D-123). The next run of the same commit there measured 84 ms cold. Since D-124 the 80 ms budget is held where the machine is known, in the audit's local run before each milestone's pull request, and CI holds the query counts (42 cold, 15 warm, 37 after a part moves). Since D-171 CI holds the time to the budget scaled by how much slower than this Mac the runner does a fixed piece of work (7.05 ms here), between 1 and 2.5 times; each run prints the runner's figure and the limit it gives.

The answer's size (#89, 23 November 2026): a move to that trucks step answers 98,659 bytes of JSON whole and 48,398 as the store asks for it, the view alone (`_fields=view`, D-302). The raw `shelf` and `options` repeated the step's cards beside the view's own. The benchmark prints both on every run and holds the second under the first.

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

The add is inside the 300 ms budget and held to it, scaled by the runner's speed in CI (D-171), and the query counts held everywhere (D-134).

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

## v0.7.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.3.0, wp-env's own database server, Xdebug off, no persistent object cache. Measured 16 November 2026, on the tree after the audit's fixes.

The kit and catalogue of v0.4.0, seven slots over 2,000 simple products, 500 of them trucks, now with the decks given layer images and their renditions, so the route draws the board from them.

| The state route, 500 candidates in the step | p95 | Median | Queries at most | Budget |
| --- | --- | --- | --- | --- |
| Cold | 60.5 ms | 58.9 ms | 45 | 80 ms |
| Warm | 44.3 ms | 38.7 ms | 18 | 80 ms |
| A move to the next step, cold | 60.9 ms | 59.0 ms | 40 | 80 ms |
| A move to the next step, warm | 43.1 ms | 38.5 ms | 18 | 80 ms |
| The first request after one part moves | 59.2 ms | 58.7 ms | 40 | 80 ms |
| The first request after a reindex of 2,000 parts, cache emptied | 55.1 ms | | 40 | 80 ms |
| With no row kept at all | 452 ms | | 97 | Printed, not held |

The queries the board adds, three a cold request, are the parts' meta read together and the deck's layer image and its meta; the ceilings moved with Zach's approval (D-202). Adding the build to the cart by the Store API: p95 30.6 ms and 58 queries, four of them Action Scheduler's for the picture's job. A cart read with the build in it: p95 20.9 ms and 28 queries, two fewer than 0.6.0's, since a build's parts' specs are read once a request.

| A build's composed pictures, GD, layers at 6.5 pixels to the millimetre | Result | Budget |
| --- | --- | --- |
| Time for the three (cart, share card, email) | 0.40 s | 4 s |
| Memory above the job's start | 19.2 MB | 64 MB |
| Files, grain the size of the picture's pixels | 2.6, 5.6 and 15.9 kB: 24 kB a build, about 8,500 builds in 200 MB | Measured against the cap |
| Files, finer grain | 1.9, 3.3 and 12.2 kB: 17 kB a build | Measured against the cap |

| The builder page | Result | Budget |
| --- | --- | --- |
| The store's script (`build/builder/view.js`), Brotli 11 | 2,852 B | 15,360 B |
| The block's editor script (`build/builder/index.js`) | 1,177 B | 15,360 B |
| The styles (`build/builder/style-index.css`, and its RTL twin) | 2,688 B | 10,240 B |
| The Part specs panel's script (`build/admin/part-specs.js`) | 1,065 B | 15,360 B |
| LCP, Kadence, mobile profile, median of three | 1.97 s | 2.5 s |
| CLS and TBT, Kadence | 0.000 and 0 ms | Under 0.1 and 200 ms |
| A build link's review with its layers drawn, Kadence: LCP, CLS, TBT | 1.97 s, 0.000, 9 ms | The same |
| Lighthouse SEO, the clean page on both themes | 0.92 | 0.9 or more |
| LCP and CLS, Twenty Twenty-Five, reported (D-165) | 2.28 s and 0.012 | Not held |
| The largest paint on a phone | The deck's layer (`builder.spec.ts`, The largest paint, D-204) | The deck's layer |
| A pick's own selected state painted, 4× slowed CPU | Under 100 ms in Chromium, desktop and phone | 100 ms |

Twenty Twenty-Five's shift measured 0.012 here, against 0.133 for 0.6.0; it stays reported, not held, until a run shows why it moved. The build link's review scores 0.61 for SEO, which is not asserted: the page says `noindex` (D-147).

## v0.9.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.4, WooCommerce 11.3.0, wp-env's own database server, Xdebug off. Measured 18 November 2026 on the milestone 9 branch. The catalogue is v0.7.0's: seven slots over 2,000 simple products, 500 of them trucks, the decks with layer images and their renditions. `composer test:benchmark` (the fit engine at 500 candidates) passed its budgets.

| The state route, 500 candidates in the step | No persistent cache: p95 | Queries | Redis 8.10 and Redis Object Cache 3.0.0: p95 | Queries | Budget |
| --- | --- | --- | --- | --- | --- |
| Cold: the cache emptied before each request | 67.6 ms | 45 | 94.2 ms (88.1 ms in a second run) | 35 | 80 ms |
| Warm | 47.4 ms | 18 | 45.2 ms | 14 | 80 ms |
| A move to the next step, cold | 65.1 ms | 40 | 88.0 ms | 35 | 80 ms |
| A move to the next step, warm | 47.0 ms | 18 | 46.4 ms | 14 | 80 ms |
| The first request after one part moves | 66.2 ms | 40 | 85.6 ms | 35 | 80 ms |
| The first request after a reindex of 2,000 parts | 58.6 ms | 40 | | | 80 ms |
| With no row kept at all | 459 ms | 106 | | | Printed, not held |

**With a persistent object cache, three rows are over the budget.** Each is a request that finds the cache empty or emptied, and pays a round trip to Redis for each miss and each write it makes. The warm rows are faster than without the cache. "Cold" empties the whole cache before each request, which models a site with no persistent cache and happens on a Redis site only after a flush. "The first request after one part moves" is a shop's ordinary request after a stock change, and it is the one that matters. The budget stays at 80 ms. Batching the plugin's own two reads of the cache (`wp_cache_get_multiple()` in `ProductMetaRepository::prime()` and `WooPartCards::cards()`) was tried on 18 November and measured no better: 98.5 ms after a part moves, in a run that shared the machine with an update test, so no gain could be trusted. It is not in 1.0.0. Most of the misses are WooCommerce's own reads of each product, which is where to look next; it is open for Zach in `docs/reviews/M9.md` before the tag. `composer test:benchmark:integration` holds the budget without the cache, as before. The run with Redis is `tools/object-cache.sh up`, then `CBUILD_OBJECT_CACHE=persistent composer test:benchmark:integration`.

| The rest, no persistent cache | Result | Budget |
| --- | --- | --- |
| Adding a 7-part build to the cart by the Store API | p95 26.4 ms, 58 queries | 300 ms, 58 queries |
| A Store API cart read with the build in it | p95 21.1 ms, 28 queries | Recorded |
| My builds with 100 saved builds, caches emptied | p95 432 ms, 112 queries | 600 ms, 130 queries |
| Reindex of 2,000 parts | 22 batches, 0.95 s in all; a batch 69 ms and 319 queries at most | Each batch under 10 s and 400 queries |
| The specs export, 2,000 rows | 0.32 s | 10 s |
| The Catalogue check, 2,000 parts | 0.45 s, peak memory 183 MB | 10 s |
| The import's dry run, 5,000 rows read; 1,800 parts written | 0.27 s planned; 20 jobs, 662 ms at the slowest | 10 s a job |
| A build's three composed pictures, GD | 0.37 s, 19.3 MB; 23 kB a build | 4 s, 64 MB |

| The builder page | Result | Budget |
| --- | --- | --- |
| The store's script, Brotli 11 | 3,027 B | 15,360 B |
| The styles, and their RTL twin | 2,987 B | 10,240 B |
| LCP, CLS and TBT, Twenty Twenty-Five, mobile profile, three runs | 2.30 s, 0.012, 1 ms (medians) | LCP and CLS reported (D-165); TBT under 200 ms |
| LCP, CLS and TBT, Kadence | 1.95 s, 0.000, 5 ms | 2.5 s, 0.1, 200 ms |
| A build link's review with its layers drawn, Kadence | 1.98 s, 0.000, 21 ms | The same |
| Lighthouse SEO, the clean page on both themes | 0.92 | 0.9 or more |

Not measured here: the renditions migration at 2,000 parts (0.8.0's review, auditor 19), which queues one index job a part and needs a site with that many layer images; it stays open in `docs/AUDIT-0.9.0.md` (O6).

## v1.0.0

Machine: Apple M1 Max, PHP 8.5.12 in wp-env on OrbStack, WordPress 7.1.5, WooCommerce 11.3.0, wp-env's own database server, Xdebug off. Measured 20 November 2026 on the milestone 10 branch, the machine running nothing else. The catalogue is v0.7.0's: seven slots over 2,000 simple products, 500 of them trucks, the decks with layer images and their renditions. `composer test:benchmark` (the fit engine at 500 candidates) passed its budgets. New this release: a search with a chip and a sort, the step's shelf as 1.0.0 lists it (the spec reviewer's B4, S-d).

| The state route, 500 candidates in the step | No persistent cache: p95 | Queries | Redis 8.10 and Redis Object Cache 3.0.0: p95 | Queries | Budget |
| --- | --- | --- | --- | --- | --- |
| Cold: the cache emptied before each request | 62.7 ms | 44 | 89.2 ms (91.6 ms in a first run) | 40 | 80 ms |
| Warm | 53.6 ms | 20 | 58.2 ms | 17 | 80 ms |
| A move to the next step, cold | 64.8 ms | 44 | 88.9 ms | 40 | 80 ms |
| A move to the next step, warm | 51.8 ms | 20 | 56.0 ms | 17 | 80 ms |
| A search with a chip and a sort, cold | 61.8 ms | 44 | 91.9 ms | 40 | 80 ms |
| A search with a chip and a sort, warm | 47.9 ms | 20 | 55.6 ms | 17 | 80 ms |
| The first request after one part moves | 59.6 ms | 44 | 79.4 ms | 41 | 80 ms |
| The first request after a reindex of 2,000 parts | 55.5 ms | 44 | 80.1 ms | 41 | 80 ms |
| With no row kept at all | 549 ms | 150 | 1,163 ms | 141 | Printed, not held |
| A visitor whose session holds a 7-part build | 9.0 ms | 59 | 29.0 ms | 58 | Recorded |

**Without a persistent object cache, every row is inside the budget**, search and chips included, and `composer test:benchmark:integration` holds it, as CI does. **With Redis, the cold rows are over it, as in 0.9.0 (O21), and 1.0.0 ships with them so (D-294).** "Cold" empties the whole cache before each request, which a Redis site sees only after a flush. A shop's ordinary request after a stock change, "the first request after one part moves", is 79.4 ms, inside the budget by a hair, and the warm rows are under 60 ms. The budget stays at 80 ms. The Redis rows were measured with the test's time limit lifted for that one run, so every row ran rather than the first over it; the limit is the test's own, back from git. The run with Redis is `tools/object-cache.sh up`, then `CBUILD_OBJECT_CACHE=persistent composer test:benchmark:integration`.

| The rest, no persistent cache | Result | Budget |
| --- | --- | --- |
| Adding a 7-part build to the cart by the Store API | p95 31.8 ms, 58 queries | 300 ms, 58 queries |
| A Store API cart read with the build in it | p95 22.2 ms, 28 queries | Recorded |
| My builds with 100 saved builds, caches emptied | p95 415 ms, 129 queries | 600 ms, 130 queries |
| Reindex of 2,000 parts | 22 batches, 0.96 s in all; a batch 75 ms and 319 queries at most | Each batch under 10 s and 400 queries |
| The specs export, 2,000 rows | 0.43 s | 10 s |
| The Catalogue check, 2,000 parts | 0.46 s, peak memory 187 MB | 10 s |
| The import's dry run, 5,000 rows read; an export with layer images read back | 0.29 s planned; 0.46 s planned | 10 s |
| The import, 1,800 parts written | 20 jobs, 673 ms at the slowest | 10 s a job |
| A build's three composed pictures, GD | 0.37 s, 19.3 MB; 23 kB a build | 4 s, 64 MB |

With Redis the cart's add is p95 52.5 ms and 48 queries, its read 39.6 ms, and My builds 515 ms and 104 queries, each inside its budget.

| The builder page | Result | Budget |
| --- | --- | --- |
| The store's script, Brotli 11 | 3,774 B | 15,360 B |
| The styles, and their RTL twin | 5,033 B and 5,034 B | 10,240 B |
| LCP, CLS and TBT, Twenty Twenty-Five, mobile profile, three runs | 2.35 s, 0.012, 27 ms (medians) | LCP and CLS reported (D-165); TBT under 200 ms |
| LCP, CLS and TBT, Kadence | 1.97 s, 0.000, 33 ms | 2.5 s, 0.1, 200 ms |
| A build link's review with its layers drawn, Kadence | 2.00 s, 0.000, 51 ms | The same |
| Lighthouse SEO, the clean page on both themes | 0.92 | 0.9 or more |

The script grew from 3,027 B to 3,774 B and the styles from 2,987 B to 5,033 B with the configurator's layout, the shelf's tools and the stage; both stay well inside their budgets.

Not measured here, as in 0.9.0: the renditions migration at 2,000 parts (`docs/AUDIT-1.0.0.md`, O6).
