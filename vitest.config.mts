/**
 * Vitest for the plugin's TypeScript (tests/js), in jsdom (docs/STANDARDS.md: Testing): the admin's script (D-086),
 * and the builder's store from milestone 6. The Interactivity API is WordPress's at runtime; in tests it is the
 * double in tests/js/support, added with the store.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		include: [ 'tests/js/**/*.test.ts' ],
		environment: 'jsdom',
		passWithNoTests: true,
	},
} );
