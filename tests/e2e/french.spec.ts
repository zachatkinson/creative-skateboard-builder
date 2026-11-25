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
	) as { page: string; widths: { page: string } };

test.describe( 'The builder in French', () => {
	test.beforeEach( async ( { page }, info ) => {
		test.skip( 'fr-ca' !== info.project.name, 'The fr-ca project alone' );
		await page.setExtraHTTPHeaders( {
			'x-cbuild-e2e-locale': 'fr_CA',
			'x-cbuild-e2e-translated': '1',
		} );
	} );

	test( 'says in French that the answer did not come in time (D-364)', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, "The limit is the store's." );
		test.slow();
		await page.goto(
			fixture().page + '?cbuild%5B_on%5D=deck&cbuild%5B_width%5D=8.25in'
		);
		await page.locator( '.cbuild[data-ready]' ).waitFor( {
			state: 'attached',
		} );
		// Held past the store's 10 s, then let go.
		await page.route( /cbuild\/v1\/kits\/\d+\/state/, async ( route ) => {
			await new Promise( ( done ) => setTimeout( done, 12000 ) );
			await route.continue().catch( () => undefined );
		} );
		await page
			.getByRole( 'radio', { name: /^Choisir Builder test deck 8\.25/ } )
			.check();
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText(
			'La boutique n’a pas répondu à temps. Réessayez dans un instant.',
			{ timeout: 20000 }
		);
		await expect( page.locator( '.cbuild__status' ) ).toHaveText(
			'La boutique n’a pas répondu à temps. Réessayez dans un instant.'
		);
	} );

	// F31 (#148, D-399): a deck's six other widths, counted in French, with its plural.
	test( "counts a deck's other widths in French", async ( { page } ) => {
		await page.goto(
			fixture().widths.page +
				'?cbuild%5Bstep%5D=deck&cbuild%5B_width%5D=8.5in'
		);
		await expect(
			page
				.locator( '.cbuild-cards:not(.cbuild-cards--sold-out) > li' )
				.first()
				.locator( '.cbuild-card__also' )
		).toHaveText( 'Aussi en 6 autres largeurs' );
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
		// The width first (D-241), in the shop's French.
		await expect(
			page.getByRole( 'group', {
				name: /^Quelle largeur roulez-vous\s\?$/,
			} )
		).toBeVisible();
		const width = page.locator( '.cbuild-width__option', {
			hasText: /^8[,.]25\s?(″|po)/,
		} );
		await width.click();
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		await page
			.getByRole( 'radio', { name: /^Choisir Builder test deck 8\.25/ } )
			.check();
		if ( javaScriptEnabled ) {
			await settled( page );
		}
		const bar = page.locator( '.cbuild-bar' );
		await expect( bar.locator( '.cbuild-bar__estimate' ) ).toHaveText(
			/^Planche complète dès environ /
		);
		await axe( page, 'the builder in French, a deck picked', '.cbuild' );
		await bar
			.getByRole( 'button', { name: /^Suivant\s:\sTrucks$/ } )
			.click();
		await expect( heading ).toHaveText(
			/^Étape 2 sur 7\s:\sChoisissez vos trucks$/
		);
		await expect( page.locator( 'html' ) ).toHaveAttribute(
			'lang',
			'fr-CA'
		);
	} );
} );
