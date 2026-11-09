/**
 * Vitest for the builder's store (tests/js), in jsdom (docs/STANDARDS.md: Testing). The Interactivity API is
 * WordPress's at runtime; in tests it is the double in tests/js/support, added with the store in milestone 6.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		include: [ 'tests/js/**/*.test.ts' ],
		environment: 'jsdom',
		passWithNoTests: true,
	},
} );
