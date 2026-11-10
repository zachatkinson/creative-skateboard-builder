/**
 * StrykerJS on the plugin's TypeScript, 90 % (docs/AUDIT.md: Mutation testing): the admin's script (D-086), and the
 * builder's store from milestone 6. tools/mutate-js.sh runs it when assets/src has a file to mutate.
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
