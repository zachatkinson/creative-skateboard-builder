import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { APIResponse, Route } from '@playwright/test';
import { test, expect, Page, inLocale } from './support/test';
import { axe } from './support/axe';
import { SITE } from './support/site';
import {
	holdAnswers,
	now,
	shiftsSince,
	themeHeader,
	watchShifts,
	type Answer,
} from './support/held';

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
		// Nothing listed, so nothing to sort and no "Showing 0 of 0" (Cowork's 1.1.0 review, F26, #124), as served
		// without the script and as the store draws it with.
		await expect( page.locator( '.cbuild-sort' ) ).toBeHidden();
		await expect( page.locator( '.cbuild-step__count' ) ).toBeHidden();
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
		await expect( page.locator( '.cbuild-sort' ) ).toBeHidden();
		await expect( page.locator( '.cbuild-step__count' ) ).toBeHidden();
		await found.getByRole( 'button', { name: 'Clear search' } ).click();
		await expect( cards( page ) ).toHaveCount( 30 );
		// Listed again, Sort and the count are back.
		await expect( page.locator( '.cbuild-sort' ) ).toBeVisible();
		await expect(
			page.locator( '.cbuild-step__more .cbuild-step__count' )
		).toHaveText( /30\D+46/ );
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
		// On a desktop, a brand past the row waits behind "N more brands", which opens it in place (#98).
		const more = page.locator( '.cbuild-chips__more' ).first();
		if (
			( await more.isVisible() ) &&
			'false' === ( await more.getAttribute( 'aria-expanded' ) ) &&
			! ( await alder.isVisible() )
		) {
			await more.click();
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
	// before "Ra" is asked. The request is held as soon as it is asked, and its answer waited for on its own: held only
	// once the server had answered, a server slower than the poll's 5 s under load looked like no search asked (#145).
	test( 'keeps every letter typed when an answer comes back mid-word', async ( {
		page,
	} ) => {
		await page.goto( at() );
		let held:
			{ route: Route; response: Promise< APIResponse > } | undefined;
		let holding = true;
		await page.route( /cbuild\/v1\/kits\/\d+\/state/, async ( route ) => {
			if ( ! holding ) {
				await route.continue();
				return;
			}
			held = { route, response: route.fetch() };
		} );
		const search = page.locator( '#cbuild-search' );
		await search.press( 'R' );
		await expect
			.poll( () => held, 'the search for "R" asked' )
			.toBeDefined();
		// The answer to "R" in hand before "a" is typed, however long the server takes.
		const response = await held!.response;
		await search.press( 'a' );
		holding = false;
		const answered = page.waitForResponse( /cbuild\/v1\/kits\/\d+\/state/ );
		await held!.route.fulfill( { response } );
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

	// #130 (D-377): with the builder's gap between the deck's picture and its words, the cards keep their rows on each
	// theme: two to a row at 1440 and one on a phone. The gap itself is measured on decks with pictures (builder.spec.ts).
	for ( const theme of [ 'twentytwentyfive', 'storefront', 'kadence' ] ) {
		test( `keeps two decks to a row at 1440 and one on a phone on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			const phone = info.project.name.includes( 'phone' );
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			if ( ! phone ) {
				await page.setViewportSize( { width: 1440, height: 900 } );
			}
			await page.goto( at() );
			await expect( cards( page ) ).toHaveCount( 30 );
			const [ one, two, three ] = await cards( page ).evaluateAll(
				( items ) =>
					items.slice( 0, 3 ).map( ( item ) => {
						const box = item.getBoundingClientRect();
						return {
							top: Math.round( box.top ),
							left: Math.round( box.left ),
						};
					} )
			);
			if ( phone ) {
				expect( two?.left, 'one column' ).toBe( one?.left );
				return;
			}
			expect( two?.top, 'two to a row' ).toBe( one?.top );
			expect( three?.left, 'and no third' ).toBe( one?.left );
		} );
	}

	// Cowork's 1.1.0 review, F24 (#122): once a width is chosen, the first view at 1440 by 900 on the demo (Board size
	// Balanced) showed two cards, cut off, under the step's lead, a plugin's notice of about 70 px, the width, the
	// search, the brands and Sort. With the lead gone once a width is chosen and Sort on the brands' line, the builder
	// at the window's top shows, under a 70 px notice above the step, four whole cards on Kadence (two before) and two
	// on Twenty Twenty-Five, whose type is larger (none before). D-322's six need the page scrolled to the cards (D-360).
	for ( const [ theme, held ] of [
		[ 'twentytwentyfive', 2 ],
		[ 'kadence', 4 ],
	] as const ) {
		test( `shows ${ held } whole decks in the first view after a width at 1440 by 900, under a 70 px notice, on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				/phone|tablet/.test( info.project.name ),
				'A desktop: the stage beside the panel.'
			);
			test.skip(
				'no-js' === info.project.name,
				'Without the script every brand shows, with Apply, over several lines (D-314).'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( { width: 1440, height: 900 } );
			await page.goto( at() );
			await expect( cards( page ) ).toHaveCount( 30 );
			// Board size Balanced, the demo's: the builder's own class for it, as the Board size test sets it.
			await page
				.locator( '.cbuild' )
				.evaluate( ( root ) =>
					root.classList.add( 'cbuild--balanced' )
				);
			// A notice of another plugin's, as Mature Content's, 70 px high above the step.
			await page.locator( '.cbuild-step' ).evaluate( ( step ) => {
				const notice = document.createElement( 'div' );
				notice.style.blockSize = '70px';
				step.before( notice );
			} );
			// The builder at the window's top, as under a shop's header.
			await page
				.locator( '.cbuild' )
				.evaluate( ( builder ) =>
					builder.scrollIntoView( { block: 'start' } )
				);
			// The brands on one line with "N more" and Sort (#98). The row is laid out again for the narrower panel Balanced
			// gives it, in the frames after the class. Counted before then, the row still held the chips the wider panel had
			// room for and took two lines, and the cards were a line lower: 0 seen on Twenty Twenty-Five and 2 on Kadence
			// (#145). The count waits for the one line.
			const line = () =>
				page.evaluate( () => {
					// Each chip shown, those past the row being out of sight, "N more" and Sort: how far apart their middles are.
					const middles = [
						...document.querySelectorAll< HTMLElement >(
							'.cbuild-chips__options--row .cbuild-chips__chip, .cbuild-chips__options--row .cbuild-chips__more, .cbuild-sort'
						),
					]
						.filter( ( shown ) =>
							shown.checkVisibility( {
								visibilityProperty: true,
							} )
						)
						.map( ( shown ) => {
							const box = shown.getBoundingClientRect();
							return box.top + box.height / 2;
						} );
					// A chip and Sort at least, or there is no line to read.
					return middles.length >= 2
						? Math.max( ...middles ) - Math.min( ...middles )
						: Number.POSITIVE_INFINITY;
				} );
			await expect
				.poll( line, 'the brands, N more and Sort on one line' )
				.toBeLessThanOrEqual( 4 );
			const seen = await cards( page ).evaluateAll( ( items ) => {
				const bar = document
					.querySelector( '.cbuild-bar:not([hidden])' )
					?.getBoundingClientRect();
				const foot =
					bar && bar.top > 0 && bar.top < innerHeight
						? bar.top
						: innerHeight;
				return items.filter( ( item ) => {
					const box = item.getBoundingClientRect();
					return box.top >= 0 && box.bottom <= foot;
				} ).length;
			} );
			expect(
				seen,
				'whole cards in the first view'
			).toBeGreaterThanOrEqual( held );
			// The step's lead has done its work once the width is chosen.
			await expect( page.locator( '.cbuild-step__lead' ) ).toBeHidden();
		} );
	}
} );

// Cowork's 1.2.0 review, F31 (#148), after F24: on the demo at 1440 by 900, the theme's header puts the builder 129 px
// down, and once a width was pressed the first view held no whole deck: each card was 316 by 237 px, against 169 in
// 1.1.0, since a deck sold in seven widths listed its six others over three lines, and the stock took one more. The
// widths kit's six decks each come in seven widths, counted in stock, with names that wrap as the demo's do. A card
// stays near 1.1.0's height, and the first view under the header holds a whole one on each theme (D-399).
test.describe( 'Deck cards after a width, under a theme header (F31)', () => {
	const widths = (): Shelf =>
		(
			JSON.parse(
				readFileSync(
					path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
					'utf8'
				)
			) as { widths: Shelf }
		 ).widths;

	/**
	 * Puts the builder `height` px below the window's top, as a theme's header above it does: the page scrolled to it, or
	 * a block that tall put before it where the page above is shorter.
	 *
	 * @param page   The page.
	 * @param height The header's height.
	 */
	const underHeader = ( page: Page, height: number ) =>
		page.locator( '.cbuild' ).evaluate( ( builder, above ) => {
			const top = builder.getBoundingClientRect().top + scrollY;
			if ( top < above ) {
				const header = document.createElement( 'div' );
				header.style.blockSize = `${ above - top }px`;
				builder.before( header );
			}
			scrollTo( 0, Math.max( 0, top - above ) );
		}, height );

	// 170 px, 1.1.0's card, where the names are set at the demo's size; Twenty Twenty-Five sets them at 22 px, and its
	// card is a name's line taller.
	for ( const [ theme, tallest ] of [
		[ 'twentytwentyfive', 190 ],
		[ 'kadence', 170 ],
		[ 'storefront', 170 ],
	] as const ) {
		test( `shows a whole deck under a 129 px header after a width is pressed, each card ${ tallest } px at most, on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			const phone = info.project.name.includes( 'phone' );
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			if ( ! phone ) {
				await page.setViewportSize( { width: 1440, height: 900 } );
			}
			const url = new URL( widths().page );
			url.searchParams.set( 'cbuild[step]', 'deck' );
			await page.goto( url.toString() );
			// The shopper's press, as served without the script and through the store with it.
			const button = page.locator( '.cbuild-width__option', {
				has: page.locator( '.cbuild-chip__text', {
					hasText: /^8[.,]5″$/,
				} ),
			} );
			await button.click();
			await expect( cards( page ) ).toHaveCount( 6 );
			await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount(
				0
			);
			await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
				'aria-busy',
				'true'
			);
			// Board size Balanced, the demo's, as D-360's test sets it.
			await page
				.locator( '.cbuild' )
				.evaluate( ( root ) =>
					root.classList.add( 'cbuild--balanced' )
				);
			await underHeader( page, 129 );
			// Balanced lays the panel out again in the frames after the class; the cards are measured once they keep still.
			const measure = () =>
				cards( page ).evaluateAll( ( items ) => {
					const bar = document
						.querySelector( '.cbuild-bar:not([hidden])' )
						?.getBoundingClientRect();
					const foot =
						bar && bar.top > 0 && bar.top < innerHeight
							? bar.top
							: innerHeight;
					return items.map( ( item ) => {
						const box = item.getBoundingClientRect();
						// A name past two lines, as a long name takes in a wider fallback font (Linux's WebKit and Firefox in
						// CI, D-436), is the name's own height, not the card's: its lines past two are counted apart.
						const name =
							item.querySelector< HTMLElement >(
								'.cbuild-card__name'
							);
						let extra = 0;
						if ( name ) {
							const style = getComputedStyle( name );
							const line =
								parseFloat( style.lineHeight ) ||
								parseFloat( style.fontSize ) * 1.2;
							const lines = Math.round(
								name.getBoundingClientRect().height / line
							);
							extra = Math.max( 0, lines - 2 ) * line;
						}
						return {
							top: Math.round( box.top ),
							height: Math.round( box.height ),
							extra,
							whole: box.top >= 0 && box.bottom <= foot,
						};
					} );
				} );
			let boxes = await measure();
			await expect
				.poll( async () => {
					const before = JSON.stringify( boxes );
					boxes = await measure();
					return JSON.stringify( boxes ) === before;
				}, 'the cards laid out' )
				.toBe( true );
			// A phone's first view is the stage and the bar; its cards are held to the height alone.
			if ( ! phone ) {
				expect(
					boxes.filter( ( box ) => box.whole ).length,
					'whole decks in the first view under the header'
				).toBeGreaterThanOrEqual( 1 );
			}
			for ( const box of boxes ) {
				// A row's cards are as tall as its tallest, so a name's extra lines count for the row.
				const extra = Math.max(
					...boxes
						.filter( ( other ) => other.top === box.top )
						.map( ( other ) => other.extra )
				);
				expect(
					box.height - extra,
					`a deck card, ${ tallest } px at most, any name's lines past two apart (${ extra } px)`
				).toBeLessThanOrEqual( tallest );
			}
			// The other widths on one line: the six of seven said as a count, not listed. In French in french.spec.ts.
			await expect(
				cards( page ).first().locator( '.cbuild-card__also' )
			).toHaveText( 'Also in 6 other widths' );
		} );
	}
} );

// Cowork's review of 1.2.0, F30: on the demo a width's answer came 0.9 to 1.3 s after the press, past the 500 ms CLS
// leaves out after an input, and the deck step moved up as it came: the step's lead went then (D-360), 97 px on an
// iPhone 13, where it takes two lines, and 73 px at 1440 by 900, and on the desktop the brand chips showed two lines
// for a frame or two before they folded to one row. 0.130 and 0.099, against M12's gate of under 0.1. Here the answer
// is held a second, the shelf kit's deck step has its four brands and "Help me choose" at its start (#134), and the
// shifts CLS counts (no recent input) are read from the press (D-405).
test.describe( 'A width pressed, its answer a second late (F30)', () => {
	for ( const theme of [ 'twentytwentyfive', 'kadence' ] ) {
		test( `moves nothing when the answer comes, on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				! info.project.name.startsWith( 'chromium' ) ||
					info.project.name.includes( 'tablet' ),
				'Only Chromium reports layout shift (the Layout Instability API); a phone and a desktop.'
			);
			test.setTimeout( 90_000 );
			const phone = info.project.name.includes( 'phone' );
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			// An iPhone 13's window, where the lead takes two lines, or the desktop Cowork measured.
			await page.setViewportSize(
				phone
					? { width: 390, height: 664 }
					: { width: 1440, height: 900 }
			);
			const builder = page.locator( '.cbuild' );
			await page.goto( shelf().page );
			await expect( builder ).toHaveAttribute( 'data-ready', '' );
			const lead = page.locator( '.cbuild-step__lead' );
			await expect( lead ).toBeVisible();
			if ( phone ) {
				const lines = await lead.evaluate(
					( shown ) =>
						shown.getBoundingClientRect().height /
						parseFloat( getComputedStyle( shown ).lineHeight )
				);
				expect(
					Math.round( lines ),
					'the lead on two lines'
				).toBeGreaterThanOrEqual( 2 );
			}
			// The guided start sits at the builder's start, as the width is asked (#134, D-402).
			await expect( page.locator( '.cbuild-guide' ) ).toBeVisible();
			// The demo's brands are more than a desktop's row holds: here each is 12rem wide, so the row folds behind
			// "N more", as D-314's tests make it.
			if ( ! phone ) {
				await page.addStyleTag( {
					content:
						'.cbuild .cbuild-chips__group:first-of-type .cbuild-chip { min-inline-size: 12rem; }',
				} );
			}
			await page.waitForLoadState( 'networkidle' );
			// The demo's theme header above the builder, its rail at 129 px (F31).
			await themeHeader( page, 129 );
			// On the demo the width's answer brings brands the row has not measured, which the shelf kit's four, in
			// stock at both widths, never do: on a desktop the answer here brings a fifth.
			await holdAnswers( page, {
				edit: ( answer ) => {
					const view = answer.view as {
						step?: {
							shelf?: {
								chips?: {
									groups?: { inputs?: Answer[] }[];
								};
							};
						};
					};
					const inputs =
						view?.step?.shelf?.chips?.groups?.[ 0 ]?.inputs;
					if ( ! phone && inputs?.[ 0 ] ) {
						inputs.push( {
							...inputs[ 0 ],
							id: 'cbuild-chip-deck-new-brand',
							value: 'shelf-new-brand',
							text: 'Elm',
							said: 'Elm, 1',
							count: '1',
							checked: false,
						} );
					}
				},
			} );
			await watchShifts( page );
			// The brands' row, as each frame draws it, for the four seconds after the press.
			await page.evaluate( () => {
				const seen = window as unknown as { rows: number[] };
				seen.rows = [];
				const from = performance.now();
				const frame = () => {
					const row = document.querySelector(
						'.cbuild-chips__options--row'
					);
					if ( row?.checkVisibility() ) {
						seen.rows.push(
							Math.round( row.getBoundingClientRect().height )
						);
					}
					if ( performance.now() - from < 4000 ) {
						requestAnimationFrame( frame );
					}
				};
				requestAnimationFrame( frame );
			} );
			const from = await now( page );
			await page
				.locator( '.cbuild-width__option', {
					has: page.locator( '.cbuild-chip__text', {
						hasText: /^8\.25″$/,
					} ),
				} )
				.click();
			// The lead and the guide go at the press, while the answer is held, so it finds neither to take away.
			await expect( builder ).toHaveAttribute( 'aria-busy', 'true' );
			// Read once, not polled: the answer would hide them a second later.
			expect
				.soft( await lead.isVisible(), 'the lead, at the press' )
				.toBe( false );
			expect
				.soft(
					await page.locator( '.cbuild-guide' ).isVisible(),
					'the guide, at the press'
				)
				.toBe( false );
			await expect
				.poll( () => page.url(), { timeout: 10_000 } )
				.toContain( 'cbuild%5B_width%5D=8.25in' );
			await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
			await expect( cards( page ) ).toHaveCount( 30 );
			// The answer drawn, its pictures painted, and the row's frames done.
			await page.waitForTimeout( 3500 );
			const { counted, said } = await shiftsSince( page, from );
			const rows = await page.evaluate(
				() => ( window as unknown as { rows: number[] } ).rows
			);
			info.annotations.push( {
				type: 'layout shift, a width answered a second late',
				description: said,
			} );
			// eslint-disable-next-line no-console
			console.log( `${ info.project.name } ${ theme }: ${ said }` );
			expect
				.soft( counted, `the width's layout shift: ${ said }` )
				.toBeLessThan( 0.1 );
			// On a desktop the brands' row is one line in every frame, the answer's first included (D-314).
			if ( ! phone ) {
				const folded = rows[ rows.length - 1 ] ?? 0;
				expect( folded, 'the row is drawn' ).toBeGreaterThan( 0 );
				expect(
					Math.max( ...rows ),
					`the brands' row, frame by frame: ${ rows.join( ' ' ) }`
				).toBeLessThanOrEqual( folded + 1 );
			}
		} );
	}
} );

// Cowork's review of 1.3.0, F39 (#162): another plugin that puts its notice above the cards when it hears
// `cbuild:rendered` moved a width's late answer by 0.062 on an iPhone 13 and 0.015 at 1440 by 900, as the event came
// a frame after the cards were laid out. Sent in the task that draws the answer, the notice is laid out with them.
test.describe( 'An element added above the cards on cbuild:rendered (F39)', () => {
	test( 'moves nothing when a width answered a second late brings the cards', async ( {
		page,
	}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ) ||
				info.project.name.includes( 'tablet' ),
			'Only Chromium reports layout shift (the Layout Instability API); a phone and a desktop.'
		);
		test.setTimeout( 90_000 );
		const phone = info.project.name.includes( 'phone' );
		await page.setViewportSize(
			phone ? { width: 390, height: 664 } : { width: 1440, height: 900 }
		);
		// Cowork's element: 118 px, put in the place above the cards on each event after the first, as a notice for
		// the decks a width lists.
		await page.addInitScript( () => {
			let heard = 0;
			const record = window as unknown as { f39Cards: number[] };
			record.f39Cards = [];
			document.addEventListener( 'cbuild:rendered', () => {
				heard++;
				// The cards the page holds as the event is heard: the answer's, drawn before it is sent.
				record.f39Cards.push(
					document.querySelectorAll( '.cbuild-cards > li' ).length
				);
				const place = document.querySelector(
					'.cbuild-step__above-cards'
				);
				if ( heard < 2 || ! place || place.querySelector( '.f39' ) ) {
					return;
				}
				const notice = document.createElement( 'div' );
				notice.className = 'f39';
				notice.style.blockSize = '118px';
				notice.textContent = 'Another plugin';
				place.append( notice );
			} );
		} );
		const builder = page.locator( '.cbuild' );
		await page.goto( shelf().page );
		await expect( builder ).toHaveAttribute( 'data-ready', '' );
		await page.waitForLoadState( 'networkidle' );
		await holdAnswers( page );
		await watchShifts( page );
		const from = await now( page );
		await page
			.locator( '.cbuild-width__option', {
				has: page.locator( '.cbuild-chip__text', {
					hasText: /^8\.25″$/,
				} ),
			} )
			.click();
		await expect( builder ).toHaveAttribute( 'aria-busy', 'true' );
		await expect( builder ).toHaveAttribute( 'aria-busy', 'false', {
			timeout: 10_000,
		} );
		await expect( page.locator( '.f39' ) ).toHaveCount( 1 );
		// The event for the width's answer is heard with its cards drawn: as many as the step shows once it is still.
		const drawn = await page.locator( '.cbuild-cards > li' ).count();
		expect( drawn, 'the width lists decks' ).toBeGreaterThan( 0 );
		expect(
			await page.evaluate(
				() => ( window as unknown as { f39Cards: number[] } ).f39Cards
			),
			'the cards drawn at each event: the start, then the width'
		).toEqual( [ expect.any( Number ), drawn ] );
		await page.waitForTimeout( 2000 );
		const { counted, said } = await shiftsSince( page, from );
		// eslint-disable-next-line no-console
		console.log( `${ info.project.name } F39: ${ said }` );
		expect( counted, `the answer's layout shift: ${ said }` ).toBeLessThan(
			0.001
		);
	} );
} );

// Issue #143: a search sends `cbuild:rendered` once its answer is drawn, not for each key typed before it.
test.describe( 'The rendered event on a search (#143)', () => {
	test( 'sends cbuild:rendered once for a search, after its answer, not per key', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'The event is the script’s.' );
		await page.addInitScript( () => {
			const heard: unknown[] = [];
			(
				window as unknown as { cbuildRendered: unknown[] }
			 ).cbuildRendered = heard;
			document.addEventListener( 'cbuild:rendered', ( event ) => {
				heard.push( ( event as CustomEvent ).detail );
			} );
		} );
		const rendered = () =>
			page.evaluate(
				() =>
					( window as unknown as { cbuildRendered: unknown[] } )
						.cbuildRendered
			);
		await page.goto( at() );
		const once = { kit: shelf().kit, step: 'deck' };
		await expect.poll( rendered ).toEqual( [ once ] );
		// Another plugin's elements where Mature Content 1.6.0 puts them: the stage's last child, the step's first.
		await page.evaluate( () => {
			const stage = document.querySelector( '.cbuild__stage' )!;
			const step = document.querySelector( '.cbuild-step' )!;
			const veil = document.createElement( 'div' );
			const notice = document.createElement( 'div' );
			stage.append( veil );
			step.prepend( notice );
			( window as unknown as { cbuildOwn: () => boolean[] } ).cbuildOwn =
				() => [
					veil.isConnected && stage.lastElementChild === veil,
					notice.isConnected && step.firstElementChild === notice,
				];
		} );
		const kept = () =>
			page.evaluate( () =>
				(
					window as unknown as { cbuildOwn: () => boolean[] }
				 ).cbuildOwn()
			);
		await page
			.locator( '#cbuild-search' )
			.pressSequentially( 'Rascals', { delay: 40 } );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect.poll( rendered ).toEqual( [ once, once ] );
		expect( await kept() ).toEqual( [ true, true ] );
	} );
} );
