import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { APIResponse, Route } from '@playwright/test';
import { test, expect, Page, inLocale } from './support/test';
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
		// Said once, at the foot beside Show more (the auditor's M10 minor 20).
		await expect(
			page.locator( '.cbuild-step__count:visible' )
		).toHaveCount( 1 );
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

	// Spec: Search, when nothing is found. The spec reviewer's M10 finding S-b.
	test( 'says which width a search finds its deck in, and offers Clear search when it finds none', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		const search = async ( query: string ) => {
			await page.locator( '#cbuild-search' ).fill( query );
			await page.locator( '#cbuild-search' ).press( 'Enter' );
		};
		await page.goto( at() );
		await search( 'Ponies' );
		const found = page.locator( '.cbuild-found' );
		await expect( found.locator( '.cbuild-found__text' ) ).toHaveText(
			inLocale(
				info.project.name,
				'No 8.25″ decks match Ponies. Ponies comes in 8″.'
			)
		);
		await expect( cards( page ) ).toHaveCount( 0 );
		if ( javaScriptEnabled ) {
			await axe( page, 'a search that finds nothing here', '.cbuild' );
		}
		await found.getByRole( 'button', { name: 'Show 8″ decks' } ).click();
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( cards( page ).first() ).toContainText( 'Ponies' );
		// Without JavaScript the button's width follows the form's own in the address, and PHP reads the last.
		await expect
			.poll( () =>
				new URL( page.url() ).searchParams
					.getAll( 'cbuild[_width]' )
					.at( -1 )
			)
			.toBe( '8in' );

		await page.goto( at() );
		await search( 'Zebracorn' );
		await expect( found.locator( '.cbuild-found__text' ) ).toContainText(
			'Zebracorn'
		);
		await expect(
			found.getByRole( 'button', { name: 'Show 8″ decks' } )
		).toHaveCount( 0 );
		await found.getByRole( 'button', { name: 'Clear search' } ).click();
		await expect( cards( page ) ).toHaveCount( 30 );
		await expect( page.locator( '#cbuild-search' ) ).toHaveValue( '' );
		// Without JavaScript the box's own text goes too, before the button's empty one, which PHP reads last.
		await expect
			.poll( () =>
				new URL( page.url() ).searchParams
					.getAll( 'cbuild[_q]' )
					.at( -1 )
			)
			.toBeFalsy();
	} );

	// Spec: Pages ("The build link's `_shown` keeps the count, so a reload or Back shows the same list") and Search.
	// The spec reviewer's M10 finding S-c.
	test( 'keeps a search, and a list grown by Show 30 more, through a reload, Back and Forward', async ( {
		page,
	} ) => {
		await page.goto( at() );
		await page.locator( '.cbuild-step__show' ).click();
		await expect( cards( page ) ).toHaveCount( 46 );
		await expect.poll( () => field( page, '_shown' ) ).not.toBeNull();
		await page.reload();
		await expect( cards( page ) ).toHaveCount( 46 );

		await page.locator( '#cbuild-search' ).fill( 'Rascals' );
		await page.locator( '#cbuild-search' ).press( 'Enter' );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect.poll( () => field( page, '_q' ) ).toBe( 'Rascals' );
		await page.reload();
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( page.locator( '#cbuild-search' ) ).toHaveValue(
			'Rascals'
		);

		// The entry Back reaches belongs to the page before the reload, so the browser loads it anew. Until then the
		// page in hand can answer the popstate itself, and in WebKit a Forward pressed before the new page was in was
		// lost (CI run 44746354681, webkit-desktop): Forward waits for the new page, settled.
		await page.evaluate( () => {
			document.documentElement.dataset.before = 'back';
		} );
		await page.goBack();
		await expect
			.poll( () =>
				page
					.evaluate( () => document.documentElement.dataset.before )
					.catch( () => 'loading' )
			)
			.toBeUndefined();
		await page.waitForLoadState( 'load' );
		await expect
			.poll( () => decodeURIComponent( page.url() ) )
			.toContain( 'cbuild[_shown]' );
		await expect( cards( page ) ).toHaveCount( 46 );
		await expect( page.locator( '#cbuild-search' ) ).toHaveValue( '' );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await page.goForward();
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( page.locator( '#cbuild-search' ) ).toHaveValue(
			'Rascals'
		);
	} );

	test( 'narrows by a brand, and a reload, and Back from the next step, keep it', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( at() );
		const alder = page.locator( '.cbuild-chips__chip', {
			hasText: 'Alder',
		} );
		// On a phone, with the script, the chips wait behind Filters (spec: Phone): once the store has folded them.
		const filters = page.locator( '.cbuild-chips__toggle' );
		if ( javaScriptEnabled ) {
			await expect( page.locator( '.cbuild-chips' ) ).toHaveClass(
				/cbuild-chips--folded/
			);
		}
		if ( await filters.isVisible() ) {
			await filters.click();
		}
		await expect( alder.locator( 'input' ) ).toHaveAccessibleName(
			'Alder, 12'
		);
		// By the keyboard: Safari gives a checkbox no focus when it is clicked or tapped, so only a key shows that focus
		// stays on the control used.
		await alder.locator( 'input' ).focus();
		await page.keyboard.press( 'Space' );
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
		// The address changes when the next page commits, before it has loaded. Without the script that is a new page,
		// and Back sent in that moment found no page to go back from (CI run 44838722384, no-js: "Not attached to an
		// active page"; #91). With the script the step is the same page, already loaded.
		await page.waitForLoadState( 'load' );
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

// Cowork's 1.0.0 review, F14 (#99): "two columns of compact cards about 134 px high … Six cards show at once at 1440
// by 900." On a phone, one column, still compact. Each card stays a 44 px target.
test.describe( 'Compact deck cards (F14)', () => {
	test( 'shows six decks at once at 1440 by 900, two to a row, and one column on a phone', async ( {
		page,
	}, info ) => {
		const phone = info.project.name.includes( 'phone' );
		if ( ! phone ) {
			await page.setViewportSize( { width: 1440, height: 900 } );
		}
		await page.goto( at() );
		await expect( cards( page ) ).toHaveCount( 30 );
		await cards( page )
			.first()
			.evaluate( ( item ) => item.scrollIntoView( { block: 'start' } ) );
		const boxes = await cards( page ).evaluateAll( ( items ) => {
			const bar = document
				.querySelector( '.cbuild-bar:not([hidden])' )
				?.getBoundingClientRect();
			// The screen's foot, or the bar's top where a bar sticks over it.
			const foot =
				bar && bar.top > 0 && bar.top < innerHeight
					? bar.top
					: innerHeight;
			return items.map( ( item ) => {
				const box = item.getBoundingClientRect();
				return {
					top: Math.round( box.top ),
					left: Math.round( box.left ),
					height: box.height,
					seen: box.top >= 0 && box.bottom <= foot,
				};
			} );
		} );
		for ( const box of boxes ) {
			expect( box.height, 'a 44 px target' ).toBeGreaterThanOrEqual( 44 );
			expect( box.height, 'about 134 px' ).toBeLessThanOrEqual( 170 );
		}
		const [ one, two, three ] = boxes;
		if ( phone ) {
			expect( two?.left, 'one column' ).toBe( one?.left );
			return;
		}
		expect( two?.top, 'two to a row' ).toBe( one?.top );
		expect( three?.left, 'and no third' ).toBe( one?.left );
		expect(
			boxes.filter( ( box ) => box.seen ).length,
			'six in view'
		).toBeGreaterThanOrEqual( 6 );
	} );
} );
