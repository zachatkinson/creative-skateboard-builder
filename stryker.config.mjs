/**
 * StrykerJS on the plugin's TypeScript, 95 % (docs/AUDIT.md: Mutation testing): the admin's script (D-086), and the
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
	// What the tests never read is not copied into the sandbox: Composer's packages alone are most of the tree, and
	// PHP_CodeSniffer's HTML fixtures in them drew a warning each (issue #31).
	ignorePatterns: [
		'vendor',
		'tmp',
		'docs',
		'build',
		'languages',
		'test-results',
		'tools/integration/vendor',
		'tests/Unit',
		'tests/Integration',
		'tests/Benchmark',
		'tests/Fixtures',
		'tests/Support',
		// The browser tests but their lock, which tests/js/e2e-lock.test.ts imports and runs (D-422, D-426).
		'tests/e2e/*',
		'!tests/e2e/support',
		'tests/e2e/support/*',
		'!tests/e2e/support/lock.ts',
	],
	thresholds: { high: 98, low: 95, break: 95 },
	coverageAnalysis: 'perTest',
};
