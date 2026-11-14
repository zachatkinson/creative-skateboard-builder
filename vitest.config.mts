/**
 * Vitest for the plugin's TypeScript (tests/js), in jsdom (docs/STANDARDS.md: Testing): the admin's script (D-086),
 * and the builder's store and block from milestone 6. The WordPress packages they import are WordPress's at runtime;
 * in tests each is a double in tests/js/support.
 */
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	resolve: {
		alias: Object.fromEntries(
			Object.entries( {
				'@wordpress/interactivity': 'interactivity',
				'@wordpress/blocks': 'editor',
				'@wordpress/element': 'editor',
				'@wordpress/block-editor': 'editor',
				'@wordpress/components': 'editor',
				'@wordpress/url': 'editor',
				'@wordpress/i18n': 'editor',
				'@wordpress/server-side-render': 'server-side-render',
				'@wordpress/api-fetch': 'api-fetch',
			} ).map( ( [ name, file ] ) => [
				name,
				fileURLToPath(
					new URL(
						`./tests/js/support/${ file }.ts`,
						import.meta.url
					)
				),
			] )
		),
	},
	test: {
		include: [ 'tests/js/**/*.test.ts' ],
		environment: 'jsdom',
		passWithNoTests: true,
	},
} );
