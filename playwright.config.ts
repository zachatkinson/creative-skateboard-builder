import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

/**
 * Three engines at desktop and phone widths, then JavaScript off, fr_CA, right to left and the visual snapshots
 * (docs/AUDIT.md: Browser tests). CI runs every project; `visual` runs only in CI's Linux container, so its
 * baselines stay stable. Locally `npx playwright test --project=chromium-desktop` is enough.
 *
 * fr-ca and rtl send a header the mu-plugin in tests/e2e/fixtures/locale.php reads to switch the site's locale for
 * that request alone, so the same specs run in each.
 *
 * macOS 27 protects ~/Library/Application Support/Firefox, which Playwright's Firefox build still reads for
 * profiles.ini even when given its own profile, so every launch died with "Could not find profile folder"
 * (microsoft/playwright#42768). Pointing CFFIXED_USER_HOME at a scratch directory makes Core Foundation resolve the
 * home, and that lookup, inside a folder the test run owns (Creative Search's D-022).
 */
const firefoxLaunchOptions =
	process.platform === 'darwin'
		? {
				env: {
					...process.env,
					CFFIXED_USER_HOME: fs.mkdtempSync(
						path.join( os.tmpdir(), 'cbuild-firefox-home-' )
					),
				},
			}
		: {};

const visual = /visual\.spec\.ts$/;

export default defineConfig( {
	testDir: './tests/e2e',
	globalSetup: './tests/e2e/support/global-setup.ts',
	timeout: 60_000,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: [ [ 'list' ] ],
	outputDir: 'test-results',
	snapshotPathTemplate:
		'{testDir}/__snapshots__/{testFilePath}/{projectName}/{arg}{ext}',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8898',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium-desktop',
			testIgnore: visual,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'chromium-phone',
			testIgnore: visual,
			use: { ...devices[ 'Pixel 7' ] },
		},
		{
			name: 'webkit-desktop',
			testIgnore: visual,
			use: { ...devices[ 'Desktop Safari' ] },
		},
		{
			name: 'webkit-phone',
			testIgnore: visual,
			use: { ...devices[ 'iPhone 14' ] },
		},
		{
			name: 'firefox-desktop',
			testIgnore: visual,
			use: {
				...devices[ 'Desktop Firefox' ],
				launchOptions: firefoxLaunchOptions,
			},
		},
		{
			name: 'firefox-phone',
			testIgnore: visual,
			use: {
				...devices[ 'Desktop Firefox' ],
				viewport: { width: 412, height: 915 },
				launchOptions: firefoxLaunchOptions,
			},
		},
		{
			name: 'no-js',
			testIgnore: visual,
			use: { ...devices[ 'Desktop Chrome' ], javaScriptEnabled: false },
		},
		{
			name: 'fr-ca',
			testIgnore: visual,
			use: {
				...devices[ 'Desktop Chrome' ],
				locale: 'fr-CA',
				extraHTTPHeaders: { 'x-cbuild-e2e-locale': 'fr_CA' },
			},
		},
		{
			name: 'rtl',
			testIgnore: visual,
			use: {
				...devices[ 'Desktop Chrome' ],
				locale: 'he-IL',
				extraHTTPHeaders: { 'x-cbuild-e2e-locale': 'he_IL' },
			},
		},
		{
			name: 'visual',
			testMatch: visual,
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
