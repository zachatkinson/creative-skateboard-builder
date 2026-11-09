/**
 * Lighthouse CI on the builder page in wp-env, median of three on the mobile profile (docs/AUDIT.md: Performance).
 * Until the Builder block ships (milestone 6) the page is the shop's front page with the plugin active, so the
 * budgets are already measured and a regression from the plugin's presence alone would show.
 * tools/lhci.sh runs it with minified assets and the shop open, as a shop serves the page (D-032).
 */
const url = process.env.CBUILD_LHCI_URL || 'http://localhost:8898/';

module.exports = {
	ci: {
		collect: {
			url: [ url ],
			numberOfRuns: 3,
			settings: {
				preset: 'perf',
				onlyCategories: [ 'performance', 'seo' ],
				chromeFlags: '--no-sandbox --headless=new',
			},
		},
		assert: {
			assertions: {
				'largest-contentful-paint': [
					'error',
					{ maxNumericValue: 2500, aggregationMethod: 'median' },
				],
				'cumulative-layout-shift': [
					'error',
					{ maxNumericValue: 0.1, aggregationMethod: 'median' },
				],
				'total-blocking-time': [
					'error',
					{ maxNumericValue: 200, aggregationMethod: 'median' },
				],
				'categories:seo': [
					'error',
					{ minScore: 0.9, aggregationMethod: 'median' },
				],
			},
		},
		upload: {
			target: 'filesystem',
			outputDir: 'tmp/lhci',
		},
	},
};
