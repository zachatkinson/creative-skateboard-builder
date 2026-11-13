/**
 * Lighthouse CI on the builder page in wp-env, median of three on the mobile profile (docs/AUDIT.md: Performance).
 * tools/lhci.sh points it at the shop fixture's builder page, a blank start with its presets and the first step's
 * parts; run alone, it reads the front page. It runs it with minified assets and the shop open, as a shop serves
 * the page (D-032), twice (D-165): on Twenty Twenty-Five, whose own web font moves the page as it arrives, with layout
 * shift a warning; then on Kadence, which uses the system's fonts, with every limit an error, so the gate measures
 * what the builder adds. The theme is asked for by the e2e theme mu-plugin's cookie.
 */
const url = process.env.CBUILD_LHCI_URL || 'http://localhost:8898/';
const theme = process.env.CBUILD_LHCI_THEME || '';
const shift = 'warn' === process.env.CBUILD_LHCI_SHIFT ? 'warn' : 'error';

module.exports = {
	ci: {
		collect: {
			url: [ url ],
			numberOfRuns: 3,
			settings: {
				preset: 'perf',
				onlyCategories: [ 'performance', 'seo' ],
				chromeFlags: '--no-sandbox --headless=new',
				...( theme
					? {
							extraHeaders: {
								Cookie: `cbuild_e2e_theme=${ theme }`,
							},
						}
					: {} ),
			},
		},
		assert: {
			assertions: {
				'largest-contentful-paint': [
					'error',
					{ maxNumericValue: 2500, aggregationMethod: 'median' },
				],
				'cumulative-layout-shift': [
					shift,
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
			outputDir: theme ? `tmp/lhci/${ theme }` : 'tmp/lhci',
		},
	},
};
