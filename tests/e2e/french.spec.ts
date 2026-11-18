import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, settled } from './support/test';
import { axe } from './support/axe';

/*
 * Milestone 9 (D-233, D-234): the builder in the French it ships, in the fr-ca project alone. The other specs run there
 * too, with the plugin's own strings kept in English so they can find it by its words; this one asks for the plugin's
 * French and walks a step with it, with axe.
 */

const fixture = () =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
			'utf8'
		)
	) as { page: string };

test.describe( 'The builder in French', () => {
	test.beforeEach( async ( { page }, info ) => {
		test.skip( 'fr-ca' !== info.project.name, 'The fr-ca project alone' );
		await page.setExtraHTTPHeaders( {
			'x-cbuild-e2e-locale': 'fr_CA',
			'x-cbuild-e2e-translated': '1',
		} );
	} );

	test( 'says each step, pick and total in French, and the next step is drawn in French', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.setViewportSize( { width: 390, height: 700 } );
		await page.goto( fixture().page );
		const heading = page.getByRole( 'heading', {
			level: 2,
			name: /^Étape \d+ sur \d+\s:/,
		} );
		await expect( heading ).toHaveText( /^Étape 1 sur 7\s:\s/ );
		await expect(
			page.getByText( 'Votre montage' ).first()
		).toBeAttached();
		await page
			.getByRole( 'radio', { name: /^Choisir Builder test deck 8\.25/ } )
			.check();
		if ( javaScriptEnabled ) {
			await settled( page );
		}
		const bar = page.locator( '.cbuild-bar' );
		await expect( bar.locator( '.cbuild-bar__estimate' ) ).toHaveText(
			/^Complète à partir d’environ /
		);
		await axe( page, 'the builder in French, a deck picked', '.cbuild' );
		await bar
			.getByRole( 'button', { name: /^Suivant\s:\sTrucks$/ } )
			.click();
		await expect( heading ).toHaveText( /^Étape 2 sur 7\s:\sTrucks$/ );
		await expect( page.locator( 'html' ) ).toHaveAttribute(
			'lang',
			'fr-CA'
		);
	} );
} );
