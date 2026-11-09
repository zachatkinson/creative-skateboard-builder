/**
 * StrykerJS on the builder's store, 90 % (docs/AUDIT.md: Mutation testing). tools/mutate-js.sh runs it once
 * assets/src has a file to mutate (milestone 6).
 *
 * @type {import('@stryker-mutator/api/core').PartialStrykerOptions}
 */
export default {
	mutate: [ 'assets/src/**/*.ts', '!assets/src/**/*.d.ts' ],
	testRunner: 'vitest',
	vitest: { configFile: 'vitest.config.mts' },
	reporters: [ 'clear-text', 'progress', 'html' ],
	htmlReporter: { fileName: 'tmp/stryker/index.html' },
	tempDirName: '.stryker-tmp',
	thresholds: { high: 95, low: 90, break: 90 },
	coverageAnalysis: 'perTest',
};
