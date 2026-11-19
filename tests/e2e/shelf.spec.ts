import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { APIResponse, Route } from '@playwright/test';
import { test, expect, Page } from './support/test';
import { axe } from './support/axe';

/**
 * Milestone 10, a long step (spec: One card per look, Search; SPEC-CONFIGURATOR): the shelf kit's deck step has 46
 * decks at 8.25″ in stock over four brands, three sold out, and six at 8″. A step past 40 looks has a search, past 12
 * chips, every step a sort and pages of 30, and its sold-out looks fold under "Show N sold out". Each works without
 * JavaScript, in the no-js project; with it the step changes in place, focus stays on the control used, and after
 * Show 30 more it goes to the first new card. The address keeps it all, so a reload, Back and Forward land on it.
 */

interface Shelf {
	kit: number;
	page: string;
	decks: number[];
}

const shelf = (): Shelf =>
	(
		JSON.parse(
			readFileSync(
				path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
				'utf8'
			)
		) as { shelf: Shelf }
	 ).shelf;

/**
 * The shelf kit's page at a build link.
 *
 * @param build The link's fields, without `cbuild[`.
 */
const at = ( build: Record< string, string > = {} ): string => {
	const url = new URL( shelf().page );
	for ( const [ key, value ] of Object.entries( {
		_width: '8.25in',
		...build,
	} ) ) {
		url.searchParams.set( `cbuild[${ key }]`, value );
	}
	return url.toString();
};

const cards = ( page: Page ) =>
	page.locator( '.cbuild-cards:not(.cbuild-cards--sold-out) > li' );

const field = ( page: Page, name: string ): string | null =>
	new URL( page.url() ).searchParams.get( `cbuild[${ name }]` );

test.describe( 'A long step', () => {
	test( 'lists 30 decks at a time, and Show 30 more adds the rest with focus on the first new one', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( at() );
		await expect( cards( page ) ).toHaveCount( 30 );
		await expect(
			page.locator( '.cbuild-step__more .cbuild-step__count' )
		).toHaveText( /30\D+46/ );
		// axe runs as a script in the page, so not in the no-js project.
		if ( javaScriptEnabled ) {
			await axe( page, 'a long step', '.cbuild' );
		}
		await page.locator( '.cbuild-step__show' ).click();
		await expect( cards( page ) ).toHaveCount( 46 );
		await expect( page.locator( '.cbuild-step__show' ) ).toBeHidden();
		if ( javaScriptEnabled ) {
			await expect(
				cards( page ).nth( 30 ).locator( 'input' )
			).toBeFocused();
		}
	} );

	test( 'folds its sold-out decks under Show 3 sold out', async ( {
		page,
	} ) => {
		await page.goto( at() );
		const fold = page.locator( '.cbuild-sold-out' );
		await expect( fold.locator( 'summary' ) ).toHaveText(
			'Show 3 sold out'
		);
		await fold.locator( 'summary' ).click();
		await expect( fold.locator( '.cbuild-cards > li' ) ).toHaveCount( 3 );
	} );

	// The auditor's M10 review, 2: Enter in the search pressed the first width button, 8″, and searched the wrong decks.
	test( 'finds a deck by its name with Enter, and keeps the width', async ( {
		page,
	} ) => {
		await page.goto( at() );
		await page.locator( '#cbuild-search' ).fill( 'Rascals' );
		await page.locator( '#cbuild-search' ).press( 'Enter' );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( cards( page ).first() ).toContainText( 'Rascals' );
		await expect.poll( () => field( page, '_width' ) ).toBe( '8.25in' );
		await expect.poll( () => field( page, '_q' ) ).toBe( 'Rascals' );
	} );

	test( 'narrows by a brand, and a reload, and Back from the next step, keep it', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( at() );
		const alder = page.locator( '.cbuild-chips__chip', {
			hasText: 'Alder',
		} );
		// On a phone, with the script, the chips wait behind Filters (spec: Phone).
		const filters = page.locator( '.cbuild-chips__toggle' );
		if ( await filters.isVisible() ) {
			await filters.click();
		}
		await expect( alder.locator( 'input' ) ).toHaveAccessibleName(
			'Alder, 12 parts'
		);
		await alder.locator( 'input' ).check();
		if ( ! javaScriptEnabled ) {
			await page.locator( '.cbuild-chips__apply' ).click();
		}
		await expect( cards( page ) ).toHaveCount( 11 );
		await expect(
			page
				.locator( '.cbuild-card__brand' )
				.filter( { hasNotText: 'Alder' } )
		).toHaveCount( 0 );
		if ( javaScriptEnabled ) {
			await expect( alder.locator( 'input' ) ).toBeFocused();
		}
		await page.reload();
		await expect( cards( page ) ).toHaveCount( 11 );
		await expect( alder.locator( 'input' ) ).toBeChecked();
		// Coming back to the step keeps its filters (spec: Pages; D-265): on to the trucks, then Back and Forward.
		await cards( page ).first().locator( 'input' ).check();
		if ( javaScriptEnabled ) {
			await expect.poll( () => field( page, 'deck' ) ).not.toBeNull();
		}
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect.poll( () => field( page, 'step' ) ).toBe( 'trucks' );
		await page.goBack();
		await expect( cards( page ) ).toHaveCount( 11 );
		await expect( alder.locator( 'input' ) ).toBeChecked();
		await page.goForward();
		await expect.poll( () => field( page, 'step' ) ).toBe( 'trucks' );
	} );

	test( 'sorts by price, highest first', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( at() );
		await page
			.locator( '.cbuild-sort__select' )
			.selectOption( 'price-desc' );
		if ( ! javaScriptEnabled ) {
			await page.locator( '.cbuild-sort__apply' ).click();
		}
		// Deck 46 is the dearest in stock at 8.25″: 96.00.
		await expect(
			cards( page ).first().locator( '.cbuild-card__price' )
		).toContainText( '96' );
		await expect.poll( () => field( page, '_sort' ) ).toBe( 'price-desc' );
	} );
} );

test.describe( 'A long step, with JavaScript', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The search as one types, and the route, are the script's."
		);
	} );

	// The auditor's M10 review, 19: an answer that came back while the shopper typed on put the box back to the letters
	// it was asked with. The answer to "R" is held until "a" is typed, then let through inside the search's 150 ms wait,
	// before "Ra" is asked.
	test( 'keeps every letter typed when an answer comes back mid-word', async ( {
		page,
	} ) => {
		await page.goto( at() );
		let held: { route: Route; response: APIResponse } | undefined;
		let holding = true;
		await page.route( /cbuild\/v1\/kits\/\d+\/state/, async ( route ) => {
			if ( ! holding ) {
				await route.continue();
				return;
			}
			held = { route, response: await route.fetch() };
		} );
		const search = page.locator( '#cbuild-search' );
		await search.press( 'R' );
		await expect.poll( () => held ).toBeDefined();
		await search.press( 'a' );
		holding = false;
		const answered = page.waitForResponse( /cbuild\/v1\/kits\/\d+\/state/ );
		await held!.route.fulfill( { response: held!.response } );
		await answered;
		await page.evaluate(
			() =>
				new Promise( ( done ) =>
					requestAnimationFrame( () => done( null ) )
				)
		);
		await expect( search, 'the answer to "R" kept "Ra"' ).toHaveValue(
			'Ra'
		);
		await search.pressSequentially( 'scals', { delay: 200 } );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( search ).toHaveValue( 'Rascals' );
	} );

	test( 'keeps the step and says when to try again past the rate limit', async ( {
		page,
	} ) => {
		await page.goto( at() );
		await expect( cards( page ) ).toHaveCount( 30 );
		await page.route( /cbuild\/v1\/kits\/\d+\/state/, ( route ) =>
			route.fulfill( {
				status: 429,
				contentType: 'application/json',
				body: JSON.stringify( {
					code: 'cbuild_rate_limited',
					message: 'Too many requests. Try again in 20 seconds.',
				} ),
			} )
		);
		await page.locator( '#cbuild-search' ).fill( 'Rascals' );
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText(
			'Too many requests. Try again in 20 seconds.'
		);
		await expect( cards( page ) ).toHaveCount( 30 );
	} );
} );
